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

class NewArticle extends SubmitForm {
	/**
	 * @param array $data
	 * @return array
	 */
	public function processData( $data ) {
		$services = MediaWikiServices::getInstance();

		$errors = [];

		/*
{
  "options": {
	"title": "",
	"content_model": "wikitext",
	"editor": "wikieditor",
	"content": "",
	"categories": [],
	"summary": "",
	"minor": false,
	"buttons": {
	  "submit": null
	}
  },
  "config": {
	"schemaUrl": "http://127.0.0.1/mediawiki-1.44.0/index.php/JsonSchema:",
	"isNewPage": true,
	"caneditdata": true,
	"canmanageschemas": true,
	"canmanageforms": true,
	"contentModels": {
	  "css": "CSS",
	  "GadgetDefinition": "GadgetDefinition",
	  "json": "JSON",
	  "javascript": "JavaScript",
	  "sanitized-css": "Sanitized CSS",
	  "Scribunto": "Scribunto module",
	  "text": "plain text",
	  "twig": "twig",
	  "wikitext": "wikitext"
	},
	"roleContentModelMap": {
	  "main": "wikitext",
	  "jsonforms-data": "json",
	  "jsonforms-metadata": "json",
	  "jsonschema": "json",
	  "jsondata": "json",
	  "header": "wikitext",
	  "footer": "wikitext"
	},
	"contentModel": "wikitext",
	"VEForAll": true,
	"captchaSiteKey": "6Ld4DYUsAAAAAB7ypPb84qYAXjGBSd9oSjQGK3jB",
	"jsonSlots": [
	  "jsonforms-data",
	  "jsonforms-metadata",
	  "jsonschema",
	  "jsondata"
	],
	"slotRoles": [
	  "main",
	  "jsonforms-data",
	  "jsonforms-metadata",
	  "jsonschema",
	  "jsondata",
	  "header",
	  "footer"
	],
	"jsonContentModels": [
	  "json",
	  "json",
	  "json",
	  "json"
	],
	"jsonforms-show-notice-outdated-version": true
  },
  "processor": "NewArticle"
}
*/
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

		if ( $targetTitle->isKnown() ) {
			return ResultWrapper::failure(
				$this->context
					->msg(
						"jsonforms-special-submit-article-exists",
						$targetTitle->getDBKey(),
					)
					->parse(),
			);
		}

		$isDataOnly = !property_exists( $data->options, "content" );

		// data only
		if ( $isDataOnly ) {
			$data->options->content_model = "json";
			$data->options->content = json_encode( $data->value );
		}

		$contentModel = $data->options->content_model ?? null;
		$main_slot_content = $data->options->content ?? null;

		if ( empty( $main_slot_content ) && empty( $data->value ) ) {
			return ResultWrapper::failure(
				$this->context
					->msg( "jsonforms-special-submit-nocontent" )
					->text(),
			);
		}

		$wikiPage = \JsonForms::getWikiPage( $targetTitle );

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

		$slots = [
			SlotRecord::MAIN => [
				"model" => $contentModel,
				"content" => $main_slot_content,
			],
		];

		// Initialize metadata as object
		$metadata = new stdClass();

		if ( $isDataOnly ) {
			$metadata->slots = new stdClass();
			$metadata->slots->{SlotRecord::MAIN} = new stdClass();
			$metadata->slots->{SlotRecord::MAIN}->schema =
				$data->metadata->schemaName;
		}

		if (
			!empty( $data->options->categories ) &&
			is_array( $data->options->categories )
		) {
			$metadata->categories = $data->options->categories;
		}

		if ( !$isDataOnly && !empty( $data->value ) ) {
			$slots[SLOT_ROLE_JSONFORMS_DATA] = [
				"model" => "json",
				"content" => json_encode( $data->value ),
			];

			if ( !isset( $metadata->slots ) ) {
				$metadata->slots = new stdClass();
			}
			$metadata->slots->{SLOT_ROLE_JSONFORMS_DATA} = new stdClass();
			$metadata->slots->{SLOT_ROLE_JSONFORMS_DATA}->model = "json";
			$metadata->slots->{SLOT_ROLE_JSONFORMS_DATA}->schema =
				$data->metadata->schemaName;

			$metadataKeys = [
				"show_infobox" => "showInfobox",
				"infobox_position" => "infoboxPosition",
				"infobox_template" => "infoboxTemplate",
			];

			foreach ( $metadataKeys as $key => $val ) {
				if ( property_exists( $data->metadata, $key ) ) {
					$metadata->slots->{SLOT_ROLE_JSONFORMS_DATA}->$val =
						$data->metadata->$key;

				} else {
					unset( $metadata->slots->{SLOT_ROLE_JSONFORMS_DATA}->$val );
				}
			}
		}

		$slots[SLOT_ROLE_JSONFORMS_METADATA] = [
			"model" => "json",
			"content" => json_encode( $metadata ),
		];

		$processedData = [
			"slots" => $slots,
			"targetTitle" => $targetTitle,
			"isNewPage" => true,
			"metadata" => $metadata,
		];

		$returnData = [
			"targetTitle" => $targetTitle->getFullText(),
			"returnUrl" => $targetTitle->getLocalURL(),
		];

		return ResultWrapper::success( [ $processedData, $returnData ] );
	}
}
