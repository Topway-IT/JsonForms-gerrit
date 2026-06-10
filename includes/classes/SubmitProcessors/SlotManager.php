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

		// this should happen only if hacked
		// if ( !$this->user->isAllowed( 'jsonforms-caneditdata' ) ) {
		// 	echo $this->context->msg( 'jsonforms-jsmodule-forms-cannot-edit-form' )->text();
		// 	exit();
		// }

		$errors = [];

		/*
{
  "value": {
	"title": "abc",
	"content_model": "wikitext",
	"editor": "source",
	"content": "ab",
	"jsonforms-data": {
	  "content_model": "json",
	  "editor": "JsonForms",
	  "content": "[{\"name\":\"a\",\"age\":22}]"
	}
  },
  "structuredValue": {
	"title": {
	  "value": "abc",
	  "schema": {
		"type": "string",
		"minLength": 1,
		"title": "Title",
		"options": {
		  "input": {
			"name": "title"
		  }
		}
	  },
	  "pathNoIndex": "title",
	  "isArrayValue": false
	},
	"content_model": {
	  "value": "wikitext",
	  "schema": {
		"type": "string",
		"title": "Content model",
		"default": "wikitext",
		"watch": {
		  "roleProperty": "role"
		},
		"enumSource": [
		  {
			"source": "contentModelByRoleSource"
		  }
		]
	  },
	  "pathNoIndex": "content_model",
	  "isArrayValue": false
	},
	"editor": {
	  "value": "source",
	  "schema": {
		"type": "string",
		"title": "Editor",
		"default": "source",
		"enum": [
		  "source",
		  "VisualEditor",
		  "JSON Editor",
		  "JsonForms"
		]
	  },
	  "pathNoIndex": "editor",
	  "isArrayValue": false
	},
	"content": {
	  "value": "ab",
	  "schema": {
		"type": "string",
		"format": "textarea",
		"options": {
		  "compact": true,
		  "format": "textarea",
		  "input": {
			"config": {
			  "autosize": true,
			  "rows": 6
			}
		  }
		}
	  },
	  "pathNoIndex": "content",
	  "isArrayValue": false
	},
	"jsonforms-data.content_model": {
	  "value": "json",
	  "schema": {
		"type": "string",
		"title": "Content model",
		"default": "wikitext",
		"watch": {
		  "roleProperty": "role"
		},
		"enumSource": [
		  {
			"source": "contentModelByRoleSource"
		  }
		]
	  },
	  "pathNoIndex": "jsonforms-data.content_model",
	  "isArrayValue": false
	},
	"jsonforms-data.editor": {
	  "value": "JsonForms",
	  "schema": {
		"type": "string",
		"title": "Editor",
		"default": "source",
		"enum": [
		  "source",
		  "VisualEditor",
		  "JSON Editor",
		  "JsonForms"
		]
	  },
	  "pathNoIndex": "jsonforms-data.editor",
	  "isArrayValue": false
	},
	"jsonforms-data.content": {
	  "value": "[{\"name\":\"a\",\"age\":22}]",
	  "schema": {
		"type": "string",
		"format": "textarea",
		"options": {
		  "compact": true,
		  "format": "json",
		  "input": {
			"name": "JsonForms",
			"config": {
			  "schemaSelector": true
			}
		  }
		}
	  },
	  "pathNoIndex": "jsonforms-data.content",
	  "isArrayValue": false,
	  "structuredValue": {
		"0.name": {
		  "value": "a",
		  "schema": {
			"type": "string",
			"description": "First and Last name",
			"minLength": 4
		  },
		  "pathNoIndex": "name",
		  "isArrayValue": false
		},
		"0.age": {
		  "value": 22,
		  "schema": {
			"type": "integer",
			"default": 21,
			"minimum": 18,
			"maximum": 99
		  },
		  "pathNoIndex": "age",
		  "isArrayValue": false
		}
	  },
	  "schemaName": "Ajax"
	}
  },
  "config": {
	"schemaUrl": "http://127.0.0.1/mediawiki-1.44.0/index.php/JsonSchema:",
	"isNewPage": true,
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
	  "jsondata": "visualdata-jsondata",
	  "jsonforms-data": "json",
	  "jsonforms-metadata": "json",
	  "jsonschema": "json",
	  "header": "wikitext",
	  "footer": "wikitext"
	},
	"contentModel": "wikitext",
	"VEForAll": true,
	"jsonSlots": [
	  "jsondata",
	  "jsonforms-data",
	  "jsonforms-metadata",
	  "jsonschema"
	],
	"slotRoles": [
	  "jsondata",
	  "jsonforms-data",
	  "jsonforms-metadata",
	  "jsonschema",
	  "header",
	  "footer"
	],
	"jsonContentModels": [
	  "visualdata-jsondata",
	  "json",
	  "json",
	  "json"
	],
	"jsonforms-show-notice-outdated-version": true
  },
  "processor": "SlotManager"
}
*/

		$output = $this->output;

		// determine targetTitle
		$isNewPage = false;
		$titleStr = null;
		$targetTitle = null;

		if ( !empty( $data->options->edit ) ) {
			$titleStr = $data->options->edit;
		} elseif ( !empty( $data->value->title ) ) {
			$titleStr = $data->value->title;
		}

		if ( !empty( $titleStr ) ) {
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

		// this should be always set
		$mainSlotcontentModel = $data->value->content_model;

		$mainSlotContent = $data->value->content ?? null;
		if ( $targetTitle->isKnown() ) {
			if ( empty( $data->options->edit ) ) {
				return ResultWrapper::failure(
					$this->context
						->msg(
							"jsonforms-special-submit-article-exists",
							$targetTitle->getDBKey(),
						)
						->parse(),
				);
			}
		} else {
			$isNewPage = true;
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
				"model" => $mainSlotcontentModel,
				"content" => $mainSlotContent,
			],
		];

		$metadataPrevious = \JsonForms::getMetadata( $wikiPage );

		// Initialize metadata as object
		$metadata = new stdClass();
		$metadata->slots = new stdClass();
		$metadata->slots->{SlotRecord::MAIN} = new stdClass();
		$metadata->slots->{SlotRecord::MAIN}->model = $mainSlotcontentModel;
		$metadata->slots->{SlotRecord::MAIN}->editor = $data->value->editor;

		if (
			$mainSlotcontentModel === "json" &&
			isset( $metadataPrevious->slots->{SlotRecord::MAIN}->schema ) &&
			!empty( $metadataPrevious->slots->{SlotRecord::MAIN}->schema )
		) {
			$metadata->slots->{SlotRecord::MAIN}->schema =
				$metadataPrevious->slots->{SlotRecord::MAIN}->schema;
		}

		if (
			!empty( $data->value->categories ) &&
			is_array( $data->value->categories )
		) {
			$metadata->categories = $data->value->categories;
		}

		$roleNames = SlotHelper::getSlotRoles();
		foreach ( get_object_vars( $data->value ) as $key => $value ) {

			// valid slot name
			if ( !in_array( $key, $roleNames ) ) {
					continue;
			}

			// ignore metadata slot
			if ( $key === SLOT_ROLE_JSONFORMS_METADATA ) {
				continue;
			}

			$metadata->slots->{$key} = new stdClass();
			$metadata->slots->{$key}->model = $value->content_model;
			$metadata->slots->{$key}->editor = $value->editor;

			if ( $key === SLOT_ROLE_JSONFORMS_DATA ) {
				if (
					isset( $metadataPrevious->slots->{$key}->schema ) &&
					!empty( $metadataPrevious->slots->{$key}->schema )
				) {
					$metadata->slots->{$key}->schema =
						$metadataPrevious->slots->{$key}->schema;
				}

				$metadataKeys = [
					"show_infobox" => "showInfobox",
					"infobox_position" => "infoboxPosition",
					"infobox_template" => "infoboxTemplate",
				];

				foreach ( $metadataKeys as $k => $v ) {
					if (
						isset( $metadataPrevious->slots->{$key}->{$v} ) &&
						property_exists( $metadataPrevious->slots->{$key}, $v )
					) {
						$metadata->slots->{$key}->{$v} =
							$metadataPrevious->slots->{$key}->{$v};
					}
				}
			}

			$slots[$key] = [
				"model" => $value->content_model,
				"content" => $value->content,
			];
		}

		$slots[SLOT_ROLE_JSONFORMS_METADATA] = [
			"model" => "json",
			"content" => json_encode( $metadata ),
		];

		$processedData = [
			"slots" => $slots,
			"targetTitle" => $targetTitle,
			"isNewPage" => $isNewPage,
			"metadata" => $metadata,
		];

		$returnData = [
			"targetTitle" => $targetTitle->getFullText(),
			"returnUrl" => $targetTitle->getLocalURL(),
		];

		return ResultWrapper::success( [ $processedData, $returnData ] );
	}
}
