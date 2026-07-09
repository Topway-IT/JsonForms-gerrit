<?php

namespace MediaWiki\Extension\JsonForms;

use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;

/**
 * QueryLink-specific parameter processor
 */
class QueryLinkParameters extends ProcessParameters {
	protected array $defaultParameters = [
		'class-attr-name' => [
			'label' => 'jsonforms-parserfunction-querylink-class-attr-name-label',
			'description' => 'jsonforms-parserfunction-querylink-class-attr-name-description',
			'type' => 'string',
			'required' => false,
			'default' => 'class',
			'example' => 'jsonforms-parserfunction-querylink-class-attr-name-example'
		],
		'target-attr-name' => [
			'label' => 'jsonforms-parserfunction-querylink-target-attr-name-label',
			'description' => 'jsonforms-parserfunction-querylink-target-attr-name-description',
			'type' => 'string',
			'required' => false,
			'default' => 'target',
			'example' => 'jsonforms-parserfunction-querylink-target-attr-name-example'
		],
	];

	protected ?object $titleObject = null;
	protected string $text = '';
	protected array $attributes = [];
	protected bool $isButtonLink = false;

	public function __construct( array $argv = [], bool $isButtonLink = false ) {
		$this->isButtonLink = $isButtonLink;
		parent::__construct( $argv );
		$this->assignKnownAttributes();
		$this->resolveLinkInfo();
		$this->applyExtraAttributes();
	}

	protected function assignKnownAttributes(): void {
		foreach ( [ 'class-attr-name', 'target-attr-name' ] as $key ) {
			if ( isset( $this->query[$this->options[$key]] ) ) {
				$this->options[$this->options[$key]] = $this->query[$this->options[$key]];
				unset( $this->query[$this->options[$key]] );
			}
		}
	}

	protected function resolveLinkInfo(): void {
		if ( empty( $this->values ) ) {
			return;
		}

		$this->titleObject = TitleClass::newFromText( $this->values[0] );

		if ( !empty( $this->values[1] ) ) {
			$this->text = $this->values[1];
		} elseif ( $this->titleObject ) {
			$this->text = $this->titleObject->getText();
		} else {
			$this->text = $this->values[0];
		}

		$this->attributes = [];
		$classAttrKey = $this->options['class-attr-name'] ?? null;
		if ( $classAttrKey && !empty( $this->options[$classAttrKey] ) ) {
			$this->attributes['class'] = $this->options[$classAttrKey];
		}

		$targetAttrKey = $this->options['target-attr-name'] ?? null;
		if ( $targetAttrKey && !empty( $this->options[$targetAttrKey] ) ) {
			$this->attributes['target'] = $this->options[$targetAttrKey];
		}
	}

	protected function applyExtraAttributes(): void {
		if ( $this->isButtonLink && empty( $this->attributes['class'] ) ) {
			$this->attributes['class'] = 'mw-ui-button mw-ui-progressive mw-ui-small';
		}

		if ( !$this->isButtonLink && !empty( $this->attributes['target'] ) && $this->attributes['target'] === '_blank' ) {
			$existing = $this->attributes['class'] ?? '';
			$this->attributes['class'] = trim( $existing . ' external text' );
		}
	}

	public function getTitle(): ?object {
		return $this->titleObject;
	}

	public function getText(): string {
		return $this->text;
	}

	public function getAttributes(): array {
		return $this->attributes;
	}
}
