<?php

namespace MediaWiki\Extension\JsonForms;

use stdClass;

class DataSchemaProcessor {

	/** @var User */
	protected $user;

	/** @var WikiPage */
	protected $wikiPage;

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
	private function extractSchemaDefinition( $schemaData ) {
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
	 * Build a map of existing schemas by normalized key
	 * 
	 * @param array $processedSchemas Array of processed schema entries (objects)
	 * @return array Map of normalized key => schema data
	 */
	private function buildProcessedSchemaMap( $processedSchemas ) {
		$map = [];
		
		foreach ( $processedSchemas as $index => $schemaData ) {
			$definition = $this->extractSchemaDefinition( $schemaData );
			$paths = $this->extractSchemaPaths( $schemaData );
			$key = $this->getNormalizedKey( $definition );
			
			$map[$key] = [
				'index' => $index,
				'definition' => $definition,
				'paths' => $paths
			];
		}
		
		return $map;
	}

	/**
	 * Create a schema object
	 * 
	 * @param mixed $definition The schema definition
	 * @param array $paths Array of paths
	 * @return stdClass Schema object
	 */
	private function createSchemaObject( $definition, $paths = [] ) {
		return (object)[
			'definition' => $definition,
			'paths' => $paths
		];
	}

	/**
	 * Find existing schema by definition key
	 * 
	 * @param string $key The normalized key to find
	 * @param array $existingSchemas Array of existing schemas
	 * @param array $newProcessedSchemas Reference to new processed schemas array
	 * @return int|null Index of existing schema, or null if not found
	 */
	private function findExistingSchemaByKey( $key, $existingSchemas, &$newProcessedSchemas ) {
		foreach ( $existingSchemas as $schemaData ) {
			$existingDef = $this->extractSchemaDefinition( $schemaData );
			if ( $this->getNormalizedKey( $existingDef ) === $key ) {
				$existingPaths = $this->extractSchemaPaths( $schemaData );
				$newProcessedSchemas[] = $this->createSchemaObject( $existingDef, $existingPaths );
				return count( $newProcessedSchemas ) - 1;
			}
		}
		return null;
	}

	/**
	 * Add or get schema index by definition
	 * 
	 * @param mixed $schema The schema definition
	 * @param array $existingSchemas Array of existing schemas
	 * @param array $addedKeys Reference to added keys map
	 * @param array $newProcessedSchemas Reference to new processed schemas array
	 * @return int Schema index
	 */
	private function getOrCreateSchemaIndex( $schema, $existingSchemas, &$addedKeys, &$newProcessedSchemas ) {
		$key = $this->getNormalizedKey( $schema );
		
		if ( !isset( $addedKeys[$key] ) ) {
			$existingIndex = $this->findExistingSchemaByKey( $key, $existingSchemas, $newProcessedSchemas );
			
			if ( $existingIndex !== null ) {
				$addedKeys[$key] = $existingIndex;
			} else {
				$newProcessedSchemas[] = $this->createSchemaObject( $schema );
				$addedKeys[$key] = count( $newProcessedSchemas ) - 1;
			}
		}
		
		return $addedKeys[$key];
	}

	/**
	 * Add a path to a schema
	 * 
	 * @param int $index Schema index
	 * @param string $path Path to add
	 * @param array $newProcessedSchemas Reference to new processed schemas array
	 */
	private function addPathToSchemaByIndex( $index, $path, &$newProcessedSchemas ) {
		if ( !in_array( $path, $newProcessedSchemas[$index]->paths ) ) {
			$newProcessedSchemas[$index]->paths[] = $path;
		}
	}

	/**
	 * Map path to index
	 * 
	 * @param string $path The path
	 * @param int $index The schema index
	 * @param array $allPaths Reference to all paths map
	 * @param array $remapped Reference to remapped paths map
	 */
	private function mapPathToIndex( $path, $index, &$allPaths, &$remapped ) {
		$allPaths[$path] = $index;
		$remapped[$path] = $index;
	}

	/**
	 * Re-index schemas and update path mappings
	 * 
	 * @param array $newProcessedSchemas Array of schemas to re-index
	 * @param array $remapped Path mappings to update
	 * @param array $allPaths All path mappings to update
	 * @return array [finalProcessedSchemas, finalRemapped, finalAllPaths]
	 */
	private function reindexAndRemap( $newProcessedSchemas, $remapped, $allPaths ) {
		$finalProcessedSchemas = [];
		$finalRemapped = [];
		$finalAllPaths = [];
		$indexMap = [];

		// Re-index schemas
		foreach ( $newProcessedSchemas as $oldIndex => $schemaData ) {
			if ( !empty( $schemaData->paths ) ) {
				$newIndex = count( $finalProcessedSchemas );
				$finalProcessedSchemas[] = $this->createSchemaObject( 
					$schemaData->definition, 
					$schemaData->paths 
				);
				$indexMap[$oldIndex] = $newIndex;
			}
		}

		// Update path mappings
		foreach ( $remapped as $path => $oldIndex ) {
			if ( isset( $indexMap[$oldIndex] ) ) {
				$finalRemapped[$path] = $indexMap[$oldIndex];
			}
		}
		
		foreach ( $allPaths as $path => $oldIndex ) {
			if ( isset( $indexMap[$oldIndex] ) ) {
				$finalAllPaths[$path] = $indexMap[$oldIndex];
			}
		}

		return [ $finalProcessedSchemas, $finalRemapped, $finalAllPaths ];
	}

	/**
	 * Remap unique schemas based on processedSchema with path tracking
	 * 
	 * @param stdClass $pathSchemaMap Object of path => schema
	 * @param array $existingSchemas Array of existing processed schema entries (objects)
	 * @return array [updatedProcessedSchemas, remappedIndices, allPaths]
	 */
	public function remapUniqueSchemas( $pathSchemaMap, $existingSchemas ) {
		$pathSchemaMap = (array)$pathSchemaMap;
		$existingSchemas = json_decode( json_encode( $existingSchemas ), true );
		
		$addedKeys = [];
		$newProcessedSchemas = [];
		$allPaths = [];
		$remapped = [];

		// First, process ALL schemas from $pathSchemaMap (NEW data)
		foreach ( $pathSchemaMap as $path => $schema ) {
			if ( !$this->isValidSchema( $schema ) ) {
				continue;
			}
			
			$index = $this->getOrCreateSchemaIndex( 
				$schema, 
				$existingSchemas, 
				$addedKeys, 
				$newProcessedSchemas 
			);
			
			$this->addPathToSchemaByIndex( $index, $path, $newProcessedSchemas );
			$this->mapPathToIndex( $path, $index, $allPaths, $remapped );
		}

		// Add any existing schemas that were NOT referenced in $pathSchemaMap
		foreach ( $existingSchemas as $schemaData ) {
			$definition = $this->extractSchemaDefinition( $schemaData );
			$paths = $this->extractSchemaPaths( $schemaData );
			
			if ( empty( $paths ) ) {
				continue;
			}
			
			$key = $this->getNormalizedKey( $definition );
			if ( !isset( $addedKeys[$key] ) ) {
				$newProcessedSchemas[] = $this->createSchemaObject( $definition, $paths );
				$addedKeys[$key] = count( $newProcessedSchemas ) - 1;
			}
		}

		// Re-index and remap paths
		return $this->reindexAndRemap( $newProcessedSchemas, $remapped, $allPaths );
	}

	/**
	 * Get a map of path to schema
	 * 
	 * @param stdClass $schemaMap The schema map object (path → index)
	 * @param array $processedSchemas Array of processed schema entries (objects)
	 * @return stdClass Path → schema object mapping
	 */
	public function getPathToSchemaMap( $schemaMap, $processedSchemas ) {
		$result = new stdClass();
		
		if ( !is_object( $schemaMap ) ) {
			return $result;
		}
		
		foreach ( $schemaMap as $path => $index ) {
			if ( isset( $processedSchemas[$index] ) ) {
				$definition = $this->extractSchemaDefinition( $processedSchemas[$index] );
				$result->$path = $definition;
			}
		}
		
		return $result;
	}

	/**
	 * Get all paths using a specific schema
	 * 
	 * @param array $processedSchemas Array of processed schema entries (objects)
	 * @param int $schemaIndex The index of the schema
	 * @return array Array of paths using this schema
	 */
	public function getPathsForSchema( $processedSchemas, $schemaIndex ) {
		if ( isset( $processedSchemas[$schemaIndex] ) ) {
			return $this->extractSchemaPaths( $processedSchemas[$schemaIndex] );
		}
		return [];
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
	 * Remove a path from all schemas
	 * 
	 * @param array $processedSchemas Array of processed schema entries (objects)
	 * @param string $pathToRemove The path to remove
	 * @return array Updated processedSchemas
	 */
	public function removePathFromSchemas( $processedSchemas, $pathToRemove ) {
		$newProcessedSchemas = [];
		
		foreach ( $processedSchemas as $schemaData ) {
			$definition = $this->extractSchemaDefinition( $schemaData );
			$paths = $this->extractSchemaPaths( $schemaData );
			
			if ( !empty( $paths ) ) {
				$paths = array_values( array_filter(
					$paths,
					function( $p ) use ( $pathToRemove ) {
						return $p !== $pathToRemove;
					}
				));
				
				if ( !empty( $paths ) ) {
					$newProcessedSchemas[] = $this->createSchemaObject( $definition, $paths );
				}
			}
		}
		
		return $newProcessedSchemas;
	}

	/**
	 * Get schema usage statistics
	 * 
	 * @param array $processedSchemas Array of processed schema entries (objects)
	 * @return array Array of usage statistics
	 */
	public function getSchemaUsageStats( $processedSchemas ) {
		$stats = [];
		
		foreach ( $processedSchemas as $index => $schemaData ) {
			$definition = $this->extractSchemaDefinition( $schemaData );
			$paths = $this->extractSchemaPaths( $schemaData );
			$stats[] = [
				'index' => $index,
				'schema' => $definition,
				'path_count' => count( $paths ),
				'paths' => $paths
			];
		}
		return $stats;
	}

	/**
	 * Save metadata to wiki page
	 * 
	 * @param stdClass $metadata The metadata to save
	 * @return true|array True on success, error array on failure
	 */
	public function saveMetadata( $metadata ) {
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

		$ret = $slotEditor->editSlots(
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
		);

		if ( $ret !== true ) {
			$errors = $ret;
			return $errors;
		}

		return true;
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
			$definition = $this->extractSchemaDefinition( $schemaData );
			
			if ( $this->getNormalizedKey( $definition ) === $key ) {
				return $index;
			}
		}
		
		return null;
	}

	/**
	 * Add a new path to an existing schema
	 * 
	 * @param array $processedSchemas Array of processed schema entries (objects)
	 * @param int $schemaIndex Index of the schema
	 * @param string $path Path to add
	 * @return array Updated processedSchemas
	 */
	public function addPathToSchema( $processedSchemas, $schemaIndex, $path ) {
		if ( isset( $processedSchemas[$schemaIndex] ) ) {
			$definition = $this->extractSchemaDefinition( $processedSchemas[$schemaIndex] );
			$paths = $this->extractSchemaPaths( $processedSchemas[$schemaIndex] );
			
			if ( !in_array( $path, $paths ) ) {
				$paths[] = $path;
			}
			
			$processedSchemas[$schemaIndex] = $this->createSchemaObject( $definition, $paths );
		}
		
		return $processedSchemas;
	}

	/**
	 * Remove a path from a specific schema
	 * 
	 * @param array $processedSchemas Array of processed schema entries (objects)
	 * @param int $schemaIndex Index of the schema
	 * @param string $path Path to remove
	 * @return array Updated processedSchemas
	 */
	public function removePathFromSchema( $processedSchemas, $schemaIndex, $path ) {
		if ( isset( $processedSchemas[$schemaIndex] ) ) {
			$definition = $this->extractSchemaDefinition( $processedSchemas[$schemaIndex] );
			$paths = $this->extractSchemaPaths( $processedSchemas[$schemaIndex] );
			
			$paths = array_values( array_filter(
				$paths,
				function( $p ) use ( $path ) {
					return $p !== $path;
				}
			));
			
			$processedSchemas[$schemaIndex] = $this->createSchemaObject( $definition, $paths );
		}
		
		return $processedSchemas;
	}

	/**
	 * Get all schemas that share a specific path pattern
	 * 
	 * @param array $processedSchemas Array of processed schema entries (objects)
	 * @param string $pathPattern Pattern to match (can use wildcards like 'user.*')
	 * @return array Array of schema indices matching the pattern
	 */
	public function findSchemasByPathPattern( $processedSchemas, $pathPattern ) {
		$matchingIndices = [];
		$pattern = str_replace( '*', '.*', $pathPattern );
		$pattern = '/^' . str_replace( '/', '\/', $pattern ) . '$/';
		
		foreach ( $processedSchemas as $index => $schemaData ) {
			$paths = $this->extractSchemaPaths( $schemaData );
			foreach ( $paths as $path ) {
				if ( preg_match( $pattern, $path ) ) {
					$matchingIndices[] = $index;
					break;
				}
			}
		}
		
		return array_unique( $matchingIndices );
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
			$paths = isset( $schemaData->paths ) ? $schemaData->paths : [];
			
			if ( !in_array( $path, $paths ) ) {
				$errors[] = "Path '{$path}' is not recorded in schema at index {$index}";
			}
		}
		
		return $errors;
	}
}
