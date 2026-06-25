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

class SpecialJsonForms extends QueryPage {

	/** @inheritDoc */
	public function __construct() {
		$listed = true;

		// https://www.mediawiki.org/wiki/Manual:Special_pages
		parent::__construct( 'JsonForms', '', $listed );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$this->addHelpLink( 'Extension:JsonForms' );

		if ( empty( $par ) ) {
			parent::execute( $par );
			return;
		}

		$out = $this->getOutput();
		$out->setArticleRelated( false );
		$out->setRobotPolicy( $this->getRobotPolicy() );

		$user = $this->getUser();
		$this->setHeaders();
		$this->outputHeader();

		$securityLevel = $this->getLoginSecurityLevel();

		if ( $securityLevel !== false && !$this->checkLoginSecurityLevel( $securityLevel ) ) {
			$this->displayRestrictionError();
			return;
		}

		if ( !$user->isAllowed( 'edit' ) ) {
			$this->displayRestrictionError();
			return;
		}

		$specialPageTitle = SpecialPage::getTitleFor( 'JsonForms' );
		$out->addWikiMsg(
			'jsonforms-special-forms-returnlink',
			$specialPageTitle->getFullText()
		);

		// $out->addHTML( '<br />' );

		$formDescriptor = \JsonForms::getSourceSchema( $par, 'JsonForm' );
		$formDescriptor->view = 'inline';
		unset( $formDescriptor->width );

		$html = \JsonForms::getPageForm( $out, $formDescriptor );

		$out->addModules( 'ext.JsonForms.pageForms' );

		\JsonForms::addJsConfigVars( $out );

		$out->addHTML( $html );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'jsonforms';
	}

	/**
	 * @inheritDoc
	 */
	public function isExpensive() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function isSyndicated() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		$ret = [];
		$conds = [];
		$join_conds = [];
		$options = [];

		$tables = [];
		$tables['page_alias'] = 'page';
		$fields = [ 'page_namespace', 'page_title', 'page_id' ];
		$conds['page_namespace'] = NS_JSONFORM;

		$ret['tables'] = $tables;
		$ret['fields'] = $fields;
		$ret['join_conds'] = $join_conds;
		$ret['conds'] = $conds;
		$ret['options'] = $options;

		return $ret;
	}

	/**
	 * @inheritDoc
	 */
	protected function getOrderFields() {
		return [ 'page_title' ];
	}

	/**
	 * @inheritDoc
	 */
	public function sortDescending() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function formatResult( $skin, $result ) {
		$pageName = $result->page_title;

		$title = SpecialPage::getTitleFor( 'JsonForms', $pageName );
		$text = str_replace( '_', ' ', $pageName );
		return $this->getLinkRenderer()->makeKnownLink( $title, htmlspecialchars( $text ) );
	}

}
