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
use MediaWiki\Revision\SlotRecord;

class NewArticle extends SubmitForm {
	/**
	 * @param array $data
	 * @return array
	 */
	public function processData( $data ) {
		$errors = [];

  		// Get target title
		$targetTitle = $this->getTargetTitleFromData( $data, $errors );
		if ( !$targetTitle ) {
			return ResultWrapper::failure( $errors[0] );
		}

		// Validate permissions (new page mode)
		if ( !$this->validatePageAccess( $targetTitle, $data, $errors, true ) ) {
			return ResultWrapper::failure( $errors[0] );
		}

		// Set context
		$this->context->setTitle( $targetTitle );
		$this->setOutput( $this->context->getOutput() );

		$isDataOnly = !property_exists( $data->options, "content" );

		// Build metadata
		$contentModelMainSlot = $this->getContentModel( $data, $targetTitle );
		$targetSlot = $isDataOnly ? SlotRecord::MAIN : SLOT_ROLE_JSONFORMS_DATA;
		$metadata = $this->buildMetadata( $data, $targetSlot, $contentModelMainSlot );

		// Process JSON data
		$dataToSave = $this->postProcessJsonData(
			$data->value,
			$data->structuredValue,
			$metadata->slots->{$targetSlot},
			$errors
		);

		// Get main content
		$mainSlotContent = $this->getMainContent( $data, $targetTitle, true );

		// Validate content
		if ( $isDataOnly && empty( $data->value ) ) {
			return ResultWrapper::failure(
				$this->context->msg( "jsonforms-special-submit-nocontent" )->text()
			);
		}

		if ( !$isDataOnly && empty( $data->value ) && empty( $mainSlotContent ) ) {
			return ResultWrapper::failure(
				$this->context->msg( "jsonforms-special-submit-nocontent" )->text()
			);
		}

		// Get wiki page
		$wikiPage = \JsonForms::getWikiPage( $targetTitle );
		if ( !$wikiPage ) {
			return ResultWrapper::failure(
				$this->context->msg( "jsonforms-special-submit-cannot-create-wikipage" )->text()
			);
		}

		// Build slots
		$slots = $this->buildSlots( $targetSlot, $dataToSave, $mainSlotContent, $contentModelMainSlot, $metadata );

		$processedData = [
			"slots" => $slots,
			"targetTitle" => $targetTitle,
			"isNewPage" => true,
			"metadata" => $metadata,
			'updateStrategy' => 'replace',
		];

		$returnData = [
			"targetTitle" => $targetTitle->getFullText(),
			"returnUrl" => $targetTitle->getLocalURL(),
		];

		return ResultWrapper::success( [ $processedData, $returnData ] );
	}
}
