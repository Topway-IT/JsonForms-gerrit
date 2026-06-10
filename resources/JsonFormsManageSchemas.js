/**
 * This file is part of the MediaWiki extension JsonForms.
 *
 * JsonForms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * JsonForms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JsonForms. If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2026, https://wikisphere.org
 */

/* eslint-disable es-x/no-rest-spread-properties */

function JsonFormsManageSchemas( el, data ) {
	JsonFormsManageSchemas.super.call( this, el, data );

	this.formDescriptor = data.formDescriptor;
}

OO.inheritClass( JsonFormsManageSchemas, JsonForms );

// ***redefine enum provider and callbacks
JsonFormsManageSchemas.prototype.initialize = async function () {
	await JsonFormsManageSchemas.super.prototype.initialize.call( this );

	if ( !this.defaultOptions ) {
		this.defaultOptions = {};
	}
	if ( !this.defaultOptions.callbacks ) {
		this.defaultOptions.callbacks = {};
	}

	this.defaultOptions.callbacks.button = Object.assign(
		{},
		this.defaultOptions.callbacks.button || {},
		{
			submitButton: ( editor ) => {
				this.onFormButton( 'submit', editor );
			},
			cancelButton: ( editor ) => {
				this.onFormButton( 'cancel', editor );
			}
		}
	);

	// this.defaultOptions.callbacks.template = {
	// ...this.defaultOptions.callbacks.template,
	// ...this.enumProviders,
	// };

	// console.log('this.defaultOptions', this.defaultOptions);

	this.schema = this.adjustFormSchema();
};

JsonFormsManageSchemas.prototype.onFormButton = function ( action, editor ) {
	const innerformEditor = this.editor.getEditor( 'root.editor' );
	const innerEditor = innerformEditor.input.editor;

	switch ( action ) {
		case 'submit': {
			const innerEditorValidationResults = innerEditor.validate();
			// console.log('innerEditorValidationResults', innerEditorValidationResults);

			if ( innerEditorValidationResults.length ) {
				console.log( 'innerEditorValidationResults', innerEditorValidationResults );
				JsonForms.Alert( 'there are errors' );
				return;
			}

			const schemaName = innerEditor.getSchemaName();
			if ( schemaName && innerEditor.getValue()[ 'x-name' ] !== schemaName ) {
				JsonForms.Alert(
					'This will rename the schema, ok ?',
					{ size: 'small' },
					() => {
						this.submitForm( innerEditor ).catch( ( err ) => console.error( 'API error:', err )
						);
					}
				);
				return;
			}
			this.submitForm( innerEditor ).catch( ( err ) => console.error( 'API error:', err )
			);
			break;
		}
		case 'cancel': {
			const url = mw.config
				.get( 'wgArticlePath' )
				.replace( '$1', mw.config.get( 'wgPageName' ) );

			window.location.href = url; }
			break;
	}
};

// adjust form schema based on form descriptor
JsonFormsManageSchemas.prototype.adjustFormSchema = function () {
	const ret = structuredClone( this.schema );

	delete ret.properties.footer.properties.minor;
	delete ret.properties.footer.properties.summary;
	delete ret.properties.footer[ 'x-css-class' ];
	delete ret.properties.footer.properties.buttons[ 'x-css-class' ];

	return ret;
};

JsonFormsManageSchemas.prototype.submitForm = function ( innerEditor ) {
	// console.log('innerEditor', innerEditor);

	const vars = {};
	const structuredValue = innerEditor.getStructuredValue();
	// console.log('structuredValue', structuredValue);

	for ( const path in structuredValue ) {
		vars[ path ] = structuredValue[ path ].value;
	}

	// Create a shallow copy to avoid mutating the original
	const formDescriptor = { ...this.formDescriptor };

	// console.log('vars', vars);

	if ( !formDescriptor.pagename_formula ) {
		console.error(
			'JsonFormsManageSchemas formDescriptor.pagename_formula not set'
		);
		return;
	}

	// $formDescriptor['pagename_formula'] = 'JsonSchema:<name>';
	// or $formDescriptor['pagename_formula'] = 'JsonSchema:<x-name>';
	// server-side

	const title = this.processTemplate( formDescriptor.pagename_formula, vars, {
		replaceAngularBrackets: true
	} );

	// *** submission data are arbitrary and depend on the
	// SubmitProcessor
	const data = {
		value: innerEditor.getValue(),
		// edit_page is set via $formDescriptor['edit_page']
		// so on move 'edit_page' will be the source and options.tite
		// the target
		options: {
			title
		},
		structuredValue,
		formDescriptor,
		config: mw.config.get( 'jsonforms' ),

		// used to redirect to the current schema or form
		schemaId: innerEditor.schema.$id,
		// submit processor
		processor: 'ManageSchemas'
	};

	console.log( 'data', data );

	const payload = {
		data: JSON.stringify( data ),
		action: 'jsonforms-submit-form'
	};

	// console.log('payload', payload);
	return new Promise( ( resolve, reject ) => {
		new mw.Api()
			.postWithToken( 'csrf', payload )
			.done( ( thisRes ) => {
				console.log( 'thisRes', thisRes );
				let result = thisRes[ payload.action ].result;
				result = JSON.parse( result );
				if ( result.errors && result.errors.length ) {
					const config = {
						htmlMessage: mw.msg(
							'jsonforms-jsmodule-return-errors',
							result.errors.join( ' ,' )
						),
						type: 'error'
					};
					const nonModalDialog = new JsonForms.NonModalDialog();
					nonModalDialog.open( config );
				} else if ( result.returnUrl ) {
					if ( result.returnUrl === window.location.href ) {
						window.location.reload();
					} else {
						window.location.href = result.returnUrl;
					}
				} else {
					const config = {
						htmlMessage: result.message,
						type: 'success'
					};
					const nonModalDialog = new JsonForms.NonModalDialog();
					nonModalDialog.open( config );
					resolve( result );
					this.editor.destroy();
					this.createDefaultEditor().then( ( editor ) => {} );
				}
			} )
			.fail( ( thisRes ) => {
				// eslint-disable-next-line no-console
				console.error( 'jsonforms-submit-form', thisRes );
				reject( thisRes );
			} );
	} );
};

$( () => {
	$( '.jsonforms-form-wrapper' ).each( async function ( index, el ) {
		this.el = el;
		const data = $( el ).data().formData;
		const editorConfig = data.editorConfig || {};
		console.log( 'data', data );

		const jsonForms = new JsonFormsManageSchemas( el, data );
		await jsonForms.initialize();

		const editor = await jsonForms.createDefaultEditor( editorConfig );
	} );
} );
