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

namespace MediaWiki\Extension\JsonForms\Specials;

use MediaWiki\Extension\JsonForms\Aliases\Linker as LinkerClass;
use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Linker\LinkRenderer;
use MWException;
use ParserOutput;
use TablePager;

class ManagePager extends TablePager {

	/** @var request */
	private $request;

	/** @var parentClass */
	private $parentClass;

	/**
	 * @param SpecialJsonFormsManage $parentClass
	 * @param Request $request
	 * @param LinkRenderer $linkRenderer
	 */
	public function __construct( $parentClass, $request, LinkRenderer $linkRenderer ) {
		$this->parentClass = $parentClass;
		parent::__construct( $parentClass->getContext(), $linkRenderer );

		$this->request = $request;

		if ( $this->getRequest()->getInt( 'limit', 0 ) === 0 ) {
			$this->mLimit = 20;
		}
	}

	/**
	 * @stable to override
	 * @return string HTML
	 */
	public function getNavigationBar() {
		if ( !$this->isNavigationBarShown() ) {
			return '';
		}

		if ( $this->mNavigationBar !== null ) {
			return $this->mNavigationBar;
		}

		$navBuilder = $this->getNavigationBuilder()
			->setPrevMsg( 'prevn' )
			->setNextMsg( 'nextn' )
			->setFirstMsg( 'page_first' )
			->setLastMsg( 'page_last' );

		$this->mNavigationBar = $navBuilder->getHtml();

		return $this->mNavigationBar;
	}

	/**
	 * @inheritDoc
	 */
	public function getFullOutput() {
		$navigation = $this->getNavigationBar();
		$body = parent::getBody();

		$pout = new ParserOutput;
		$pout->setRawText( $navigation . '<br />' . $body . '<br />' . $navigation );
		$pout->addModuleStyles( $this->getModuleStyles() );
		return $pout;
	}

	/**
	 * @param IResultWrapper $result
	 */
	public function preprocessResults( $result ) {
	}

	/**
	 * @return array
	 */
	protected function getFieldNames() {
		$headers = [
			'page_id' => 'jsonforms-special-browse-pager-header-pagetitle'
		];

		// @TODO show actions for schemas as soon as the metaschema editor works
		// if ( $this->parentClass->par === 'Forms' ) {
			$headers['actions'] = 'jsonforms-special-browse-pager-header-actions';
		// }

		foreach ( $headers as $key => $val ) {
			$headers[$key] = $this->msg( $val )->text();
		}

		return $headers;
	}

	/**
	 * @param string $field
	 * @param string $value
	 * @return string HTML
	 * @throws MWException
	 */
	public function formatValue( $field, $value ) {
		/** @var object $row */
		$row = $this->mCurrentRow;
		$linkRenderer = $this->getLinkRenderer();
		$formatted = '';

		switch ( $field ) {
			case 'page_id':
				// error, page_id is 0 for new articles
				if ( !$row->page_id ) {
					$formatted = '';
				} else {
					$title = TitleClass::newFromID( $row->page_id );
					$formatted = LinkerClass::link( $title, $title->getText() );
				}
				break;

			case 'actions':
				// if ( $this->parentClass->par === 'Forms' ) {
					$link = '<span class="mw-ui-button mw-ui-progressive">edit</span>';
					$query = [ 'action' => 'edit', 'pageid' => $row->page_id ];
					$formatted = LinkerClass::link( $this->parentClass->localTitle, $link, [], $query );
				// }
				break;

			default:
				throw new MWException( "Unknown field '$field'" );
		}

		return $formatted;
	}

	/**
	 * @return array
	 */
	public function getQueryInfo() {
		$dbr = \JsonForms::getDB( DB_REPLICA );
		$ret = [];
		$conds = [];
		$join_conds = [];
		$options = [];

		$tables = [];
		$tables['page_alias'] = 'page';
		$fields = [ 'page_namespace', 'page_title', 'page_id' ];
		$conds['page_namespace'] = $this->parentClass->namespace;

		$ret['tables'] = $tables;
		$ret['fields'] = $fields;
		$ret['join_conds'] = $join_conds;
		$ret['conds'] = $conds;
		$ret['options'] = $options;

		return $ret;
	}

	/**
	 * @return string
	 */
	protected function getTableClass() {
		return parent::getTableClass() . ' jsonforms-special-browse-pager-table';
	}

	/**
	 * @return string
	 */
	public function getIndexField() {
		return 'page_title';
	}

	/**
	 * @return string
	 */
	public function getDefaultSort() {
		return 'page_title';
	}

	/**
	 * @param string $field
	 * @return bool
	 */
	protected function isFieldSortable( $field ) {
		// @see here https://doc.wikimedia.org/mediawiki-core/
		// 'USE INDEX' => ( version_compare( MW_VERSION, '1.36', '<' ) ? 'name_title' : 'page_name_title' ),

		// return false;
	}
}
