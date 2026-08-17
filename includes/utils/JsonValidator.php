<?php

namespace MediaWiki\Extension\JsonForms\Utils;

class JsonValidator {
	private $data;
	private $error;
	private $errorCode;
	
	public function validate( $jsonString ) {
		$this->data = json_decode( $jsonString );
		$this->errorCode = json_last_error();
		
		if ( $this->errorCode === JSON_ERROR_NONE ) {
			return true;
		}
		
		$this->error = $this->getErrorMessage( $this->errorCode );
		return false;
	}
	
	public function getData() {
		return $this->data;
	}
	
	public function getError() {
		return $this->error;
	}
	
	public function getErrorCode() {
		return $this->errorCode;
	}
	
	private function getErrorMessage( $code ) {
		$messages = [
			JSON_ERROR_NONE => 'No error',
			JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
			JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
			JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
			JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
			JSON_ERROR_UTF8 => 'Malformed UTF-8 characters',
			JSON_ERROR_RECURSION => 'Recursive references detected',
			JSON_ERROR_INF_OR_NAN => 'Inf or NaN detected',
			JSON_ERROR_UNSUPPORTED_TYPE => 'Unsupported type'
		];
		
		return $messages[ $code ] ?? 'Unknown error';
	}
}
