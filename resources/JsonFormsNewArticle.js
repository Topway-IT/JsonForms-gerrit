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

/* global JsonForms */
/* eslint-disable es-x/no-rest-spread-properties */
/* eslint-disable no-console */
/* eslint-disable no-unused-vars */

function JsonFormsNewArticle( el, data ) {
	JsonFormsNewArticle.super.call( this, el, data );

	this.jsonformsConfig = mw.config.get( 'jsonforms' );
	this.editPage = data.editPage;
}

OO.inheritClass( JsonFormsNewArticle, JsonForms );

JsonFormsNewArticle.prototype.onFormButton = function ( action, editor ) {
	switch ( action ) {
		case 'submit': {
			const validationResults = this.editor.validate();
			console.log( 'validationResults', validationResults );

			if ( validationResults.length ) {
				JsonForms.Alert( 'there are errors' );
				return;
			} else {
				this.submitForm().catch( ( err ) => console.error( 'API error:', err ) );
			} }
			break;
	}
};

// ***redefine enum provider and callbacks
JsonFormsNewArticle.prototype.initialize = async function () {
	await JsonFormsNewArticle.super.prototype.initialize.call( this );

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
			}
		}
	);
};

JsonForms.prototype.initButtons = function ( jsonEditor ) {
	switch ( jsonEditor.schema.$id ) {
		case 'NewArticleDataOnly':
			{
				const summaryInput = jsonEditor.getEditor( 'root.footer.summary' );
				const minorInput = jsonEditor.getEditor( 'root.footer.minor' );

				summaryInput.theme.toggle( summaryInput.container, false );
				minorInput.theme.toggle( minorInput.container, false );
			}
			break;
	}
};

JsonFormsNewArticle.prototype.submitForm = function () {
	let options,
		value,
		metadata = {};

	const editorValue = this.editor.getValue();
	const articleID = this.data.schema.$id.split( '/' ).slice( -1 )[ 0 ];

	switch ( articleID ) {
		case 'NewArticleCombined':
			{
				const mainEditor = this.editor.getEditor( 'root.form.main' );
				const footerEditor = this.editor.getEditor( 'root.footer' );

				options = {
					...mainEditor.getValue(),
					...footerEditor.getValue()
				};

				metadata = { ...( editorValue.form.options || {} ) };

				const schemaEditor = this.editor.getEditor(
					'root.form.schema.editor'
				);
				if ( schemaEditor ) {
					value = schemaEditor.getValue();
				}

				const schemaNameEditor = this.editor.getEditor(
					'root.form.schema.schemaName'
				);

				if ( schemaNameEditor ) {
					metadata.schemaName = schemaNameEditor.getValue();
				}

				if ( !metadata.schemaName ) {
					JsonForms.Alert( 'schema is required' );
					return;
				}
			}
			break;
		case 'NewArticleDataOnly':
			{
				const formEditor = this.editor.getEditor( 'root.form' );

				options = {
					...formEditor.getValue()
				};
				delete options.schema;
				delete options.editor;

				const schemaEditor = this.editor.getEditor(
					'root.form.editor'
				);
				if ( schemaEditor ) {
					value = schemaEditor.getValue();
				}

				const schemaNameEditor = this.editor.getEditor(
					'root.form.schema'
				);

				if ( schemaNameEditor ) {
					metadata.schemaName = schemaNameEditor.getValue();
				}

				if ( !metadata.schemaName ) {
					JsonForms.Alert( 'schema is required' );
					return;
				}
			}
			break;
		case 'NewArticleRegular':
		default:
			{
				const formEditor = this.editor.getEditor( 'root.form' );
				const footerEditor = this.editor.getEditor( 'root.footer' );

				options = {
					...formEditor.getValue(),
					...footerEditor.getValue()
				};
			}
			break;
	}

	// *** submission data are arbitrary and depend on the
	// SubmitProcessor
	const data = {
		options,
		value,
		metadata,
		config: mw.config.get( 'jsonforms' ),

		// submit processor
		processor: 'NewArticle'
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
					resolve( result );
					const nonModalDialog = new JsonForms.NonModalDialog();
					nonModalDialog.open( config );
				} else {
					if ( result.returnUrl === window.location.href ) {
						window.location.reload();
					} else {
						window.location.href = result.returnUrl;
					}
				}
			} )
			.fail( ( thisRes ) => {

				console.error( 'jsonforms-submit-form', thisRes );
				reject( thisRes );
			} );
	} );
};

( function ( $ ) {
	$( () => {
		$( '.jsonforms-form-wrapper' ).each( async function ( index, el ) {
			this.el = el;
			const data = $( el ).data().formData;

			console.log( 'data', data );

			const jsonForms = new JsonFormsNewArticle( el, data );

			await jsonForms.initialize();

			const editor = jsonForms.createDefaultEditor();

		} );
	} );
// eslint-disable-next-line no-undef
}( jQuery ) );
