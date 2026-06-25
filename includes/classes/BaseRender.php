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
use MediaWiki\MediaWikiServices;
use User;

class BaseRender {
	protected string $schemaName;
	protected User $user;

	/** @var Title|TitleClass */
	protected $title;

	protected array $parameters = [];
	protected array $schemaMap = [];

	/**
	 * param User $user
	 * param Title $title
	 * param array $parameters
	 * param stdClass $processedSchema null
	 */
	public function __construct( $user, $title, $parameters, $processedSchema = null ) {
		$this->user = $user;
		$this->title = $title;
		$this->parameters = $parameters;

		if ( $processedSchema ) {
			$this->buildSchemaMap( $processedSchema );
		}
	}

    /**
     * Build a map of property paths to their schema definitions from processed schema (flat structure)
     *
     * The flat structure has entries like:
     * {
     *   "a": { "schema": {...}, "children": [] },
     *   "b": { "schema": { "type": "array", "items": {...} }, "children": ["b.0", "b.1"] },
     *   "b.0": { "schema": {...}, "children": [] }
     * }
     *
     * @param stdClass|array $schema - The processed schema object
     * @param string $path - Current path (for recursion)
     */
    protected function buildSchemaMap( $schema, $path = "" ) {
        $schemaArray = is_object( $schema ) ? get_object_vars( $schema ) : $schema;

        foreach ( $schemaArray as $key => $value ) {
            $currentPath = $path ? $path . "." . $key : $key;

            // Check if this is a node with schema (flat structure)
            if ( is_object( $value ) && property_exists( $value, "schema" ) ) {
                // Store schema in map
                $this->schemaMap[$currentPath] = $value->schema;

                // Check if this schema has an "items" property (shared items schema)
                if ( property_exists( $value->schema, "items" ) && is_object( $value->schema->items ) ) {
                    $itemsPath = $currentPath . ".items";
                    $this->schemaMap[$itemsPath] = $value->schema->items;
                }

                // If has children, traverse them (the children are path strings)
                if ( property_exists( $value, "children" ) && is_array( $value->children ) ) {
                    foreach ( $value->children as $childPath ) {
                        // Child path is a string like "b.0" or "a"
                        // We need to find the child in the flat schema
                        // It will be processed when we iterate the parent schema
                        // Since it's a flat structure, the child exists at the same level
                        // with key = childPath
                    }
                }
            } elseif ( is_array( $value ) && isset( $value["schema"] ) ) {
                // Handle array-based schema (if any)
                $this->schemaMap[$currentPath] = (object)$value["schema"];

                if ( isset( $value["schema"]["items"] ) && is_array( $value["schema"]["items"] ) ) {
                    $itemsPath = $currentPath . ".items";
                    $this->schemaMap[$itemsPath] = (object)$value["schema"]["items"];
                }

                if ( isset( $value["children"] ) && is_array( $value["children"] ) ) {
                    foreach ( $value["children"] as $childPath ) {
                        // Will be processed when iterating
                    }
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
	protected function getSchemaInfo( $key, $path = "" ) {
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
				"x-render-template" => $isObj
					? $schema->{'x-render-template'} ?? ""
					: $schema['x-render-template'] ?? "",
			];
		}

		// Try to get schema info from items path if this is an array item
		if ( strpos( $path, '.' ) !== false ) {
			$parts = explode( '.', $path );
			$lastPart = end( $parts );
			
			if ( is_numeric( $lastPart ) ) {
				// This is an array item, try to get items schema
				$parentPath = implode( '.', array_slice( $parts, 0, -1 ) );
				$itemsPath = $parentPath . '.items';
				
				if ( isset( $this->schemaMap[$itemsPath] ) ) {
					$schema = $this->schemaMap[$itemsPath];
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
						"x-render-template" => $isObj
							? $schema->{'x-render-template'} ?? ""
							: $schema['x-render-template'] ?? "",
					];
				}
			}
		}

		return [
			"title" => '',
			"description" => '',
			"format" => "",
			"layout" => "",
			"uniqueItems" => false,
			"required" => false,
			'x-render-template' => ''
		];
	}

	/**
	 * @param string $html
	 * @param int $length
	 * @param string $ellipsis
	 * @return string
	 */
	protected function truncateTextUtf8($html, $length = 100, $ellipsis = '…') {
		$text = strip_tags($html);
    
		if (mb_strlen($text) > $length) {
			$text = mb_substr($text, 0, $length) . $ellipsis;
		}
    
		return $text;
	}

	/**
	 * Check if content contains HTML
	 *
	 * @param mixed $content
	 * @return bool
	 */
	protected function isHtml( $content ) {
		if ( !is_string( $content ) ) {
			return false;
		}
		return preg_match( "/<[a-z][a-z0-9]*[^>]*>/i", $content ) === 1;
	}
}
