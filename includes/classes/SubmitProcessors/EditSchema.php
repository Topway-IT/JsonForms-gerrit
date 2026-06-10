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
use MediaWiki\Extension\JsonForms\SubmitForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use stdClass;

class EditSchema extends SubmitForm {
	/**
	 * @param array $data
	 * @return array
	 */
	public function processData( $data ) {
		$services = MediaWikiServices::getInstance();

		$errors = [];

		// Convert array access ($data['key']) to object access ($data->key)

		if ( empty( $data->options->title ) ) {
			return ResultWrapper::failure(
				$this->context->msg( "jsonforms-special-submit-notitle" ),
			);
		}

		$titleStr = $data->options->title;
		$targetTitle = TitleClass::newFromText( $titleStr );

		if ( empty( $targetTitle ) ) {
			return ResultWrapper::failure(
				$this->context->msg( "jsonforms-special-submit-notitle" )->text(),
			);
		}

		if (
			!\JsonForms::checkWritePermissions(
				$this->user,
				$targetTitle,
				$errors,
			)
		) {
			return ResultWrapper::failure(
				$this->context
					->msg( "jsonforms-special-submit-permission-error" )
					->text(),
			);
		}

		if ( !$targetTitle->isKnown() ) {
			return ResultWrapper::failure(
				$this->context
					->msg(
						"jsonforms-special-edit-title-unknown",
						$targetTitle->getDBKey(),
					)
					->parse(),
			);
		}

		$deleteSchema = empty( $data->metadata->schemaName );

		$wikiPage = \JsonForms::getWikiPage( $targetTitle );

		$metadataPrevious = \JsonForms::getMetadata( $wikiPage );
		$targetSlot = null;

		if (
			$metadataPrevious &&
			isset( $metadataPrevious->slots ) &&
			is_object( $metadataPrevious->slots )
		) {
			$metadataPreviousSlots = $metadataPrevious->slots;

			if ( property_exists( $metadataPreviousSlots, SLOT_ROLE_JSONFORMS_DATA ) ) {
				$targetSlot = SLOT_ROLE_JSONFORMS_DATA;

			} elseif (
				property_exists( $metadataPreviousSlots, SlotRecord::MAIN ) &&
				( $metadataPreviousSlots->{SlotRecord::MAIN}->schema ?? null ) !== null
			) {
				$targetSlot = SlotRecord::MAIN;

			} else {
				$targetSlot = null;
			}
		}

		if ( !$targetSlot ) {
			$targetSlot = SLOT_ROLE_JSONFORMS_DATA;
		}

		$isDataOnly = $targetSlot === SlotRecord::MAIN;

		if ( !$wikiPage ) {
			return ResultWrapper::failure(
				$this->context
					->msg( "jsonforms-special-submit-cannot-create-wikipage" )
					->text(),
			);
		}

		// @set title for further use of parseWikitext
		$this->context->setTitle( $targetTitle );
		$this->setOutput( $this->context->getOutput() );

		$slots = [];
		$slots_ = \JsonForms::getSlots( $wikiPage );
		foreach ( $slots_ as $role => $slot ) {
			if ( $role === SLOT_ROLE_JSONFORMS_METADATA ) {
				continue;
			}
			$content = \JsonForms::getSlotContent( $wikiPage, $role );

			$slots[$role] = [
				"model" => $slot->getModel(),
				"content" => $content,
			];
		}

		// Initialize metadata as object
		$metadata = $metadataPrevious
			? clone $metadataPrevious
			: new stdClass();

		if ( $deleteSchema ) {
			unset( $slots[$targetSlot] );
			if ( isset( $metadata->slots ) && is_object( $metadata->slots ) ) {
				unset( $metadata->slots->{$targetSlot} );
			}
		}

		if ( !$deleteSchema ) {
			$slots[$targetSlot] = [
				"model" => "json",
				"content" => json_encode( $data->value ),
			];
		}

		// Ensure slots property exists as an object
		if ( !isset( $metadata->slots ) || !is_object( $metadata->slots ) ) {
			$metadata->slots = new stdClass();
		}

		if ( !$deleteSchema ) {
			// Ensure the target slot exists
			if ( !isset( $metadata->slots->{$targetSlot} ) ) {
				$metadata->slots->{$targetSlot} = new stdClass();
			}
			$metadata->slots->{$targetSlot}->model = "json";
			$metadata->slots->{$targetSlot}->schema = $data->metadata->schemaName;
		}

		if (
			!empty( $data->options->categories ) &&
			is_array( $data->options->categories )
		) {
			$metadata->categories = $data->options->categories;
		}

		if ( !$deleteSchema && !$isDataOnly ) {
			// Ensure the data slot exists
			if ( !isset( $metadata->slots->{SLOT_ROLE_JSONFORMS_DATA} ) ) {
				$metadata->slots->{SLOT_ROLE_JSONFORMS_DATA} = new stdClass();
			}

			$metadataKeys = [
				"show_infobox" => "showInfobox",
				"infobox_position" => "infoboxPosition",
				"infobox_template" => "infoboxTemplate",
			];

			foreach ( $metadataKeys as $key => $value ) {
				if ( property_exists( $data->metadata, $key ) ) {
					$metadata->slots->{SLOT_ROLE_JSONFORMS_DATA}->$value =
						$data->metadata->$key;
				} else {
					unset( $metadata->slots->{SLOT_ROLE_JSONFORMS_DATA}->$value );
				}
			}

			$metadata->slots->{SLOT_ROLE_JSONFORMS_DATA}->processedSchema =
				$data->processedSchema;
		}

		if ( empty( (array)$metadata->slots ) ) {
			unset( $metadata->slots );
		}

		if ( !empty( (array)$metadata ) ) {
			$slots[SLOT_ROLE_JSONFORMS_METADATA] = [
				"model" => "json",
				"content" => json_encode( $metadata ),
			];
		}

		$processedData = [
			"slots" => $slots,
			"targetTitle" => $targetTitle,
			"isNewPage" => false,
			"metadata" => $metadata,
		];

		$returnData = [
			"targetTitle" => $targetTitle->getFullText(),
			"returnUrl" => $targetTitle->getLocalURL(),
		];

		return ResultWrapper::success( [ $processedData, $returnData ] );
	}
}
