/* global JsonForms */

// use IIFE, this ensure name is scoped
( function () {
	function newSchema( el, data ) {}

	newSchema.prototype.onBeforeCreateItem = function (
		uiSchemaValue,
		UISchema,
		targetSchema
	) {
		return {
			key: 'selectedSchema',
			schema: targetSchema,
			value: {
				schemaName: uiSchemaValue.schema
			}
		};
	};

	/*
	newSchema.prototype.onBeforeCreateItem_ = function (
		uiSchemaValue,
		UISchema,
		targetSchema,
	) {
		return {
			key: 'schema',
			schema:
				// `JsonSchema:${uiSchemaValue.schema}`
				// use describedBy editor
				{
					'x-collapsible': true,
					'x-collapsible-config': {
						collapsed: false,
					},
					links: [
						{
							rel: 'describedBy',
							href: `JsonSchema:${uiSchemaValue.schema}`,
						},
					],
				},

			value: {},
		};
	};
*/

	// attach to constructor
	JsonForms.UISchemaConverters.newSchema = newSchema;
}() );
