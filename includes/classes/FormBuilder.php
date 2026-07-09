<?php

namespace MediaWiki\Extension\JsonForms;

use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Revision\SlotRecord;
use stdClass;

/**
 * Handles the generation of page forms for the JsonForms extension.
 * Responsible for loading existing data, processing schemas, and preparing
 * the form configuration for rendering.
 */
class FormBuilder {
	/** @var  \OutputPage The output page object */
	private $output;

	/** @var \JsonForms The JsonForms main class */
	private $jsonForms;

	/** @var array|null The form descriptor configuration */
	private $formDescriptor;

	/** @var stdClass The start value object */
	private $startVal;

	/** @var stdClass The JSON form schema */
	private $jsonForm;

	/** @var array The processed schema */
	private $processedSchema = [];

	/** @var string|null The schema name */
	private $schemaName = null;

	/** @var string|null The schema name */
	private $editSchemaName = null;

	/**
	 * Constructor
	 *
	 * @param \OutputPage $output
	 * @param array|stdClass $formDescriptor
	 */
	public function __construct( $output, $formDescriptor ) {
		$this->output = $output;
		$this->formDescriptor = $this->normalizeFormDescriptor( $formDescriptor );
		$this->startVal = new stdClass();
		$this->jsonForm = \JsonForms::getSourceSchema( "PageFormUI", "JsonSchema/Core" );
	}

	/**
	 * Static entry point - creates instance and builds the form
	 *
	 * @param \OutputPage $output
	 * @param array|stdClass $formDescriptor
	 * @return string
	 */
	public static function getPageForm( $output, $formDescriptor ) {
		$builder = new self( $output, $formDescriptor );
		return $builder->build();
	}

	/**
	 * Main build method
	 *
	 * @return string
	 */
	public function build() {
		$this->loadExistingData();
		$this->processSchema();
		$formData = $this->prepareFormData();
		$attributes = $this->buildAttributes();
		$result = \JsonForms::getJsonFormHtml( $formData, $attributes );

		if ( !$result->ok ) {
			return $result->error;
		}

		return $result->value;
	}

	/**
	 * Load existing data from the page if editing
	 *
	 * @return void
	 */
	private function loadExistingData(): void {
		if ( !$this->shouldLoadExistingData() ) {
			return;
		}

		$editTitle = $this->getEditTitle();
		if ( !$editTitle || !$editTitle->isKnown() ) {
			return;
		}

		$wikiPage = \JsonForms::getWikiPage( $editTitle );

		if ( !$wikiPage ) {
			return;
		}

		$this->loadFormData( $wikiPage, $editTitle );
		$this->loadCategories( $editTitle );
		$this->loadFreeText( $editTitle );
	}

	/**
	 * Check if we should load existing data
	 *
	 * @return bool
	 */
	private function shouldLoadExistingData(): bool {
		$isCreateAction = property_exists( $this->formDescriptor, 'action' ) &&
			$this->formDescriptor->action === 'create';

		return !empty( $this->formDescriptor->edit ) && !$isCreateAction;
	}

	/**
	 * Get the edit title
	 *
	 * @return Title|null
	 */
	private function getEditTitle() {
		return TitleClass::newFromText( $this->formDescriptor->edit );
	}

	/**
	 * Load form data from the wiki page
	 *
	 * @param \WikiPage $wikiPage
	 * @param Title|MediaWiki\Title\Title $editTitle
	 * @return void
	 */
	private function loadFormData( $wikiPage, $editTitle ): void {
		if ( empty( $this->formDescriptor->schema ) ) {
			return;
		}

		$metadata = \JsonForms::getMetadata( $wikiPage );
		if ( !$this->isValidMetadata( $metadata ) ) {
			return;
		}

		$slotRole = $this->getSlotRole();
		if ( !isset( $metadata->slots->$slotRole ) ) {
			return;
		}

		$slotMetadata = $metadata->slots->$slotRole;
		$content = \JsonForms::getSlotContent( $wikiPage, $slotRole );

		if ( !$content ) {
			return;
		}

		$this->startVal->form = new stdClass();
		$json = \JsonForms::processFormData( $content, $slotMetadata );
		$jsonData = $this->extractJsonData( $json );
		$this->startVal->form->editor = $jsonData;
	}

	/**
	 * Check if metadata is valid
	 *
	 * @param stdClass|null $metadata
	 * @return bool
	 */
	private function isValidMetadata( $metadata ): bool {
		return $metadata &&
			isset( $metadata->slots ) &&
			is_object( $metadata->slots );
	}

	/**
	 * Get the slot role from form descriptor or default
	 *
	 * @return string
	 */
	private function getSlotRole(): string {
		return $this->formDescriptor->slot ?? \JsonForms::SLOT_ROLE_JSONFORMS_DATA;
	}

	/**
	 * Extract JSON data based on edit_path
	 *
	 * @param mixed $json
	 * @return string
	 */
	private function extractJsonData( $json ): string {
		if ( empty( $this->formDescriptor->edit_path ) ) {
			return SlotEditor::stringifyMaybeJSON( $json );
		}

		[ $shouldAppend, $_ ] = SchemaUtils::parseAppendPath(
			$this->formDescriptor->edit_path
		);

		if ( $shouldAppend ) {
			return SlotEditor::stringifyMaybeJSON( $json );
		}

		$extractedJson = SchemaUtils::getValueByPath(
			$json,
			$this->formDescriptor->edit_path
		);

		if ( !empty( (array)$extractedJson ) ) {
			return SlotEditor::stringifyMaybeJSON( $extractedJson );
		}

		return '';
	}

	/**
	 * Load categories if requested
	 *
	 * @param itle|MediaWiki\Title\Title $editTitle
	 * @return void
	 */
	private function loadCategories( $editTitle ): void {
		$shouldLoadCategories = isset( $this->formDescriptor->edit_categories ) &&
			$this->formDescriptor->edit_categories === true;

		if ( !$shouldLoadCategories ) {
			return;
		}

		$categories = \JsonForms::getNonAnnotatedCategories( $editTitle );
		if ( empty( $categories ) ) {
			return;
		}

		if ( !isset( $this->startVal->form->options ) ) {
			$this->startVal->form->options = new stdClass();
		}
		$this->startVal->form->options->categories = $categories;
	}

	/**
	 * Load free text content if requested
	 *
	 * @param itle|MediaWiki\Title\Title $editTitle
	 * @return void
	 */
	private function loadFreeText( $editTitle ): void {
		$slotRole = $this->getSlotRole();
		$shouldLoadFreeText = $slotRole !== SlotRecord::MAIN &&
			isset( $this->formDescriptor->edit_freetext ) &&
			$this->formDescriptor->edit_freetext === true;

		if ( !$shouldLoadFreeText ) {
			return;
		}

		if ( !isset( $this->startVal->form->options ) ) {
			$this->startVal->form->options = new stdClass();
		}

		$this->startVal->form->options->freetext_content_model = $editTitle->getContentModel();
		$this->startVal->form->options->freetext = \JsonForms::getArticleContent( $editTitle );
	}

	/**
	 * Process the schema for the form
	 *
	 * @return void
	 */
	private function processSchema(): void {
		if ( empty( $this->formDescriptor->schema ) ) {
			return;
		}

		$this->schemaName =  $this->formDescriptor->schema;
		$this->editSchemaName =  $this->formDescriptor->edit_schema;

		// first ensure article revision exists, then use the indicated
		// or last revision

		if ( empty( $this->formDescriptor->schema_revision ) ) {

			// also if not used, this will create the article if missing
			$rawSchema = \JsonForms::getSourceSchema( $this->schemaName, "JsonSchema" );

			$this->formDescriptor->schema_revision = $this->getSchemaRevision( $this->schemaName );
		}

		if (
			!empty( $this->editSchemaName ) &&
			empty( $this->formDescriptor->edit_schema_revision )
		) {
			$rawSchema = \JsonForms::getSourceSchema( $this->editSchemaName, "JsonSchema" );
			$this->formDescriptor->edit_schema_revision = $this->getSchemaRevision( $this->editSchemaName );
		}

		if ( empty( $this->editSchemaName ) ) {
			$title = TitleClass::newFromText( "JsonSchema:" . $this->schemaName );
			$rawSchema = \JsonForms::getRevisionContent( $title, $this->formDescriptor->schema_revision );		

		} else {
			$title = TitleClass::newFromText( "JsonSchema:" . $this->editSchemaName );
			$rawSchema = \JsonForms::getRevisionContent( $title, $this->formDescriptor->edit_schema_revision );
		}

		if ( empty( $rawSchema ) ) {
			return;
		}

		if ( !$rawSchema instanceof stdClass ) {
			$rawSchema = json_decode( $rawSchema, false );
		}

		$this->processedSchema = \JsonForms::processSchema( $this->output, $rawSchema );

		$this->setEditorConfig();
	}

	/**
	 * Set the schema configuration
	 *
	 * @return void
	 */
	private function setEditorConfig(): void {

		// either schema or edit_schema
		$this->jsonForm->properties->form->properties->editor->{'x-input-config'}->schema = json_encode(
			$this->processedSchema
		);

		// edit_path
		if ( !empty( $this->formDescriptor->edit_path ) ) {
			$this->jsonForm->properties->form->properties->editor->{'x-input-config'}->edit_path =
				$this->formDescriptor->edit_path;
		}

		// create only fields
		$isEditing = !empty( $this->formDescriptor->edit );
		$hasCreateOnlyFields = isset( $this->formDescriptor->create_only_fields ) &&
			is_array( $this->formDescriptor->create_only_fields );

		if ( $isEditing && $hasCreateOnlyFields ) {
			$this->jsonForm->properties->form->properties->editor->{'x-input-config'}->disableFields =
				$this->formDescriptor->create_only_fields;
		}
	}

	/**
	 * @param string $schemaName
	 * @return null|int
	 */
	private function getSchemaRevision( $schemaName ) {
		$title = TitleClass::newFromText( "JsonSchema:" . $schemaName );
		$revisionRecord = \JsonForms::revisionRecordFromTitle( $title );

		if ( !$revisionRecord ) {
			return null;
		}

		return $revisionRecord->getId();
	}

	/**
	 * Prepare the form data object
	 *
	 * @return stdClass
	 */
	private function prepareFormData(): stdClass {
		$formData = new stdClass();
		$formData->schema = $this->jsonForm;
		$formData->schemaName = "PageForm";
		$formData->editorOptions = $this->formDescriptor->editor_options ?? "MediaWiki:DefaultEditorOptions";
		$formData->formDescriptor = $this->formDescriptor;
		$formData->startval = $this->startVal;

		return \JsonForms::prepareFormData( $this->output, $formData );
	}

	/**
	 * Build HTML attributes for the form
	 *
	 * @return array
	 */
	private function buildAttributes(): array {
		$attributes = [];

		if ( !empty( $this->formDescriptor->width ) ) {
			$attributes['width'] = $this->formDescriptor->width;
		}

		if ( !empty( $this->formDescriptor->css_class ) ) {
			$attributes['css_class'] = $this->formDescriptor->css_class;
		}

		return $attributes;
	}

	/**
	 * Normalize form descriptor to object
	 *
	 * @param array|stdClass $formDescriptor
	 * @return stdClass
	 */
	private function normalizeFormDescriptor( $formDescriptor ): stdClass {
		if ( is_array( $formDescriptor ) ) {
			return (object)$formDescriptor;
		}
		return $formDescriptor;
	}
}
