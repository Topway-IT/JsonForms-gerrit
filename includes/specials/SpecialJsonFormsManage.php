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

use MediaWiki\Extension\JsonForms\Aliases\Html as HtmlClass;
use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Revision\SlotRecord;

/**
 * A special page that lists protected pages
 *
 * @ingroup SpecialPage
 */
class SpecialJsonFormsManage extends SpecialPage {
	/** @var user */
	public $user;

	/** @var Request */
	public $request;

	/** @var string */
	public $par;

	/** @var int */
	public $namespace;

	/** @var string */
	public $localTitle;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		$listed = false;
		parent::__construct( "JsonFormsManage", "", $listed );
	}

	/**
	 * @return string|Message
	 */
	public function getDescription() {
		$msg = $this->msg( "jsonformsbrowse" . $this->par );
		if ( version_compare( MW_VERSION, "1.40", ">" ) ) {
			return $msg;
		}
		return $msg->text();
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		// $this->requireLogin();
		$allowedItems = [ "Forms", "Schemas" ];

		if ( !in_array( $par, $allowedItems ) ) {
			$this->displayRestrictionError();
			return;
		}

		$this->par = strtolower( (string)$par );
		$user = $this->getUser();

		if (
			$this->par === "forms" &&
			!$user->isAllowed( "jsonforms-canmanageforms" )
		) {
			$this->displayRestrictionError();
			return;
		}

		if (
			$this->par === "schemas" &&
			!$user->isAllowed( "jsonforms-canmanageschemas" )
		) {
			$this->displayRestrictionError();
			return;
		}

		$this->setHeaders();
		$this->outputHeader();

		$out = $this->getOutput();

		$out->addModuleStyles( "mediawiki.special" );
		$this->addHelpLink( "Extension:JsonForms" );

		$request = $this->getRequest();

		$this->request = $request;
		$this->user = $user;

		$this->addJsConfigVars( $out );

		$out->enableOOUI();

		$this->addNavigationLinks( $par );

		$out->addWikiMsg(
			"jsonforms-special-browse-" . $this->par . "-description",
		);

		$this->localTitle = SpecialPage::getTitleFor( "JsonFormsManage", $par );

		$action = $this->getRequest()->getVal( "action" );

		$jsonForm = \JsonForms::getSourceSchema(
			"SimpleFormUI",
			"JsonSchema/Core",
		);
		$formDescriptor = \JsonForms::getSourceSchema( "Default", "JsonForm" );

		$formDescriptor->slot = SlotRecord::MAIN;
		$formDescriptor->edit_categories = false;
		$formDescriptor->return = "url";
		$formDescriptor->return_url = $this->localTitle->getLocalURL();

		$schemaName = "";
		$pagename = $this->getRequest()->getVal( "pagename" );

		if ( $pagename ) {
			$title_ = TitleClass::newFromText( $pagename );
			if ( $title_ ) {
				$pageid = $title_->getArticleID();
			}

		} else {
			$pageid = $this->getRequest()->getVal( "pageid" );
		}

		if ( $pageid ) {
			$title = TitleClass::newFromID( $pageid );
			if ( !$title ) {
				return $this->printError(
					$out,
					"jsonforms-special-browse-error-invalid-article",
				);
			}

			$formDescriptor->edit = $title->getFullText();
			$articleContent = \JsonForms::getArticleContent( $title );

			$schemaName = $title->getText();
		}

		$item = null;
		$startVal = [];
		switch ( $this->par ) {
			case "forms":
				$specialpage_title = SpecialPage::getTitleFor(
					"JsonFormsManage",
					"Schemas",
				);
				$message = new \OOUI\MessageWidget( [
					"type" => "info",
					"label" => new \OOUI\HtmlSnippet(
						$this->msg(
							"jsonforms-special-manage-forms-alert",
						)->parse(),
					),
				] );
				$out->addHTML( $message );
				$out->addHTML( "<br />" );

				$item = "form";
				$formDescriptor->pagename_formula = "JsonForm:<name>";
				$innerSchema = \JsonForms::getSourceSchema(
					"CreatePageForm",
					"JsonSchema/Core",
				);
				$innerSchema = \JsonForms::processSchema( $out, $innerSchema );

				// ***important, encode schema otherwise $refs can mess with
				// those of the host schema
				$config = new stdClass();
				$config->schema = json_encode( $innerSchema );

				$jsonForm->properties->editor->{'x-input-config'} = $config;
				break;

			case "schemas":
				$item = "schema";
				$formDescriptor->pagename_formula = "JsonSchema:<x-name>";
				$innerSchema = \JsonForms::getSourceSchema(
					"MetaSchema",
					"JsonSchema/SchemaBuilder",
				);

				$innerSchema = \JsonForms::processSchema( $out, $innerSchema );

				// ***important, encode schema otherwise $refs can mess with
				// those of the host schema
				$config = new stdClass();
				$config->schema = json_encode( $innerSchema );
				$config->isMetaSchema = true;
				$config->schemaName = $schemaName;

				$jsonForm->properties->editor->{'x-input-config'} = $config;
				break;
		}

		if ( $pageid ) {
			$specialPageTitle = SpecialPage::getTitleFor(
				"JsonFormsManage",
				$par,
			);
			$out->addWikiMsg(
				"jsonforms-special-manage-returnlink",
				$specialPageTitle->getFullText(),
			);

			$out->addHTML(
				HtmlClass::rawElement(
					"p",
					[],
					$this->msg(
						"jsonforms-special-manage-schemas-schemaname",
						$title->getFullText(),
					)->parse(),
				),
			);
		}

		switch ( $action ) {
			case "edit":
				$formData = new stdClass();
				$formData->schema = $jsonForm;
				$formData->schemaName =
					$this->par === "forms" ? "Core/CreatePageForm" : "SchemaBuilder/MetaSchema";
				$formData->editorOptions = "MediaWiki:DefaultEditorOptions";
				$formData->editorScript = "MediaWiki:DefaultEditorScript";
				$formData->formDescriptor = $formDescriptor;
				$formData->startval = new stdClass();

				if ( isset( $articleContent ) ) {
					$formData->startval->editor = $articleContent;
				}

				$formData = \JsonForms::prepareFormData( $out, $formData );

				$data = [];
				$res_ = \JsonForms::getJsonFormHtml( $formData, [
					"width" => "calc(100% - 24px)",
				] );

				if ( !$res_->ok ) {
					return $this->printError( $out, $res_->error );
					// return $this->printError( $out, 'jsonforms-special-browse-error-invalid-form' );
				}

				$html = HtmlClass::rawElement(
					"div",
					[ "class" => "jsonforms-build-container" ],
					$res_->value,
				);

				$out->addModules( "ext.JsonForms.ManageSchemas" );

				\JsonForms::addJsConfigVars( $out );

				$out->addHTML( $html );
				break;

			default:
				$layout = new OOUI\PanelLayout( [
					"id" => "jsonforms-panel-layout",
					"expanded" => false,
					"padded" => false,
					"framed" => false,
				] );

				$layout->appendContent(
					new OOUI\ButtonWidget( [
						"href" => wfAppendQuery(
							$this->localTitle->getLocalURL(),
							[ "action" => "edit" ],
						),
						"label" => $this->msg(
							"jsonforms-manage-form-button-add-" . $item,
						)->text(),
						"infusable" => true,
						"flags" => [ "progressive", "primary" ],
					] ),
				);

				$out->addHTML( $layout );

				$options = $this->showOptions( $request );

				if ( $options ) {
					$out->addHTML( "<br />" );
					$out->addHTML( $options );
					$out->addHTML( "<br />" );
				}

				$class = null;
				switch ( $item ) {
					case "schema":
						$this->namespace = NS_JSONSCHEMA;
						$class = "ManagePager";
						break;

					case "form":
					default:
						$this->namespace = NS_JSONFORM;
						$class = "ManagePager";
				}

				$class = "MediaWiki\\Extension\\JsonForms\\Specials\\$class";
				$pager = new $class( $this, $request, $this->getLinkRenderer() );

				if ( $pager->getNumRows() ) {
					$parserOptions = version_compare( MW_VERSION, "1.44", ">=" )
						? ParserOptions::newFromContext( $this->getContext() )
						: [];
					$out->addParserOutputContent(
						$pager->getFullOutput(),
						$parserOptions,
					);
				} else {
					$out->addWikiMsg( "jsonforms-special-browse-table-empty" );
				}
		}
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
	 * @param Output $out
	 */
	protected function addJsConfigVars( $out ) {
		$context = $this->getContext();
		$out->addJsConfigVars( [] );
	}

	/**
	 * @see AbuseFilterSpecialPage
	 * @param string $pageType
	 */
	protected function addNavigationLinks( $pageType ) {
		$linkDefs = [
			"forms" => "JsonFormsManage/Forms",
			"schemas" => "JsonFormsManage/Schemas",
		];

		$links = [];

		foreach ( $linkDefs as $name => $page ) {
			// Give grep a chance to find the usages:
			// abusefilter-topnav-home, abusefilter-topnav-recentchanges, abusefilter-topnav-test,
			// abusefilter-topnav-log, abusefilter-topnav-tools, abusefilter-topnav-examine
			$msgName = "jsonformsbrowse$name";

			$msg = $this->msg( $msgName )->parse();

			if ( $name === $pageType ) {
				$links[] = Xml::tags( "strong", null, $msg );
			} else {
				$links[] = $this->getLinkRenderer()->makeLink(
					new TitleValue( NS_SPECIAL, $page ),
					new HtmlArmor( $msg ),
				);
			}
		}

		$linkStr = $this->msg( "parentheses" )
			->rawParams( $this->getLanguage()->pipeList( $links ) )
			->text();
		$linkStr =
			$this->msg( "jsonformsbrowsedata-topnav" )->parse() . " $linkStr";

		$linkStr = Xml::tags(
			"div",
			[ "class" => "mw-jsonforms-browsedata-navigation" ],
			$linkStr,
		);

		$this->getOutput()->setSubtitle( $linkStr );
	}

	/**
	 * @param Request $request
	 * @return string
	 */
	protected function showOptions( $request ) {
		$formDescriptor = [];

		switch ( $this->par ) {
			case "schemas":
			case "forms":
			default:
				$schemaname = $request->getVal( "schemaname" );
				$formDescriptor["schema"] = [
					"label-message" =>
						"jsonforms-special-browse-form-search-schema-label",
					"name" => "schemaname",
					"type" => "title",
					"namespace" => $this->namespace,
					"relative" => true,
					"required" => false,

					// @fixme this has no effect, create a custom widget
					"limit" => 20,
					"help-message" =>
						"jsonforms-special-browse-form-search-schema-help",
					"default" => $schemaname ?? null,
				];
		}

		$htmlForm = HTMLForm::factory(
			"ooui",
			$formDescriptor,
			$this->getContext(),
		);

		$htmlForm
			->setMethod( "get" )
			->setWrapperLegendMsg( "jsonforms-special-browse-form-search-legend" )
			->setSubmitText(
				$this->msg(
					"jsonforms-special-browse-form-search-submit",
				)->text(),
			);

		return $htmlForm->prepareForm()->getHTML( false );
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return "jsonforms";
	}
}
