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

use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Extension\JsonForms\ResultWrapper;
use MediaWiki\Extension\JsonForms\SlotHelper;
use MediaWiki\Extension\JsonForms\SubmitForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use stdClass;

class SlotManager extends SubmitForm {
    
	/**
	 * @param array $data
	 * @return array
	 */
	public function processData( $data ) {
		$services = MediaWikiServices::getInstance();

		// check permissions
		$groups = \JsonForms::slotManagerGroups();
		if ( !\JsonForms::isAuthorized( $this->user, $groups ) ) {
			return ResultWrapper::failure( 'no permissions' );
		}

		$errors = [];

		$targetTitle = $this->getTargetTitleFromData( $data, $errors );
		if ( !$targetTitle ) {
			return ResultWrapper::failure( $errors[0] );
		}

		$wikiPage = \JsonForms::getWikiPage( $targetTitle );

		$metadataPrevious = \JsonForms::getMetadata( $wikiPage );

		$contentModelMainSlot = $this->getContentModel( $data );
		$mainSlotContent = $this->getMainContent( $data );

		$metadata = $this->initializeMetadata( $metadataPrevious, $contentModelMainSlot );

		$metadata->slots->{SlotRecord::MAIN}->editor = $data->value->editor ?? 
			$this->defaultEditorForContentModel( $contentModelMainSlot );

		$this->preserveMainSlotSchema( $metadata, $metadataPrevious, $contentModelMainSlot );

		$this->addCategories( $metadata, $data );

		$slots = [];		
		$slots[SlotRecord::MAIN] = [
			'model' => $contentModelMainSlot,
			'content' => $mainSlotContent
		];

		$this->processAdditionalSlots( $metadata, $data, $slots );

		// Add/delete metadata slot
		$slots[SLOT_ROLE_JSONFORMS_METADATA] = [
			'model' => 'json',
			'content' => !empty( (array)$metadata ) ? json_encode( $metadata ) : '',
		];

		$isNewPage = !$targetTitle->isKnown();
		$processedData = [
			'slots' => $slots,
			'targetTitle' => $targetTitle,
			'isNewPage' => $isNewPage,
			'metadata' => $metadata,
			'updateStrategy' => 'replace',
		];

		$returnData = [
			'targetTitle' => $targetTitle->getFullText(),
			'returnUrl' => $targetTitle->getLocalURL(),
		];

		return ResultWrapper::success( [ $processedData, $returnData ] );
	}
}
