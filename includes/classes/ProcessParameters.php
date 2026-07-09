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

namespace MediaWiki\Extension\JsonForms;

/**
 * Generic parameter processor
 */
class ProcessParameters {
	protected array $defaultParameters = [];

	protected array $flatDefaults = [];
	protected array $values = [];
	protected array $options = [];
	protected array $query = [];
	public array $initialKnown = [];

	/**
	 * @param array $argv
	 * @param stdClass|null $schema null
	 */
	public function __construct( array $argv = [], $schema = null ) {
		if ( $schema !== null ) {
			$this->defaultParameters = $this->buildDefaultParametersFromSchema( $schema );
		}
		$this->prepareDefaults();
		$this->parse( $argv );

		$this->initialKnown = array_keys( $this->options );
		$this->applyDefaults();
	}

	/**
	 * Build default parameters from schema object (stdClass only)
	 *
	 * @param stdClass $schema
	 */
	protected function buildDefaultParametersFromSchema( $schema ): array {
		$parameters = [];

		// Get required properties (array from object property)
		$required = property_exists( $schema, 'required' ) && is_array( $schema->required )
			? $schema->required
			: [];

		// Get properties object
		$properties = property_exists( $schema, 'properties' ) && is_object( $schema->properties )
			? $schema->properties
			: new \stdClass();

		foreach ( get_object_vars( $properties ) as $name => $definition ) {
			$parameters[$name] = [
				"label" => property_exists( $definition, 'title' ) ? $definition->title : $name,
				"description" => property_exists( $definition, 'description' ) ? $definition->description : "",
				"type" => property_exists( $definition, 'type' ) ? $definition->type : "string",
				"required" => in_array( $name, $required, true ),
				"default" => property_exists( $definition, 'default' ) ? $definition->default : null,
			];
		}

		return $parameters;
	}

	protected function prepareDefaults(): void {
		$this->flatDefaults = [];
		foreach ( $this->defaultParameters as $key => $def ) {
			$this->flatDefaults[$key] = [
				$def["default"] ?? null,
				$def["type"] ?? null,
			];
		}
	}

	/**
	 * @param array $argv
	 */
	protected function parse( array $argv ): void {
		$unnamed = [];
		$known = [];
		$unknown = [];
		$prevKey = null;

		foreach ( $argv as $key => $value ) {
			if ( strpos( $value, "+" ) === 0 ) {
				$argv[$prevKey] .= " |+" . urlencode( substr( $value, 1 ) );
				unset( $argv[$key] );
			} else {
				$prevKey = $key;
			}
		}

		foreach ( $argv as $value ) {
			if ( strpos( $value, "=" ) !== false ) {
				[ $k, $v ] = explode( "=", $value, 2 );
				$k = trim( $k );
				$k_ = str_replace( " ", "-", $k );
				$v = trim( $v );

				if (
					array_key_exists( $k, $this->flatDefaults ) ||
					array_key_exists( $k_, $this->flatDefaults )
				) {
					$known[$k_] = $v;
					$prevKey = $k_;
				} else {
					$unknown[$k] = $v;
					$prevKey = $k;
				}
			} else {
				$unnamed[] = $value;
			}
		}

		$this->values = $unnamed;
		$this->options = $known;
		$this->query = $unknown;
	}

	protected function applyDefaults(): void {
		foreach ( $this->flatDefaults as $key => [ $defaultValue, $type ] ) {
			if ( array_key_exists( $key, $this->options ) ) {
				$val = $this->options[$key];
			} else {
				$val = $defaultValue;
			}

			$this->options[$key] = $this->castValueByType(
				$type,
				$val,
				$defaultValue
			);
		}
	}

	/**
	 * @param string $type
	 * @param mixed $value
	 * @param mixed $default null
	 * @return mixed
	 */
	protected function castValueByType( ?string $type, $value, $default = null ) {
		if ( $value === null ) {
			return $default;
		}

		switch ( $type ) {
			case "int":
			case "integer":
				return filter_var( $value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE );

			case "float":
			case "number":
			case 'numeric':
				return filter_var( $value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE );

			case "bool":
			case "boolean":
				$ret = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

				if ( $ret === null ) {
					$ret = filter_var( $default, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				}

				return $ret ?? false;

			case "string":
				return (string)$value;

			case "array":
				return is_array( $value ) ? $value : $this->splitString( $value );

			case "array-chunks":
				return is_array( $value ) ? $value : str_split( (string)$value );

			case "array-string":
			case "array-int":
			case "array-integer":
			case "array-float":
			case "array-number":
			case "array-bool":
			case "array-boolean":
				// Convert to array if needed
				$values = is_array( $value )
					? $value
					: $this->splitString( (string)$value );

				$subType = explode( "-", $type )[1] ?? null;
				$result = [];
				foreach ( $values as $v ) {
					$result[] = $this->castValueByType( $subType, $v, $default );
				}
				return $result;

			default:
				return $value;
		}
	}

	/**
	 * @param string $str
	 * @return array
	 */
	protected function splitString( string $str ): array {
		return array_map( "trim", explode( ",", $str ) );
	}

	public function getValues(): array {
		return $this->values;
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function getQuery(): array {
		return $this->query;
	}
}
