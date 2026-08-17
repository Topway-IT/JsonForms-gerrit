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

class JsonFormsApiProcessSchema extends ApiBase {

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return false;
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
		$output = $this->getContext()->getOutput();

		$schema = json_decode( $params['schema'], false );

		if ( $schema === null && json_last_error() !== JSON_ERROR_NONE ) {
			$msg = 'Invalid JSON schema: ' . json_last_error_msg();
			$result->addValue( [ $this->getModuleName() ], 'error', $msg );
			return;
		}

		$result_ = \JsonForms::processSchema( $output, $schema );
		$result->addValue( [ $this->getModuleName() ], 'result', json_encode( $result_ ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'schema' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=jsonforms-process-schema'
			=> 'apihelp-jsonforms-process-schema-example-1'
		];
	}
}
