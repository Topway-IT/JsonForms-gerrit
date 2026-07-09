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
use stdClass;

class PageForms extends SubmitForm {

	/**
	 * @param array &$data
	 * @param string $path
	 * @param array $value
	 */
	protected function setDataAtPath( ?array &$data, string $path, $value ): void {
		if ( !is_array( $data ) ) {
			$data = [];
		}

		$keys = explode( ".", $path );
		$current = &$data;

		foreach ( $keys as $index => $key ) {
			if ( $index === count( $keys ) - 1 ) {
				if (
					is_array( $current ) &&
					isset( $current[$key] ) &&
					is_array( $current[$key] ) &&
					is_array( $value )
				) {
					$current[$key] = array_replace_recursive(
						$current[$key],
						$value,
					);
				} else {
					$current[$key] = $value;
				}
				return;
			}

			// Create path if it doesn't exist or isn't an array
			if ( !isset( $current[$key] ) || !is_array( $current[$key] ) ) {
				$current[$key] = [];
			}

			$current = &$current[$key];
		}
	}

	/**
	 * @param array $data
	 * @return array
	 */
	public function processData( $data ) {
		$errors = [];

		$services = MediaWikiServices::getInstance();

		// Ensure options exists
		if ( !isset( $data->options ) ) {
			$data->options = new stdClass();
		}

		// Validate CAPTCHA
		if ( !$this->validateCaptcha( $data, $errors ) ) {
			return ResultWrapper::failure( $errors[0] );
		}

		// Get target title
		$targetTitle = $this->getTargetTitleFromData( $data, $errors );
		if ( !$targetTitle ) {
			return ResultWrapper::failure( $errors[0] );
		}

		// Validate permissions
		if ( !$this->validatePageAccess( $targetTitle, $data, $errors ) ) {
			return ResultWrapper::failure( $errors[0] );
		}

		// Get previous page (for moves)
		$previousPage = $this->getPreviousPage( $data );
		$previousTargetTitle = $previousPage ?: $targetTitle;

		// Determine if new page
		$isNewPage = !$targetTitle->isKnown() && !$previousPage;

		// Get content model and main content
		$contentModelMainSlot = $this->getContentModel( $data, $previousTargetTitle );
		$mainSlotContent = $this->getMainContent( $data, $targetTitle, $isNewPage );

		// Handle page move
		$movePage = $this->handlePageMove( $previousPage, $targetTitle );

		// Get wiki pages
		$wikiPage = \JsonForms::getWikiPage( $targetTitle );
		$previousWikiPage = \JsonForms::getWikiPage( $previousTargetTitle );

		if ( !$wikiPage ) {
			return ResultWrapper::failure(
				$this->context->msg( "jsonforms-special-submit-cannot-create-wikipage" )->text()
			);
		}

		// Set context
		$this->context->setTitle( $targetTitle );
		$this->setOutput( $this->context->getOutput() );

		// Process return URL
		$returnResult = $this->processReturnUrl( $data, $targetTitle, $isNewPage, $errors );
		if ( !$returnResult ) {
			return ResultWrapper::failure( $errors[0] );
		}

		// Update content model if needed
		if (
			!$isNewPage &&
			$contentModelMainSlot && 
			$previousTargetTitle &&
			$previousTargetTitle->getContentModel() !== $contentModelMainSlot
		) {
			$this->updateContentModel( $previousTargetTitle, $wikiPage, $contentModelMainSlot, $errors );
		}

		if ( count( $errors ) ) {
			return ResultWrapper::failure( $errors[0] );
		}

		// Determine target slot
		$targetSlot = $this->determineTargetSlot( $data, $isNewPage, $mainSlotContent, $previousWikiPage, $wikiPage );

		$previousMetadata = \JsonForms::getMetadata( $previousWikiPage );

		// Build metadata
		$metadata = $this->buildMetadata( $data, $targetSlot, $contentModelMainSlot, $previousMetadata );

		// $metadata = $this->initializeMetadata( $metadataPrevious, $contentModelMainSlot );

		$slotMetadata = &$metadata->slots->{$targetSlot};
		$errors_ = $this->processStructuredValue( $data, $slotMetadata );

		if ( is_array( $errors_ ) && count( $errors_ ) ) {
			return ResultWrapper::failure( $errors_[0] );
		}

		// Process JSON data
		$dataToSave = $this->postProcessJsonData(
			$data->value,
			$data->structuredValue,
			$slotMetadata,
			$errors
		);

		if ( count( $errors ) ) {
			return ResultWrapper::failure( $errors[0] );
		}

		// Handle partial edit
		$dataToSave = $this->handlePartialEdit( $data, $previousWikiPage, $targetSlot, $dataToSave );

		// @TODO
		$deleteSchema = false;

		// Build slots
		$slots = $this->buildSlots( $targetSlot, $dataToSave, $mainSlotContent, $contentModelMainSlot, $metadata, $deleteSchema );

		// Merge with previous metadata slots
		if ( $previousMetadata && isset( $previousMetadata->slots ) ) {
			$slots = $this->mergePreviousMetadataSlots( $slots, $previousMetadata->slots );
		}

		$processedData = [
			"slots" => $slots,
			"targetTitle" => $targetTitle,
			"targetSlot" => $targetSlot,
			"isNewPage" => $isNewPage,
			"contentModel" => $contentModelMainSlot,
			"main_slot_content" => $mainSlotContent,
			"metadata" => $metadata,
			"movePage" => $movePage,
			'updateStrategy' => 'merge',
		];

		return ResultWrapper::success( [ $processedData, $returnResult ]);
	}
}
