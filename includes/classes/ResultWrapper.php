<?php

namespace MediaWiki\Extension\JsonForms;

class ResultWrapper {
	public bool $ok;
	public mixed $value;
	public ?string $error;

	/**
	 * @param bool $ok
	 * @param mixed $value null
	 * @param string|null $error null
	 */
	private function __construct( bool $ok, $value = null, ?string $error = null ) {
		$this->ok = $ok;
		$this->value = $value;
		$this->error = $error;
	}

	/**
	 * @param mixed $value
	 * @return ResultWrapper
	 */
	public static function success( $value ): self {
		return new self( true, $value );
	}

	/**
	 * @param string $error
	 * @return ResultWrapper
	 */
	public static function failure( string $error ): self {
		return new self( false, null, $error );
	}

	/**
	 * @param callable $fn
	 * @return ResultWrapper
	 */
	public function andThen( callable $fn ) {
		if ( !$this->ok ) {
			return $this;
		}

		return $fn( $this->value );
	}

}
