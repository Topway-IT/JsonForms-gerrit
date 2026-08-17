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
use MediaWiki\Extension\JsonForms\BaseRender;
use MediaWiki\Extension\JsonForms\SubmitForm;
use MediaWiki\MediaWikiServices;
use SpecialPage;

class ManageSchemas extends SubmitForm {

	/**
	 * @param WikiPage $wikiPage
	 * @param array $data
	 * @return stdClass
	 */
	private function updateSchemaMetadata( $wikiPage, $data ) {
		$metadata = \JsonForms::getMetadata( $wikiPage );

		if ( !$metadata || !is_object( $metadata ) ) {
			$metadata = new stdClass();
		}

		if ( !isset( $metadata->processedSchema ) ) {
			$metadata->processedSchema = [];

		} elseif ( !is_array( $metadata->processedSchema ) ) {
			$metadata->processedSchema = (array)$metadata->processedSchema;
		}

		// @TODO show alert
		$editType = [];
		foreach ( $data->structuredValue->values as $jsonPath => $value ) {
			$parentJsonpath = substr( $jsonPath, 0, -strlen( $value->key ) - 1 );
			if (
				array_key_exists( $value->key, BaseRender::$schemaInfo ) &&
				array_key_exists( $parentJsonpath, $metadata->processedSchema )	&&
				!$this->isEmptyValue( $value->value )
			) {
				$metadata->processedSchema[$parentJsonpath]->{$value->key} = $value->value;

				if (
					$value->key === 'type' &&
					$metadata->processedSchema[$parentJsonpath]->type !== $value->value
				) {
					$editType[] = $parentJsonpath;
				}
			}
		}

		return $metadata;
	}		

	/**
	 * @param array $data
	 * @return array
	 */
	public function processData( $data ) {
		$errors = [];
		$services = MediaWikiServices::getInstance();

		if ( !$this->user->isAllowed( 'jsonforms-canmanageschemas' ) ) {
			return ResultWrapper::failure(
				$this->context->msg( 'jsonforms-special-submit-no-permissions' )->text()
			);
		}

		if ( !isset( $data->options ) ) {
			$data->options = new stdClass();
		}

		$targetTitle = $this->getTargetTitleFromData( $data, $errors );
		if ( !$targetTitle ) {
			return ResultWrapper::failure( $errors[0] );
		}

		if ( !$this->validatePageAccess( $targetTitle, $data, $errors ) ) {
			return ResultWrapper::failure( $errors[0] );
		}

		$previousPage = $this->getPreviousPage( $data );
		$previousTargetTitle = $previousPage ?: $targetTitle;

		$isNewPage = !$targetTitle->isKnown() && !$previousPage;

		$contentModelMainSlot = $this->getContentModel( $data, $previousTargetTitle );
		$mainSlotContent = $this->getMainContent( $data, $targetTitle, $isNewPage );

		$movePage = $this->handlePageMove( $previousPage, $targetTitle );

		$wikiPage = \JsonForms::getWikiPage( $targetTitle );
		$previousWikiPage = \JsonForms::getWikiPage( $previousTargetTitle );

		if ( !$wikiPage ) {
			return ResultWrapper::failure(
				$this->context->msg( 'jsonforms-special-submit-cannot-create-wikipage' )->text()
			);
		}

		$this->context->setTitle( $targetTitle );
		$this->setOutput( $this->context->getOutput() );

		$returnData = $this->processReturnUrl( $data, $targetTitle, $isNewPage, $errors );
		if ( !$returnData ) {
			return ResultWrapper::failure( $errors[0] );
		}

		$targetSlot = $this->determineTargetSlot( $data, $isNewPage, $mainSlotContent, $previousWikiPage, $wikiPage );

		if ( count( $errors ) ) {
			return ResultWrapper::failure( $errors[0] );
		}

		$dataToSave = $this->postProcessJsonData(
			$data->value,
			$data->structuredValue,
			$slotMetadata,
			$targetSlot,
			$previousWikiPage,
			$errors
		);

		if ( count( $errors ) ) {
			return ResultWrapper::failure( $errors[0] );
		}

		// https://wikisphere.org/wiki/JsonSchema:Core/NewArticleDataOnly
		$parsedId = explode( 'JsonSchema:', $data->schemaId )[ 1 ];
	
		$metadata = [];
		switch ( $parsedId ) {
			case 'SchemaBuilder/MetaSchema':
				// update
				$metadata = $this->updateSchemaMetadata( $wikiPage, $data );
				$specialpage_title = SpecialPage::getTitleFor( 'JsonFormsManage', 'Schemas' );

				$url = $specialpage_title->getLinkURL( [ 'action' => 'edit', 'pagename' => $targetTitle->getFullText() ] );

				$returnData['returnUrl'] = $url;
				break;

			case 'Core/CreatePageForm':
				$specialpage_title = SpecialPage::getTitleFor( 'JsonFormsManage', 'Forms' );

				$url = $specialpage_title->getLinkURL( [ 'action' => 'edit', 'pagename' => $targetTitle->getFullText() ] );

				$returnData['returnUrl'] = $url;
				break;
		}

		$deleteSchema = false;
		$slots = $this->buildSlots( $targetSlot, $dataToSave, $mainSlotContent, $contentModelMainSlot, $metadata, $deleteSchema );

		$processedData = [
			'slots' => $slots,
			'targetTitle' => $targetTitle,
			'targetSlot' => $targetSlot,
			'isNewPag' => $isNewPage,
			'contentModel' => $contentModelMainSlot,
			'main_slot_content' => $mainSlotContent,
			'metadata' => $metadata,
			'movePage' => $movePage,
			'updateStrategy' => 'merge',
		];

		return ResultWrapper::success( [ $processedData, $returnData ] );
	}
}
