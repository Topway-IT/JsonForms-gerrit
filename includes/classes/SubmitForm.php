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

use CommentStoreComment;
use ContentHandler;
use ContentModelChange;
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
		$summary = "JsonForms initial revision";

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
		$performer = method_exists( RequestContext::class, "getAuthority" )
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
		if ( method_exists( ContentModelChange::class, "authorizeChange" ) ) {
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
			"",
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
			$errors_ = $status->getErrorsByType( "error" );
			foreach ( $errors_ as $error ) {
				$msg = array_merge( [ $error["message"] ], $error["params"] );
				// @see SpecialVisualData -> getMessage
				$errors[] = \Message::newFromSpecifier( $msg )
					->setContext( $this->context )
					->parse();
			}
		}
	}

	/**
	 * @param array $json
	 * @param array $structuredValue
	 * @return array
	 */
	protected function postProcessJsonData( $json, $structuredValue ) {
		$callback = static function ( &$parent, $key, $value, $pathArr ) use (
			$structuredValue,
		) {
			$path = implode( ".", $pathArr );

			// strip writeOnly
			// Access as object: $structuredValue->$path instead of $structuredValue[$path]
			if (
				isset( $structuredValue->$path ) &&
				isset( $structuredValue->$path->schema->writeOnly ) &&
				$structuredValue->$path->schema->writeOnly === true
			) {
				// Remove property from object
				unset( $parent->$key );
			}
		};

		return SchemaUtils::traverseSchema( $json, $callback );
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
				->msg( "jsonforms-special-submit-processor-not-found" )
				->text();
			return [
				"errors" => $errors,
			];
		}

		$services = $this->services;

		$errors = [];
		$services
			->getHookContainer()
			->run( "JsonForms::FormSubmitBeforeProcess", [
				$this->user,
				&$data,
				&$errors,
			] );

		if ( count( $errors ) ) {
			return [
				"errors" => $errors,
			];
		}

		$submitProcessor = new $class( $this->user );
		$res_ = $submitProcessor->processData( $data );

		if ( !$res_->ok ) {
			return [
				"errors" => [ $res_->error ],
			];
		}

		[ $processedData, $returnData ] = $res_->value;

		// move page
		if ( !empty( $processedData["movePage"] ) ) {
			[ $oldTitle, $newTitle ] = $processedData["movePage"];
			$reason = "JsonForms move";
			$createRedirect = false;
			if (
				!\JsonForms::movePage(
					$this->user,
					$oldTitle,
					$newTitle,
					$reason,
					$createRedirect,
				)
			) {
				return [
					"errors" => [
						$this->context
							->msg(
								"jsonforms-special-submit-move-error",
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
			->run( "JsonForms::FormSubmitBeforeSave", [
				$this->user,
				&$data,
				&$processedData,
				&$errors,
			] );

		if ( count( $errors ) ) {
			return [
				"errors" => $errors,
			];
		}

		if ( $hookResult === false ) {
			return $returnData;
		}

		$slotEditor = new SlotEditor();

		$summary = isset( $data->options ) ? $data->options->summary ?? "" : "";
		$minor = isset( $data->options ) ? $data->options->minor ?? false : false;
		$append = false;
		$watchlist = "";
		$prepend = false;
		$bot = false;
		$createonly = false;
		$nocreate = false;
		$suppress = false;
		$updateStrategy = "replace";

		$wikiPage = \JsonForms::getWikiPage( $processedData["targetTitle"] );

		$ret = $slotEditor->editSlots(
			$this->user,
			$wikiPage,
			$processedData["slots"],
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
		);

		if ( $ret !== true ) {
			$errors = $ret;
			return [
				"errors" => $errors,
			];
		}

		// \JsonForms::setMetadata( $this->context, $wikiPage, $metadata );
		if ( !$processedData["isNewPage"] ) {
			$wikiPage->doPurge();
		}

		$services
			->getHookContainer()
			->run( "JsonForms::FormSubmitSuccess", [
				$this->user,
				$data,
				$processedData,
				&$returnData,
				&$errors,
			] );

		if ( count( $errors ) ) {
			return [
				"errors" => $errors,
			];
		}

		return $returnData;
	}
}
