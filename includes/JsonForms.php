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
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2025-2026, https://wikisphere.org
 */

use MediaWiki\Content\JsonContent;
use MediaWiki\Extension\JsonForms\Aliases\Html as HtmlClass;
use MediaWiki\Extension\JsonForms\Aliases\Linker as LinkerClass;
use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Extension\JsonForms\FormParameters;
use MediaWiki\Extension\JsonForms\InfoboxRender;
use MediaWiki\Extension\JsonForms\QueryLinkParameters;
use MediaWiki\Extension\JsonForms\ResultWrapper;
use MediaWiki\Extension\JsonForms\SchemaUtils;
use MediaWiki\Extension\JsonForms\SlotEditor;
use MediaWiki\Extension\JsonForms\SlotHelper;
use MediaWiki\Extension\JsonForms\TemplateRender;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

if ( is_readable( __DIR__ . "/../vendor/autoload.php" ) ) {
	include_once __DIR__ . "/../vendor/autoload.php";
}

class JsonForms {
	/** @var array */
	private static $slotsCache = [];

	/** @var int */
	public static $queryLimit = 500;

	/** @var array */
	public static $UserGroupsCache = [];

	/** @var UserGroupManager */
	public static $userGroupManager;

	public static function initialize() {
		self::$userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();
	}

	/**
	 * @param Parser $parser
	 * @param mixed ...$argv
	 * @return array
	 */
	public static function parserFunctionRender( Parser $parser, ...$argv ) {
		// @TODO, create separate schema or merge with parserFunctionForm
		// (action = render)
		$formSchema = (object)[
			"type" => "object",
			"properties" => (object)[
				"schema" => (object)[
					"type" => "string",
				],
				"slot" => (object)[
					"type" => "string",
					"default" => SLOT_ROLE_JSONFORMS_DATA,
				],
				"print_scalar" => (object)[
					"type" => "bool",
					"default" => true,
				],
			],
		];

		$titleText = $argv[0];

		$parameters = new FormParameters( $argv, $formSchema );

		$named = $parameters->getOptions();

		$title = TitleClass::newFromText( $titleText );

		$wikiPage = self::getWikiPage( $title );

		$content = self::getSlotContent( $wikiPage, $named['slot'] );

		$obj = $content ? json_decode( $content, false ) : [];

		$templateRender = new TemplateRender( $parser, $named );
		$ret = $templateRender->render( $obj );

		// echo $ret;
		// exit;
		return [ $ret, "noparse" => false, "isHTML" => false ];
	}

	/**
	 * @param Parser $parser
	 * @param mixed ...$argv
	 * @return array
	 */
	public static function parserFunctionForm( Parser $parser, ...$argv ) {
		$parserOutput = $parser->getOutput();
		$parserOutput->setExtensionData( "jsonforms", true );

		$functionReturn = static function ( $value ) {
			return [ $value, "noparse" => true, "isHTML" => true ];
		};

		if ( empty( $argv[0] ) ) {
			return $functionReturn(
				self::printError(
					$parserOutput,
					"jsonforms-parserfunction-error-no-form-name",
				),
			);
		}

		$function = "form";
		$formName = $argv[0];
		$data = [];
		$errorMessage = null;

		$formSchema = self::getSourceSchema(
			"CreatePageForm",
			"JsonSchema/Core",
		);

		$parameters = new FormParameters( $argv, $formSchema );

		$named = $parameters->getOptions();
		$context = RequestContext::getMain();
		$output = $context->getOutput();

		if ( empty( $formName ) ) {
			return $functionReturn(
				self::printError(
					$parserOutput,
					"jsonforms-parserfunction-error-no-form-name",
				),
			);
		}

		$formDescriptor = self::getSourceSchema( $formName, "JsonForm" );
		if ( empty( $formDescriptor ) ) {
			return $functionReturn(
				self::printError(
					$parserOutput,
					"jsonforms-parserfunction-error-no-form",
				),
			);
		}

		// formDescriptor prevails over default parameters but
		// inline parameters prevail over formDescriptor
		foreach ( $named as $k => $v ) {
			// Check if property exists in the object
			$propertyExists = property_exists( $formDescriptor, $k );

			if ( !$propertyExists || in_array( $k, $parameters->initialKnown ) ) {
				$formDescriptor->$k = $v;
			}
		}

		// Handle css_class
		$css_classes = [];
		if (
			property_exists( $formDescriptor, "css_class" ) &&
			!empty( $formDescriptor->css_class )
		) {
			$css_classes[] = $formDescriptor->css_class;
		}

		if (
			!property_exists( $formDescriptor, "view" ) ||
			$formDescriptor->view !== 'popup'
		) {
			$css_classes[] = "jsonforms-form-inline";

		} else {
			unset( $formDescriptor->width );
			$css_classes[] = "jsonforms-form-popup";
		}

		$formDescriptor->css_class = implode( ' ', $css_classes );

		$result = self::getPageForm( $output, $formDescriptor );

		return [ $result, "noparse" => true, "isHTML" => true ];

		/*
		$parser->addTrackingCategory( "jsonforms-trackingcategory-parserfunction-$function" );
		$title = $parser->getTitle();

		$spinner = HtmlClass::rawElement(
			'div',
			[ 'class' => 'mw-rcfilters-spinner mw-rcfilters-spinner-inline', 'style' => 'display:none' ],
			HtmlClass::element(
				'div',
				[ 'class' => 'mw-rcfilters-spinner-bounce' ]
			)
		);

		$errorMessage = '';
		return [
			$errorMessage . HtmlClass::rawElement(
				'div',
				[
					'class' => 'VisualDataFormItem VisualDataFormWrapper',
					'data-form-data' => json_encode( $formData )
				],
				wfMessage( "visualdata-parserfunction-$function-placeholder" )->text()
			),
			'noparse' => true,
			'isHTML' => true
		];
*/
	}

	/**
	 * @param Output $output
	 * @param array $formDescriptor
	 * @return string
	 */
	public static function getPageForm( $output, $formDescriptor ) {
		$jsonForm = self::getSourceSchema( "PageFormUI", "JsonSchema/Core" );

		$startVal = new stdClass();

		if ( !empty( $formDescriptor->edit ) ) {
			$editTitle = TitleClass::newFromText( $formDescriptor->edit );

			if ( $editTitle && $editTitle->isKnown() ) {
				$wikiPage = self::getWikiPage( $editTitle );

				if ( !empty( $formDescriptor->schema ) ) {
					$metadata = self::getMetadata( $wikiPage );

					if (
						$metadata &&
						isset( $metadata->slots ) &&
						is_object( $metadata->slots ) &&
						property_exists( $metadata->slots, $formDescriptor->slot )
					) {
						$role = $formDescriptor->slot;
						$slotData = $metadata->slots->$role;

						$content = self::getSlotContent(
							$wikiPage,
							$role,
						);

						if ( $content ) {
							$startVal->form = new stdClass();

							if ( empty( $formDescriptor->edit_path ) ) {
								$startVal->form->editor = $content;

							} else {
								[
									$shouldAppend,
									$_,
								] = SchemaUtils::parseAppendPath( $formDescriptor->edit_path );

								if ( !$shouldAppend ) {
									$json = SlotEditor::parseMaybeJSON( $content );
									$json = SchemaUtils::getValueByPath(
										$json,
										$formDescriptor->edit_path,
									);
									if ( !empty( $json ) ) {
										$startVal->form->editor = SlotEditor::stringifyMaybeJSON(
											$json,
										);
									}
								}
							}
						}
					}
				}

				if (
					isset( $formDescriptor->edit_categories ) &&
					$formDescriptor->edit_categories === true
				) {
					$categories = self::getNonAnnotatedCategories( $editTitle );
					if ( !isset( $startVal->form->options ) ) {
						$startVal->form->options = new stdClass();
					}
					$startVal->form->options->categories = $categories;
				}

				if (
					$formDescriptor->slot !== SlotRecord::MAIN &&
					isset( $formDescriptor->edit_freetext ) &&
					$formDescriptor->edit_freetext === true
				) {
					if ( !isset( $startVal->form->options ) ) {
						$startVal->form->options = new stdClass();
					}
					$startVal->form->options->freetext_content_model = $editTitle->getContentModel();
					$startVal->form->options->freetext = self::getArticleContent(
						$editTitle,
					);
				}
			}
		}

		$schemaName = null;
		$schema = [];
		if ( !empty( $formDescriptor->schema ) ) {
			$schema = self::getSourceSchema(
				!empty( $formDescriptor->edit_schema ) ? $formDescriptor->edit_schema : $formDescriptor->schema,
				"JsonSchema",
			);
			$schemaName = $formDescriptor->schema;

			// Initialize nested objects for jsonForm
			if (
				!isset(
					$jsonForm->properties->form->properties->editor
						->{'x-input-config'}
				)
			) {
				if ( !isset( $jsonForm->properties ) ) {
					$jsonForm->properties = new stdClass();
				}
				if ( !isset( $jsonForm->properties->form ) ) {
					$jsonForm->properties->form = new stdClass();
				}
				if ( !isset( $jsonForm->properties->form->properties ) ) {
					$jsonForm->properties->form->properties = new stdClass();
				}
				if ( !isset( $jsonForm->properties->form->properties->editor ) ) {
					$jsonForm->properties->form->properties->editor = new stdClass();
				}
				if (
					!isset(
						$jsonForm->properties->form->properties->editor
							->{'x-input-config'}
					)
				) {
					$jsonForm->properties->form->properties->editor->{'x-input-config'} = new stdClass();
				}
			}

			$schema = self::processSchema( $output, $schema );
			$jsonForm->properties->form->properties->editor->{'x-input-config'}->schema = json_encode(
				$schema,
			);

			if ( !empty( $formDescriptor->edit_path ) ) {
				$jsonForm->properties->form->properties->editor->{'x-input-config'}->edit_path =
					$formDescriptor->edit_path;
			}

			if (
				!empty( $formDescriptor->edit ) &&
				isset( $formDescriptor->create_only_fields ) &&
				is_array( $formDescriptor->create_only_fields )
			) {
				$jsonForm->properties->form->properties->editor->{'x-input-config'}->disableFields =
					$formDescriptor->create_only_fields;
			}
		}

		$formData = new stdClass();
		$formData->schema = $jsonForm;
		$formData->schemaName = "PageForm";
		$formData->editorOptions =
			$formDescriptor->editor_options ?? "MediaWiki:DefaultEditorOptions";
		$formData->formDescriptor = $formDescriptor;
		$formData->startval = $startVal;

		$formData = self::prepareFormData( $output, $formData );

		$attr = [];
		if ( !empty( $formDescriptor->width ) ) {
			$attr["width"] = $formDescriptor->width;
		}
		if ( !empty( $formDescriptor->css_class ) ) {
			$attr["css_class"] = $formDescriptor->css_class;
		}

		$res_ = self::getJsonFormHtml( $formData, $attr );

		if ( !$res_->ok ) {
			return $res_->error;
		}

		return $res_->value;
	}

	/**
	 * @param Context $context
	 * @param string $titleStr
	 * @return mixed
	 */
	public static function getArticleMetadata( $context, $titleStr ) {
		$parserOptions = ParserOptions::newFromContext( $context );
		$title = TitleClass::newFromText( $titleStr );
		$wikiPage = self::getWikiPage( $title );
		$parserOutput = $wikiPage->getParserOutput( $parserOptions );
		return $parserOutput->getExtensionData( "JsonForms" );
	}

	/**
	 * @param int $ns
	 * @return string|null
	 */
	public static function getFormattedNamespace( $ns ) {
		$formattedNamespaces = MediaWikiServices::getInstance()
			->getContentLanguage()
			->getFormattedNamespaces();

		if ( isset( $formattedNamespaces[$ns] ) ) {
			return $formattedNamespaces[$ns];
		}

		return "";
	}

	/**
	 * @param Context $context
	 * @param WikiPage $wikiPage
	 * @return mixed
	 */
	public static function getMetadata( $wikiPage ) {
		$ret = self::getSlotContent( $wikiPage, SLOT_ROLE_JSONFORMS_METADATA );
		if ( $ret ) {
			$ret = json_decode( $ret, false );
		}
		return $ret ?? [];
	}

	/**
	 * @see https://www.php.net/manual/en/function.array-merge-recursive.php
	 * @param array $arr1
	 * @param array $arr2
	 * @param bool $replaceLists false
	 * @return array
	 */
	public static function arrayMergeRecursive(
		$arr1,
		$arr2,
		$replaceLists = false,
	) {
		$ret = $arr1;

		if ( self::isList( $arr1 ) && self::isList( $arr2 ) ) {
			if ( $replaceLists ) {
				return $arr2;
			}

			// append values to list
			foreach ( $arr2 as $value ) {
				$ret[] = $value;
			}

			return $ret;
		}

		foreach ( $arr2 as $key => $value ) {
			if ( is_array( $value ) && isset( $ret[$key] ) && is_array( $ret[$key] ) ) {
				$ret[$key] = self::arrayMergeRecursive(
					$ret[$key],
					$value,
					$replaceLists,
				);
			} else {
				$ret[$key] = $value;
			}
		}

		return $ret;
	}

	/**
	 * @param array $arr
	 * @see https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential
	 * @return bool
	 */
	public static function isList( $arr ) {
		if ( function_exists( "array_is_list" ) ) {
			return array_is_list( $arr );
		}
		if ( $arr === [] ) {
			return true;
		}
		return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
	}

	/**
	 * @return array
	 */
	public static function getDefaultTrackingCategories() {
		$services = MediaWikiServices::getInstance();
		if ( method_exists( $services, "getTrackingCategories" ) ) {
			$trackingCategoriesClass = $services->getTrackingCategories();
			$trackingCategories = $trackingCategoriesClass->getTrackingCategories();
		} else {
			$context = RequestContext::getMain();
			$config = $context->getConfig();
			$trackingCategories = new TrackingCategories( $config );
		}

		$ret = [];
		foreach ( $trackingCategories as $value ) {
			foreach ( $value["cats"] as $title_ ) {
				$ret[] = $title_->getText();
			}
		}
		return $ret;
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return array
	 */
	public static function getTrackingCategories( $title ) {
		$ret = self::getCategories( $title );

		$trackingCategories = self::getDefaultTrackingCategories();
		foreach ( $ret as $key => $category ) {
			if ( !in_array( $category, $trackingCategories ) ) {
				unset( $ret[$key] );
			}
		}

		return array_values( $ret );
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return array
	 */
	public static function getNonAnnotatedCategories( $title ) {
		$ret = self::getCategories( $title );

		// remove tracking categories
		$trackingCategories = self::getTrackingCategories( $title );
		foreach ( $ret as $key => $category ) {
			if ( in_array( $category, $trackingCategories ) ) {
				unset( $ret[$key] );
			}
		}

		// remove categories annotated on the page,
		// since we will not tinker with wikitext
		// necessary only if content model is wikitext
		$wikiPage = self::getWikiPage( $title );
		if ( $wikiPage->getContentModel() === CONTENT_MODEL_WIKITEXT ) {
			// $jsonData = self::getJsonData( $title );
			$context = RequestContext::getMain();
			$data = self::getMetadata( $context, $wikiPage );
			if ( $data && !empty( $data["categories"] ) ) {
				foreach ( $ret as $key => $category ) {
					if ( !in_array( $category, $data["categories"] ) ) {
						unset( $ret[$key] );
					}
				}
			}
		}

		return array_values( $ret );
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return array
	 */
	public static function getCategories( $title ) {
		if ( !$title || !$title->isKnown() ) {
			return [];
		}

		$wikiPage = self::getWikiPage( $title );

		// a special page
		if ( !$wikiPage ) {
			return [];
		}

		$arr = $wikiPage->getCategories();
		$ret = [];
		foreach ( $arr as $title_ ) {
			$ret[] = $title_->getText();
		}

		return $ret;
	}

	/**
	 * @param MediaWiki\Parser\ParserOutput $parserOutput
	 * @param string $msg
	 * @return string
	 */
	public static function printError( $parserOutput, $msg ) {
		$parserOutput->setEnableOOUI();
		\OOUI\Theme::setSingleton( new \OOUI\WikimediaUITheme() );

		return new \OOUI\MessageWidget( [
			"type" => "error",
			"label" => new \OOUI\HtmlSnippet( wfMessage( $msg )->parse() ),
		] );
	}

	/**
	 * @param Output $output
	 * @param mixed $value
	 * @return string
	 */
	public static function parseWikitext( $output, $value ) {
		// return $this->parser->recursiveTagParseFully( $str );
		return Parser::stripOuterParagraph( $output->parseAsContent( $value ) );
	}

	/**
	 * @param Output $output
	 * @param array $formData
	 * @param array $attr
	 * @return ResultWrapper
	 */
	public static function getJsonForm( $output, $formData = [], $attr = [] ) {
		$res = self::prepareFormData( $output, $formData );
		if ( !$res->ok ) {
			return ResultWrapper::failure( $res->error );
		}

		return self::getJsonFormHtml( $res->value, $attr );
	}

	/**
	 * @param Output $output
	 * @param StdClass $schema
	 * @return array
	 */
	public static function processSchema( $output, $schema ) {
		if ( empty( $schema ) ) {
			return [];
		}

		$wikitextKeys = [
			"x-title-format" => "title",
			"x-description-format" => "description",
			"x-label-format" => "label",
			"x-enum-titles-format" => "x-enum-titles",
		];

		$callback = static function ( &$parent, $key, &$value, $pathArr ) use (
			$output,
			$wikitextKeys,
		) {
			// Handle both array and object
			$isObject = is_object( $value );

			if ( !$isObject && !is_array( $value ) ) {
				return;
			}

			foreach ( $wikitextKeys as $k => $v ) {
				// Get the value of the property (works for both array and object)
				$fieldValue = null;
				if ( $isObject ) {
					if ( !property_exists( $value, $v ) || is_object( $value->$v ) ) {
						continue;
					}
					$fieldValue = $value->$v;
				} else {
					if ( !isset( $value[$v] ) || is_array( $value[$v] ) ) {
						continue;
					}
					$fieldValue = $value[$v];
				}

				// Get or set the format
				$format = "text";
				if ( $isObject ) {
					if ( property_exists( $value, $k ) ) {
						$format = $value->$k;

					} else {
						$value->$k = "text";
					}
				} else {
					if ( isset( $value[$k] ) ) {
						$format = $value[$k];

					} else {
						$value[$k] = "text";
					}
				}

				switch ( $format ) {
					case "html":
						// do not escape
						break;

					case "wikitext":
						if ( !is_array( $fieldValue ) ) {
							$parsed = self::parseWikitext( $output, $fieldValue );

						} else {
							$parsed = [];
							foreach ( $fieldValue as $k_ => $v_ ) {
								$parsed[] = self::parseWikitext( $output, $v_ );
							}
						}

						if ( $isObject ) {
							$value->$v = $parsed;

						} else {
							$value[$v] = $parsed;
						}
						break;

					case "text":
					default:
						if ( !is_array( $fieldValue ) ) {
							$escaped = htmlspecialchars( $fieldValue );

						} else {
							$escaped = [];
							foreach ( $fieldValue as $k_ => $v_ ) {
								$escaped[] = htmlspecialchars( $v_ );
							}
						}

						if ( $isObject ) {
							$value->$v = $escaped;

						} else {
							$value[$v] = $escaped;
						}
						break;
				}
			}
		};

		return SchemaUtils::traverseSchema( $schema, $callback );
	}

	/**
	 * @param Output $output
	 * @param array $formParameters
	 * @param array $schemaObj
	 * @return ResultWrapper
	 */
	public static function prepareFormData( $output, $data ) {
		if ( !empty( $data->schema ) ) {
			$data->schema = self::processSchema( $output, $data->schema );
		}

		if ( !empty( $data->editorOptions ) ) {
			$title_ = TitleClass::newFromText(
				$data->editorOptions,
				NS_MEDIAWIKI,
			);
			if ( $title_ && $title_->isKnown() ) {
				$data->editorOptions = self::getWikipageContent( $title_ );
			}
		}

		if ( !empty( $data->editorScript ) ) {
			$title_ = TitleClass::newFromText(
				$data->editorScript,
				NS_MEDIAWIKI,
			);
			if ( $title_ && $title_->isKnown() ) {
				$data->editorScript = self::getWikipageContent( $title_ );
			}
		}

		return $data;
	}

	/**
	 * @param array $data
	 * @param array $attr
	 * @return ResultWrapper
	 */
	public static function getJsonFormHtml( $data, $attr = [] ) {
		// $requiredKeys = [ 'schema','schemaName', 'editorOptions' ];
		// if ( count( array_intersect_key( (array)$data, array_flip( $requiredKeys ) ) ) !== 3 ) {
		// 	return ResultWrapper::failure('jsonforms-parserfunction-error-invalid-data');
		// }

		$loadingContainer = HtmlClass::rawElement(
			"div",
			[
				"class" => "rcfilters-head mw-rcfilters-head",
				"id" => "mw-rcfilters-spinner-wrapper",
				"style" => "position: relative",
			],
			HtmlClass::rawElement(
				"div",
				[
					"class" => "initb mw-rcfilters-spinner",
					"style" => "margin-top: auto; top: 25%",
				],
				HtmlClass::element( "div", [
					"class" => "inita mw-rcfilters-spinner-bounce",
				] ),
			),
		);

		$loadingPlaceholder = HtmlClass::rawElement(
			"div",
			[ "class" => "jsonforms-form-placeholder" ],
			// $this->msg( 'jsonforms-loading-placeholder' )->text()
			wfMessage( "jsonforms-loading-placeholder" )->text(),
		);

		$ret = HtmlClass::rawElement(
			"div",
			[
				"data-form-data" => json_encode( $data ),
				"class" =>
					"jsonforms-form jsonforms-form-wrapper" .
					( !isset( $attr["css_class"] )
						? ""
						: " " . $attr["css_class"] ),
				"style" => !isset( $attr["width"] )
					? ""
					: "width:" . $attr["width"],
			],
			$loadingContainer . $loadingPlaceholder,
		);

		return ResultWrapper::success( $ret );
	}

	/**
	 * @param Parser $parser
	 * @param mixed ...$argv
	 * @return array
	 */
	public static function parserFunctionQueryLink( Parser $parser, ...$argv ) {
		$parserOutput = $parser->getOutput();

		/*
{{#querylink: pagename
|label
|class=
|class-attr-name=class
|target=
|target-attr-name=target
|a=b
|c=d
|...
}}
*/

		$ql = new QueryLinkParameters( $argv );

		// unnamed
		$values = $ql->getValues();

		// known named
		$options = $ql->getOptions();

		// unknown named
		$query = $ql->getQuery();

		$attributes = $ql->getAttributes();
		$text = $ql->getText();
		$title = $ql->getTitle();

		// *** alternatively use $linkRenderer->makePreloadedLink
		// or $GLOBALS['wgArticlePath'] and wfAppendQuery
		$ret = LinkerClass::link( $title, $text, $attributes, $query );

		return [ $ret, "noparse" => true, "isHTML" => true ];
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return bool
	 */
	public static function isKnownArticle( $title ) {
		// *** unfortunately we cannot always rely on $title->isContentPage()
		// @see https://github.com/debtcompliance/EmailPage/pull/4#discussion_r1191646022
		// or use $title->exists()
		return $title &&
			$title->canExist() &&
			$title->getArticleID() > 0 &&
			$title->isKnown();
	}

	/**
	 * @param OutputPage $outputPage
	 * @return void
	 */
	public static function appendContent( $outputPage ) {
		$wikiPage = $outputPage->getWikiPage();

		if ( !$wikiPage ) {
			return;
		}

		$title = $outputPage->getTitle();
		$ns = $title->getNamespace();
		$user = $outputPage->getUser();

		if ( $ns === NS_JSONSCHEMA && $user->isAllowed( "jsonforms-canmanageschemas" ) ) {
			$outputPage->enableOOUI();
			$outputPage->addModules( "ext.JsonForms.infobox" );

			$specialpage_title = SpecialPage::getTitleFor( "JsonFormsManage", "Schemas" );
			$url = $specialpage_title->getLinkURL( [ 'action' => 'edit', 'pageid' => $title->getID() ] );

			$html = new \OOUI\MessageWidget( [
				// success
				"type" => "info",
				"icon" => "edit",
				"label" => new \OOUI\HtmlSnippet(
					wfMessage(
						"jsonforms-jsonschema-namespace-schema-message",
						$url
					)->text(),
				),
			] ) . HtmlClass::rawElement( 'p' );

			$outputPage->prependHTML( $html );

			return;
		}

		if ( $ns === NS_JSONFORM && $user->isAllowed( "jsonforms-canmanageforms" ) ) {
			$outputPage->enableOOUI();
			$outputPage->addModules( "ext.JsonForms.infobox" );

			$specialpage_title = SpecialPage::getTitleFor( "JsonFormsManage", "Forms" );
			$url = $specialpage_title->getLinkURL( [ 'action' => 'edit', 'pageid' => $title->getID() ] );

			$html = new \OOUI\MessageWidget( [
				// success
				"type" => "info",
				"icon" => "edit",
				"label" => new \OOUI\HtmlSnippet(
					wfMessage(
						"jsonforms-jsonschema-namespace-form-message",
						$url
					)->text(),
				),
			] ) . HtmlClass::rawElement( 'p' );

			$outputPage->prependHTML( $html );

			return;
		}

		if ( !in_array( $ns, self::getConfigValue( 'JsonFormsEditSchemaNamespaces' ) ) ) {
			return;
		}

		$metadata = self::getMetadata( $wikiPage );

		if ( !$metadata ) {
			return;
		}

		// Check if slots exists and is an object, and if SLOT_ROLE_JSONFORMS_DATA exists
		if (
			!property_exists( $metadata, "slots" ) ||
			!is_object( $metadata->slots ) ||
			!property_exists( $metadata->slots, SLOT_ROLE_JSONFORMS_DATA )
		) {
			return;
		}

		$data = $metadata->slots->{SLOT_ROLE_JSONFORMS_DATA};

		if ( empty( $data->showInfobox ) ) {
			return;
		}

		$text = self::getSlotContent( $wikiPage, SLOT_ROLE_JSONFORMS_DATA );

		if ( !$text ) {
			return;
		}

		$obj = json_decode( $text, false );
		$position = $data->infoboxPosition ?? 'right';

		$outputPage->enableOOUI();

		// custom template
		if ( !empty( $data->infoboxTemplate ) ) {
			// @see ApiExpandTemplates
			$parser = MediaWikiServices::getInstance()
				->getParserFactory()
				->create();
			$context = $outputPage->getContext();
			$parserOptions = ParserOptions::newFromContext( $context );

			$parser->startExternalParse(
				$title,
				$parserOptions,
				Parser::OT_PREPROCESS,
			);

			$templateRender = new TemplateRender( $parser );
			$templatePrefix = "Template:" . $data->infoboxTemplate;
			$ret = $templateRender->render( $obj, $templatePrefix );

		// automatic template
		} else {
			$processedSchema = new stdClass();
			if (
				!empty(
					$metadata->slots->{SLOT_ROLE_JSONFORMS_DATA}
						->processedSchema
				)
			) {
				// $processedSchema = (array)$obj_->slots->{SLOT_ROLE_JSONFORMS_DATA}->processedSchema;
				$processedSchema =
					$metadata->slots->{SLOT_ROLE_JSONFORMS_DATA}
						->processedSchema;
			}

			$schema = $metadata->slots->{SLOT_ROLE_JSONFORMS_DATA}->schema;

			$infoboxRender = new InfoboxRender( $user, $title, $schema, $processedSchema );
			$ret = $infoboxRender->render( $obj );
		}

		$outputPage->addModules( "ext.JsonForms.infobox" );

		switch ( $position ) {
			case "top":
			case "right":
			case "left":
				$html = HtmlClass::rawElement(
					"div",
					// toccolours
					[
						"class" => "jsonforms-infobox jsonforms-infobox-$position",
					],
					$ret,
				);

				$outputPage->prependHTML( $html );
				break;

			case "bottom":
				$html = HtmlClass::rawElement(
					"div",
					// toccolours
					[
						"class" => "jsonforms-infobox jsonforms-infobox-$position",
					],
					$ret,
				);

				$outputPage->addHTML( $html );
				break;
		}
	}

	/**
	 * @param string $name
	 * @param string|null $path
	 * @return stdClass
	 */
	public static function getSourceSchema( $name, $path = null ) {
		// JsonSchema:SchemaBuilder/EnumProvider
		// from the api
		if ( !$path ) {
			$title = TitleClass::newFromText( $name );
			if ( $title && $title->isKnown() ) {
				$name = $title->getText();
				$ns = $title->getNamespace();
				$formattedNamespaces = MediaWikiServices::getInstance()
					->getContentLanguage()
					->getFormattedNamespaces();
				$path = $formattedNamespaces[$ns];
			} else {
				// Fallback: extract path from the name or use default
				[ $path, $name ] = explode( ":", $name, 2 );
			}
		}

		$pathArr = explode( "/", $path );
		$namespace = array_shift( $pathArr );
		$titleText =
			$namespace .
			":" .
			( count( $pathArr ) ? implode( "/", $pathArr ) . "/" : "" ) .
			$name;

		$pageTimestamp = (int)self::getPageLastRevisionTimestamp(
			$titleText,
			TS_UNIX,
		);
		$filePath = __DIR__ . "/../data/" . $path . "/" . $name . ".json";

		$fileTimestamp = file_exists( $filePath ) ? filemtime( $filePath ) : 0;
		if ( !$pageTimestamp && !$fileTimestamp ) {
			// throw new MWException( "no json '$name' ('$path')");
			return new stdClass();
		}

		if ( $pageTimestamp === null || $fileTimestamp > $pageTimestamp ) {
			$contents = file_get_contents( $filePath );
			return json_decode( $contents, false );
		}

		return self::getJsonArticle( $titleText );
	}

	/**
	 * Get the last revision timestamp of a page
	 *
	 * @param string $titleText
	 * @param string $format Output format
	 * @return string|null
	 */
	public static function getPageLastRevisionTimestamp(
		$titleText,
		$format = TS_MW,
	) {
		$title = TitleClass::newFromText( $titleText );

		if ( !$title || !$title->exists() ) {
			return null;
		}

		$wikiPage = MediaWikiServices::getInstance()
			->getWikiPageFactory()
			->newFromTitle( $title );

		$revisionRecord = $wikiPage->getRevisionRecord();
		if ( !$revisionRecord ) {
			return null;
		}

		$timestamp = $revisionRecord->getTimestamp();
		// convert format
		return wfTimestamp( $format, $timestamp );
	}

	/**
	 * @param OutputPage $output
	 * @param array $config
	 * @return void
	 */
	public static function addJsConfigVars( $output, $config = [] ) {
		$title = $output->getTitle();
		$user = $output->getUser();
		$context = $output->getContext();

		$schemaPath = self::getFullUrlOfNamespace( NS_JSONSCHEMA );
		$VEForAll = false;
		if (
			ExtensionRegistry::getInstance()->isLoaded( "VEForAll" ) &&
			self::visualEditorIsEnabled( $user )
		) {
			$userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
			$userOptionsManager->setOption( $user, "visualeditor-enable", true );
			$VEForAll = true;
			$output->addModules( "ext.veforall.main" );
		}

		$groups = [ "sysop", "bureaucrat", "jsonforms-admin" ];
		$showOutdatedVersion =
			empty( $GLOBALS["wgJsonFormsDisableVersionCheck"] ) &&
			( $user->isAllowed( "jsonforms-canmanageschemas" ) ||
				count( array_intersect( $groups, self::getUserGroups( $user ) ) ) );

		$config = array_merge(
			[
				"schemaPath" => $schemaPath,
				// 'actionUrl' => SpecialPage::getTitleFor( 'VisualDataSubmit', $title->getPrefixedDBkey() )->getLocalURL(),
				"isNewPage" =>
					$title->getArticleID() === 0 || !$title->isKnown(),
				// 'allowedMimeTypes' => $allowedMimeTypes,
				"caneditdata" => $user->isAllowed( "jsonforms-caneditdata" ),
				"canmanageschemas" => $user->isAllowed(
					"jsonforms-canmanageschemas",
				),
				"canmanageforms" => $user->isAllowed(
					"jsonforms-canmanageforms",
				),
				"contentModels" => array_flip( self::getContentModels() ),
				"roleContentModelMap" => SlotHelper::getRoleContentModelMap(),
				"contentModel" => $title->getContentModel(),
				"VEForAll" => $VEForAll,
				"captchaSiteKey" => $GLOBALS["wgJsonFormsReCaptchaSiteKey"],

				// @TODO or move to api
				"jsonSlots" => SlotHelper::getJsonSlots(),
				"slotRoles" => SlotHelper::getSlotRoles(),
				"jsonContentModels" => SlotHelper::getJsonContentModels(),

				// 'maptiler-apikey' => $GLOBALS['wgJsonFormsMaptilerApiKey']
				"jsonforms-show-notice-outdated-version" => $showOutdatedVersion,
			],
			$config,
		);

		// if ( isset( $config['context'] ) && $config['context'] === 'parserfunction' ) {
		// 	$pageFormUI = file_get_contents(  __DIR__ . '/schemas/PageFormUI.json');
		// 	$config['pageFormUI'] = json_decode( $pageFormUI, true );
		// 	$config['pageFormUI'] = self::processSchema( $output, $config['pageFormUI'] );
		// }

		$output->addJsConfigVars( [
			// @see VEForAll ext.veforall.target.js -> getPageName
			"wgPageFormsTargetName" => ( $title && $title->canExist()
				? $title
				: TitleClass::newMainPage()
			)->getFullText(),

			"jsonforms" => $config,
		] );
	}

	/**
	 * @see includes/api/ApiBase.php
	 * @param User $user
	 * @param Title|MediaWiki\Title\Title $title
	 * @param array &$errors
	 * @return bool
	 */
	public static function checkWritePermissions( $user, $title, &$errors ) {
		$services = MediaWikiServices::getInstance();

		$actions = [ "edit" ];
		if ( !$title->isKnown() ) {
			$actions[] = "create";
		}

		if ( class_exists( "MediaWiki\Permissions\PermissionStatus" ) ) {
			$status = new MediaWiki\Permissions\PermissionStatus();
			foreach ( $actions as $action ) {
				$user->authorizeWrite( $action, $title, $status );
			}
			if ( !$status->isGood() ) {
				return false;
			}
			return true;
		}

		$PermissionManager = $services->getPermissionManager();
		$errors = [];
		foreach ( $actions as $action ) {
			$errors = array_merge(
				$errors,
				$PermissionManager->getPermissionErrors( $action, $user, $title ),
			);
		}

		return count( $errors ) === 0;
	}

	/**
	 * @param int $ns
	 * @return string
	 */
	public static function getFullUrlOfNamespace( $ns ) {
		global $wgArticlePath;

		$formattedNamespaces = MediaWikiServices::getInstance()
			->getContentLanguage()
			->getFormattedNamespaces();
		$namespace = $formattedNamespaces[$ns];

		$schemaUrl = str_replace( '$1', "$namespace:", $wgArticlePath );
		if ( method_exists( MediaWikiServices::class, "getUrlUtils" ) ) {
			// MW 1.39+
			return MediaWikiServices::getInstance()
				->getUrlUtils()
				->expand( $schemaUrl );
		}
		return wfExpandUrl( $schemaUrl );
	}

	/**
	 * @see \MediaWiki\Extension\VisualEditor\Services\VisualEditorAvailabilityLookup::isEnabledForUser
	 * @param User $user
	 * @return bool
	 */
	private static function visualEditorIsEnabled( $user ) {
		$services = MediaWikiServices::getInstance();
		$veConfig = $services->getConfigFactory()->makeConfig( "visualeditor" );
		$userOptionsLookup = $services->getUserOptionsLookup();
		$isBeta =
			$veConfig->has( "VisualEditorEnableBetaFeature" ) &&
			$veConfig->get( "VisualEditorEnableBetaFeature" );

		return ( $isBeta
			? $userOptionsLookup->getOption( $user, "visualeditor-enable" )
			: !$userOptionsLookup->getOption(
				$user,
				"visualeditor-betatempdisable",
			) ) &&
			!$userOptionsLookup->getOption( $user, "visualeditor-autodisable" );
	}

	/**
	 * @see includes/specials/SpecialChangeContentModel.php
	 * @return array
	 */
	public static function getContentModels() {
		$services = MediaWiki\MediaWikiServices::getInstance();
		$contentHandlerFactory = $services->getContentHandlerFactory();
		$models = $contentHandlerFactory->getContentModels();
		$options = [];

		foreach ( $models as $model ) {
			$handler = $contentHandlerFactory->getContentHandler( $model );

			if ( !$handler->supportsDirectEditing() ) {
				continue;
			}

			$options[ContentHandler::getLocalizedName( $model )] = $model;
		}

		ksort( $options );

		return $options;
	}

	/**
	 * @param WikiPage $wikiPage
	 * @return string|null
	 */
	public static function getFirstJsonSlot( WikiPage $wikiPage ): ?string {
		$revisionRecord = $wikiPage->getRevisionRecord();
		if ( !$revisionRecord ) {
			return null;
		}

		foreach ( $revisionRecord->getSlots()->getSlots() as $role => $slot ) {
			if ( $slot->getContent() instanceof JsonContent ) {
				return $role;
			}
		}

		return null;
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return MediaWiki\Revision\RevisionRecord|null
	 */
	public static function revisionRecordFromTitle( $title ) {
		$wikiPage = self::getWikiPage( $title );
		if ( !$wikiPage ) {
			return null;
		}
		return $wikiPage->getRevisionRecord();
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param string $role
	 * @return null|string
	 */
	public static function getSlotContent( $wikiPage, $role ) {
		$slots = self::getSlots( $wikiPage );
		if ( !is_array( $slots ) ) {
			return;
		}

		foreach ( $slots as $role_ => $slot ) {
			if ( $role_ === $role ) {
				$content = $slot->getContent();
				return $content->getText();
				// $ret = $content->getNativeData();
				// if ( $content instanceof JsonContent) {
				// 	$ret = json_decode( $ret, true );
				// 	$ret = $ret ?? [];
				// }
				// return $ret;
			}
		}
		return null;
	}

	/**
	 * @param WikiPage $wikiPage
	 * @return null|array
	 */
	public static function getSlots( $wikiPage ) {
		$title = $wikiPage->getTitle();
		$key = $title->getFullText();

		if ( array_key_exists( $key, self::$slotsCache ) ) {
			return self::$slotsCache[$key];
		}

		$revision = $wikiPage->getRevisionRecord();

		if ( !$revision ) {
			return null;
		}

		self::$slotsCache[$key] = $revision->getSlots()->getSlots();

		return self::$slotsCache[$key];
	}

	/**
	 * @param string $titleText
	 * @return StdObj
	 */
	public static function getJsonArticle( $titleText ) {
		$title = TitleClass::newFromText( $titleText );

		if ( !$title || !$title->isKnown() ) {
			return new stdClass();
		}

		$text = self::getArticleContent( $title );

		if ( !$text ) {
			return new stdClass();
		}

		return json_decode( $text, false );
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return WikiPage|null
	 */
	public static function getWikiPage( $title ) {
		if ( !$title || !$title->canExist() ) {
			return null;
		}
		// MW 1.36+
		if ( method_exists( MediaWikiServices::class, "getWikiPageFactory" ) ) {
			return MediaWikiServices::getInstance()
				->getWikiPageFactory()
				->newFromTitle( $title );
		}
		return WikiPage::factory( $title );
	}

	public static function createStdClass( array $data ): stdClass {
		$obj = new stdClass();
		foreach ( $data as $key => $value ) {
			// Handle hyphens in property names
			$obj->{$key} = $value;
		}
		return $obj;
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return string|null
	 */
	public static function getArticleContent( $title ) {
		$wikiPage = self::getWikiPage( $title );
		if ( !$wikiPage ) {
			return null;
		}
		$content = $wikiPage->getContent(
			\MediaWiki\Revision\RevisionRecord::RAW,
		);
		if ( !$content ) {
			return null;
		}
		return $content->getText();
	}

	/**
	 * @see specials/SpecialPrefixindex.php -> showPrefixChunk
	 * @param string $prefix
	 * @param int $namespace
	 * @return array
	 */
	public static function getPagesWithPrefix( $prefix, $namespace = NS_MAIN ) {
		$dbr = self::getDB( DB_REPLICA );

		$conds = [
			"page_namespace" => $namespace,
			"page_is_redirect" => 0,
		];

		if ( !empty( $prefix ) ) {
			$conds[] =
				"page_title " . $dbr->buildLike( $prefix, $dbr->anyString() );
		}

		$options = [
			"LIMIT" => self::$queryLimit,
			"ORDER BY" => "page_title",
			"USE INDEX" => version_compare( MW_VERSION, "1.36", "<" )
				? "name_title"
				: "page_name_title",
		];

		$res = $dbr->select(
			"page",
			[ "page_namespace", "page_title", "page_id" ],
			$conds,
			__METHOD__,
			$options,
		);

		if ( !$res->numRows() ) {
			return [];
		}

		$ret = [];
		foreach ( $res as $row ) {
			$title = TitleClass::newFromRow( $row );
			if ( $title && $title->isKnown() ) {
				$ret[] = $title;
			}
		}

		return $ret;
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @param User $user
	 * @param string $reason
	 * @return void
	 */
	public static function deleteArticle( $title, $user, $reason ) {
		$wikiPage = self::getWikiPage( $title );
		return self::deletePage( $wikiPage, $user, $reason );
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param string $reason
	 */
	public static function deletePage( $wikiPage, $user, $reason ) {
		if ( !( $wikiPage instanceof WikiPage ) ) {
			return;
		}
		if ( version_compare( MW_VERSION, "1.35", "<" ) ) {
			$error = "";
			$wikiPage->doDeleteArticle(
				$reason,
				false,
				null,
				null,
				$error,
				$user,
			);
		} else {
			$wikiPage->doDeleteArticleReal( $reason, $user );
		}
	}

	/**
	 * @param string &$titleStr
	 * @return null|Title
	 */
	public static function parseTitleCounter( &$titleStr ) {
		if ( !preg_match( '/#count\s*$/', $titleStr ) ) {
			return TitleClass::newFromText( $titleStr );
		}

		$titleStr = preg_replace( '/#count\s*$/', "", $titleStr );
		$nsIndex = self::getRegisteredNamespace( $titleStr );
		$title = TitleClass::newFromText( $titleStr, $nsIndex );

		if ( !$title || !$title->canExist() ) {
			return null;
		}

		$dbr = self::getDB( DB_REPLICA );

		$conds = [
			"page_title REGEXP " . $dbr->addQuotes( $title->getDbKey() . "\d+" ),
			"page_namespace" => $nsIndex,
		];

		$options = [
			"USE INDEX" => version_compare( MW_VERSION, "1.36", "<" )
				? "name_title"
				: "page_name_title",
			"ORDER BY" => "substr_count DESC",
			"LIMIT" => 1,
		];

		$row = $dbr->selectRow(
			"page",
			[
				"page_title",
				"SUBSTRING(page_title, " .
				( strlen( $title->getDbKey() ) + 1 ) .
				") + 0 as substr_count",
			],
			$conds,
			__METHOD__,
			$options,
		);

		if ( $row !== false ) {
			$titleStr .= (string)( (int)$row->substr_count + 1 );
		} else {
			$titleStr .= "1";
		}

		return TitleClass::newFromText( $titleStr, $nsIndex );
	}

	/**
	 * @param string &$titleStr
	 * @return int
	 */
	public static function getRegisteredNamespace( &$titleStr ) {
		$arr = explode( ":", $titleStr, 2 );
		if ( count( $arr ) < 2 ) {
			return NS_MAIN;
		}
		$formattedNamespaces = MediaWikiServices::getInstance()
			->getContentLanguage()
			->getFormattedNamespaces();

		$nameSpace = array_shift( $arr );
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found
		$nsIndex = array_search( $nameSpace, $formattedNamespaces );
		if ( $nsIndex === false ) {
			return NS_MAIN;
		}
		$titleStr = implode( ":", $arr );
		return $nsIndex;
	}

	/**
	 * @param int $db
	 * @return \Wikimedia\Rdbms\DBConnRef
	 */
	public static function getDB( $db ) {
		if ( !method_exists( MediaWikiServices::class, "getConnectionProvider" ) ) {
			// @see https://gerrit.wikimedia.org/r/c/mediawiki/extensions/PageEncryption/+/1038754/comment/4ccfc553_58a41db8/
			return MediaWikiServices::getInstance()
				->getDBLoadBalancer()
				->getConnection( $db );
		}
		$connectionProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		switch ( $db ) {
			case DB_PRIMARY:
				return $connectionProvider->getPrimaryDatabase();
			case DB_REPLICA:
			default:
				return $connectionProvider->getReplicaDatabase();
		}
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return string|null
	 */
	public static function getWikipageContent( $title ) {
		$wikiPage = self::getWikiPage( $title );
		if ( !$wikiPage ) {
			return null;
		}
		$content = $wikiPage->getContent(
			\MediaWiki\Revision\RevisionRecord::RAW,
		);
		if ( !$content ) {
			return null;
		}
		return $content->getText();
	}

	/**
	 * @param string $titletText
	 * @return Title|null
	 */
	public static function getTitleIfKnown( $titletText ) {
		$title = TitleClass::newFromText( $titletText );
		if ( $title && $title->isKnown() ) {
			return $title;
		}
		return null;
	}

	/**
	 * @return MediaWiki\User\UserGroupManager|null
	 */
	public static function getUserGroupManager() {
		if (
			self::$userGroupManager instanceof MediaWiki\User\UserGroupManager
		) {
			return self::$userGroupManager;
		}
		return MediaWikiServices::getInstance()->getUserGroupManager();
	}

	/**
	 * @param User $user
	 * @return array
	 */
	public static function getUserGroups( $user ) {
		$cacheKey = $user->getName();
		if ( array_key_exists( $cacheKey, self::$UserGroupsCache ) ) {
			return self::$UserGroupsCache[$cacheKey];
		}

		$userGroupManager = self::getUserGroupManager();

		$userGroups = array_unique(
			array_merge(
				$userGroupManager->getUserEffectiveGroups( $user ),
				$userGroupManager->getUserImplicitGroups( $user ),
			),
		);

		if ( !in_array( "*", $userGroups ) ) {
			$userGroups[] = "*";
		}

		self::$UserGroupsCache[$cacheKey] = $userGroups;
		return self::$UserGroupsCache[$cacheKey];
	}

	/**
	 * @param User $user
	 * @param array $refGroups
	 * @return bool
	 */
	public static function isAuthorized( $user, $refGroups ) {
		$userGroups = self::getUserGroups( $user );
		return count( array_intersect( $refGroups, $userGroups ) ) > 0;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public static function getConfigValue( $key ) {
		return MediaWikiServices::getInstance()
			->getMainConfig()
			->get( $key );
	}

	/**
	 * @return array
	 */
	public static function slotManagerGroups() {
		return [ 'sysop', 'bureaucrat', 'jsonforms-admin', 'jsonforms-editor' ];
	}

	/**
	 * @see PageOwnership -> SpecialPageOwnershipPermissions
	 * @param Context context
	 * @return bool
	 */
	public static function groupsList( $context ) {
		$ret = [];

		$config = $context->getConfig();
		$groupPermissions = $config->get( "GroupPermissions" );
		$revokePermissions = $config->get( "RevokePermissions" );
		$addGroups = $config->get( "AddGroups" );
		$removeGroups = $config->get( "RemoveGroups" );
		$groupsAddToSelf = $config->get( "GroupsAddToSelf" );
		$groupsRemoveFromSelf = $config->get( "GroupsRemoveFromSelf" );
		$allGroups = array_unique(
			array_merge(
				array_keys( $groupPermissions ),
				array_keys( $revokePermissions ),
				array_keys( $addGroups ),
				array_keys( $removeGroups ),
				array_keys( $groupsAddToSelf ),
				array_keys( $groupsRemoveFromSelf ),
			),
		);
		asort( $allGroups );

		$lang = method_exists( Language::class, "getGroupName" )
			// MW 1.38+
			? $context->getLanguage()
			: null;

		foreach ( $allGroups as $groupname ) {
			// $permissions = $groupPermissions[$groupname] ?? [];
			// Replace * with a more descriptive groupname

			$groupnameLocalized =
				$lang !== null
					// MW 1.38+
					? $lang->getGroupName( $groupname )
					: UserGroupMembership::getGroupName( $groupname );

			$groupnameLocalized =
				$groupnameLocalized === "*" ? "all" : $groupnameLocalized;

			$ret[$groupname] = $groupnameLocalized;
		}

		ksort( $ret );

		return $ret;
	}

	/**
	 * @see api/ApiMove.php => MovePage
	 * @param User $user
	 * @param Title|MediaWiki\Title\Title $from
	 * @param Title|MediaWiki\Title\Title $to
	 * @param string|null $reason
	 * @param bool $createRedirect
	 * @param array $changeTags
	 * @return bool
	 */
	public static function movePage(
		$user,
		$from,
		$to,
		$reason = null,
		$createRedirect = false,
		$changeTags = [],
	) {
		// Validate inputs
		if ( !$from || !$to ) {
			return false;
		}

		$services = MediaWikiServices::getInstance();
		$movePageFactory = $services->getMovePageFactory();
		$mp = $movePageFactory->newMovePage( $from, $to );

		$validStatus = $mp->isValidMove();
		if ( !$validStatus->isOK() ) {
			return false;
		}

		// Check permissions
		$permStatus = $mp->authorizeMove( $user, $reason ?? "" );
		if ( !$permStatus->isOK() ) {
			return false;
		}

		$status = $mp->move( $user, $reason ?? "", $createRedirect, $changeTags );

		return $status->isOK();
	}

	/**
	 * @param Title $title
	 * @param array slots
	 * @param array &$errors []
	 * @return
	 */
	public static function importRevision( $title, $slots, &$errors = [] ) {
		$services = MediaWikiServices::getInstance();
		$wikiPage = $services->getWikiPageFactory()->newFromTitle( $title );

		$wikiPage = new WikiPage( $title );
		if ( !$wikiPage ) {
			$errors[] = "cannot create wikipage";
			return false;
		}

		$slotsData = [];
		foreach ( $slots as $value ) {
			$slotsData[$value["role"]] = $value;
		}

		if ( !array_key_exists( SlotRecord::MAIN, $slotsData ) ) {
			$slotsData = array_merge(
				[
					SlotRecord::MAIN => [
						"model" => "wikitext",
						"text" => "",
					],
				],
				$slotsData,
			);
		}

		$oldRevisionRecord = $wikiPage->getRevisionRecord();
		$slotRoleRegistry = $services->getSlotRoleRegistry();
		$contentHandlerFactory = $services->getContentHandlerFactory();
		$contentModels = $contentHandlerFactory->getContentModels();

		$revision = new WikiRevision();
		$revision->setTitle( $title );

		// $content = $this->makeContent( $title, $revId, $revisionInfo );
		// $revision->setContent( SlotRecord::MAIN, $content );
		foreach ( $slotsData as $role => $value ) {
			if ( empty( $value["text"] ) && $role !== SlotRecord::MAIN ) {
				continue;
			}

			if (
				!empty( $value["model"] ) &&
				in_array( $value["model"], $contentModels )
			) {
				$modelId = $value["model"];
			} elseif ( $slotRoleRegistry->getRoleHandler( $role ) ) {
				$modelId = $slotRoleRegistry
					->getRoleHandler( $role )
					->getDefaultModel( $title );
			} elseif (
				$oldRevisionRecord !== null &&
				$oldRevisionRecord->hasSlot( $role )
			) {
				$modelId = $oldRevisionRecord
					->getSlot( $role )
					->getContent()
					->getContentHandler()
					->getModelID();
			} else {
				$modelId = CONTENT_MODEL_WIKITEXT;
			}

			if ( !isset( $modelId ) ) {
				$errors[] = "cannot determine content model for role $role";
				continue;
			}

			$content = ContentHandler::makeContent(
				$value["text"],
				$title,
				$modelId,
			);
			$revision->setContent( $role, $content );
		}

		return $revision->importOldRevision();
	}
}
