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
 * @copyright Copyright ©2025-2026, https://wikisphere.org
 */

use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Extension\JsonForms\Importer;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class ImportData extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'import data' );
		$this->requireExtension( 'JsonForms' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$delete = $this->getOption( 'delete' ) ?? false;
		$user = User::newSystemUser( 'Maintenance script', [ 'steal' => true ] );
		$services = MediaWikiServices::getInstance();
		$services->getUserGroupManager()->addUserToGroup( $user, 'bureaucrat' );

		$dirPath = __DIR__ . '/../data';
		$prefix = '';

		$callbackRefs = static function ( $value ) {
			$value = str_replace( '../', '', $value );
			return str_replace( '.schema', '', $value );
		};

		$callbackPagename = static function ( $value ) {
			$value = str_replace( '../', '', $value );
			return str_replace( '.schema', '', $value );
		};

		$callbackContent = static function ( $content, $contentModel ) {
			if ( $contentModel !== 'json' ) {
				return $content;
			}

			$json = json_decode( $content, false );
			if ( !$json || !isset( $json->{'$id'} ) ) {
				return $content;
			}

			$id = (string)$json->{'$id'};
			if ( stripos( $id, '//wikisphere.org' ) === false || !str_contains( $id, 'JsonSchema:' ) ) {
				return $content;
			}

			$parts = explode( 'JsonSchema:', $id );
			if ( count( $parts ) < 2 || empty( $parts[1] ) ) {
				return $content;
			}

			$parsedId = end( explode( '/', $parts[1] ) );
			if ( empty( $parsedId ) ) {
				return $content;
			}

			$title = TitleClass::newFromText( 'JsonSchema:' . $parsedId );
			if ( !$title ) {
				return $content;
			}

			$json->{'$id'} = $title->getFullURL();
			return json_encode( $json );
		};

		$importer = new Importer( $dirPath, $prefix, $callbackRefs, $callbackPagename, $callbackContent );
		$importer->doImport();
	}

}

$maintClass = ImportData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
