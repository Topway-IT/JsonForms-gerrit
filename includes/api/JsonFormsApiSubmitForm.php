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

use MediaWiki\Extension\JsonForms\SubmitForm;

class JsonFormsApiSubmitForm extends ApiBase {

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function mustBePosted(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$user = $this->getUser();

		\JsonForms::initialize();
		$result = $this->getResult();
		$params = $this->extractRequestParams();
		$context = RequestContext::getMain();

		// *** required by onContentAlterParserOutput
		// $specialpage_title = SpecialPage::getTitleFor( 'JsonFormsSubmit' );
		// $context->setTitle( $specialpage_title );

		// @IMPORTANT !! preserve as object otherwise {} will
		// be converted to PHP empty array !!
		$data = json_decode( $params['data'], false );

		$submitForm = new SubmitForm( $user, $context );
		$result_ = $submitForm->processData( $data );

		$result->addValue( [ $this->getModuleName() ], 'result', json_encode( $result_ ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'data' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=jsonforms-submit-form'
			=> 'apihelp-jsonforms-submit-form-example-1'
		];
	}
}
