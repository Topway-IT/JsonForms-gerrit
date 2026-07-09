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

class SpecialJsonFormsCreate extends SpecialPage {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		$listed = true;

		// https://www.mediawiki.org/wiki/Manual:Special_pages
		parent::__construct( 'JsonFormsCreate', '', $listed );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		// $this->requireLogin();
		$this->setHeaders();
		$this->outputHeader();

		$user = $this->getUser();

		if ( !$user->isAllowed( 'edit' ) ) {
			$this->displayRestrictionError();
			return;
		}

		$out = $this->getOutput();

		$out->addModuleStyles( 'mediawiki.special' );
		$this->addHelpLink( 'Extension:JsonForms' );

		$out->enableOOUI();

		$this->addNavigationLinks( $par ?? 'Regular' );

		$out->addWikiMsg( 'jsonforms-special-edit-message' );

		switch ( $par ) {
			case 'Combined':
				$schema = 'NewArticleCombined';
				break;

			case 'DataOnly':
				$schema = 'NewArticleDataOnly';
				break;

			case 'Regular':
			default:
				$schema = 'NewArticleRegular';
				break;

		}

		$jsonForm = \JsonForms::getSourceSchema( $schema, 'JsonSchema/Core' );
		// $jsonForm = \JsonForms::processSchema( $out, $jsonForm );

		$formData = (object)[
			'schema' => $jsonForm,
			'editorOptions' => 'MediaWiki:DefaultEditorOptions',
			'editorScript' => 'MediaWiki:DefaultEditorScript',
			// 'metadata'=> $metadata,
			// 'editPage' => $editPage,
		];

		$formData = \JsonForms::prepareFormData( $out, $formData );

		$res_ = \JsonForms::getJsonFormHtml( $formData, [
			'width' => 'auto'
		] );

		if ( !$res_->ok ) {
			return $this->printError( $out, $res_->error );
		}

		$html = $res_->value;

		$out->addModules( 'ext.JsonForms.newArticle' );

		\JsonForms::addJsConfigVars( $out );

		$out->addHTML( $html );
	}

	/**
	 * @param Output $out
	 * @param string $msg
	 */
	private function printError( $out, $msg ) {
		$out->addHTML( new \OOUI\MessageWidget( [
			'type' => 'error',
			'label' => new \OOUI\HtmlSnippet( $this->msg( $msg )->parse() )
		] ) );
	}

	/**
	 * @see AbuseFilterSpecialPage
	 * @param string $pageType
	 */
	protected function addNavigationLinks( $pageType ) {
		$pageType = strtolower( $pageType );
		$linkDefs = [
			'regular' => 'JsonFormsCreate',
			'combined' => 'JsonFormsCreate/Combined',
			'dataonly' => 'JsonFormsCreate/DataOnly',
		];

		$links = [];

		foreach ( $linkDefs as $name => $page ) {
			// Give grep a chance to find the usages:
			// abusefilter-topnav-home, abusefilter-topnav-recentchanges, abusefilter-topnav-test,
			// abusefilter-topnav-log, abusefilter-topnav-tools, abusefilter-topnav-examine
			$msgName = "jsonformsbrowse$name";
			$msg = $this->msg( $msgName )->parse();

			if ( $name === $pageType ) {
				$links[] = Xml::tags( 'strong', null, $msg );
			} else {
				$links[] = $this->getLinkRenderer()->makeLink(
					new TitleValue( NS_SPECIAL, $page ),
					new HtmlArmor( $msg )
				);
			}
		}

		$linkStr = $this->msg( 'parentheses' )
			->rawParams( $this->getLanguage()->pipeList( $links ) )
			->text();
		$linkStr = $this->msg( 'jsonformsbrowsedata-topnav' )->parse() . " $linkStr";

		$linkStr = Xml::tags( 'div', [ 'class' => 'mw-jsonforms-browsedata-navigation' ], $linkStr );

		$this->getOutput()->setSubtitle( $linkStr );
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return 'jsonforms';
	}

}
