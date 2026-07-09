<?php

namespace MediaWiki\Extension\JsonForms;

use MediaWiki\Output\OutputPage;
use stdClass;

/**
 * Schema processor that transforms schema properties by:
 * - Parsing wikitext fields
 * - Escaping text fields
 * - Removing writeOnly properties
 * - Applying format-specific processing
 */
class SchemaProcessor {
	/**
	 * @var OutputPage
	 */
	private $output;

	/**
	 * @var array Configuration options
	 */
	private array $options;

	/**
	 * @var array Format handler callbacks
	 */
	private array $formatHandlers;

	/**
	 * Constructor
	 *
	 * @param Output $output
	 * @param array $options Configuration options
	 */
	public function __construct( OutputPage $output, array $options = [] ) {
		$this->output = $output;
		$this->options = array_merge( [
			'wikitext_keys' => [
				'x-title-format' => 'title',
				'x-description-format' => 'description',
				'x-label-format' => 'label',
				'x-enum-titles-format' => 'x-enum-titles',
			],
			'remove_writeonly' => true,
			'default_format' => 'text',
			'escape_html' => true,
		], $options );

		$this->formatHandlers = [
			'html' => fn( $v ) => $v,
			'wikitext' => fn( $v ) => is_array( $v ) 
				? array_map( [ $this, 'parseWikitext' ], $v )
				: $this->parseWikitext( $v ),
			'text' => function( $v ) {
				if ( !$this->options['escape_html'] ) {
					return $v;
				}
				return is_array( $v ) 
					? array_map( [ $this, 'escapeHtml' ], $v )
					: $this->escapeHtml( $v );
			},
		];
	}

	/**
	 * Static entry point - process schema with default options
	 *
	 * @param Output $output
	 * @param stdClass|array $schema
	 * @param array $options Optional configuration overrides
	 * @return array
	 */
	public static function processSchema( $output, $schema, array $options = [] ): stdClass {
		if ( empty( $schema ) ) {
			return new stdClass();
		}

		$processor = new self( $output, $options );
		return $processor->process( $schema );
	}

	/**
	 * Process the schema
	 *
	 * @param stdClass|array $schema
	 * @return stdClass
	 */
	public function process( $schema ): stdClass {
		return SchemaUtils::traverseSchema( $schema, function( &$parent, $key, &$value, $pathArr ) {
			$this->processNode( $value );
		} );
	}

	/**
	 * Process a single node
	 *
	 * @param mixed &$value
	 * @return void
	 */
	private function processNode( &$value ): void {
		if ( !$this->isProcessable( $value ) ) {
			return;
		}

		$isObject = is_object( $value );

		$this->removeWriteOnlyProperties( $value, $isObject );
		$this->processFields( $value, $isObject );
	}

	/**
	 * Check if value can be processed
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private function isProcessable( $value ): bool {
		return is_object( $value ) || is_array( $value );
	}

	/**
	 * Remove writeOnly properties from node
	 *
	 * @param object|array &$value
	 * @param bool $isObject
	 * @return void
	 */
	private function removeWriteOnlyProperties( &$value, bool $isObject ): void {
		if ( !$this->options['remove_writeonly'] ) {
			return;
		}

		foreach ( ['writeonly', 'writeOnly'] as $variant ) {
			$this->removePropertyIfWriteOnly( $value, $variant, $isObject );
		}
	}

	/**
	 * Remove a single property if it's writeOnly
	 *
	 * @param object|array &$value
	 * @param string $variant
	 * @param bool $isObject
	 * @return void
	 */
	private function removePropertyIfWriteOnly( &$value, string $variant, bool $isObject ): void {
		if ( $isObject ) {
			if ( property_exists( $value, $variant ) && $value->$variant === true ) {
				unset( $value->$variant );
			}
		} else {
			if ( isset( $value[$variant] ) && $value[$variant] === true ) {
				unset( $value[$variant] );
			}
		}
	}

	/**
	 * Process all configured fields in the node
	 *
	 * @param object|array &$value
	 * @param bool $isObject
	 * @return void
	 */
	private function processFields( &$value, bool $isObject ): void {
		foreach ( $this->options['wikitext_keys'] as $formatKey => $fieldKey ) {
			$this->processField( $value, $fieldKey, $formatKey, $isObject );
		}
	}

	/**
	 * Process a single field
	 *
	 * @param object|array &$value
	 * @param string $fieldKey
	 * @param string $formatKey
	 * @param bool $isObject
	 * @return void
	 */
	private function processField( &$value, string $fieldKey, string $formatKey, bool $isObject ): void {
		$fieldValue = $this->getFieldValue( $value, $fieldKey, $isObject );
		
		if ( $fieldValue === null ) {
			return;
		}

		$format = $this->getOrSetFormat( $value, $formatKey, $isObject );
		$processedValue = $this->applyFormat( $fieldValue, $format );

		$this->setFieldValue( $value, $fieldKey, $processedValue, $isObject );
	}

	/**
	 * Get field value from object or array
	 *
	 * @param object|array $value
	 * @param string $fieldKey
	 * @param bool $isObject
	 * @return mixed|null
	 */
	private function getFieldValue( $value, string $fieldKey, bool $isObject ) {
		if ( $isObject ) {
			return property_exists( $value, $fieldKey ) && !is_object( $value->$fieldKey )
				? $value->$fieldKey
				: null;
		}

		return isset( $value[$fieldKey] ) && !is_array( $value[$fieldKey] )
			? $value[$fieldKey]
			: null;
	}

	/**
	 * Set field value on object or array
	 *
	 * @param object|array &$value
	 * @param string $fieldKey
	 * @param mixed $processedValue
	 * @param bool $isObject
	 * @return void
	 */
	private function setFieldValue( &$value, string $fieldKey, $processedValue, bool $isObject ): void {
		if ( $isObject ) {
			$value->$fieldKey = $processedValue;
		} else {
			$value[$fieldKey] = $processedValue;
		}
	}

	/**
	 * Get or set format for a field
	 *
	 * @param object|array &$value
	 * @param string $formatKey
	 * @param bool $isObject
	 * @return string
	 */
	private function getOrSetFormat( &$value, string $formatKey, bool $isObject ): string {
		$defaultFormat = $this->options['default_format'];
		
		if ( $isObject ) {
			if ( property_exists( $value, $formatKey ) ) {
				return $value->$formatKey;
			}
			$value->$formatKey = $defaultFormat;
			return $defaultFormat;
		}

		if ( isset( $value[$formatKey] ) ) {
			return $value[$formatKey];
		}
		$value[$formatKey] = $defaultFormat;
		return $defaultFormat;
	}

	/**
	 * Apply format handler to field value
	 *
	 * @param mixed $fieldValue
	 * @param string $format
	 * @return mixed
	 */
	private function applyFormat( $fieldValue, string $format ) {
		$handler = $this->formatHandlers[$format] ?? $this->formatHandlers['text'];
		return $handler( $fieldValue );
	}

	/**
	 * Parse wikitext
	 *
	 * @param string $text
	 * @return string
	 */
	private function parseWikitext( string $text ): string {
		return \JsonForms::parseWikitext( $this->output, $text );
	}

	/**
	 * Escape HTML
	 *
	 * @param string $text
	 * @return string
	 */
	private function escapeHtml( string $text ): string {
		return htmlspecialchars( $text );
	}
}
