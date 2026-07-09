// use IIFE, this ensure name is scoped

/* global JsonForms */

( function () {
	function Geolocation() {
	}

	const GeolocationSchema = {
		$schema: 'https://json-schema.org/draft/2020-12/schema',
		title: 'Geolocation',
		description:
			'A geographic location with latitude and longitude coordinates',
		type: 'object',
		properties: {
			latitude: {
				title: 'Latitude',
				description: 'North-south position (-90 to 90)',
				type: 'number',
				minimum: -90,
				maximum: 90,
				'x-input-config': {}
			},
			longitude: {
				title: 'Longitude',
				description: 'East-west position (-180 to 180)',
				type: 'number',
				minimum: -180,
				maximum: 180,
				'x-input-config': {}
			}
		},
		required: [ 'latitude', 'longitude' ]
	};

	// convertDataToUISchema

	// convertUISchemaDataToRegularSchema

	// onUISave
	// onSchemaLoad

	// fromSourceSchemaToUISchemaData

	Geolocation.prototype.onBeforeCreateItem = function (
		uiSchemaValue,
		UISchema
	) {
		return { key: uiSchemaValue.name, schema: UISchema, value: uiSchemaValue };
	};

	Geolocation.prototype.convertFrom = function ( key, value ) {
		return {
			latitudeLabel: value.latitude.title,
			longitudeLabel: value.longitude.title
		};
	};
	Geolocation.prototype.convertTo = function ( key, value ) {
		// delete value.name;
		const ret = GeolocationSchema;
		ret[ 'x-ui-name' ] = 'geolocation';
		ret.properties.latitude.title = value.latitudeLabel;
		ret.properties.longitude.title = value.longitudeLabel;
		return ret;
	};

	// attach to constructor
	JsonForms.UISchemaConverters.Geolocation = Geolocation;
}() );
