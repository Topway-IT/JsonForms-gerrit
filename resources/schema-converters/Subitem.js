/* global JsonForms */

// use IIFE, this ensure name is scoped
( function () {
	function Subitem( el, data ) {
	}

	Subitem.prototype.onBeforeCreateItem = function ( uiSchemaValue, UISchema ) {
		return { key: uiSchemaValue.name, schema: UISchema, value: uiSchemaValue };
	};

	Subitem.prototype.convertFrom = function ( key, value ) {
		return value;
	};

	Subitem.prototype.convertTo = function ( key, value ) {
		value[ 'x-ui-name' ] = 'subitem';
		return value;
	};

	// attach to constructor
	JsonForms.UISchemaConverters.Subitem = Subitem;
}() );
