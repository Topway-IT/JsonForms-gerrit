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

		// Get target title
		$targetTitle = $this->getTargetTitleFromData( $data, $errors );
		if ( !$targetTitle ) {
			return ResultWrapper::failure( $errors[0] );
		}

		// Validate permissions
		if ( !$this->validatePageAccess( $targetTitle, $data, $errors ) ) {
			return ResultWrapper::failure( $errors[0] );
		}

		$schemaName = $data->metadata->schemaName;
		$deleteSchema = empty( $schemaName );

		$wikiPage = \JsonForms::getWikiPage( $targetTitle );

		if ( !$wikiPage ) {
			return ResultWrapper::failure(
				$this->context->msg( "jsonforms-special-submit-cannot-create-wikipage" )->text()
			);
		}

		// Set context
		$this->context->setTitle( $targetTitle );
		$this->setOutput( $this->context->getOutput() );

		$previousMetadata = \JsonForms::getMetadata( $wikiPage );

		// Get target slot from existing metadata
		$targetSlot = $this->getTargetSlotFromMetadata( $previousMetadata );

		// trigger_error('^^$targetSlot'.$targetSlot);
		$isDataOnly = $targetSlot === SlotRecord::MAIN;

		// Build metadata
		$contentModelMainSlot = $this->getContentModel( $data, $targetTitle );
		$metadata = $this->buildMetadata( $data, $targetSlot, $contentModelMainSlot, $previousMetadata, $deleteSchema );

		// Handle delete
		if ( $deleteSchema ) {
			// unset( $slots[$targetSlot] );
			$slots[$targetSlot] = [
				'content' => null,
			];
			if ( isset( $metadata->slots ) && is_object( $metadata->slots ) ) {
				unset( $metadata->slots->{$targetSlot} );
			}
		}

		// Process JSON data
		$dataToSave = null;
		if ( !$deleteSchema ) {
			$slotMetadata = &$metadata->slots->{$targetSlot};
			 $errors_ = $this->processStructuredValue( $data, $slotMetadata );

			if ( is_array( $errors_ ) && count( $errors_ ) ) {
				return ResultWrapper::failure( $errors_[0] );
			}

			$dataToSave = $this->postProcessJsonData(
				$data->value,
				$data->structuredValue,
				$slotMetadata,
				$errors
			);
		}

		// Build slots
		$mainSlotContent = null;
		$slots = $this->buildSlots( $targetSlot, $dataToSave, $mainSlotContent, $contentModelMainSlot, $metadata, $deleteSchema );

		// Clean up empty slots
		if ( empty( (array)$metadata->slots ) ) {
			unset( $metadata->slots );
		}

		$isNewPage = !$wikiPage->exists();

		$processedData = [
			"slots" => $slots,
			"targetTitle" => $targetTitle,
			"isNewPage" => $isNewPage,
			"metadata" => $metadata,
			'updateStrategy' => 'merge',
		];

		$returnData = [
			"targetTitle" => $targetTitle->getFullText(),
			"returnUrl" => $targetTitle->getLocalURL(),
		];

		return ResultWrapper::success( [ $processedData, $returnData ] );

	}
}
