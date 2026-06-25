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

use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Revision\SlotRecord;

/**
 * A special page that lists protected pages
 *
 * @ingroup SpecialPage
 */
class SpecialJsonFormsSlotManager extends SpecialPage {
	/**
	 * @inheritDoc
	 */
	public function __construct() {
		$listed = true;
		parent::__construct( "JsonFormsSlotManager", "", $listed );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->requireLogin();

		$this->setHeaders();
		$this->outputHeader();

		$user = $this->getUser();

		$groups = \JsonForms::slotManagerGroups();
		if (
			!count( array_intersect( $groups, \JsonForms::getUserGroups( $user ) ) )
		) {
			$this->displayRestrictionError();
			return;
		}

		$out = $this->getOutput();

		$out->addModuleStyles( "mediawiki.special" );
		$this->addHelpLink( "Extension:JsonForms" );

		$out->enableOOUI();

		$jsonForm = \JsonForms::getSourceSchema(
			"SimpleFormUI",
			"JsonSchema/Core",
		);
		// $jsonForm = \JsonForms::processSchema( $out, $jsonForm );

		$innerSchema = \JsonForms::getSourceSchema(
			"SlotManager",
			"JsonSchema/Core",
		);
		$innerSchema = \JsonForms::processSchema( $out, $innerSchema );

		// ***important, encode schema otherwise $refs can mess with
		// those of the host schema
		// Convert to object if needed, then set property
		if ( is_array( $jsonForm ) ) {
			$jsonForm = json_decode( json_encode( $jsonForm ) );
		}

		// Initialize nested objects
		if ( !isset( $jsonForm->properties ) ) {
			$jsonForm->properties = new stdClass();
		}
		if ( !isset( $jsonForm->properties->editor ) ) {
			$jsonForm->properties->editor = new stdClass();
		}
		if ( !isset( $jsonForm->properties->editor->{'x-input-config'} ) ) {
			$jsonForm->properties->editor->{'x-input-config'} = new stdClass();
		}

		$jsonForm->properties->editor->{'x-input-config'}->schema = json_encode(
			$innerSchema,
		);

		$editTitle = null;
		if ( !empty( $par ) ) {
			$editTitle = TitleClass::newFromText( $par );
		}

		$startValInnerForm = new stdClass();
		$editPage = null;
		$metadata = null;

		if ( $editTitle ) {
			$startValInnerForm->title = $par;

			// Initialize disableFields as array
			if (
				!isset(
					$jsonForm->properties->editor->{'x-input-config'}
						->disableFields
				)
			) {
				$jsonForm->properties->editor->{'x-input-config'}->disableFields = [];
			}
			$jsonForm->properties->editor->{'x-input-config'}->disableFields = [
				"title",
			];

			if ( $editTitle->isKnown() ) {
				$editPage = $editTitle->getFullText();
				$wikiPage = \JsonForms::getWikiPage( $editTitle );
				$metadata = \JsonForms::getMetadata( $wikiPage );

				// $startVal['categories'] = \JsonForms::getCategories($editTitle);
				if ( isset( $metadata->categories ) ) {
					$startValInnerForm->categories =
						(array)$metadata->categories;
				}

				$slots = \JsonForms::getSlots( $wikiPage );

				$setStartVal = static function ( &$val, $role, $slot ) use (
					$metadata,
					$wikiPage,
					$editTitle,
				) {
					// $val['content_model'] = $slot->getContent()->getContentHandler()->getModelID();
					$val->content_model = $slot->getModel();
					if ( isset( $metadata->slots->$role->editor ) ) {
						$val->editor = $metadata->slots->$role->editor;
					}
					$content = \JsonForms::getSlotContent( $wikiPage, $role );
					if (
						!isset( $val->editor ) ||
						strtolower( $val->editor ) !== "jsonforms"
					) {
						$val->content = $content;
					} else {
						$content = json_decode( $content, false );
						$schemaName = $metadata->slots->$role->schema ?? "";
						$val->content = json_encode( [
							"schema" => $schemaName,
							"editor" => $content,
						] );
					}
				};

				if ( array_key_exists( SlotRecord::MAIN, $slots ) ) {
					$setStartVal(
						$startValInnerForm,
						SlotRecord::MAIN,
						$slots[SlotRecord::MAIN],
					);
					unset( $slots[SlotRecord::MAIN] );
				}

				foreach ( $slots as $role => $slot ) {
					if ( $role === SLOT_ROLE_JSONFORMS_METADATA ) {
						continue;
					}
					$val = new stdClass();
					$setStartVal( $val, $role, $slot );
					$startValInnerForm->$role = $val;
				}
			}
		}

		// Create formData as stdClass
		$formData = new stdClass();
		$formData->schema = $jsonForm;
		$formData->editorOptions = "MediaWiki:DefaultEditorOptions";
		$formData->editorScript = "MediaWiki:DefaultEditorScript";
		$formData->metadata = $metadata;
		$formData->editPage = $editPage;

		if ( !empty( (array)$startValInnerForm ) ) {
			$formData->startval = new stdClass();
			$formData->startval->editor = json_encode( $startValInnerForm );
		}

		$formData = \JsonForms::prepareFormData( $out, $formData );

		$res_ = \JsonForms::getJsonFormHtml( $formData, [
			"width" => "auto",
		] );

		if ( !$res_->ok ) {
			return $this->printError( $out, $res_->error );
		}

		$html = $res_->value;

		$out->addModules( "ext.JsonForms.slotManager" );

		\JsonForms::addJsConfigVars( $out );

		$out->addHTML( $html );
	}

	/**
	 * @param Output $out
	 * @param string $msg
	 */
	private function printError( $out, $msg ) {
		$out->addHTML(
			new \OOUI\MessageWidget( [
				"type" => "error",
				"label" => new \OOUI\HtmlSnippet( $this->msg( $msg )->parse() ),
			] ),
		);
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return "jsonforms";
	}
}
