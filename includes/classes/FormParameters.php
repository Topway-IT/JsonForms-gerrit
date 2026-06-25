<?php

namespace MediaWiki\Extension\JsonForms;

/**
 * QueryLink-specific parameter processor
 */
class FormParameters extends ProcessParameters {
	protected array $defaultParameters = [];

	public function __construct( array $argv = [], \stdClass $schema = new \stdClass() ) {
		$this->defaultParameters = $this->buildDefaultParametersFromSchema( $schema );

		parent::__construct( $argv );
	}
}
