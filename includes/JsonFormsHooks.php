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

define( "SLOT_ROLE_JSONFORMS_DATA", "jsonforms-data" );
define( "SLOT_ROLE_JSONFORMS_METADATA", "jsonforms-metadata" );

use MediaWiki\Extension\JsonForms\Aliases\Html as HtmlClass;

class JsonFormsHooks {
	/** @var array */
	public static $PageUpdate = [];

	/**
	 * @param array $credits
	 * @return void
	 */
	public static function initExtension( $credits = [] ) {
	}

	/**
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( "jsonforms", [
			\JsonForms::class,
			"parserFunctionForm",
		] );
		$parser->setFunctionHook( "jsonformsrender", [
			\JsonForms::class,
			"parserFunctionRender",
		] );
		$parser->setFunctionHook( "jsonformsquerylink", [
			\JsonForms::class,
			"parserFunctionQueryLink",
		] );

		// @see https://www.mediawiki.org/wiki/Extension:HTML_Tags
		$parser->setHook( 'details', [ self::class, 'renderDetailsTag' ] );
	}

	/**
	 * @param DatabaseUpdater|null $updater
	 */
	public static function onLoadExtensionSchemaUpdates(
		?DatabaseUpdater $updater = null,
	) {
	}

	/**
	 * @param MediaWikiServices $services
	 * @return void
	 */
	public static function onMediaWikiServices( $services ) {
		$services->addServiceManipulator( "SlotRoleRegistry", static function (
			\MediaWiki\Revision\SlotRoleRegistry $registry,
		) {
			if ( !$registry->isDefinedRole( SLOT_ROLE_JSONFORMS_DATA ) ) {
				$registry->defineRoleWithModel(
					SLOT_ROLE_JSONFORMS_DATA,
					"json",
					[
						"display" => "none",
						"region" => "center",
						"placement" => "append",
					],
				);
			}
			if ( !$registry->isDefinedRole( SLOT_ROLE_JSONFORMS_METADATA ) ) {
				$registry->defineRoleWithModel(
					SLOT_ROLE_JSONFORMS_METADATA,
					"json",
					[
						"display" => "none",
						"region" => "center",
						"placement" => "append",
					],
				);
			}
		} );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Extension:HTML_Tags
	 *
	 * @param string $input
	 * @param string[] $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public static function renderDetailsTag( $input, $args, $parser, $frame ) {
		$input = $parser->replaceVariables( $input, $frame );
		$summaryHtml = HtmlClass::element( 'summary', [], 'Params' );
		$attributes = [];
		return HtmlClass::rawElement( 'details', $attributes, $summaryHtml . $input );
	}

	/**
	 * @param User $user
	 * @param stdClass &$submittedData
	 * @param array &$errors
	 * @return void
	 */
	public static function onFormSubmitBeforeProcess(
		User $user,
		stdClass &$submittedData,
		&$errors = [],
	) {
	}

	/**
	 * @param User $user
	 * @param stdClass $submittedData
	 * @param array &$processedData
	 * @param array &$errors
	 * @return void
	 */
	public static function onFormSubmitBeforeSave(
		User $user,
		stdClass $submittedData,
		array &$processedData,
		&$errors = [],
	) {
	}

	/**
	 * @param User $user
	 * @param stdClass $submittedData
	 * @param array $processedData
	 * @param array &$errors
	 * @return void
	 */
	public static function onJsonFormsFormSubmitSuccess(
		User $user,
		stdClass $submittedData,
		array $processedData,
		array &$returnData,
		&$errors = [],
	) {
	}

	/**
	 * @param Content $content
	 * @param Title|Mediawiki\Title\Title $title
	 * @param ParserOutput &$parserOutput
	 * @return void
	 */
	public static function onContentAlterParserOutput(
		Content $content,
		$title,
		ParserOutput &$parserOutput,
	) {
		// $key = $title->getFullText();
		// if ( self::$PageUpdate[$key] ) {
		//	$parserOutput->setExtensionData( 'JsonForms', self::$PageUpdate[$key] );
		//}

		$wikiPage = \JsonForms::getWikiPage( $title );
		if ( !$wikiPage ) {
			return;
		}

		$data = \JsonForms::getSlotContent(
			$wikiPage,
			SLOT_ROLE_JSONFORMS_METADATA,
		);

		// $data = $parserOutput->getExtensionData( 'JsonForms' );
		if ( !$data ) {
			return;
		}

		$data = json_decode( $data, true );

		// this includes annotated categories and tracking categories
		$getCategoriesMethod = version_compare( MW_VERSION, "1.38", ">=" )
			? "getCategoryNames"
			: "getCategoryLinks";

		$categoryNames = $parserOutput->$getCategoriesMethod();

		foreach ( $categoryNames as $category ) {
			$parserOutput->addCategory( $category );
		}

		if ( $data && !empty( $data["categories"] ) ) {
			foreach ( $data["categories"] as $category ) {
				$parserOutput->addCategory( $category );
			}
		}
	}

	/**
	 * @param OutputPage $outputPage
	 * @param Skin $skin
	 * @return void
	 */
	public static function onBeforePageDisplay(
		OutputPage $outputPage,
		Skin $skin,
	) {
		$title = RequestContext::getMain()->getTitle();
		if ( !$title ) {
			return;
		}

		$articleUrl = $title->getLocalURL();
		$requestUrl = $skin->getRequest()->getRequestURL();

		if ( \JsonForms::isKnownArticle( $outputPage->getTitle() ) &&
			$articleUrl === $requestUrl
		) {
			\JsonForms::appendContent( $outputPage );
		}
	}

	/**
	 * @param SkinTemplate $skinTemplate
	 * @param array &$links
	 * @return void
	 */
	public static function onSkinTemplateNavigation(
		SkinTemplate $skinTemplate,
		array &$links,
	) {
		$user = $skinTemplate->getUser();
		$title = $skinTemplate->getTitle();

		if ( !$title->canExist() ) {
			return;
		}

		$errors = [];
		if (
			\JsonForms::checkWritePermissions( $user, $title, $errors ) &&
			!$title->isSpecialPage()
		) {
			$ns = $title->getNamespace();

			// edit slots
			$groups = \JsonForms::slotManagerGroups();
			if (
				count( array_intersect( $groups, \JsonForms::getUserGroups( $user ) ) ) &&
				in_array( $ns, JsonForms::getConfigValue( 'JsonFormsEditSlotsNamespaces' ) )
			) {
				$link = [
					"class" =>
						$skinTemplate->getRequest()->getVal( "action" ) ===
						"slotedit"
							? "selected"
							: "",
					"text" => wfMessage( "jsonforms-slotedit-label" )->text(),
					"href" => $title->getLocalURL( "action=slotedit" ),
				];

				$keys = array_keys( $links["views"] );
				$pos = array_search( "edit", $keys );

				$links["views"] =
					array_intersect_key(
						$links["views"],
						array_flip( array_slice( $keys, 0, $pos + 1 ) ),
					) + [ "slotedit" => $link ] +
					array_intersect_key(
						$links["views"],
						array_flip( array_slice( $keys, $pos + 1 ) ),
					);
			}

			// edit schema
			if ( in_array( $ns, JsonForms::getConfigValue( 'JsonFormsEditSchemaNamespaces' ) ) ) {
				$keys = array_keys( $links["views"] );
				$pos = array_search( "edit", $keys );

				$link = [
					"class" =>
						$skinTemplate->getRequest()->getVal( "action" ) === "jsonedit"
							? "selected"
							: "",
					"text" => wfMessage( "jsonforms-jsonedit-label" )->text(),
					"href" => $title->getLocalURL( "action=jsonedit" ),
				];

				$links["views"] =
					array_intersect_key(
						$links["views"],
						array_flip( array_slice( $keys, 0, $pos + 1 ) ),
					) + [ "jsonedit" => $link ] +
					array_intersect_key(
						$links["views"],
						array_flip( array_slice( $keys, $pos + 1 ) ),
					);
			}
		}
	}

	/**
	 * @param Title|Mediawiki\Title\Title &$title
	 * @param null $unused
	 * @param OutputPage $output
	 * @param User $user
	 * @param WebRequest $request
	 * @param MediaWiki $mediaWiki
	 * @return void
	 */
	public static function onBeforeInitialize(
		&$title,
		$unused,
		OutputPage $output,
		User $user,
		WebRequest $request,
		/* MediaWiki|MediaWiki\Actions\ActionEntryPoint */ $mediaWiki,
	) {
		\JsonForms::initialize();
	}

	/**
	 * @param OutputPage $out
	 * @param ParserOutput $parserOutput
	 * @return void
	 */
	public static function onOutputPageParserOutput(
		OutputPage $out,
		ParserOutput $parserOutput,
	) {
		$title = $out->getTitle();
		$user = $out->getUser();

		if ( $parserOutput->getExtensionData( "jsonforms" ) !== null ) {
			\JsonForms::addJsConfigVars( $out, [
				"context" => "parserfunction",
			] );

			$out->addModules( "ext.JsonForms.pageForms" );
		}
	}

	/**
	 * @param Skin $skin
	 * @param array &$bar
	 * @return void
	 */
	public static function onSkinBuildSidebar( $skin, &$bar ) {
		if ( !empty( $GLOBALS["wgJsonFormsDisableSidebarLink"] ) ) {
			return;
		}

		$user = $skin->getUser();
		$title = $skin->getTitle();

		$specialpage_title = SpecialPage::getTitleFor( "JsonForms" );
		$bar[wfMessage( "jsonforms-sidepanel-section" )->text()][] = [
			"text" => wfMessage( "jsonforms-forms" )->text(),
			"class" => "jsonforms-forms",
			"href" => $specialpage_title->getLocalURL(),
		];

		$specialpage_title = SpecialPage::getTitleFor( "JsonFormsCreate" );
		$bar[wfMessage( "jsonforms-sidepanel-section" )->text()][] = [
			"text" => wfMessage( "jsonforms-new-article" )->text(),
			"class" => "jsonforms-new-article",
			"href" => $specialpage_title->getLocalURL(),
		];

		if ( $user->isAllowed( "jsonforms-canmanageforms" ) ) {
			$specialpage_title = SpecialPage::getTitleFor( "JsonFormsManage", "Forms" );
			$bar[wfMessage( "jsonforms-sidepanel-section" )->text()][] = [
				"text" => wfMessage( "jsonforms-sidepanel-managesforms" )->text(),
				"href" => $specialpage_title->getLocalURL(),
			];
		}

		if ( $user->isAllowed( "jsonforms-canmanageschemas" ) ) {
			$specialpage_title = SpecialPage::getTitleFor( "JsonFormsManage", "Schemas" );
			$bar[wfMessage( "jsonforms-sidepanel-section" )->text()][] = [
				"text" => wfMessage(
					"jsonforms-sidepanel-manageschemas",
				)->text(),
				"href" => $specialpage_title->getLocalURL(),
			];
		}

		$groups = \JsonForms::slotManagerGroups();
		if ( count( array_intersect( $groups, \JsonForms::getUserGroups( $user ) ) ) ) {
			$specialpage_title = SpecialPage::getTitleFor( "JsonFormsSlotManager" );
			$bar[wfMessage( "jsonforms-sidepanel-section" )->text()][] = [
				"text" => wfMessage( "jsonforms-sidepanel-slotmanager" )->text(),
				"href" => $specialpage_title->getLocalURL(),
			];
		}
	}

	/**
	 * @param array &$vars
	 * @param string $skin
	 * @param Config $config
	 * @return void
	 */
	public static function onResourceLoaderGetConfigVars(
		&$vars,
		$skin,
		$config,
	) {
	}
}
