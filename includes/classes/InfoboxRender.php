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
 * @copyright Copyright ©2026, https://wikisphere.org
 */

namespace MediaWiki\Extension\JsonForms;

use MediaWiki\Extension\JsonForms\Aliases\Linker as LinkerClass;
use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use User;

class InfoboxRender {
	private string $schemaName;
	private User $user;

	/** @var Title|TitleClass */
	private $title;

	private array $schemaMap = [];

	/**
	 * param string $schemaName
	 * param stdClass $processedSchema null
	 */
	public function __construct( $user, $title, $schemaName, $processedSchema = null ) {
		$this->user = $user;
		$this->title = $title;
		$this->schemaName = $schemaName;

		if ( $processedSchema ) {
			$this->buildSchemaMap( $processedSchema );
		}
	}

	/**
	 * Build a map of property paths to their schema definitions from processed schema (object)
	 *
	 * param stdClass $schema
	 * param string $path
	 */
	private function buildSchemaMap( $schema, $path = "" ) {
		// Convert object to array for iteration if needed
		$schemaArray = is_object( $schema ) ? get_object_vars( $schema ) : $schema;

		foreach ( $schemaArray as $key => $value ) {
			// Handle array of items (numeric keys)
			if (
				is_numeric( $key ) &&
				is_object( $value ) &&
				property_exists( $value, "schema" )
			) {
				$currentPath = $path ? $path . "." . $key : $key;
				$this->schemaMap[$currentPath] = $value->schema;

				if (
					property_exists( $value, "value" ) &&
					( is_object( $value->value ) || is_array( $value->value ) )
				) {
					$this->buildSchemaMapFromValue( $value->value, $currentPath );
				}
			} elseif ( is_object( $value ) && property_exists( $value, "schema" ) ) {
				// Regular property with schema
				$currentPath = $path ? $path . "." . $key : $key;
				$this->schemaMap[$currentPath] = $value->schema;

				if (
					property_exists( $value, "value" ) &&
					( is_object( $value->value ) || is_array( $value->value ) )
				) {
					$this->buildSchemaMapFromValue( $value->value, $currentPath );
				}
			}
		}
	}

	/**
	 * Build schema map from value part of processed schema
	 *
	 * param stdClass $value
	 * param string $path
	 */
	private function buildSchemaMapFromValue( $value, $path ) {
		$valueArray = is_object( $value ) ? get_object_vars( $value ) : $value;

		foreach ( $valueArray as $key => $item ) {
			if ( is_object( $item ) && property_exists( $item, "schema" ) ) {
				$currentPath = $path . "." . $key;
				$this->schemaMap[$currentPath] = $item->schema;

				if (
					property_exists( $item, "value" ) &&
					( is_object( $item->value ) || is_array( $item->value ) )
				) {
					$this->buildSchemaMapFromValue( $item->value, $currentPath );
				}
			} elseif ( is_array( $item ) && isset( $item["schema"] ) ) {
				$currentPath = $path . "." . $key;
				$this->schemaMap[$currentPath] = $item["schema"];

				if (
					isset( $item["value"] ) &&
					( is_array( $item["value"] ) || is_object( $item["value"] ) )
				) {
					$this->buildSchemaMapFromValue(
						$item["value"],
						$currentPath,
					);
				}
			}
		}
	}

	/**
	 * Get schema info for a property path
	 *
	 * @param string $key
	 * @param string $path
	 * @return array
	 */
	private function getSchemaInfo( $key, $path = "" ) {
		$fullPath = $path;

		if ( isset( $this->schemaMap[$fullPath] ) ) {
			$schema = $this->schemaMap[$fullPath];

			// Handle both object and array schema
			$isObj = is_object( $schema );

			return [
				"title" => $isObj
					? $schema->title ?? $key
					: $schema["title"] ?? $key,
				"description" => $isObj
					? $schema->description ?? ""
					: $schema["description"] ?? "",
				"format" => $isObj
					? $schema->format ?? ""
					: $schema["format"] ?? "",
				"layout" => $isObj
					? $schema->layout ?? ""
					: $schema["layout"] ?? "",
				"uniqueItems" => $isObj
					? $schema->uniqueItems ?? false
					: $schema["uniqueItems"] ?? false,
				"required" => $isObj
					? $schema->required ?? false
					: $schema["required"] ?? false,
			];
		}

		return [
			"title" => '',
			"description" => '',
			"format" => "",
			"layout" => "",
			"uniqueItems" => false,
			"required" => false,
		];
	}

	/**
	 * @param array $processedNode
	 * @return array
	 */
	private function extractData( $processedNode ) {
		if ( !is_object( $processedNode ) && !is_array( $processedNode ) ) {
			return $processedNode;
		}

		// If this is a processed schema node with schema and value
		if (
			is_object( $processedNode ) &&
			property_exists( $processedNode, "schema" ) &&
			property_exists( $processedNode, "value" )
		) {
			return $this->extractData( $processedNode->value );
		}

		if (
			is_array( $processedNode ) &&
			isset( $processedNode["schema"] ) &&
			isset( $processedNode["value"] )
		) {
			return $this->extractData( $processedNode["value"] );
		}

		// Preserve the original type
		$isInputObject = is_object( $processedNode );
		$result = $isInputObject ? new \stdClass() : [];

		$items = $isInputObject
			? get_object_vars( $processedNode )
			: $processedNode;

		foreach ( $items as $key => $value ) {
			$extractedValue = $this->extractData( $value );
			if ( $isInputObject ) {
				$result->$key = $extractedValue;
			} else {
				$result[$key] = $extractedValue;
			}
		}

		return $result;
	}

	/**
	 * @param array $node
	 * @param string $path
	 * @param string $pathNoIndex
	 * @param int $level
	 * @return string
	 */
	public function renderNode( $node, $path, $pathNoIndex, $level = 0 ) {
		$ret = "";
		$nextLevel = $level + 1;

		// Handle both objects and arrays
		if ( is_object( $node ) ) {
			$nodeArray = get_object_vars( $node );
		} elseif ( is_array( $node ) ) {
			$nodeArray = $node;
		} else {
			return $ret;
		}

		foreach ( $nodeArray as $key => $value ) {
			$newPath = $path;
			$newPathNoIndex = $pathNoIndex;

			$newPath[] = $key;

			if ( !is_numeric( $key ) && is_string( $key ) ) {
				$newPathNoIndex[] = $key;
			} else {
				$newPathNoIndex[] = "Item";
			}

			$pathStr = implode( ".", $newPath );

			if ( is_array( $value ) || is_object( $value ) ) {
				$childrenHtml = $this->renderNode(
					$value,
					$newPath,
					$newPathNoIndex,
					$nextLevel,
				);
				$ret .= $this->renderContainer(
					$key,
					$value,
					$childrenHtml,
					$level,
					$pathStr,
				);
			} else {
				$ret .= $this->renderLeaf(
					$key,
					$value,
					gettype( $value ),
					$pathStr,
				);
			}
		}

		return $ret;
	}

	/**
	 * Get display label for a key (unified logic)
	 *
	 * @param string $key
	 * @param string $path
	 * @return string
	 */
	private function getDisplayKey( $key, $path = "" ) {
		$schemaInfo = $this->getSchemaInfo( $key, $path );

		if ( !empty( $schemaInfo["title"] ) ) {
			return $schemaInfo["title"];
		}

		// For numeric keys (array items)
		if ( is_numeric( $key ) ) {
			return "Item " . ( $key + 1 );
		}

		return $key;
	}

	/**
	 * @param array $processedData
	 * @return string
	 */
	public function render( $processedData ) {
		// Extract actual data from processed schema
		$actualData = $this->extractData( $processedData );
		$childrenHtml = $this->renderNode( $actualData, [], [], 0 );
		return $this->rootContainer( $actualData, $childrenHtml );
	}

	/**
	 * @param stdClass $value
	 * @param string $childrenHtml
	 * @return string
	 */
	private function rootContainer( $value, $childrenHtml ) {
		$type = gettype( $value );
		$isArray = $type === "array";
		$schema = \JsonForms::getSourceSchema( $this->schemaName, 'JsonSchema' );
		$count = is_array( $value ) ? count( $value ) : null;

		$countDisplay = "";
		if ( $count !== null ) {
			$countDisplay = '<span class="infobox-count">(' . $count . ")</span>";
		}

		if ( $this->user->isAllowed( 'jsonforms-caneditdata' ) ) {
			$editUrl = $this->title->getLocalURL( "action=jsonedit" );
			$edit = new \OOUI\ButtonWidget( [
				"icon" => "edit",
				'flags' => [ 'progressive' ],
				'framed' => false,
				"href" => $editUrl
			] );
		}

		$title = TitleClass::newFromText( 'JsonSchema:' . $this->schemaName );
		$link = LinkerClass::link( $title, $schema->title ?? $this->schemaName );

		if ( $isArray ) {
			$ret =
			'<div class="toccolours infobox-array">
<div class="infobox-array-header infobox-root"><span class="infobox-header-left">' .
'<span class="infobox-key">' .
				$link .
			'</span>' .
			'<span class="infobox-meta"><span class="infobox-type">array</span>' .
				$countDisplay .
			'</span></span><span class="infobox-header-right"><span class="infobox-edit">' .
				$edit .
			'</span>
</span>
</div>
<div class="infobox-array-content">' .
				$childrenHtml .
			'</div>
		</div>';

		} else {
			$ret =
			'<div class="toccolours infobox-object">
<div class="infobox-object-header infobox-root"><span class="infobox-header-left">' .
	'<span class="infobox-key">' .
					$link .
				'</span>' .
			'<span class="infobox-meta"><span class="infobox-type">object</span>' .
				$countDisplay .
			'</span></span><span class="infobox-header-right"><span class="infobox-edit">' .
				$edit .
			'</span>
</span>
</div>
<div class="infobox-object-content">' .
				$childrenHtml .
			'</div>
		</div>';
		}

		return $ret;
	}

	/**
	 * @param string $key
	 * @param stdClass $value
	 * @param string $childrenHtml
	 * @param int $level
	 * @param string $path
	 * @return string
	 */
	private function renderContainer( $key, $value, $childrenHtml, $level, $path ) {
		$displayKey = $this->getDisplayKey( $key, $path );
		$escapedKey = htmlspecialchars( (string)$displayKey );
		$count = is_array( $value ) ? count( $value ) : null;
		$type = gettype( $value );
		$isArray = $type === "array";

		$schemaInfo = $this->getSchemaInfo( $key, $path );

		$layoutHint = "";
		if ( !empty( $schemaInfo["layout"] ) ) {
			$layoutHint =
			'<span class="infobox-layout-hint" title="Layout: ' .
			htmlspecialchars( $schemaInfo["layout"] ) .
			'">📐</span>';
		}

		$uniqueHint = "";
		if ( $isArray && $schemaInfo["uniqueItems"] ) {
			$uniqueHint =
			'<span class="infobox-unique-hint" title="Unique values only">🔒</span>';
		}

		$countDisplay = "";
		if ( $count !== null ) {
			$countDisplay =
			'<span class="infobox-count">(' . $count . ")</span>";
		}

		// Only add collapsible classes and toggle for level > 0
		$isCollapsible = $level > 0;
		$collapsibleClass = $isCollapsible ? "mw-collapsible mw-collapsed" : "";
		$collapsibleContentClass = $isCollapsible ? "mw-collapsible-content" : "";

		// Add expand/collapse toggle for collapsible sections
		$toggleHtml = "";
		if ( $isCollapsible ) {
			$toggleHtml = '';
		}

		if ( $isArray ) {
			$ret =
			'<div class="toccolours infobox-array ' . $collapsibleClass . '">
<div class="infobox-array-header">' . $toggleHtml .
	'<span class="infobox-key">' .
					$escapedKey .
				'</span>' .
				$layoutHint .
				$uniqueHint .
				'<span class="infobox-meta"><span class="infobox-type">array</span>' .
					$countDisplay .
				'</span>
</div>
<div class="infobox-array-content ' . $collapsibleContentClass . '">' .
				$childrenHtml .
			'</div>
		</div>';

		} else {
			$ret =
			'<div class="toccolours infobox-object ' . $collapsibleClass . '">
<div class="infobox-object-header">' . $toggleHtml .
	'<span class="infobox-key">' .
					$escapedKey .
				'</span>' .
				$layoutHint .
				'<span class="infobox-meta"><span class="infobox-type">object</span>' .
					$countDisplay .
				'</span>
</div>
<div class="infobox-object-content ' . $collapsibleContentClass . '">' .
				$childrenHtml .
			'</div>
		</div>';
		}

		return $ret;
	}

	/**
	 * @param string $key
	 * @param stdClass $value
	 * @param string $type
	 * @param string $path
	 * @return string
	 */
	private function renderLeaf( $key, $value, $type, $path ) {
		$displayKey = $this->getDisplayKey( $key, $path );
		$escapedKey = htmlspecialchars( (string)$displayKey );
		$escapedType = htmlspecialchars( $type );
		$displayValue = $this->formatValue( $value, $type, $path );

		$schemaInfo = $this->getSchemaInfo( $key, $path );
		$requiredMark = $schemaInfo["required"]
			? '<span class="infobox-required" title="Required">*</span>'
			: "";

		$descriptionAttr = "";
		if ( !empty( $schemaInfo["description"] ) ) {
			$descriptionAttr =
				' title="' . htmlspecialchars( $schemaInfo["description"] ) . '"';
		}

		$formatHint = "";
		if ( !empty( $schemaInfo["format"] ) ) {
			$formatHint =
				'<span class="infobox-format-hint" title="Format: ' .
				htmlspecialchars( $schemaInfo["format"] ) .
				'">' .
				$this->getFormatIcon( $schemaInfo["format"] ) .
				"</span>";
		}

		return '<div class="infobox-row"' .
			$descriptionAttr .
			'>
<div class="infobox-label">
<span class="infobox-key-label">' .
			$escapedKey .
			'</span>' .
			$requiredMark .
			$formatHint .
			'<sup><span class="infobox-type-badge">' .
			$escapedType .
			'</span></sup>
</div>
<div class="infobox-value">' .
			$displayValue .
			'
</div>
</div>';
	}

	/**
	 * @param string $format
	 * @return string
	 */
	private function getFormatIcon( $format ) {
		switch ( $format ) {
			case "textarea":
				return "📝";
			case "text":
				return "📄";
			case "email":
				return "📧";
			case "url":
				return "🔗";
			case "tel":
				return "📞";
			case "number":
				return "#️⃣";
			default:
				return "🏷️";
		}
	}

	/**
	 * @param stdClass $value
	 * @param string $format
	 * @param string $path
	 * @return string
	 */
	private function formatValue( $value, $type, $path ) {
		$schemaInfo = $this->getSchemaInfo( null, $path );

		switch ( $type ) {
			case "boolean":
				return '<span class="value-boolean">' .
					( $value ? "true" : "false" ) .
					"</span>";
			case "NULL":
				return '<span class="value-null">null</span>';
			case "integer":
			case "double":
				return '<span class="value-number">' .
					htmlspecialchars( (string)$value ) .
					"</span>";
			case "string":
				if ( $this->isHtml( $value ) ) {
					return '<div class="value-html">' . $value . "</div>";
				}
				if (
					!empty( $schemaInfo["format"] ) &&
					$schemaInfo["format"] === "textarea"
				) {
					return '<div class="value-textarea">' .
						nl2br( htmlspecialchars( $value ) ) .
						"</div>";
				}
				if ( $value === "" ) {
					return '<span class="value-empty"></span>';
				}
				return '<span class="value-string">' .
					nl2br( htmlspecialchars( $value ) ) .
					"</span>";
			default:
				if ( is_object( $value ) ) {
					if ( method_exists( $value, "__toString" ) ) {
						return '<span class="value-object">' .
							htmlspecialchars( $value->__toString() ) .
							"</span>";
					}
					return '<span class="value-object">Object (' .
						htmlspecialchars( get_class( $value ) ) .
						")</span>";
				}
				return '<span class="value-default">' .
					htmlspecialchars( (string)$value ) .
					"</span>";
		}
	}

	/**
	 * @param mixed $content
	 * @return bool
	 */
	private function isHtml( $content ) {
		if ( !is_string( $content ) ) {
			return false;
		}
		return preg_match( "/<[a-z][a-z0-9]*[^>]*>/i", $content ) === 1;
	}
}
