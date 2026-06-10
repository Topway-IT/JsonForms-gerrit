/* global JsonForms */
/* eslint-disable es-x/no-rest-spread-properties */

// use IIFE, this ensure name is scoped
( function () {
	function NewProperty( el, data ) {
		// JsonForms.UISchemaConverters.call(this);
	}

	// OO.inheritClass(NewProperty, JsonForms.UISchemaConverters);

	NewProperty.prototype.onBeforeCreateItem = function (
		uiSchemaValue,
		UISchema
	) {
		return {
			key: uiSchemaValue.name,
			schema: this.schemaFromPseudoType(
				uiSchemaValue.type,
				uiSchemaValue.multiple
			),
			value: uiSchemaValue
		};
	};

	NewProperty.prototype.convertFrom = function ( key, value ) {
		return value;
	};

	NewProperty.prototype.convertTo = function ( key, value ) {
		return value;
	};

	NewProperty.prototype.schemaFromPseudoType = function (
		type,
		multiple,
		options
	) {
		if ( multiple ) {
			let inputName = null;
			switch ( type ) {
				case 'text':
					inputName = 'tagmultiselect';
					break;
			}

			const thisOptions = { 'x-input': inputName };
			return {
				type: 'array',
				items: this.schemaFromPseudoType( type, false, thisOptions )
			};
		}

		switch ( type ) {
			case 'time':
			case 'email':
			case 'date':
				return { type: 'string', format: type, ...options };

			case 'text':
			case 'textarea':
			case 'tel':
			case 'url':
			case 'color':
			case 'datetime-local':
			case 'json':
			case 'range':
				return { type: 'string', 'x-format': type, ...options };

			case 'number':
			case 'integer':
			case 'boolean':
				return { type };

			case 'object':
			case 'subitem':
				return { type: 'object', additionalProperties: true };

			default:
				throw new Error( `Unsupported type: ${ type }` );
		}
	};

	// attach to constructor
	JsonForms.UISchemaConverters.NewProperty = NewProperty;
}() );
