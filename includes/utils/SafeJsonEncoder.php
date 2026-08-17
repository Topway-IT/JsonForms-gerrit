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
 * @copyright Copyright ©2025, https://wikisphere.org
 */

// *** this has been created by ChatGPT after a few brainstorming, with few edits

namespace MediaWiki\Extension\JsonForms\Utils;

class SafeJsonEncoder {
	/** @var int */
	protected $options;

	/** @var int */
	protected $depth;

	/** @var callable */
	protected $showMsg;

	/**
	 * @param callable $showMsg
	 * @param int $options 0
	 * @param int $depth 512
	 */
	public function __construct( callable $showMsg, int $options = 0, int $depth = 512 ) {
		$this->showMsg = $showMsg;
		$this->options = $options;
		$this->depth = $depth;
	}

	/**
	 * @param array $data
	 * @return array
	 * @throws JsonException
	 */
	public function encode( $data ): string {
		$json = json_encode( $data, $this->options, $this->depth );

		if ( $json === false ) {
			$errMsg = json_last_error_msg();

			switch ( json_last_error() ) {
				case JSON_ERROR_NONE:
					return $json;

				case JSON_ERROR_DEPTH:
					throw new Exception( $errMsg );

				case JSON_ERROR_STATE_MISMATCH:
					throw new Exception( $errMsg );

				case JSON_ERROR_CTRL_CHAR:
					( $this->showMsg )( '***fixing error ' . $errMsg );
					$data = $this->removeControlChars( $data );
					return $this->encode( $data );

				case JSON_ERROR_SYNTAX:
					throw new Exception( $errMsg );

				case JSON_ERROR_UTF8:
					( $this->showMsg )( '***fixing error ' . $errMsg );
					$data = $this->utf8ize( $data );
					return $this->encode( $data );

				case JSON_ERROR_RECURSION:
					( $this->showMsg )( '***fixing error ' . $errMsg );
					$data = $this->breakRecursion( $data );
					return $this->encode( $data );

				case JSON_ERROR_INF_OR_NAN:
					( $this->showMsg )( '***fixing error ' . $errMsg );
					$data = $this->fixInfNan( $data );
					return $this->encode( $data );

				case JSON_ERROR_UNSUPPORTED_TYPE:
					( $this->showMsg )( '***fixing error ' . $errMsg );
					$data = $this->removeUnsupportedTypes( $data );
					return $this->encode( $data );

				case JSON_ERROR_INVALID_PROPERTY_NAME:
					throw new Exception( $errMsg );

				case JSON_ERROR_UTF16:
					( $this->showMsg )( '***fixing error ' . $errMsg );
					$data = $this->utf8ize( $data );
					return $this->encode( $data );

				default:
					throw new Exception( $errMsg );
			}
		}

		return $json;
	}

	/**
	 * @param array|string $mixed
	 * @return array
	 */
	protected function utf8ize( $mixed ) {
		if ( is_array( $mixed ) ) {
			foreach ( $mixed as $key => $value ) {
				unset( $mixed[$key] );
				$mixed[$this->utf8ize( $key )] = $this->utf8ize( $value );
			}
		} elseif ( is_object( $mixed ) ) {
			foreach ( $mixed as $key => $value ) {
				$mixed->$key = $this->utf8ize( $value );
			}
		} elseif ( is_string( $mixed ) ) {
			return mb_convert_encoding( $mixed, 'UTF-8', 'UTF-8' );
		}
		return $mixed;
	}

	/**
	 * @param array $data
	 * @return array
	 */
	protected function removeControlChars( $data ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[$key] = $this->removeControlChars( $value );
			}
		} elseif ( is_object( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data->$key = $this->removeControlChars( $value );
			}
		} elseif ( is_string( $data ) ) {
			// Remove ASCII control chars except newline(0x0A), tab(0x09), carriage return(0x0D)
			return preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $data );
		}
		return $data;
	}

	/**
	 * @param array $data
	 * @return array|null
	 */
	protected function fixInfNan( $data ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[$key] = $this->fixInfNan( $value );
			}
		} elseif ( is_object( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data->$key = $this->fixInfNan( $value );
			}
		} elseif ( is_float( $data ) ) {
			if ( is_infinite( $data ) || is_nan( $data ) ) {
				return null;
			}
		}
		return $data;
	}

	/**
	 * @param array $data
	 * @return array|null
	 */
	protected function removeUnsupportedTypes( $data ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[$key] = $this->removeUnsupportedTypes( $value );
			}
		} elseif ( is_object( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data->$key = $this->removeUnsupportedTypes( $value );
			}
		// phpcs:ignore  MediaWiki.Usage.ForbiddenFunctions.is_resource
		} elseif ( is_resource( $data ) || $data instanceof Closure ) {
			return null;
		}
		return $data;
	}

	/**
	 * @param array $data
	 * @param array &$seen []
	 * @return array|null
	 */
	protected function breakRecursion( $data, &$seen = [] ) {
		if ( is_object( $data ) || is_array( $data ) ) {
			foreach ( $seen as $seenItem ) {
				if ( $seenItem === $data ) {
					return null;
				}
			}
			$seen[] = $data;

			if ( is_array( $data ) ) {
				foreach ( $data as $key => $value ) {
					$data[$key] = $this->breakRecursion( $value, $seen );
				}
			} else {
				foreach ( $data as $key => $value ) {
					$data->$key = $this->breakRecursion( $value, $seen );
				}
			}
		}
		return $data;
	}
}
