/* global JsonForms */

// use IIFE, this ensure name is scoped
( function () {
	function Survey( el, data ) {
	}

	Survey.prototype.onBeforeCreateItem = function ( uiSchemaValue, UISchema ) {
		return { key: uiSchemaValue.name, schema: UISchema, value: uiSchemaValue };
	};

	Survey.prototype.convertFrom = function ( key, value ) {
		return value;
	};

	Survey.prototype.convertTo = function ( key, value ) {
		value[ 'x-ui-name' ] = 'survey';
		return value;
	};

	// attach to constructor
	JsonForms.UISchemaConverters.Survey = Survey;
}() );
