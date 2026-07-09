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

class SpecialJsonFormsEdit extends SpecialPage {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		$listed = false;

		// https://www.mediawiki.org/wiki/Manual:Special_pages
		parent::__construct( "JsonFormsEdit", "", $listed );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();

		$out = $this->getOutput();

		$out->addModuleStyles( "mediawiki.special" );
		$this->addHelpLink( "Extension:JsonForms" );

		$out->enableOOUI();

		if ( !$par ) {
			return $this->printError( $out, "jsonforms-special-edit-notitle" );
		}

		$editTitle = TitleClass::newFromText( $par );

		// title can be empty (new article)
		if ( !$editTitle ) {
			return $this->printError(
				$out,
				"jsonforms-special-submit-title-not-valid",
			);
		}

		$out->addWikiMsg( "jsonforms-special-edit-message" );

		$jsonForm = \JsonForms::getSourceSchema(
			"EditDataUI",
			"JsonSchema/Core",
		);

		$startVal = new stdClass();
		$startVal->form = new stdClass();
		$startVal->form->schema = new stdClass();
		$startVal->form->schema->selectedSchema = new stdClass();

		$wikiPage = \JsonForms::getWikiPage( $editTitle );
		$metadata = \JsonForms::getMetadata( $wikiPage );

		if ( isset( $metadata->categories ) ) {
			$startVal->categories = (array)$metadata->categories;
		}

		if (
			$metadata &&
			isset( $metadata->slots ) &&
			is_object( $metadata->slots )
		) {
			$metadataSlots = $metadata->slots;

			if ( property_exists( $metadataSlots, SLOT_ROLE_JSONFORMS_DATA ) ) {
				$targetSlot = SLOT_ROLE_JSONFORMS_DATA;

			} elseif (
				property_exists( $metadataSlots, SlotRecord::MAIN ) &&
				( $metadataSlots->{SlotRecord::MAIN}->schema ?? null ) !== null
			) {
				$targetSlot = SlotRecord::MAIN;

			} else {
				$targetSlot = null;
			}

			if ( $targetSlot ) {
				$slotMetadata = $metadataSlots->$targetSlot;
				$content = \JsonForms::getSlotContent( $wikiPage, $targetSlot );

				if ( isset( $slotMetadata->schema ) ) {
					$startVal->form->schema->selectedSchema->schemaName = $slotMetadata->schema;
				}

				if ( $content ) {
					$json = \JsonForms::processFormData( $content, $slotMetadata );
					$startVal->form->schema->selectedSchema->editor = $json;
				}

				$metadataKeys = [
					"show_infobox" => "showInfobox",
					"infobox_template" => "infoboxTemplate",
					"infobox_position" => "infoboxPosition",
				];

				foreach ( $metadataKeys as $k => $v ) {
					if ( property_exists( $slotMetadata, $v ) ) {
						if ( !isset( $startVal->form->options ) ) {
							$startVal->form->options = new stdClass();
						}
						$startVal->form->options->$k = $slotMetadata->$v;
					}
				}
			}

		} elseif ( $wikiPage && $editTitle->getContentModel() === 'json' ) {
			$startVal->form->schema->selectedSchema->editor = \JsonForms::getJsonArticle( $editTitle );
		}

		if ( empty( (array)$startVal->form->schema->selectedSchema ) ) {
			unset( $startVal->form->schema->selectedSchema );
		}

		$formData = new stdClass();
		$formData->schema = $jsonForm;
		$formData->editorOptions = "MediaWiki:DefaultEditorOptions";
		$formData->editorScript = "MediaWiki:DefaultEditorScript";
		$formData->metadata = $metadata;
		$formData->formDescriptor = new stdClass();
		$formData->formDescriptor->edit = $editTitle->getFullText();

		if ( !empty( (array)$startVal->form ) ) {
			$formData->startval = $startVal;
		}

		$formData = \JsonForms::prepareFormData( $out, $formData );

		$res_ = \JsonForms::getJsonFormHtml( $formData, [ "width" => "auto" ] );

		if ( !$res_->ok ) {
			return $this->printError( $out, $res_->error );
		}

		$html = $res_->value;

		$out->addModules( "ext.JsonForms.editSchema" );
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
