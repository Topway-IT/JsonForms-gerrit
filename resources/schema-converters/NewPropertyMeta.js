/* global JsonForms */
/* eslint-disable es-x/no-rest-spread-properties */

// use IIFE, this ensure name is scoped
(function () {
	function NewPropertyMeta(el, data) {
		// JsonForms.UISchemaConverters.call(this);
	}

	// OO.inheritClass(NewPropertyMeta, JsonForms.UISchemaConverters);

	NewPropertyMeta.prototype.onBeforeCreateItem = function (
		uiSchemaValue,
		UISchema,
	) {
		const value = {};
		if (uiSchemaValue.type !== 'multiple') {
			value.type = uiSchemaValue.type;
		}
		return {
			key: uiSchemaValue.name,
			// schema: this.schemaFromType(uiSchemaValue.type),
			schema: {
				$ref: '#/definitions/schema',
			},
			value,
		};
	};

	NewPropertyMeta.prototype.convertFrom = function (key, value) {
		return value;
	};

	NewPropertyMeta.prototype.convertTo = function (key, value) {
		return value;
	};

	// attach to constructor
	JsonForms.UISchemaConverters.NewPropertyMeta = NewPropertyMeta;
})();

