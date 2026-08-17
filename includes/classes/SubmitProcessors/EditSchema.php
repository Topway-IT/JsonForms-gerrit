<?php

/**
 * This file is part of the MediaWiki extension JsonForms.
 *
 * JsonForms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * JsonForms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JsonForms.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2026, https://wikisphere.org
 */

namespace MediaWiki\Extension\JsonForms\SubmitProcessors;

use MediaWiki\Extension\JsonForms\ResultWrapper;
use MediaWiki\Extension\JsonForms\SubmitForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

class EditSchema extends SubmitForm {

	/**
	 * @param array $data
	 * @return array
	 */
	public function processData( $data ) {
		$services = MediaWikiServices::getInstance();
		$errors = [];

		$targetTitle = $this->getTargetTitleFromData( $data, $errors );
		if ( !$targetTitle ) {
			return ResultWrapper::failure( $errors[0] );
		}

		if ( !$this->validatePageAccess( $targetTitle, $data, $errors ) ) {
			return ResultWrapper::failure( $errors[0] );
		}

		$schemaName = $data->metadata->schemaName;
		$deleteSchema = empty( $schemaName );

		$wikiPage = \JsonForms::getWikiPage( $targetTitle );

		if ( !$wikiPage ) {
			return ResultWrapper::failure(
				$this->context->msg( 'jsonforms-special-submit-cannot-create-wikipage' )->text()
			);
		}

		$this->context->setTitle( $targetTitle );
		$this->setOutput( $this->context->getOutput() );

		$previousMetadata = \JsonForms::getMetadata( $wikiPage );

		$targetSlot = $this->getTargetSlotFromMetadata( $previousMetadata );

		$isDataOnly = $targetSlot === SlotRecord::MAIN;

		$contentModelMainSlot = $this->getContentModel( $data, $targetTitle );
		$metadata = $this->buildMetadata( $data, $targetSlot, $contentModelMainSlot, $previousMetadata, $deleteSchema );

		if ( $deleteSchema ) {
			$slots[$targetSlot] = [
				'content' => null,
			];
			if ( isset( $metadata->slots ) && is_object( $metadata->slots ) ) {
				unset( $metadata->slots->{$targetSlot} );
			}
		}

		$dataToSave = null;
		if ( !$deleteSchema ) {
			$slotMetadata = &$metadata->slots->{$targetSlot};
		 	$this->processStructuredValue( $data, $slotMetadata, $targetSlot, $wikiPage, $errors );

			if ( count( $errors ) ) {
				return ResultWrapper::failure( $errors[0] );
			}

			$dataToSave = $this->postProcessJsonData(
				$data->value,
				$data->structuredValue,
				$slotMetadata,
				$targetSlot,
				$wikiPage,
				$errors
			);
			
			if ( count( $errors ) ) {
				return ResultWrapper::failure( $errors[0] );
			}
		}

		$mainSlotContent = null;
		$slots = $this->buildSlots( $targetSlot, $dataToSave, $mainSlotContent, $contentModelMainSlot, $metadata, $deleteSchema );

		if ( empty( (array)$metadata->slots ) ) {
			unset( $metadata->slots );
		}

		$isNewPage = !$wikiPage->exists();

		$processedData = [
			'slots' => $slots,
			'targetTitle' => $targetTitle,
			'isNewPage' => $isNewPage,
			'metadata' => $metadata,
			'updateStrategy' => 'merge',
		];

		$returnData = [
			'targetTitle' => $targetTitle->getFullText(),
			'returnUrl' => $targetTitle->getLocalURL(),
		];

		return ResultWrapper::success( [ $processedData, $returnData ] );
	}
}
