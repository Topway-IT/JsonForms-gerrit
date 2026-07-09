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

/**
 * A special page that lists protected pages
 *
 * @ingroup SpecialPage
 */

namespace MediaWiki\Extension\JsonForms\Specials;

use SpecialPage;

class ManageSchemas extends SpecialPage {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		$listed = true;
		parent::__construct( 'JsonFormsManageSchemas', '', $listed );
	}

	/**
	 * @inheritDoc
	 */
	public function getPageTitle( $subpage = false ) {
		return SpecialPage::getTitleFor( 'JsonFormsManage', 'Schemas' );
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return 'jsonforms';
	}
}
