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

namespace MediaWiki\Extension\JsonForms\SubmitProcessors;

use MediaWiki\Extension\JsonForms\ResultWrapper;
use SpecialPage;

class ManageSchemas extends PageForms {

	/**
	 * @param array $data
	 * @return array
	 */
	public function processData( $data ) {
		// adjust processData as needed

		// parent::processData is from extended class PageForms
		$res_ = parent::processData( $data );

		if ( !$res_->ok ) {
			return $res_;
		}

		[ $processedData, $returnData ] = $res_->value;

		switch ( $data->schemaId ) {
			case "SchemaBuilder/MetaSchema":
				$specialpage_title = SpecialPage::getTitleFor( "JsonFormsManage", "Schemas" );

				$title = $processedData['targetTitle'];
				$url = $specialpage_title->getLinkURL( [ 'action' => 'edit', 'pagename' => $title->getFullText() ] );

				$returnData["returnUrl"] = $url;
				break;

			case "Core/CreatePageForm":
				$specialpage_title = SpecialPage::getTitleFor( "JsonFormsManage", "Forms" );

				$title = $processedData['targetTitle'];
				$url = $specialpage_title->getLinkURL( [ 'action' => 'edit', 'pagename' => $title->getFullText() ] );

				$returnData["returnUrl"] = $url;
				break;
		}

		return ResultWrapper::success( [ $processedData, $returnData ] );
	}
}
