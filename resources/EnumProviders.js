// use IIFE, this ensure name is scoped

/* eslint-disable no-unused-vars */
/* eslint-disable no-case-declarations */

( function () {
	function EnumProviders() {}

	EnumProviders.prototype.metaSchemaArrayFormatToInput = function () {
		return {
			source: ( jseditor, { item, watched } ) => {
				const format = watched[ 'x-format' ] || watched.format;

				switch ( format ) {
					case 'text':
					case 'strings':
						return [ 'OO.ui.TagMultiselectWidget', 'OO.ui.MenuTagMultiselectWidget', 'OO.ui.CheckboxMultiselectInputWidget', 'ButtonMultiselectWidget' ];

					// case 'file':
					// 	// Uppy
					// 	return [ 'FileMultiselectWidget' ];

					case 'title':
					case 'titles':
						return [ 'mw.widgets.TitlesMultiselectWidget' ];

					case 'user':
					case 'users':
						return [ 'mw.widgets.UsersMultiselectWidget' ];

					case 'category':
					case 'categories':
						return [ 'mw.widgets.CategoryMultiselectWidget' ];

				}
			},
			filter: ( jseditor, { item, watched } ) => true,
			title: ( jseditor, { item, watched } ) => item.text,
			value: ( jseditor, { item, watched } ) => item.value
		};
	};


/*
	layout:
		"auto/default",  
		"tabs", "booklet", "table"
		
	format: "
		
	EnumProviders.prototype.metaSchemaArrayFormatToInput = function () {
		return {
			source: ( jseditor, { item, watched } ) => {
				const format = watched[ 'x-format' ] || watched.format;

				switch ( format ) {
					case 'primitives':
						return [ 'OO.ui.TagMultiselectWidget', 'OO.ui.MenuTagMultiselectWidget', 'OO.ui.CheckboxMultiselectInputWidget', 'ButtonMultiselectWidget' ];

					case 'files':
					// Uppy
						return [ 'FileMultiselectWidget' ];

					case 'titles':
						return [ 'mw.widgets.TitlesMultiselectWidget' ];

					case 'users':
						return [ 'mw.widgets.UsersMultiselectWidget' ];

					case 'categories':
						return [ 'mw.widgets.CategoryMultiselectWidget' ];

				}
			},
			filter: ( jseditor, { item, watched } ) => true,
			title: ( jseditor, { item, watched } ) => item.text,
			value: ( jseditor, { item, watched } ) => item.value
		};
	};
*/

	EnumProviders.prototype.metaSchemaIntegerFormatToInput = function () {
		return {
			source: ( jseditor, { item, watched } ) => {
				const format = watched[ 'x-format' ] || watched.format;

				switch ( format ) {
					case 'number':
						return [ 'OO.ui.NumberInputWidget' ];

					case 'range':
						return [ 'RangeWidget' ];

					case 'rating':
						return [ 'RatingWidget' ];
				}
			},
			filter: ( jseditor, { item, watched } ) => true,
			title: ( jseditor, { item, watched } ) => item.text,
			value: ( jseditor, { item, watched } ) => item.value
		};
	};

	EnumProviders.prototype.metaSchemaFormatToInput = function () {
		return {
			source: ( jseditor, { item, watched } ) => {
				const format = watched[ 'x-format' ] || watched.format;
				// console.log('watched', watched);

				switch ( format ) {
					case 'autocomplete':
						return [ 'Autocomplete', 'LookupElement' ];

					case 'captcha':
						return [ 'captcha' ];

					case 'color':
						return [ 'ColorPicker' ];

					case 'date-time':
					case 'time':
						return [ 'mw.widgets.DateTimeInputWidget' ];

					case 'date':
						return [ 'mw.widgets.DateInputWidget' ];

					case 'json':
						return [ 'JsonEditor', 'jsonForms' ];

					case 'hidden':
						return [ 'OO.ui.HiddenInputWidget' ];

					case 'file':
						return [ 'OO.ui.SelectFileWidget' ];

					case 'month':
						return [ 'month' ];

					case 'rating':
						return [ 'RatingWidget' ];

					case 'stripe':
						return [ 'stripe' ];

					case 'tel':
						return [ 'intl-tel-input' ];

					case 'text':
						return [
							'OO.ui.TextInputWidget',
							'OO.ui.DropdownInputWidget',
							'OO.ui.RadioSelectInputWidget',
							'OO.ui.ButtonSelectWidget',
							'OO.ui.ComboBoxInputWidget'
						];

					// case 'upload':
					//	return [ 'uppy' ];

					case 'email':
					case 'idn-email':
					case 'hostname':
					case 'idn-hostname':
					case 'ipv4':
					case 'ipv6':
					case 'uri':
					case 'uri-reference':
					case 'iri':
					case 'uri-template':
					case 'json-pointer':
					case 'relative-json-pointer':
					case 'regex':
						return [ 'OO.ui.TextInputWidget' ];

					case 'textarea':
						return [ 'OO.ui.MultilineTextInputWidget' ];

					case 'html':
						return [ 'SunEditor' ];

					case 'markdown':
						return [ 'EasyMDE' ];

					case 'url':
						return [ 'OO.ui.TextInputWidget' ];

					case 'title':
					case 'pagename':
						return [
							'mw.widgets.TitleInputWidget'
						];

					case 'user':
						return [
							'mw.widgets.UserInputWidget'
						]

					case 'uuid':
						return [ 'uuid' ];

					case 'week':
						return [ 'week' ];

					case 'wikitext':
						return [ 'VisualEditor', 'WikiEditor' ];
				}
			},
			filter: ( jseditor, { item, watched } ) => true,
			title: ( jseditor, { item, watched } ) => item.text,
			value: ( jseditor, { item, watched } ) => item.value
		};
	};

	EnumProviders.prototype.uGroups = function () {
		return {
			source: async () => {
				const payload = {
					action: 'jsonforms-groupnames',
					format: 'json'
				};

				try {
					const api = new mw.Api();
					const response = await new Promise( ( resolve, reject ) => {
						api.get( payload ).done( resolve ).fail( reject );
					} );

					const result = response[ payload.action ].result;

					return JSON.parse( result );
				} catch ( error ) {
					// eslint-disable-next-line no-console
					console.error( 'Error fetching user groups:', error );
					return {};
				}
			}
		};
	};

	EnumProviders.prototype.contentModelByRole = function () {
		const contentModels = mw.config.get( 'jsonforms' ).contentModels;
		const roleContentModelMap =
			mw.config.get( 'jsonforms' ).roleContentModelMap;

		// key/value object, this is also supported
		return {
			source: ( jseditor, { item, watched } ) => {
				const role = ( watched && watched.role ) || 'main';
				// console.log('contentModelByRole role',role,roleContentModelMap)

				switch ( role ) {
					case 'main':
						return contentModels;

					default:
						const contentModel = roleContentModelMap[ role ];
						return { [ contentModel ]: contentModels[ contentModel ] };
				}
			}
		};
	};

	EnumProviders.prototype.slotRoles = function () {
		const roles = mw.config.get( 'jsonforms' ).slotRoles;

		return {
			source: () => roles
		};
	};

	EnumProviders.prototype.jsonSlots = function () {
		const slots = [ 'main', ...mw.config.get( 'jsonforms' ).jsonSlots ];

		return {
			source: () => slots
		};
	};

	EnumProviders.prototype.contentModels = function () {
		const contentModels = mw.config.get( 'jsonforms' ).contentModels;

		// key/value object, this is also supported
		return {
			source: () => contentModels
		};
	};

	EnumProviders.prototype.editorByContentModel = function () {
		const contentModels = mw.config.get( 'jsonforms' ).contentModels;

		// key/value object, this is also supported
		return {
			source: ( editor, { item, watched } ) => {
				// console.log('editorByContentModel watched',editor.path, watched)
				const jsonformsConfig = mw.config.get( 'jsonforms' );
				const contentModel = ( watched && ( watched.content_model || watched.freetext_content_model ) ) || 'wikitext';

				// console.log('editorByContentModel contentModel',editor.path, contentModel)

				let options = [ 'source' ];
				switch ( contentModel ) {
					case 'wikitext':
						return {
							wikieditor: 'WikiEditor',
							visualeditor: 'VisualEditor'
						};

					case 'html':
						return {
							suneditor: 'SunEditor',
							source: 'source',
						};

					case 'markdown':
						return {
							easymde: 'EasyMDE',
							source: 'source',
						};
			
					default:
						if ( jsonformsConfig.jsonContentModels.includes( contentModel ) ) {
							// , jsonforms: 'JsonForms',  codeeditor: 'codeEditor',
							options = { jsoneditor: 'JSON Editor' };
						}
				}

				// @TODO complete codeEditor widget
				// delete options.codeeditor;

				// console.log('options', options);
				return options;
			}
		};
	};

	// eslint-disable-next-line no-undef
	JsonForms.enumProviders = new EnumProviders();
}() );
