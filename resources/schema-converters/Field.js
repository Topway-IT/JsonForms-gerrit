// use IIFE, this ensure name is scoped

/* global JsonForms */

( function () {
	function Field() {
	}

	Field.prototype.onBeforeCreateItem = function ( uiSchemaValue, UISchema ) {
		return { key: uiSchemaValue.name, schema: UISchema, value: uiSchemaValue };
	};

	// value is a schema
	Field.prototype.convertFrom = function ( key, value ) {
		return {
			type: value.properties.type
		};
	};

	Field.prototype.convertTo = function ( key, value ) {
		// console.log( 'convertTo', value, this.UISchema );
		if ( value.multiple ) {
			// ...
		}

		return {
			type: 'object',
			'x-ui-name': 'field',
			properties: {
				type: {
					name: value.name,
					type: value.type
				}
			}
		};
	};

	// attach to constructor
	JsonForms.UISchemaConverters.Field = Field;
}() );
