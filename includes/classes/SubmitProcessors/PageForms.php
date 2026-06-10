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
use MediaWiki\Extension\JsonForms\SchemaUtils;
use MediaWiki\Extension\JsonForms\SubmitForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
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
		$services = MediaWikiServices::getInstance();

		// this should happen only if hacked
		// if ( !$this->user->isAllowed( 'jsonforms-caneditdata' ) ) {
		// 	echo $this->context->msg( 'jsonforms-jsmodule-forms-cannot-edit-form' )->text();
		// 	exit();
		// }

		$errors = [];

		/*
{
  "value": {
	"name": "aaaa"
  },
  "options": {
	"categories": [
	  "Ab"
	],
	"captcha": "...."
  },
  "structuredValue": {
	"name": {
	  "value": "aaaa",
	  "schema": {
		"type": "string",
		"description": "First and Last name",
		"minLength": 4
	  },
	  "pathNoIndex": "name",
	  "isArrayValue": false
	}
  },
  "formDescriptor": {
	"@type": "JsonForms default schema",
	"name": "Add person",
	"schema": "Person",
	"uischema": "",
	"edit_categories": true,
	"default_categories": [],
	"default_data_slot": "main",
	"edit_data_slot_role": false,
	"edit_main_slot_content_model": false,
	"edit_main_slot_content": false,
	"default_main_slot_content_model": "wikitext",
	"edit": "",
	"pagename_formula": "JsonData:Person/#count",
	"create_only_fields": [],
	"overwrite_existing_article_on_create": false,
	"view": "popup",
	"popup_button_label": "Add person",
	"callback": "",
	"preload": "",
	"preload_data": "",
	"preload_data_separator": "",
	"return_page": "",
	"return_url": "",
	"start_path": "",
	"popup_size": "medium",
	"css_class": "",
	"editor_options": "MediaWiki:DefaultEditorOptions",
	"editor_script": "MediaWiki:DefaultEditorScript",
	"width": "800px",
	"captcha": false
  },
  "config": {
	"schemaUrl": "http://127.0.0.1/mediawiki-1.43.0/index.php/JsonSchema:",
	"isNewPage": false,
	"caneditdata": false,
	"canmanageschemas": false,
	"canmanageforms": false,
	"contentModels": {
	  "css": "CSS",
	  "GadgetDefinition": "GadgetDefinition",
	  "json": "JSON",
	  "javascript": "JavaScript",
	  "sanitized-css": "Sanitized CSS",
	  "Scribunto": "Scribunto module",
	  "translate-messagebundle": "Translatable message bundle",
	  "html": "html",
	  "pageproperties-jsondata": "pageproperties-jsondata",
	  "pageproperties-semantic": "pageproperties-semantic",
	  "text": "plain text",
	  "twig": "twig",
	  "visualdata-jsondata": "visualdata-jsondata",
	  "wikitext": "wikitext"
	},
	"roleContentModelMap": {
	  "main": "wikitext",
	  "jsondata": "visualdata-jsondata"
	},
	"contentModel": "wikitext",
	"VEForAll": true,
	"jsonSlots": [
	  "jsondata"
	],
	"slotRoles": [
	  "main",
	  "jsondata"
	],
	"jsonContentModels": [
	  "visualdata-jsondata"
	],
	"jsonforms-show-notice-outdated-version": true
  },
  "processor": "Pageforms"
}


FORM OPTIONS

title:
data_slot:
main_slot_content_model:
main_slot_content:
categories:
summary:
*/

		/*
metadata can be stored:
-- using an outer schema (VisualData)
-- as a reference in the data schema itself (OSL)
-- using a meta schema in a different slot
-- using page_props  (output page setProperty/getProperty)
*/

		$output = $this->output;

		if ( !isset( $data->options ) ) {
			$data->options = new stdClass();
		}

		if ( !empty( $data->options->captcha ) ) {
			$recaptchaSecret = $GLOBALS["wgJsonFormsReCaptchaSecretKey"];
			$recaptchaResponse = $data->options->captcha;

			$response = file_get_contents(
				"https://www.google.com/recaptcha/api/siteverify?secret={$recaptchaSecret}&response={$recaptchaResponse}",
			);
			$responseKeys = json_decode( $response, true );

			if ( !$responseKeys["success"] ) {
				return ResultWrapper::failure(
					$this->context
						->msg( "jsonforms-special-submit-captcha-error" )
						->text(),
				);
			}
		}

		// determine targetTitle
		$isNewPage = false;
		$titleStr = null;
		$targetTitle = null;
		if ( !empty( $data->options->title ) ) {
			$titleStr = $data->options->title;
		} elseif ( !empty( $data->formDescriptor->edit ) ) {
			$titleStr = $data->formDescriptor->edit;
		} elseif ( !empty( $data->formDescriptor->pagename_formula ) ) {
			$targetTitle = $data->formDescriptor->pagename_formula;
			$targetTitle = $this->parseWikitext( $targetTitle );
			$targetTitle = \JsonForms::parseTitleCounter( $targetTitle );

			if ( empty( $targetTitle ) ) {
				return ResultWrapper::failure(
					$this->context
						->msg(
							"jsonforms-special-submit-computed-target-title-error",
						)
						->text(),
				);
			}
		}

		if ( !$targetTitle ) {
			$targetTitle = TitleClass::newFromText( $titleStr );
		}

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

		$previousPage = null;
		if (
			!empty( $data->options->title ) &&
			!empty( $data->formDescriptor->edit )
		) {
			$previousPage = TitleClass::newFromText(
				$data->formDescriptor->edit,
			);
		}

		$refTargetTitle = !$previousPage ? $targetTitle : $previousPage;

		$contentModelMainSlot = "wikitext";

		if ( !empty( $data->options->freetext_content_model ) ) {
			$contentModelMainSlot = $data->options->freetext_content_model;
		} elseif ( $refTargetTitle->isKnown() ) {
			$contentModelMainSlot = $refTargetTitle->getContentModel();
		}

		$main_slot_content = $data->options->freetext ?? null;

		if ( !$targetTitle->isKnown() && !$previousPage ) {
			$isNewPage = true;
		}

		if (
			$targetTitle->isKnown() &&
			empty( $data->formDescriptor->edit ) &&
			$data->formDescriptor->overwrite_existing_article_on_create !== true
		) {
			return ResultWrapper::failure(
				$this->context
					->msg(
						"jsonforms-special-submit-article-exists",
						$targetTitle->getDBKey(),
					)
					->parse(),
			);
		}

		$movePage = false;
		if (
			$previousPage &&
			$previousPage->getFullText() !== $targetTitle->getFullText()
		) {
			$movePage = [ $previousPage, $targetTitle ];
		}

		$wikiPage = \JsonForms::getWikiPage( $targetTitle );
		$refWikiPage = \JsonForms::getWikiPage( $refTargetTitle );

		if ( !$wikiPage ) {
			return ResultWrapper::failure(
				$this->context
					->msg( "jsonforms-special-submit-cannot-create-wikipage" )
					->text(),
			);
		}

		$this->context->setTitle( $targetTitle );
		$this->setOutput( $this->context->getOutput() );

		$returnMessage = null;
		$returnUrl = null;

		if ( empty( $data->formDescriptor->return ) ) {
			if ( !empty( $data->formDescriptor->return_url ) ) {
				$data->formDescriptor->return = "url";
			} elseif ( !empty( $data->formDescriptor->return_page ) ) {
				$data->formDescriptor->return = "article";
			} else {
				$data->formDescriptor->return = "target";
			}
		}

		switch ( $data->formDescriptor->return ) {
			case "none":
				$localUrl = $targetTitle->getLocalURL();
				$targetUrl = (string)$services
					->getUrlUtils()
					->expand( $localUrl, PROTO_FALLBACK );

				$messageKey =
					"jsonforms-jsmodule-return-message-" .
					( $isNewPage ? "create" : "edit" );
				$returnMessage = $this->context
					->msg( $messageKey, $targetTitle->getFullText(), $targetUrl )
					->text();
				break;

			case "article":
				if ( !empty( $data->formDescriptor->return_page ) ) {
					$title_ = TitleClass::newFromText(
						$data->formDescriptor->return_page,
					);
					if ( $title_ ) {
						$localUrl = $title_->getLocalURL();
					}
				}
				break;

			case "url":
				if ( !empty( $data->formDescriptor->return_url ) ) {
					$localUrl = $data->formDescriptor->return_url;
					$returnUrl = (string)$services
						->getUrlUtils()
						->expand( $localUrl, PROTO_FALLBACK );
				}
				break;

			case "target":
			default:
				$localUrl = $targetTitle->getLocalURL();
		}

		if ( !$returnUrl ) {
			if ( !$localUrl ) {
				return ResultWrapper::failure(
					$this->context
						->msg( "jsonforms-special-submit-return-no-return-url" )
						->text(),
				);
			}

			$returnUrl = (string)$services
				->getUrlUtils()
				->expand( $localUrl, PROTO_FALLBACK );
		}

		if ( filter_var( $returnUrl, FILTER_VALIDATE_URL ) === false ) {
			return ResultWrapper::failure(
				$this->context
					->msg(
						"jsonforms-special-submit-return-validate-url-error",
						$returnUrl,
					)
					->text(),
			);
		}

		if (
			!$isNewPage &&
			$contentModelMainSlot &&
			$contentModelMainSlot !== $refTargetTitle->getContentModel()
		) {
			$this->updateContentModel(
				$refTargetTitle,
				$wikiPage,
				$contentModelMainSlot,
				$errors,
			);
		}

		if ( count( $errors ) ) {
			return ResultWrapper::failure( $errors[0] );
		}

		if ( !empty( $data->formDescriptor->slot ) ) {
			$targetSlot = $data->formDescriptor->slot;
		} elseif ( $isNewPage && $main_slot_content === null ) {
			$targetSlot = "main";
		} else {
			$targetSlot = \JsonForms::getFirstJsonSlot( $refWikiPage );
		}

		if ( !$targetSlot ) {
			$targetSlot = SLOT_ROLE_JSONFORMS_DATA;
		}

		$dataToSave = $this->postProcessJsonData(
			$data->value,
			$data->structuredValue,
		);

		if ( !empty( $data->formDescriptor->edit_path ) ) {
			$wholeDataStr = \JsonForms::getSlotContent(
				$refWikiPage,
				$targetSlot,
			);
			$wholeData = json_decode( $wholeDataStr, false );
			$partialData = SchemaUtils::getValueByPath( $wholeData, $data->formDescriptor->edit_path );

			if ( is_object( $partialData ) && is_object( $dataToSave ) ) {
				$dataToSave = SchemaUtils::mergeObjectsRecursive( $dataToSave, $partialData );
			}

			SchemaUtils::setValueByPath(
				$wholeData,
				$data->formDescriptor->edit_path,
				$dataToSave,
			);
			$dataToSave = $wholeData;
		}

		$slots = [
			$targetSlot => [
				"model" => "json",
				"content" => json_encode( $dataToSave ),
			],
		];

		if ( $isNewPage && $main_slot_content === null ) {
			if ( !empty( $data->formDescriptor->preload_article ) ) {
				$title_ = \JsonForms::getTitleIfKnown(
					$data->formDescriptor->preload_article,
				);
				if ( $title_ ) {
					$main_slot_content = \JsonForms::getWikipageContent(
						$title_,
					);
				}
			} elseif ( !empty( $data->formDescriptor->preload_wikitext ) ) {
				$main_slot_content = $data->formDescriptor->preload_wikitext;
			}
		}

		if ( $main_slot_content === null && !$isNewPage ) {
			$main_slot_content = \JsonForms::getWikipageContent( $targetTitle );
		}

		if ( $targetSlot !== "main" ) {
			$slots[SlotRecord::MAIN] = [
				"model" => $contentModelMainSlot,
				"content" => $main_slot_content,
			];
		}

		// keep existing slots - $previousMetadata is an object
		$previousMetadata = \JsonForms::getMetadata( $wikiPage );

		$previousMetadataSlots = null;
		if (
			$previousMetadata &&
			isset( $previousMetadata->slots ) &&
			is_object( $previousMetadata->slots )
		) {
			$previousMetadataSlots = $previousMetadata->slots;
		}

		// Initialize metadata as object
		$metadata = new stdClass();
		$metadata->slots = new stdClass();

		$metadata->slots->{SlotRecord::MAIN} = new stdClass();
		$metadata->slots->{SlotRecord::MAIN}->model = $contentModelMainSlot;

		if ( $contentModelMainSlot === "wikitext" ) {
			$metadata->slots->{SlotRecord::MAIN}->editor = "WikiEditor";

		} elseif ( $contentModelMainSlot === "json" ) {
			$metadata->slots->{SlotRecord::MAIN}->editor = "JsonEditor";

		} else {
			$metadata->slots->{SlotRecord::MAIN}->editor = "source";
		}

		if ( $targetSlot !== "main" ) {
			$metadata->slots->{$targetSlot} = new stdClass();
			$metadata->slots->{$targetSlot}->editor = "JsonEditor";
			$metadata->slots->{$targetSlot}->model = "json";
			$metadata->slots->{$targetSlot}->schema = $data->formDescriptor->schema;

			$metadataKeys = [
				"show_infobox" => "showInfobox",
				"infobox_position" => "infoboxPosition",
				"infobox_template" => "infoboxTemplate",
			];

			foreach ( $metadataKeys as $key => $value ) {
				if ( property_exists( $data->formDescriptor, $key ) ) {
					$metadata->slots->{$targetSlot}->{$value} =
						$data->formDescriptor->$key;

				} else {
					unset( $metadata->slots->{$targetSlot}->$value );
				}
			}

			if ( isset( $metadata->slots->{$targetSlot}->processedSchema ) ) {
				$metadata->slots->{$targetSlot}->processedSchema = $data->processedSchema;
			}

		} else {
			$metadata->slots->{$targetSlot}->schema = $data->formDescriptor->schema;

			// processedSchema is not defined from JsonFormsManageSchemas
			if ( isset( $metadata->slots->{$targetSlot}->processedSchema ) ) {
				$metadata->slots->{$targetSlot}->processedSchema = $data->processedSchema;
			}
		}

		if (
			!empty( $data->options->categories ) &&
			is_array( $data->options->categories )
		) {
			$metadata->categories = $data->options->categories;
		}

		$slots[SLOT_ROLE_JSONFORMS_METADATA] = [
			"model" => "json",
			"content" => json_encode( $metadata ),
		];

		if ( $previousMetadataSlots ) {
			// Convert object slots to array for merging
			$previousSlotsArray = [];
			foreach (
				get_object_vars( $previousMetadataSlots )
				as $role => $slotData
			) {
				$previousSlotsArray[$role] = $slotData;
			}
			$slots = $slots + $previousSlotsArray;
		}

		$processedData = [
			"slots" => $slots,
			"targetTitle" => $targetTitle,
			"targetSlot" => $targetSlot,
			"isNewPage" => $isNewPage,
			"contentModel" => $contentModelMainSlot,
			"main_slot_content" => $main_slot_content,
			"metadata" => $metadata,
			"movePage" => $movePage,
		];

		$returnData = [
			"returnUrl" => $returnUrl,
			"message" => $returnMessage,
			"targetTitle" => $targetTitle->getFullText(),
		];

		return ResultWrapper::success( [ $processedData, $returnData ] );
	}
}
