// use IIFE, this ensure name is scoped

/* global JsonForms */
( function () {
	function UISchemaConverters() {
		// key is lower case
		this.converters = {
			newproperty: new JsonForms.UISchemaConverters.NewProperty(),
			newpropertymeta: new JsonForms.UISchemaConverters.NewPropertyMeta(),
			survey: new JsonForms.UISchemaConverters.Survey(),
			field: new JsonForms.UISchemaConverters.Field(),
			subitem: new JsonForms.UISchemaConverters.Subitem(),
			geolocation: new JsonForms.UISchemaConverters.Geolocation(),
			newslot: new JsonForms.UISchemaConverters.NewSlot(),
			newschema: new JsonForms.UISchemaConverters.newSchema()
		};
	}

	JsonForms.UISchemaConverters = UISchemaConverters;
}() );
