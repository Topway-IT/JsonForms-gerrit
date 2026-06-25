/* global JsonForms */

// use IIFE, this ensure name is scoped
( function () {
	function NewSlot( el, data ) {
	}

	NewSlot.prototype.onBeforeCreateItem = function ( uiSchemaValue, UISchema, targetSchema ) {
		return { key: uiSchemaValue.name, schema: targetSchema, value: {
			role: uiSchemaValue.name
		} };
	};

	// attach to constructor
	JsonForms.UISchemaConverters.NewSlot = NewSlot;
}() );
