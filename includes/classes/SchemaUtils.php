<?php

namespace MediaWiki\Extension\JsonForms;

use stdClass;

class SchemaUtils {

	/**
	 * Get value from an array by dot notation path
	 *
	 * @param array|null $obj Source array
	 * @param string|null $path Dot notation path (e.g., "a.b.1.c")
	 * @param mixed $default Default value to return if path not found
	 * @return mixed Value at path or default
	 */
	public static function getValueByPath( $obj, $path, $default = null ) {
		if ( $obj === null ) {
			return $default;
		}

		// Handle both arrays and objects
		if ( !is_array( $obj ) && !is_object( $obj ) ) {
			return empty( $path ) ? $obj : $default;
		}

		if ( empty( $path ) ) {
			return $obj;
		}

		// Convert bracket notation to dot notation: a[1].b -> a.1.b
		$normalizedPath = preg_replace( "/\[(\d+)\]/", '.$1', $path );
		$keys = explode( ".", $normalizedPath );
		$current = $obj;

		foreach ( $keys as $key ) {
			if ( $current === null ) {
				return $default;
			}

			// array
			if ( is_array( $current ) ) {
				if ( !array_key_exists( $key, $current ) ) {
					return $default;
				}
				$current = $current[$key];

			// object
			} elseif ( is_object( $current ) ) {
				if ( !property_exists( $current, $key ) ) {
					return $default;
				}
				$current = $current->$key;
			} else {
				return $default;
			}
		}

		return $current !== null ? $current : $default;
	}

	/**
	 * Recursively merges source into target. Target values always take precedence.
	 *
	 * Arrays keep target length and merge child elements. Objects merge properties.
	 * When types differ, target prevails without merging.
	 *
	 * @param stdClass|array|null $target
	 * @param stdClass|array|null $source
	 * @return stdClass|array|null
	 */
	public static function mergeObjectsRecursive( $target, $source ) {
		if ( $source === null ) {
			return $target;
		}
		if ( $target === null ) {
			return $source;
		}

		// Arrays
		if ( is_array( $target ) && is_array( $source ) ) {
			$result = $target;
			for ( $i = 0; $i < count( $target ); $i++ ) {
				$sourceItem = isset( $source[$i] ) ? $source[$i] : null;
				$result[$i] = self::mergeObjectsRecursive( $target[$i], $sourceItem );
			}
			return $result;
		}

		// Objects
		if ( is_object( $target ) && is_object( $source ) ) {
			$result = clone $target;
			foreach ( get_object_vars( $source ) as $property => $value ) {
				if ( property_exists( $result, $property ) ) {
					if (
						( is_array( $result->$property ) && is_array( $value ) ) ||
						( is_object( $result->$property ) && is_object( $value ) )
					) {
						$result->$property = self::mergeObjectsRecursive( $result->$property, $value );
					}
				// Preserve target
				} else {
					$result->$property = $value;
				}
			}
			return $result;
		}

		return $target;
	}

	/**
	 * Check if path ends with an append symbol
	 *
	 * @param string $path The original path
	 * @param array $appendSymbols Array of symbols to check for
	 * @return array [shouldAppend, cleanedPath]
	 */
	public static function parseAppendPath(
		$path,
		$appendSymbols = [ ".[]", ".", "[]" ],
	) {
		foreach ( $appendSymbols as $symbol ) {
			$symbolLen = strlen( $symbol );
			if ( substr( $path, -$symbolLen ) === $symbol ) {
				return [ true, substr( $path, 0, -$symbolLen ) ];
			}
		}
		return [ false, $path ];
	}

	/**
	 * Set a value in an array by dot notation path
	 *
	 * @param stdClass|null &$obj Source array (passed by reference)
	 * @param string $path Dot notation path (e.g., "a.b.1.c")
	 * @param mixed $value Value to set
	 * @param bool $createMissing Whether to create missing intermediate arrays
	 * @return bool True if value was set, false otherwise
	 */
	public static function setValueByPath(
		&$obj,
		$path,
		$value,
		$createMissing = true,
	) {
		if ( $obj === null ) {
			$obj = new stdClass();
		}

		if ( !is_object( $obj ) && !is_array( $obj ) ) {
			return false;
		}

		if ( empty( $path ) ) {
			$obj = $value;
			return true;
		}

		[ $shouldAppend, $cleanPath ] = self::parseAppendPath( $path );

		// Handle root-level append (e.g., "[]" or ".[]")
		if ( $shouldAppend && empty( $cleanPath ) ) {
			if ( !is_array( $obj ) ) {
				$obj = [];
			}
			$obj[] = $value;
			return true;
		}

		$pathToUse = $cleanPath ?: $path;

		// Convert bracket notation to dot notation
		$normalizedPath = preg_replace( "/\[(\d+)\]/", '.$1', $pathToUse );
		$keys = explode( ".", $normalizedPath );
		$current = &$obj;

		foreach ( $keys as $i => $key ) {
			$isLastKey = $i === count( $keys ) - 1;

			if ( $isLastKey ) {
				if ( $shouldAppend ) {
					// Get or create the container for append operation
					if ( is_array( $current ) ) {
						if ( !isset( $current[$key] ) ) {
							if ( !$createMissing ) {
								return false;
							}
							$current[$key] = [];
						}
						if ( !is_array( $current[$key] ) ) {
							if ( !$createMissing ) {
								return false;
							}
							$current[$key] = [];
						}
						$current[$key][] = $value;
					} else {
						if ( !property_exists( $current, $key ) ) {
							if ( !$createMissing ) {
								return false;
							}
							$current->$key = [];
						}
						if ( !is_array( $current->$key ) ) {
							if ( !$createMissing ) {
								return false;
							}
							$current->$key = [];
						}
						$current->$key[] = $value;
					}
				} else {
					// Set value
					if ( is_array( $current ) ) {
						$current[$key] = $value;
					} else {
						$current->$key = $value;
					}
				}
				return true;
			}

			// Navigate to next level
			$isNextNumeric = isset( $keys[$i + 1] ) && is_numeric( $keys[$i + 1] );

			if ( is_array( $current ) ) {
				// Current is array
				if ( !isset( $current[$key] ) ) {
					if ( !$createMissing ) {
						return false;
					}
					// Create array or object
					$current[$key] = $isNextNumeric ? [] : new stdClass();
				}
				$current = &$current[$key];
			} else {
				// object
				if ( !property_exists( $current, $key ) ) {
					if ( !$createMissing ) {
						return false;
					}
					// Create array or object
					$current->$key = $isNextNumeric ? [] : new stdClass();
				}
				$current = &$current->$key;
			}
		}

		return false;
	}

	/**
	 * @param stdClass|array $schema
	 * @param callable $callback
	 * @return stdClass
	 */
	public static function traverseSchema(
		$schema,
		callable $callback,
		$path = [],
		&$parent = null,
		$parentKey = null,
	) {
		if ( $parent === null ) {
			// Root call
			$emptyParent = is_object( $schema ) ? new stdClass() : [];
			$callback( $emptyParent, "", $schema, [] );

			if ( is_object( $schema ) ) {
				foreach ( get_object_vars( $schema ) as $key => $value ) {
					self::traverseSchema(
						$value,
						$callback,
						[ $key ],
						$schema,
						$key,
					);
				}
			} elseif ( is_array( $schema ) ) {
				foreach ( $schema as $key => $value ) {
					self::traverseSchema(
						$value,
						$callback,
						[ $key ],
						$schema,
						$key,
					);
				}
			}

			return $schema;
		}

		// Process current node
		$callback( $parent, $parentKey, $schema, $path );

		// Recurse into children
		if ( is_object( $schema ) ) {
			foreach ( get_object_vars( $schema ) as $key => $value ) {
				$newPath = $path;
				$newPath[] = $key;
				self::traverseSchema(
					$value,
					$callback,
					$newPath,
					$schema,
					$key,
				);
			}
		} elseif ( is_array( $schema ) ) {
			foreach ( $schema as $key => $value ) {
				$newPath = $path;
				$newPath[] = $key;
				self::traverseSchema(
					$value,
					$callback,
					$newPath,
					$schema,
					$key,
				);
			}
		}

		return $schema;
	}

	/**
	 * @param Title $title
	 * @param array slots
	 * @param array &$errors []
	 * @return
	 */
	/*
	public static function traverseSchema( array $schema, callable $callback ): array {
		// Process root
		$rootRef = &$schema;
		$emptyParent = [];
		$callback( $emptyParent, '', $rootRef, [] );

		$it = new RecursiveIteratorIterator(
			new RecursiveArrayIterator( $schema ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $it as $key => $value ) {
			$path = [];
			for ( $depth = 0; $depth <= $it->getDepth(); $depth++ ) {
				$path[] = $it->getSubIterator( $depth )->key();
			}

			$parent =& $schema;
			for ( $depth = 0; $depth < $it->getDepth(); $depth++ ) {
				$parent =& $parent[ $it->getSubIterator( $depth )->key() ];
			}

			$valueRef =& $parent[$key];

			$callback( $parent, $key, $valueRef, $path );
		}

		return $schema;
	}
*/
}
