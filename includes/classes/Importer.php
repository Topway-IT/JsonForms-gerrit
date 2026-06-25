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

namespace MediaWiki\Extension\JsonForms;

use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Extension\JsonForms\Utils\SafeJsonEncoder;
use MediaWiki\MediaWikiServices;

class Importer {

	/** @var string */
	private $prefix;

	/** @var array */
	private $pageSlots;

	/** @var array */
	private $errors = [];

	/** @var array */
	private $filenameMap = [];

	/** @var array */
	private $contentModels = [];

	/** @var callable */
	protected $callbackRefs;

	/** @var callable */
	protected $callbackPagename;

	/** @var callable */
	protected $callbackContent;

	/**
	 * @param string $path
	 */
	public function __construct( $path, $prefix, $callbackRefs, $callbackPagename, $callbackContent = null ) {
		$this->prefix = $prefix;
		$this->callbackRefs = $callbackRefs;
		$this->callbackPagename = $callbackPagename;
		$this->callbackContent = $callbackContent;

		$services = MediaWikiServices::getInstance();

		$contentHandlerFactory = $services->getContentHandlerFactory();
		$this->contentModels = $contentHandlerFactory->getContentModels();

		$this->groupSlotContents( $path, '' );

		// print_r($this->pageSlots);
		// print_r($this->filenameMap);
	}

	public function doImport() {
		$failed = [];
		foreach ( $this->pageSlots as $titleStr => $slots ) {
			echo "importing $titleStr" . PHP_EOL;

			foreach ( $slots as &$value ) {
				if ( $value['model'] === 'json' ) {
					$value['text'] = $this->updateRefs( $titleStr, $value['text'] );
				}
			}

			try {
				$title_ = TitleClass::newFromText( $titleStr );
				$titleStr = $this->makeTitle( $title_ );

				echo $titleStr . "\n";
				$title_ = TitleClass::newFromText( $titleStr );

				\JsonForms::importRevision( $title_, $slots, $this->errors );
				echo ' (success)' . PHP_EOL;

			} catch ( \Exception $e ) {
				echo '***error ' . $e->getMessage();
				$failed[$titleStr] = $e->getMessage();
			}
		}

		if ( count( $failed ) ) {
			echo '(JsonForms) ***error importing ' . count( $failed ) . ' articles' . PHP_EOL;
			foreach ( $failed as $titleStr => $message ) {
				echo "Failed to import: $titleStr - Error: $message" . PHP_EOL;
			}
		}
	}

	/**
	 * @param string $path
	 * @param string $relPath
	 */
	private function groupSlotContents( $path, $relPath ) {
		$files = scandir( $path );
		foreach ( $files as $filename ) {
			if ( $filename === '.' || $filename === '..' || strpos( $filename, '.' ) === 0 ) {
				continue;
			}
			$filePath_ = "$path/$filename";

			if ( is_dir( $filePath_ ) ) {
				$this->groupSlotContents( $filePath_, ( $relPath ? "$relPath/$filename" : $filename ) );

			} elseif ( is_file( $filePath_ ) ) {
				$pageName = $this->extractPageName( $filename, $relPath );
				$slotData = $this->extractSlotData( $filename, $filePath_ );

				if ( $pageName && $slotData ) {
					$this->pageSlots[$pageName][] = $slotData;
					echo "Found file: $filename -> Page: $pageName, Slot: " . $slotData['role'] . PHP_EOL;
				} else {
					echo "Skipping file: $filename (pageName: " . ( $pageName ?: 'null' ) . ", slotData: " . ( $slotData ? 'valid' : 'null' ) . ")" . PHP_EOL;
				}
			}
		}
	}

	/**
	 * @param string $str
	 * @return string
	 */
	private function toCamelCase( $str ) {
		$str = str_replace( [ '-', '_' ], ' ', $str );
		$str = str_replace( ' ', '', ucwords( $str ) );
		return lcfirst( $str );
	}

	/**
	 * @param string $filename
	 * @param string $relPath
	 * @return string
	 */
	private function extractPageName( $filename, $relPath ) {
		$parts = explode( '/', $relPath );
		$namespace = array_shift( $parts );

		$filename_ = explode( '.', $filename, 2 );
		$baseName = $filename_[0];

		$parts[] = $baseName;

		$parts = implode( '/', $parts );

		$parts = ( $this->callbackPagename )( $parts );

		$ret = $namespace . ':' . $parts;

		$title = TitleClass::newFromText( $ret );

		// $title->getLocalURL( [ 'action' => 'raw' ] );
		$this->filenameMap[$parts] = $this->makeTitle( $title );  

		return $ret;
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	private function makeTitle( $title ) {
		return \JsonForms::getFormattedNamespace( $title->getNamespace() ) .
			':' . $this->prefix . $title->getText();
	}

	/**
	 * @param string $filename
	 * @param string $text
	 * @return array
	 */
	private function updateRefs( $filename, $text ) {
		$schema = json_decode( $text, false );

		if ( $schema === false ) {
			$this->errors[] = "cannot decode '$filename'";
			return $text;
		}

		$thisClass = $this;

		$callback = static function ( &$parent, $key, $value, $pathArr ) use ( $thisClass ) {
			if ( $key !== '$ref' || !is_string( $value ) ) {
				return;
			}

			$filename_ = explode( '.', $value, 2 );
			$value = $filename_[0];

			$value = ( $thisClass->callbackRefs )( $value );

			if ( !empty( $value ) &&
				array_key_exists( $value, $thisClass->filenameMap )
			) {
				$parent->$key = $thisClass->filenameMap[$value];
			}
		};

		$obj = SchemaUtils::traverseSchema( $schema, $callback );

		$showMsg = static function ( $msg ) {
			echo '(SafeJsonEncoder) ' . $msg . PHP_EOL;
		};

		$encoder = new SafeJsonEncoder( $showMsg, JSON_PRETTY_PRINT );

		try {
			return $encoder->encode( $obj );

		} catch ( \Exception $e ) {
			$this->errors[] = "error, json_encode failed: " . $e->getMessage();
			return false;
		}
	}

	/**
	 * @param string $filename
	 * @param string $filePath
	 * @return array
	 */
	private function extractSlotData( $filename, $filePath ) {
		// ex: baseName.slot_type.contentModel
		$parts = explode( '.', $filename, 3 );

		if ( count( $parts ) < 2 ) {
			return null;
		}

		$slot = $parts[1] ?? '';
		$contentModel = $parts[2] ?? '';

		if ( empty( $contentModel ) ) {
			$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			if ( $ext === 'js' ) {
				$ext = 'javascript';
			}
			if ( in_array( $ext, $this->contentModels ) ) {
				$contentModel = $ext;
			}
		}

		// exceptions
		switch ( $contentModel ) {
			case 'lua':
				$contentModel = 'Scribunto';
				break;
		}

		// slots defined using $wgWSSlotsDefinedSlots
		if ( strpos( $slot, 'slot_' ) === 0 ) {
			$slotRole = str_replace( 'slot_', '', $slot );

		// exceptions
		} else {
			switch ( $slot ) {
				case 'schema':
				default:
					$slotRole = 'main';
					break;
			}
		}

		$content = file_get_contents( $filePath );

		if ( $this->callbackContent ) {
			$content = ( $this->callbackContent )( $content, $contentModel );
		}

		return [
			'role' => $slotRole,
			'model' => $contentModel,
			'text' => $content
		];
	}
	
}
