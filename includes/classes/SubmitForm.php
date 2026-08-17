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

namespace MediaWiki\Extension\JsonForms;

use stdClass;
use CommentStoreComment;
use ContentHandler;
use ContentModelChange;
use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use Parser;
use RawMessage;
use RequestContext;
use Status;

class SubmitForm {
	/** @var Output */
	protected $output;

	/** @var Context */
	protected $context;

	/** @var User */
	protected $user;

	/** @var MediaWikiServices */
	protected $services;

	/** @var stdClass */
	protected $existingDataOfTargetSlot;

	/** @var stdClass */
	protected $schemaMetadata;

	/**
	 * @param User $user
	 * @param Context|null $context can be null
	 */
	public function __construct( $user, $context = null ) {
		$this->user = $user;
		// @ATTENTION ! use always Main context, in api
		// context OutputPage -> parseAsContent works
		// in a different way !
		$this->context = $context ?? RequestContext::getMain();
		$this->output = $this->context->getOutput();
		$this->services = MediaWikiServices::getInstance();
	}

	/**
	 * @param Output $output
	 */
	protected function setOutput( $output ) {
		$this->output = $output;
	}

	/**
	 * @param string|array $value
	 * @return string
	 */
	protected function parseWikitext( $value ) {
		// return $this->parser->recursiveTagParseFully( $str );
		$values = is_array( $value ) ? $value : [ $value ];

		$parsed = array_map(
			fn ( $v ) => Parser::stripOuterParagraph(
				$this->output->parseAsContent( $v ),
			),
			$values,
		);

		return is_array( $value ) ? $parsed : $parsed[0];
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @param string $content
	 * @param string $contentModel
	 * @param array &$errors
	 * @return bool
	 */
	protected function createInitialRevision(
		$title,
		$content,
		$contentModel,
		&$errors = [],
	) {
		// "" will trigger an error by ContentHandler::makeContent
		// if ( empty( $contentModel ) ) {
		// 	$contentModel = null;
		// }

		// @see https://github.com/wikimedia/mediawiki/blob/master/includes/page/WikiPage.php
		$flags = EDIT_SUPPRESS_RC | EDIT_AUTOSUMMARY | EDIT_INTERNAL;
		$summary = 'JsonForms initial revision';

		$wikiPage = \JsonForms::getWikiPage( $title );
		$pageUpdater = $wikiPage->newPageUpdater( $this->user );

		$services = MediaWikiServices::getInstance();
		$contentHandlerFactory = $services->getContentHandlerFactory();
		$contentHandler = $contentHandlerFactory->getContentHandler(
			$contentModel,
		);

		$main_content = !empty( $content )
			? ContentHandler::makeContent(
				(string)$content,
				$title,
				$contentModel,
			)
			: $contentHandler->makeEmptyContent();

		$pageUpdater->setContent( SlotRecord::MAIN, $main_content );
		$comment = CommentStoreComment::newUnsavedComment( $summary );
		$revisionRecord = $pageUpdater->saveRevision( $comment, $flags );
		$status = $pageUpdater->getStatus();
		return $status->isOK();
	}

	/**
	 * @see includes/specials/SpecialChangeContentModel.php
	 * @param WikiPage $page
	 * @param string $model
	 * @return Status
	 */
	protected function changeContentModel( $page, $model ) {
		// $page = $this->wikiPageFactory->newFromTitle( $title );
		// ***edited
		$performer = method_exists( RequestContext::class, 'getAuthority' )
			? $this->context->getAuthority()
			: $this->user;
		// ***edited
		$services = $this->services;
		$contentModelChangeFactory = $services->getContentModelChangeFactory();
		$changer = $contentModelChangeFactory->newContentModelChange(
			// ***edited
			$performer,
			$page,
			// ***edited
			$model,
		);
		// MW 1.36+
		if ( method_exists( ContentModelChange::class, 'authorizeChange' ) ) {
			$permissionStatus = $changer->authorizeChange();
			if ( !$permissionStatus->isGood() ) {
				// *** edited
				$out = $this->output;
				$wikitext = $out->formatPermissionStatus( $permissionStatus );
				// Hack to get our wikitext parsed
				return Status::newFatal( new RawMessage( '$1', [ $wikitext ] ) );
			}
		} else {
			$errors = $changer->checkPermissions();
			if ( $errors ) {
				// *** edited
				$out = $this->output;
				$wikitext = $out->formatPermissionsErrorMessage( $errors );
				// Hack to get our wikitext parsed
				return Status::newFatal( new RawMessage( '$1', [ $wikitext ] ) );
			}
		}
		// Can also throw a ThrottledError, don't catch it
		$status = $changer->doContentModelChange(
			// ***edited
			$this->context,
			// $data['reason'],
			'',
			true,
		);
		return $status;
	}

	/**
	 * @param Title|MediaWiki\Title\Title $targetTitle
	 * @param \WikiPage $wikiPage
	 * @param string $contentModel
	 * @param array &$errors
	 * @return bool
	 */
	protected function updateContentModel(
		$targetTitle,
		$wikiPage,
		$contentModel,
		&$errors,
	) {
		$status = $this->changeContentModel( $wikiPage, $contentModel );
		if ( !$status->isOK() ) {
			$errors_ = $status->getErrorsByType( 'error' );
			foreach ( $errors_ as $error ) {
				$msg = array_merge( [ $error['message'] ], $error['params'] );
				// @see SpecialVisualData -> getMessage
				$errors[] = \Message::newFromSpecifier( $msg )
					->setContext( $this->context )
					->parse();
			}
		}
	}


	/**
	 * @param array $json
	 * @param stdClass $structuredValue
	 * @param stdClass $slotMetadata
	 * @param string $targetSlot
	 * @param WikiPage $wikiPage
	 * @param array &$errors
	 * @return array|false
	 */
	protected function postProcessJsonData(
		$json,
		$structuredValue,
		$slotMetadata, 
		$targetSlot,
		$wikiPage,
		&$errors
	) {
		if ( !$structuredValue || !is_object( $structuredValue ) ) {
			return;
		}
	
		$thisClass = $this;
		$callback = static function ( &$parent, $key, &$value, $pathArr ) use (
			$slotMetadata,
			$structuredValue,
			$thisClass,
			$targetSlot,
			$wikiPage,
			$errors
		) {
			$path = implode( '.', $pathArr );

			if (
				isset( $structuredValue->schemas ) && 
				is_object( $structuredValue->schemas )
			) {
				
				if ( isset( $structuredValue->schemas->$path ) ) {
					// strip x-runtime-only
					if (
						isset( $structuredValue->schemas->$path->{'x-runtime-only'} ) &&
						$structuredValue->schemas->$path->{'x-runtime-only'} === true
					) {
						// Remove property from object
						unset( $parent->$key );
					}

					if (
						property_exists( $structuredValue->schemas->$path, 'x-value-formula' ) &&
						!empty( $structuredValue->schemas->$path->{'x-value-formula'} )
					) {
						if ( !isset( $slotMetadata->originalValues ) ) {
							$slotMetadata->originalValues = [];

						} elseif ( is_object( $slotMetadata->originalValues ) ) {
							$slotMetadata->originalValues = (array)$slotMetadata->originalValues;
						}
    
						$slotMetadata->originalValues[$path] = $value;
						$value_ = str_replace( '<value>', $value, $structuredValue->schemas->$path->{'x-value-formula'} );
						$parent->{$key} = $thisClass->parseWikitext( $value_ );
					}
				}
			}

			if ( 
				isset( $structuredValue->values ) && 
				is_object( $structuredValue->values ) && 
				isset( $structuredValue->values->$path ) 				
			) {
				if ( isset( $structuredValue->values->$path->renamedFrom ) ) {
					$oldTitle = TitleClass::newFromText( 'File:' . $structuredValue->values->$path->renamedFrom );
					$newTitle = TitleClass::newFromText( 'File:' . $value );
					
					if ( !$newTitle ) {
						$errors[] = $thisClass->context->msg( 'jsonforms-special-submit-rename-file-invalid-name',
							$oldTitle->getFullText(),
							$value,
						)->text();

						return false;
					}
					
					if ( $newTitle->exists() ) {
						$errors[] = $thisClass->context->msg( 'jsonforms-special-submit-rename-file-target-title-exists',
							$oldTitle->getFullText(),
							$value,
						)->text();

						return false;
					}

					$reason = 'JsonForms rename file';
					$createRedirect = false;
					if (
						!\JsonForms::movePage(
							$thisClass->user,
							$oldTitle,
							$newTitle,
							$reason,
							$createRedirect
						)
					) {
						$errors[] = $thisClass->context->msg( 'jsonforms-special-submit-rename-file-error',
							$oldTitle->getFullText(),
							$newTitle->getFullText(),
						)->text();

						return false;
					}
				}

	 			if ( isset( $structuredValue->values->$path->filekey ) ) {
					$user = $thisClass->user;
					$filekey = $structuredValue->values->$path->filekey;
					$filename = $value;
					$comment = '';
					$text = '';
					$watch = false;
					$tags = [];
					$watchlistExpiry = null;

					$publishStashedFile = new PublishStashedFile(
						$user,
						$filekey,
						$filename,
						$comment,
						$text,
						$watch,
						$tags,
						$watchlistExpiry				
					);

					if ( $publishStashedFile->publish() ) {
						// $fileName = $publishStashedFile->getUploadedFileName();
						// $imageInfo = $publishStashedFile->getImageInfo();

					} else {
						$errors[] = $publishStashedFile->getLastError();
						return false;
					}
				}
			}
		};

		return SchemaUtils::traverseSchema( $json, $callback );
	}

	/**
	 * Validate CAPTCHA if present
	 *
	 * @param stdClass $data The request data
	 * @param array &$errors Reference to errors array
	 * @return bool True if CAPTCHA validation passes or no CAPTCHA present, false on failure
	 */
	protected function validateCaptcha( $data, &$errors ) {
		if ( property_exists( $data->options, 'captcha' ) ) {
			$recaptchaSecret = $GLOBALS['wgJsonFormsReCaptchaSecretKey'];
			$recaptchaResponse = $data->options->captcha;

			$response = file_get_contents(
				"https://www.google.com/recaptcha/api/siteverify?secret={$recaptchaSecret}&response={$recaptchaResponse}"
			);
			$responseKeys = json_decode( $response, true );

			if ( !$responseKeys['success'] ) {
				$errors[] = $this->context->msg( 'jsonforms-special-submit-captcha-error' )->text();
				return false;
			}
		}
		return true;
	}

	/**
	 * Get target title from various sources
	 *
	 * @param stdClass $data The request data
	 * @param array &$errors Reference to errors array
	 * @return Title|null The target title or null on error
	 */
	protected function getTargetTitleFromData( $data, &$errors ) {
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
				$errors[] = $this->context->msg( 'jsonforms-special-submit-computed-target-title-error' )->text();
				return null;
			}
		}
		
		if ( empty( $targetTitle ) && empty( $titleStr ) ) {
			$errors[] = $this->context->msg( 'jsonforms-special-submit-notitle' )->text();
			return null;
		}
		
		// If targetTitle is still null, create from titleStr
		if ( !$targetTitle ) {
			$targetTitle = TitleClass::newFromText( $titleStr );
			
			if ( !$targetTitle ) {
				$errors[] = $this->context->msg( 'jsonforms-special-submit-title-not-valid' )->text();
				return null;
			}
		}
		
		return $targetTitle;
	}

	/**
	 * Check if page exists and handle overwrite rules
	 *
	 * @param Title $targetTitle The target page title
	 * @param stdClass $data The request data
	 * @param array &$errors Reference to errors array
	 * @param bool $isNewPageMode Whether this is new page creation mode
	 * @return bool True if access is valid, false otherwise
	 */
	protected function validatePageAccess( $targetTitle, $data, &$errors, $isNewPageMode = false ) {
		// Check write permissions
		if ( !\JsonForms::checkWritePermissions( $this->user, $targetTitle, $errors ) ) {
			return false;
		}
		
		// New page creation mode
		if ( $isNewPageMode ) {
			if ( $targetTitle->isKnown() ) {
				$errors[] = $this->context->msg(
					'jsonforms-special-submit-article-exists',
					$targetTitle->getDBKey()
				)->parse();
				return false;
			}
			return true;
		}

		// Edit/update modes
		if ( $targetTitle->isKnown() && 
			isset( $data->formDescriptor ) &&
			 empty( $data->formDescriptor->edit ) && 
			 $data->formDescriptor->overwrite_existing_article_on_create !== true
		) {
			$errors[] = $this->context->msg(
				'jsonforms-special-submit-article-exists',
				$targetTitle->getDBKey()
			)->parse();
			return false;
		}
		
		return true;
	}

	/**
	 * Get content model for main slot
	 *
	 * @param stdClass $data The request data
	 * @param Title $previousTargetTitle null The reference target title
	 * @return string The content model name
	 */
	protected function getContentModel( $data, $previousTargetTitle = null ) {		
		if ( !empty( $data->options->freetext_content_model ) ) {
			return $data->options->freetext_content_model;
		}

		if ( isset( $data->options->content_model ) ) {
			return $data->options->content_model;
		}

		// slot manager
		if (
			$data->processor === 'SlotManager' &&
			isset( $data->value->content_model )
		) {
			return $data->value->content_model;
		}

		if ( $previousTargetTitle && $previousTargetTitle->isKnown() ) {
			return $previousTargetTitle->getContentModel();
		}

		return 'wikitext';
	}

	/**
	 * Get main slot content
	 *
	 * @param stdClass $data The request data
	 * @param Title $targetTitle null The target page title
	 * @param bool $isNewPage false Whether this is a new page
	 * @return string|null The main slot content or null if not found
	 */
	protected function getMainContent( $data, $targetTitle = null, $isNewPage = false ) {
		if ( property_exists( $data, 'options' ) ) {
			if ( property_exists( $data->options, 'freetext' ) ) {
				return $data->options->freetext;
			}

			if ( property_exists( $data->options, 'content' ) ) {
				return $data->options->content;
			}
		}

		// slot manager
		if (
			$data->processor === 'SlotManager' &&
			is_object( $data->value ) &&
			property_exists( $data->value, 'content' )
		) {
			return $data->value->content;
		}

		$ret = null;

		// For new pages with preload
		if ( $isNewPage  ) {
			if ( !empty( $data->formDescriptor->preload_article ) ) {
				$title_ = \JsonForms::getTitleIfKnown( $data->formDescriptor->preload_article );
				if ( $title_ ) {
					$ret = \JsonForms::getWikipageContent( $title_ );
				}
			} elseif ( !empty( $data->formDescriptor->preload_wikitext ) ) {
				$ret = $data->formDescriptor->preload_wikitext;
			}

		// For existing pages, get existing content if needed
		} else {
			$ret = \JsonForms::getWikipageContent( $targetTitle );
		}
		
		return $ret;
	}

	/**
	 * Get previous page for move operations
	 *
	 * @param stdClass $data The request data
	 * @return Title|null The previous page title or null
	 */
	protected function getPreviousPage( $data ) {
		if ( !empty( $data->options->title ) && !empty( $data->formDescriptor->edit ) ) {
			return TitleClass::newFromText( $data->formDescriptor->edit );
		}
		return null;
	}

	/**
	 * Determine target slot based on context
	 *
	 * @param stdClass $data The request data
	 * @param bool $isNewPage Whether this is a new page
	 * @param string|null $mainSlotContent The main slot content
	 * @param WikiPage $previousWikiPage The reference wiki page
	 * @param WikiPage $wikiPage The target wiki page
	 * @return string The target slot name
	 */
	protected function determineTargetSlot( $data, $isNewPage, $mainSlotContent, $previousWikiPage, $wikiPage ) {
		// Check if slot is explicitly specified
		if (
			isset( $data->formDescriptor) &&
			!empty( $data->formDescriptor->slot )
		) {
			return $data->formDescriptor->slot;
		}

		// For new pages with no main content, use main slot
		if ( $isNewPage && $mainSlotContent === null ) {
			return SlotRecord::MAIN;
		}

		// Check previous metadata for existing slot
		$previousMetadata = \JsonForms::getMetadata( $wikiPage );
		if ( $previousMetadata && isset( $previousMetadata->slots ) && is_object( $previousMetadata->slots ) ) {
			$slots = $previousMetadata->slots;
			if ( property_exists( $slots, SLOT_ROLE_JSONFORMS_DATA ) ) {
				return SLOT_ROLE_JSONFORMS_DATA;
			}

			if (
				property_exists( $slots, SlotRecord::MAIN ) && 
				isset( $slots->{SlotRecord::MAIN}->schema )
			) {
				return SlotRecord::MAIN;
			}
		}

		// Try to get existing JSON slot
		$targetSlot = \JsonForms::getFirstJsonSlot( $previousWikiPage );
		if ( $targetSlot ) {
			return $targetSlot;
		}

		// Default to data slot
		return SLOT_ROLE_JSONFORMS_DATA;
	}

	/**
	 * Get target slot from existing metadata
	 *
	 * @param stdClass|null $metadata
	 * @return string The target slot name
	 */
	protected function getTargetSlotFromMetadata( $metadata ) {
		if ( !$metadata ) {
			return SLOT_ROLE_JSONFORMS_DATA;
		}

		if ( !isset( $metadata->slots ) || !is_object( $metadata->slots ) ) {
			return SLOT_ROLE_JSONFORMS_DATA;
		}

		$metadataSlots = $metadata->slots;
		if ( property_exists( $metadataSlots, SLOT_ROLE_JSONFORMS_DATA ) ) {
			return SLOT_ROLE_JSONFORMS_DATA;
		}

		if ( property_exists( $metadataSlots, SlotRecord::MAIN ) &&
			( $metadataSlots->{SlotRecord::MAIN}->schema ?? null ) !== null
		) {
			return SlotRecord::MAIN;
		}

		return SLOT_ROLE_JSONFORMS_DATA;
	}

	/**
	 * Build metadata object
	 *
	 * @param stdClass $data The request data
	 * @param string $targetSlot The target slot name
	 * @param string $contentModelMainSlot The main slot content model
	 * @param stdClass|null $previousMetadata Previous metadata if exists
	 * @param bool $isDeleteSchema Whether schema is being deleted
	 * @return stdClass The built metadata object
	 */
	protected function buildMetadata( $data, $targetSlot, $contentModelMainSlot, $previousMetadata = null, $isDeleteSchema = false ) {
		$metadata = $previousMetadata ? clone $previousMetadata : new stdClass();

		if ( !isset( $metadata->slots ) || !is_object( $metadata->slots ) ) {
			$metadata->slots = new stdClass();
		}

		// Set main slot metadata
		$metadata->slots->{SlotRecord::MAIN} = new stdClass();
		$metadata->slots->{SlotRecord::MAIN}->model = $contentModelMainSlot;
		$metadata->slots->{SlotRecord::MAIN}->editor = $this->defaultEditorForContentModel( $contentModelMainSlot );

		// Set target slot metadata (if not main and not deleting schema)
		if ( $targetSlot !== SlotRecord::MAIN && !$isDeleteSchema ) {
			$this->setTargetSlotMetadata( $metadata, $data, $targetSlot );

		} elseif ( $targetSlot === SlotRecord::MAIN && !$isDeleteSchema ) {
			$this->setMainSlotSchema( $metadata, $data );
		}

		// Add categories if present
		if ( !empty( $data->options->categories ) && is_array( $data->options->categories ) ) {
			$metadata->categories = $data->options->categories;
		}

		if ( !empty( $data->value->categories ) && is_array( $data->value->categories ) ) {
			$metadata->categories = $data->value->categories;
		}

		return $metadata;
	}

	/**
	 * Set target slot metadata fields
	 *
	 * @param stdClass $metadata Reference to metadata object
	 * @param stdClass $data The request data
	 * @param string $targetSlot The target slot name
	 * @return void
	 */
	protected function setTargetSlotMetadata( &$metadata, $data, $targetSlot ) {
		if ( !property_exists( $metadata->slots, $targetSlot ) ) {
			$metadata->slots->$targetSlot = new stdClass();
		}

		$slotMetadata = &$metadata->slots->{$targetSlot};

		$slotMetadata->editor = 'JsonEditor';
		$slotMetadata->model = 'json';

		// Set schema
		$schema = null;
		if ( isset( $data->metadata->schemaName ) ) {
			$schema = $data->metadata->schemaName;
		} elseif ( isset( $data->formDescriptor->schema ) ) {
			$schema = $data->formDescriptor->schema;
		}

		if ( !empty( $data->formDescriptor->schema_revision ) ) {
			$slotMetadata->schemaRevision = $data->formDescriptor->schema_revision;
		}

		// @TODO this is not correct, since there could be multiple edit_schema
		if ( !empty( $data->formDescriptor->edit_schema_revision ) ) {
			$slotMetadata->schemaRevision = $data->formDescriptor->edit_schema_revision;
		}

		$slotMetadata->schema = $schema;

		// Set additional metadata fields
		$metadataKeys = [
			'show_infobox' => 'showInfobox',
			'infobox_position' => 'infoboxPosition',
			'infobox_template' => 'infoboxTemplate',
		];

		foreach ( $metadataKeys as $key => $value ) {
			// if is pageform and data already exist, do not overwrite
			if (
				property_exists( $slotMetadata, $value ) &&
				$data->processor === 'PageForms'
			) {
				continue;
			}

			if ( property_exists( $data->metadata, $key ) ) {
				$slotMetadata->{$value} = $data->metadata->$key;
			}
		}
	}

	/**
	 * Set main slot schema
	 *
	 * @param stdClass $metadata Reference to metadata object
	 * @param stdClass $data The request data
	 * @return void
	 */
	protected function setMainSlotSchema( &$metadata, $data ) {
		$slotMetadata = &$metadata->slots->{SlotRecord::MAIN};

		if ( isset( $data->metadata->schemaName ) ) {
			$slotMetadata->schema = $data->metadata->schemaName;

		} elseif ( isset( $data->formDescriptor->schema ) ) {
			$slotMetadata->schema = $data->formDescriptor->schema;
		}
	}

	/**
	 * Get editor based on content model
	 *
	 * @param string $contentModel The content model name
	 * @return string The editor name
	 */
	protected function defaultEditorForContentModel( $contentModel ) {
		if ( $contentModel === 'wikitext' ) {
			return 'WikiEditor';
		}

		if ( $contentModel === 'json' ) {
			return 'JsonEditor';
		}

		return 'source';
	}

	/**
	 * Build slots array
	 *
	 * @param string $targetSlot The target slot name
	 * @param mixed $dataToSave The data to save
	 * @param string|null $mainSlotContent The main slot content
	 * @param string $contentModelMainSlot The main slot content model
	 * @param stdClass $metadata The metadata object
	 * @param bool $deleteSchema Whether schema is being deleted
	 * @return array The built slots array
	 */
	protected function buildSlots( $targetSlot, $dataToSave, $mainSlotContent, $contentModelMainSlot, $metadata, $deleteSchema = false ) {
		$slots = [];

		// Add JSON data slot if we have data and not deleting
		if ( $dataToSave !== null && !$deleteSchema ) {
			$slots[$targetSlot] = [
				'model' => 'json',
				'content' => json_encode( $dataToSave )
			];
		}
		
		// Add main slot if not data-only and we have content
		if ( $targetSlot !== SlotRecord::MAIN && $mainSlotContent !== null ) {
			$slots[SlotRecord::MAIN] = [
				'model' => $contentModelMainSlot,
				'content' => $mainSlotContent
			];
		}
		
		// Add metadata slot if we have metadata
		if ( !empty( (array)$metadata ) ) {
			$slots[SLOT_ROLE_JSONFORMS_METADATA] = [
				'model' => 'json',
				'content' => json_encode( $metadata )
			];
		}

		return $slots;
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param string $targetSlot
	 * @return mixed
	 */
	protected function getExistingDataOfSlot( $wikiPage, $targetSlot ) {
		if ( $this->existingDataOfTargetSlot ) {
			return $this->existingDataOfTargetSlot;
		}

		$content = \JsonForms::getSlotContent( $wikiPage, $targetSlot );
		$data = SlotEditor::parseMaybeJSON( $content );
		$this->existingDataOfTargetSlot = $data;

		return $data;
	}

	/**
	 * Handle partial edit
	 *
	 * @param stdClass $data The request data
	 * @param WikiPage $previousWikiPage The reference wiki page
	 * @param string $targetSlot The target slot name
	 * @param mixed $dataToSave The data to save
	 * @return mixed The processed data
	 */
	protected function handlePartialEdit( $data, $previousWikiPage, $targetSlot, $dataToSave, &$errors ) {
		if ( empty( $data->formDescriptor->edit_path ) ) {
			return $dataToSave;
		}

		$baseData = $this->getExistingDataOfSlot( $previousWikiPage, $targetSlot );

		if ( !is_array( $baseData ) && !is_object( $baseData ) ) {
			$errors[] = $this->context->msg( 'jsonforms-special-submit-source-data-error' )->text();
			return;
		}

		$partialData = SchemaUtils::getValueByPath( $baseData, $data->formDescriptor->edit_path );

		if ( is_object( $partialData ) && is_object( $dataToSave ) ) {
			$dataToSave = SchemaUtils::mergeObjectsRecursive( $dataToSave, $partialData );
		}

		SchemaUtils::setValueByPath( $baseData, $data->formDescriptor->edit_path, $dataToSave );
		return $baseData;
	}

	/**
	 * Process return URL logic
	 *
	 * @param stdClass $data The request data
	 * @param Title $targetTitle The target page title
	 * @param bool $isNewPage Whether this is a new page
	 * @param array &$errors Reference to errors array
	 * @return array|null The return URL data or null on error
	 */
	protected function processReturnUrl( $data, $targetTitle, $isNewPage, &$errors ) {
		$services = MediaWikiServices::getInstance();
		$returnMessage = null;
		$returnUrl = null;
		$localUrl = null;

		if ( !empty( $data->formDescriptor->return_url ) ) {
			$data->formDescriptor->return = 'url';
	
		} elseif ( !empty( $data->formDescriptor->return_page ) ) {
			$data->formDescriptor->return = 'article';

		} else if ( empty( $data->formDescriptor->return ) ) {
			$data->formDescriptor->return = 'target';
		}
	
		switch ( $data->formDescriptor->return ) {
			case 'none':
				$localUrl = $targetTitle->getLocalURL();
				$targetUrl = (string)$services->getUrlUtils()->expand( $localUrl, PROTO_FALLBACK );
				$messageKey = 'jsonforms-jsmodule-return-message-' . ( $isNewPage ? 'create' : 'edit' );
				$returnMessage = $this->context->msg( $messageKey, $targetTitle->getFullText(), $targetUrl )->text();
				break;
				
			case 'article':
				if ( !empty( $data->formDescriptor->return_page ) ) {
					$title_ = TitleClass::newFromText( $data->formDescriptor->return_page );
					if ( $title_ && $title_->isKnown() ) {
						$localUrl = $title_->getLocalURL();
						break;
					}
				}
				$localUrl = $targetTitle->getLocalURL();
				break;
				
			case 'url':
				if ( !empty( $data->formDescriptor->return_url ) ) {
					$localUrl = $data->formDescriptor->return_url;
					$returnUrl = (string)$services->getUrlUtils()->expand( $localUrl, PROTO_FALLBACK );
				}
				break;
				
			case 'target':
			default:
				$localUrl = $targetTitle->getLocalURL();
		}
		
		// Ensure we have a URL
		if ( !$returnUrl ) {
			if ( !$localUrl ) {
				$errors[] = $this->context->msg( 'jsonforms-special-submit-return-no-return-url' )->text();
				return null;
			}
			$returnUrl = (string)$services->getUrlUtils()->expand( $localUrl, PROTO_FALLBACK );
		}
		
		// Validate URL
		if ( filter_var( $returnUrl, FILTER_VALIDATE_URL ) === false ) {
			$errors[] = $this->context->msg( 'jsonforms-special-submit-return-validate-url-error', $returnUrl )->text();
			return null;
		}
		
		return [
			'returnUrl' => $returnUrl,
			'message' => $returnMessage,
			'targetTitle' => $targetTitle
		];
	}

	/**
	 * Handle page move logic
	 *
	 * @param Title|null $previousPage The previous page title
	 * @param Title $targetTitle The target page title
	 * @return array|false Array with [oldTitle, newTitle] or false if no move needed
	 */
	protected function handlePageMove( $previousPage, $targetTitle ) {
		if ( $previousPage && $previousPage->getFullText() !== $targetTitle->getFullText() ) {
			return [ $previousPage, $targetTitle ];
		}
		return false;
	}

	/**
	 * Get existing slots (excluding metadata)
	 *
	 * @param WikiPage $wikiPage The wiki page
	 * @return array The existing slots
	 */
	protected function getExistingSlots( $wikiPage ) {
		$slots = [];
		$slots_ = \JsonForms::getSlots( $wikiPage );
		foreach ( $slots_ as $role => $slot ) {
			if ( $role === SLOT_ROLE_JSONFORMS_METADATA ) {
				continue;
			}
			$content = \JsonForms::getSlotContent( $wikiPage, $role );
			$slots[$role] = [
				'model' => $slot->getModel(),
				'content' => $content,
			];
		}
		return $slots;
	}

	/**
	 * Initialize metadata object
	 *
	 * @param stdClass|null $metadataPrevious
	 * @param string $contentModelMainSlot
	 * @return stdClass
	 */
	protected function initializeMetadata( $metadataPrevious, $contentModelMainSlot ) {
		$metadata = $metadataPrevious ? clone $metadataPrevious : new stdClass();
		
		if ( !isset( $metadata->slots ) || !is_object( $metadata->slots ) ) {
			$metadata->slots = new stdClass();
		}

		$metadata->slots->{SlotRecord::MAIN} = new stdClass();
		$metadata->slots->{SlotRecord::MAIN}->model = $contentModelMainSlot;

		return $metadata;
	}

	protected function isEmptyValue( $value ) {
		if ( $value === null || $value === false || $value === '' ) {
			return true;
		}
	
		if ( is_numeric( $value ) ) {
			return false;
		}

		return false;
	}

	/**
	 * @param stdClass $schema
	 * @return stdClass|false
	 */
	 protected function getSchemaMetadata( $schema ) {
	 	if ( $this->schemaMetadata ) {
	 		return $this->schemaMetadata;
	 	}

		$title = TitleClass::newFromText( 'JsonSchema:' . $schema );

		if ( !$title || !$title->exists() ) {
			return false;
		}

		$wikiPage = \JsonForms::getWikiPage( $title );

		if ( !$wikiPage ) {
			return false;
		}

		$metadata = \JsonForms::getMetadata( $wikiPage );

		if ( !$metadata || !is_object( $metadata ) ) {
			$metadata = new stdClass();
		}

		if ( !isset( $metadata->processedSchema ) ) {
			$metadata->processedSchema = [];

		} elseif ( !is_array( $metadata->processedSchema ) ) {
			$metadata->processedSchema = (array)$metadata->processedSchema;
		}

		$this->schemaMetadata = $metadata;
		return [ $metadata, $wikiPage ];
	}

	/**
	 * @param User $user
	 * @param WikiPage $wikiPage
	 * @param stdClass $metadata The metadata to save
	 * @param array &$errors
	 * @return true|array True on success, error array on failure
	 */
	public function saveSchemaMetadata( $user, $wikiPage, $metadata, &$errors ) {
		$slots = [
			SLOT_ROLE_JSONFORMS_METADATA => [
				'content' => json_encode( $metadata ),
				'model' => 'json'
			]
		];

		$slotEditor = new SlotEditor();

		$summary = '';
		$minor = false;
		$append = false;
		$watchlist = '';
		$prepend = false;
		$bot = false;
		$createonly = false;
		$nocreate = false;
		$suppress = false;

		$updateStrategy = 'merge';

		return $slotEditor->editSlots(
			$user,
			$wikiPage,
			$slots,
			$summary,
			$append,
			$watchlist,
			$prepend,
			$bot,
			$minor,
			$createonly,
			$nocreate,
			$suppress,
			$updateStrategy,
			$errors
		);
	}

	/**
	 * Process structured value with schema deduplication and path tracking
	 * 
	 * @param stdClass $data
	 * @param stdClass $slotMetadata
	 * @param string $targetSlot
	 * @param WikiPage $wikiPage
	 * @param array &errors
	 * @return bool|void
	 */
	 protected function processStructuredValue( $data, $slotMetadata, $targetSlot, $wikiPage, &$errors ) {
		if (
			!$slotMetadata ||
			empty( $slotMetadata->schema )
		) {
			$errors[] = 'schema is not set';
			return;
		}

		if (
			!isset( $data->structuredValue ) ||
			!isset( $data->structuredValue->schemas ) ||
			!isset( $data->structuredValue->jsonPaths )
		) {
			$errors[] = 'internal error';
			return;
		}

		[ $schemaMetadata, $schemaWikiPage ] = $this->getSchemaMetadata( $slotMetadata->schema );

		if ( !$schemaMetadata ) {
			$errors[] = 'Cannot load related schema';
			return;
		}
		// trigger_error('^^$data ' . print_r($data,1));

		// trigger_error('^^$slotMetadata brefore' . print_r($slotMetadata,1));
		$editPath = $data->formDescriptor->edit_path ?? '';
		if ( empty( $editPath ) ) {
			$schemas = $data->structuredValue->schemas;
			$slotMetadata->jsonPaths = $data->structuredValue->jsonPaths;

		} else {
			if ( empty ( $data->formDescriptor->edit_jsonpath ) ) {
				$errors[] = 'no partial jsonpath';
				return;
			}

			$edit_jsonpath = $data->formDescriptor->edit_jsonpath;		
			[ $shouldAppend, $editPath ] = SchemaUtils::parseAppendPath( $editPath );

			// a dot shouldn't be appended here, so it's a safe check
			[ $_, $edit_jsonpath ] = SchemaUtils::parseAppendPath( $edit_jsonpath );

			$schemas = new stdClass();
			foreach ( $data->structuredValue->schemas as $jsonPath => $schema) {
				$schemas->{$edit_jsonpath . ( $jsonPath ? '.' . $jsonPath : '' )} = $schema;
			}

			if ( $shouldAppend ) {
				$baseData = $this->getExistingDataOfSlot( $wikiPage, $targetSlot );
				$partialData = SchemaUtils::getValueByPath( $baseData, $editPath );
				$editPath = $editPath . '.' . count( $partialData );
			}

			$slotMetadata->jsonPaths = $slotMetadata->jsonPaths ?? new stdClass();
			foreach ( $data->structuredValue->jsonPaths as $path => $jsonPath ) {
				$appendJsonPath = $edit_jsonpath . ( $jsonPath ? '.' . $jsonPath : '' );
				$newPath = $editPath . ( $path ? '.' . $path : '' );
				$slotMetadata->jsonPaths->$newPath = $appendJsonPath;
			}
		}

		// trigger_error('^^$slotMetadata ' . print_r($slotMetadata,1));

		$thisClass = $this;
		foreach ( $schemas as $jsonPath => $schema ) {
			$schemaMetadata->processedSchema[ $jsonPath ] =	(object)array_filter( (array)$schema,
				static function ( $value ) use ( $thisClass ) {
					return !$thisClass->isEmptyValue( $value );
				} );
		}

		// trigger_error('^^$schemaMetadata ' . print_r($schemaMetadata,1));
		return $this->saveSchemaMetadata( $this->user, $schemaWikiPage, $schemaMetadata, $errors );
	}

	/**
	 * Preserve main slot schema
	 *
	 * @param stdClass $metadata
	 * @param stdClass|null $metadataPrevious
	 * @param string $contentModelMainSlot
	 * @return void
	 */
	protected function preserveMainSlotSchema( $metadata, $metadataPrevious, $contentModelMainSlot ) {
		if (
			$contentModelMainSlot === 'json' && 
			$metadataPrevious &&
			isset( $metadataPrevious->slots->{SlotRecord::MAIN}->schema ) &&
			!empty( $metadataPrevious->slots->{SlotRecord::MAIN}->schema )
		) {
			$metadata->slots->{SlotRecord::MAIN}->schema = $metadataPrevious->slots->{SlotRecord::MAIN}->schema;
		}
	}

	/**
	 * Add categories to metadata
	 *
	 * @param stdClass $metadata
	 * @param stdClass $data
	 * @return void
	 */
	protected function addCategories( $metadata, $data ) {
		if ( !empty( $data->value->categories ) && is_array( $data->value->categories ) ) {
			$metadata->categories = $data->value->categories;
		}
	}

	/**
	 * Process additional slots from data->value
	 *
	 * @param stdClass $metadata
	 * @param stdClass $data
	 * @param array &$slots
	 * @return void
	 */
	protected function processAdditionalSlots( $metadata, $data, &$slots ) {
		$roleNames = SlotHelper::getSlotRoles();

		foreach ( get_object_vars( $data->value ) as $key => $value ) {
			// Skip non-slot properties
			if ( !in_array( $key, $roleNames ) ) {
				continue;
			}

			// Skip metadata slot
			if ( $key === SLOT_ROLE_JSONFORMS_METADATA ) {
				continue;
			}
			
			// Create slot metadata
			if ( !isset( $metadata->slots->{$key} ) ) {
				$metadata->slots->{$key} = new stdClass();
			}

			$metadata->slots->{$key}->model = $value->content_model;
			$metadata->slots->{$key}->editor = $value->editor;

			// Add to slots
			$slots[$key] = [
				'model' => $value->content_model,
				'content' => $value->content,
			];
		}

		$this->removeUnusedMetadata( $roleNames, $metadata, $slots );
	}

	/**
	 * @param array $roleNames
	 * @param stdClass &$metadata
	 * @param array $slots
	 */
	protected function removeUnusedMetadata( $roleNames, &$metadata, $slots ) {
		foreach ( get_object_vars( $metadata->slots ) as $role => $value ) {
			if ( $role === SLOT_ROLE_JSONFORMS_METADATA || $role === SlotRecord::MAIN ) {
				continue;
			}

			if ( !array_key_exists( $role, $slots ) ) {
				unset( $metadata->slots->$role );
			}
		}
	}

	/**
	 * Check if slot has data in metadata
	 *
	 * @param stdClass|null $metadata
	 * @param string $slotKey
	 * @param string $property
	 * @return bool
	 */
	protected function hasSlotData( $metadata, $slotKey, $property ) {
		return $metadata &&
			   isset( $metadata->slots->{$slotKey}->{$property} ) &&
			   !empty( $metadata->slots->{$slotKey}->{$property} );
	}

	/**
	 * @param stdClass $data
	 * @return array
	 */
	public function processData( $data ) {
		$className = $data->processor;
		$class = "MediaWiki\Extension\JsonForms\SubmitProcessors\\{$className}";
		if ( !class_exists( $class ) ) {
			$errors[] = $this->context
				->msg( 'jsonforms-special-submit-processor-not-found' )
				->text();
			return [
				'errors' => $errors,
			];
		}

		$services = $this->services;

		$errors = [];
		$services
			->getHookContainer()
			->run( 'JsonForms::FormSubmitBeforeProcess', [
				$this->user,
				&$data,
				&$errors,
			] );

		if ( count( $errors ) ) {
			return [
				'errors' => $errors,
			];
		}

		$submitProcessor = new $class( $this->user );
		$res_ = $submitProcessor->processData( $data );

		if ( !$res_->ok ) {
			return [
				'errors' => [ $res_->error ],
			];
		}

		[ $processedData, $returnData ] = $res_->value;

		// move page
		if ( !empty( $processedData['movePage'] ) ) {
			[ $oldTitle, $newTitle ] = $processedData['movePage'];
			$reason = 'JsonForms move';
			$createRedirect = false;
			if (
				!\JsonForms::movePage(
					$this->user,
					$oldTitle,
					$newTitle,
					$reason,
					$createRedirect
				)
			) {
				return [
					'errors' => [
						$this->context->msg(
								'jsonforms-special-submit-move-error',
								$oldTitle->getFullText(),
								$newTitle->getFullText(),
							)
							->text(),
					],
				];
			}
		}

		$hookResult = $services
			->getHookContainer()
			->run( 'JsonForms::FormSubmitBeforeSave', [
				$this->user,
				&$data,
				&$processedData,
				&$errors,
			] );

		if ( count( $errors ) ) {
			return [
				'errors' => $errors,
			];
		}

		if ( $hookResult === false ) {
			return $returnData;
		}

		$slotEditor = new SlotEditor();

		$summary = isset( $data->options ) ? $data->options->summary ?? '' : '';
		$minor = isset( $data->options ) ? $data->options->minor ?? false : false;
		$append = false;
		$watchlist = '';
		$prepend = false;
		$bot = false;
		$createonly = false;
		$nocreate = false;
		$suppress = false;

		$wikiPage = \JsonForms::getWikiPage( $processedData['targetTitle'] );

		$ret = $slotEditor->editSlots(
			$this->user,
			$wikiPage,
			$processedData['slots'],
			$summary,
			$append,
			$watchlist,
			$prepend,
			$bot,
			$minor,
			$createonly,
			$nocreate,
			$suppress,

			// with merge as update strategy SLOT_ROLE_JSONFORMS_METADATA
			// needs to be explicitly unset setting an empty content on save
			$processedData['updateStrategy'] ?? 'merge',
			$errors
		);

		if ( $ret !== true ) {
			return [
				'errors' => $errors,
			];
		}

		// \JsonForms::setMetadata( $this->context, $wikiPage, $metadata );
		if ( !$processedData['isNewPage'] ) {
			$wikiPage->doPurge();
		}

		$services
			->getHookContainer()
			->run( 'JsonForms::FormSubmitSuccess', [
				$this->user,
				$data,
				$processedData,
				&$returnData,
				&$errors,
			] );

		if ( count( $errors ) ) {
			return [
				'errors' => $errors,
			];
		}

		return $returnData;
	}
}

