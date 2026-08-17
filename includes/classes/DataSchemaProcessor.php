<?php

namespace MediaWiki\Extension\JsonForms;

use stdClass;

class DataSchemaProcessor {

	/** @var User */
	protected $user;

	/** @var WikiPage */
	protected $wikiPage;

	/** @var array */
	public static $schemaKeys = [
		'title' => '',
		'description' => '',
		'type' => '',
		'required' => false,
		'format' => '',
		'x-format' => '',
		'default' => null,
		'enum' => [],
		'pattern' => '',
		'minimum' => null,
		'maximum' => null,
		'minLength' => null,
		'maxLength' => null,
		'readOnly' => false,
		'x-render-template' => '',
		'x-value-formula' => '',
		'x-hidden' => '',
		'x-runtime-only' => '',
	];				

	/**
	 * @param User $user
	 * @param WikiPage $wikiPage
	 */
	public function __construct( $user, $wikiPage ) {
		$this->user = $user;
		$this->wikiPage = $wikiPage;
	}

	/**
	 * Get normalized key for a schema
	 * 
	 * @param mixed $schema The schema to normalize
	 * @return string Normalized key for comparison
	 */
	public function getNormalizedKey( $schema ) {
		if ( $schema === null ) {
			return 'null';
		}
		
		if ( !is_array( $schema ) && !is_object( $schema ) ) {
			return (string)$schema;
		}
		
		$array = is_object( $schema ) ? get_object_vars( $schema ) : $schema;
					
		$considerKeys = [
			'title',
			'description',
			'type',
			'required',
			'format',
			'default',
			'enum',
			'pattern',
			'minimum',
			'maximum',
			'minLength',
			'maxLength',
			'readOnly',
			'x-render-template',
			'x-value-formula',
			'x-hidden',
			'x-runtime-only'		
		];
		
		if ( !empty( $considerKeys ) && is_array( $array ) ) {
			$array = array_intersect_key( $array, array_flip( $considerKeys ) );
		}
		
		if ( is_array( $array ) ) {
			ksort( $array );
		}
		
		return json_encode( $array );
	}

	/**
	 * Check if a value is a valid schema
	 * 
	 * @param mixed $value The value to check
	 * @return bool True if valid schema
	 */
	private function isValidSchema( $value ) {
		if ( !is_array( $value ) && !is_object( $value ) ) {
			return false;
		}
		
		$array = is_object( $value ) ? get_object_vars( $value ) : $value;
		return isset( $array['type'] ) || !empty( $array );
	}

	/**
	 * Extract schema definition from schema data (assumes object format)
	 * 
	 * @param stdClass $schemaData The schema data with 'definition' and 'paths'
	 * @return mixed The schema definition
	 */
	private static function extractSchemaDefinition( $schemaData ) {
		if ( isset( $schemaData->definition ) ) {
			return $schemaData->definition;
		}
		return $schemaData;
	}

	/**
	 * Extract paths from schema data (assumes object format)
	 * 
	 * @param stdClass $schemaData The schema data with 'definition' and 'paths'
	 * @return array The paths array
	 */
	private function extractSchemaPaths( $schemaData ) {
		if ( isset( $schemaData->paths ) ) {
			return $schemaData->paths;
		}
		return [];
	}

	/**
	 * @param stdClass $pathSchemaMap Object of path => schema
	 * @param stdClass $existingSchemas
	 * @return stdClass
	 */
	public function remapUniqueSchemas( $pathSchemaMap, $existingSchemas ) {
		$addedKeys = [];
		$allPaths = new stdClass();

		// Index existing schemas
		foreach ( $existingSchemas as $index => $schemaData ) {
			$definition = self::extractSchemaDefinition( $schemaData );
			$key = $this->getNormalizedKey( $definition );
			$addedKeys[$key] = $index;

			if ( !isset( $schemaData->paths ) ) {
				$schemaData->paths = [];
			}
		}

		// Process new schemas
		foreach ( $pathSchemaMap as $path => $schema ) {
			if ( !$this->isValidSchema( $schema ) ) {
				continue;
			}

			$key = $this->getNormalizedKey( $schema );

			if ( !isset( $addedKeys[$key] ) ) {
				// Add new schema as object
				$newSchema = new stdClass();
				$newSchema->definition = $schema;
				$newSchema->paths = [$path];
				$existingSchemas[] = $newSchema;
				$index = count( $existingSchemas ) - 1;
				$addedKeys[$key] = $index;
			} else {
				$index = $addedKeys[$key];
				if ( !in_array( $path, $existingSchemas[$index]->paths ) ) {
					$existingSchemas[$index]->paths[] = $path;
				}
			}

			$allPaths->$path = $index;
		}

		// Sort $allPaths by index
		$allPathsArray = (array)$allPaths;
		asort( $allPathsArray );
		$allPaths = (object)$allPathsArray;

		return [ $existingSchemas, $allPaths ];
	}

	/**
	 * Get a map of path to schema
	 * 
	 * @param stdClass $schemaMap The schema map object (path → index)
	 * @param array $processedSchemas Array of processed schema entries (objects)
	 * @return stdClass Path → schema object mapping
	 */
	public static function getPathToSchemaMap( $schemaMap, $processedSchemas ) {
		$result = new stdClass();
		
		if ( !is_object( $schemaMap ) ) {
			return $result;
		}
		
		foreach ( $schemaMap as $path => $index ) {
			if ( isset( $processedSchemas[$index] ) ) {
				$definition = self::extractSchemaDefinition( $processedSchemas[$index] );
				$result->$path = $definition;
			}
		}
		
		return $result;
	}

	/**
	 * Find unused schemas (schemas with no paths)
	 * 
	 * @param array $processedSchemas Array of processed schema entries (objects)
	 * @return array Array of indices of unused schemas
	 */
	public function findUnusedSchemas( $processedSchemas ) {
		$unused = [];
		
		foreach ( $processedSchemas as $index => $schemaData ) {
			$paths = $this->extractSchemaPaths( $schemaData );
			if ( empty( $paths ) ) {
				$unused[] = $index;
			}
		}
		return $unused;
	}

	/**
	 * Get schema usage statistics
	 * 
	 * @param object|array $processedSchemas Array of processed schema entries (objects)
	 * @return array Array of usage statistics
	 */
	public function getSchemaUsageStats( $processedSchemas ) {
		$stats = [];
		
		foreach ( $processedSchemas as $index => $schemaData ) {
			$definition = self::extractSchemaDefinition( $schemaData );
			$paths = $this->extractSchemaPaths( $schemaData );
			
			foreach ( $paths as $path ) {
				// Group by path
				if ( !isset( $stats[$path] ) ) {
					$stats[$path] = [
						'path' => $path,
						'schema_indices' => []
					];
				}
				
				// Check if this schema index already exists
				$found = false;
				foreach ( $stats[$path]['schema_indices'] as &$item ) {
					if ( $item['index'] === $index ) {
						$item['count']++;
						$found = true;
						break;
					}
				}
				
				if ( !$found ) {
					$stats[$path]['schema_indices'][] = [
						'index' => $index,
						'count' => 1
					];
				}
			}
		}
		
		return $stats;
	}

	/**
	 * Save metadata to wiki page
	 * 
	 * @param stdClass $metadata The metadata to save
	 * @param array &$errors
	 * @return true|array True on success, error array on failure
	 */
	public function saveMetadata( $metadata, &$errors ) {
		$slots = [
			SLOT_ROLE_JSONFORMS_METADATA => [
				'content' => json_encode( $metadata ),
				'model' => 'json'
			]
		];

		$slotEditor = new SlotEditor();

		$summary = '';
		$minor = false;
		$append = false;
		$watchlist = "";
		$prepend = false;
		$bot = false;
		$createonly = false;
		$nocreate = false;
		$suppress = false;

		$updateStrategy = 'merge';

		return $slotEditor->editSlots(
			$this->user,
			$this->wikiPage,
			$slots,
			$summary,
			$append,
			$watchlist,
			$prepend,
			$bot,
			$minor,
			$createonly,
			$nocreate,
			$suppress,
			$updateStrategy,
			$errors
		);
	}

	/**
	 * Get a schema by its definition
	 * 
	 * @param array $processedSchemas Array of processed schema entries (objects)
	 * @param mixed $schemaDefinition The schema definition to find
	 * @return int|null Index of the schema, or null if not found
	 */
	public function findSchemaIndex( $processedSchemas, $schemaDefinition ) {
		$key = $this->getNormalizedKey( $schemaDefinition );
		
		foreach ( $processedSchemas as $index => $schemaData ) {
			$definition = self::extractSchemaDefinition( $schemaData );
			
			if ( $this->getNormalizedKey( $definition ) === $key ) {
				return $index;
			}
		}
		
		return null;
	}

	/**
	 * Validate that all schemaMap paths exist in processedSchema
	 * 
	 * @param stdClass $schemaMap Schema map object
	 * @param array $processedSchemas Array of processed schema entries (objects)
	 * @return array Array of validation errors
	 */
	public function validateSchemaMap( $schemaMap, $processedSchemas ) {
		$errors = [];

		if ( !is_object( $schemaMap ) ) {
			$errors[] = 'schemaMap is not an object';
			return $errors;
		}
		
		foreach ( $schemaMap as $path => $index ) {
			// Check if index exists
			if ( !isset( $processedSchemas[$index] ) ) {
				$errors[] = "Path '{$path}' references missing index {$index}";
				continue;
			}

			// Check if path is recorded in the schema's paths
			$schemaData = $processedSchemas[$index];
			$paths = $schemaData->paths ?? [];
			
			if ( !in_array( $path, $paths ) ) {
				$errors[] = "Path '{$path}' is not recorded in schema at index {$index}";
			}
		}
		
		return $errors;
	}
}

