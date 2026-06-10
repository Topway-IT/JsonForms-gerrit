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

/* global JsonForms, jQuery */
/* eslint-disable es-x/no-rest-spread-properties */

function JsonFormsSlotManager( el, data ) {
	JsonFormsSlotManager.super.call( this, el, data );

	this.metadata = data.metadata;
	this.jsonformsConfig = mw.config.get( 'jsonforms' );
	this.editPage = data.editPage;
}

OO.inheritClass( JsonFormsSlotManager, JsonForms );

JsonFormsSlotManager.prototype.onFormButton = function ( action, editor ) {
	const innerformEditor = this.editor.getEditor( 'root.editor' );
	const innerEditor = innerformEditor.input.editor;

	switch ( action ) {
		case 'cancel': {
			const url = mw.config
				.get( 'wgArticlePath' )
				.replace( '$1', mw.config.get( 'wgPageName' ) );

			window.location.href = url; }
			break;

		case 'submit': {
			const innerEditorValidationResults = innerEditor.validate();
			console.log( 'innerEditorValidationResults', innerEditorValidationResults );

			if ( innerEditorValidationResults.length ) {
				JsonForms.Alert( 'there are errors' );
				return;
			} else {
				this.submitForm().catch( ( err ) => console.error( 'API error:', err ) );
			} }
			break;
	}
};

// ***redefine enum provider and callbacks
JsonFormsSlotManager.prototype.initialize = async function () {
	await JsonFormsSlotManager.super.prototype.initialize.call( this );

	let roles = mw.config.get( 'jsonforms' ).slotRoles;
	roles = JsonForms.Utilities.removeArrayItem( roles, 'main' );
	roles = JsonForms.Utilities.removeArrayItem( roles, 'jsonforms-metadata' );

	this.enumProviders.slotRolesNoMain = () => ( {
		source: () => roles
	} );

	if ( !this.defaultOptions ) {
		this.defaultOptions = {};
	}
	if ( !this.defaultOptions.callbacks ) {
		this.defaultOptions.callbacks = {};
	}
	if ( !this.defaultOptions.callbacks.button ) {
		this.defaultOptions.callbacks.button = {};
	}

	this.defaultOptions.callbacks.button.submitButton = ( editor ) => {
		this.onFormButton( 'submit', editor );
	};
	this.defaultOptions.callbacks.button.cancelButton = ( editor ) => {
		this.onFormButton( 'cancel', editor );
	};

	// this.defaultOptions.callbacks.template = {
	// ...this.defaultOptions.callbacks.template,
	// ...this.enumProviders,
	// };
};

JsonFormsSlotManager.prototype.submitForm = function () {
	const formEditor = this.editor.getEditor( 'root.editor' );

	const innerEditor = formEditor.input.editor;
	console.log( 'innerEditor', innerEditor );

	const vars = {};
	const structuredValue = innerEditor.getStructuredValue();
	// console.log('structuredValue', structuredValue);

	for ( const path in structuredValue ) {
		vars[ path ] = structuredValue[ path ].value;
	}

	// *** submission data are arbitrary and depend on the
	// SubmitProcessor
	const data = {
		value: innerEditor.getValue(),
		structuredValue,
		options: {
			...this.editor.getEditor( 'root.footer' ).getValue(),
			edit: this.editPage
		},
		config: mw.config.get( 'jsonforms' ),

		// submit processor
		processor: 'SlotManager'
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
				// eslint-disable-next-line no-console
				console.error( 'jsonforms-submit-form', thisRes );
				reject( thisRes );
			} );
	} );
};

( function ( $ ) {
	$( () => {
	// console.log(' mw.config', mw.config);

		$( '.jsonforms-form-wrapper' ).each( async function ( index, el ) {
			this.el = el;
			const data = $( el ).data().formData;

			console.log( 'data', data );

			const jsonForms = new JsonFormsSlotManager( el, data );

			await jsonForms.initialize();

			const editor = jsonForms.createDefaultEditor();
			const editorOnChange = async ( editor ) => {
			// console.log('editorOnChange');

				const watching = [];
				const formEditor = editor.getEditor( 'root.editor' );
				// console.log('formEditor', formEditor);

				if ( !formEditor ) {
					console.warn( 'formEditor not set' );
					return;
				}

				// *** do something with the child editor if needed
				// await is necessary since the input is the JsonForms
				// widget that needs to be loaded
				const innerEditor = await formEditor.input.getEditor();

				// console.log('innerEditor', innerEditor);

				const slotRoles = mw.config.get( 'jsonforms' ).slotRoles;

				const innerEditorOnChange = async ( editor ) => {
				// console.log('innerEditorOnChange');

					const editors = editor.getEditors();

					// assign watchers to new slots
					for ( const path in editors ) {
					// console.log('path', path);
					// maybe role
						const role = path.replace( /^root\./, '' );

						// on slot creation
						if ( slotRoles.includes( role ) ) {
							if ( !watching.includes( path ) ) {
							// set role to hidden property

								// console.log('`${path}.role`', `${path}.role`);
								const roleEditor = editor.getEditor( `${ path }.role` );
								// roleEditor.setValue(role);

								// @TODO replace with setValue
								// after updating the editor's setValue method
								roleEditor.setStateValue( role );
								roleEditor.input.setValue( role );

								watching.push( path );
							}
						}
					}
				};

				// inner editor is ready/changed before outer editor
				// is ready, therefore this is necessary
				// innerEditorOnChange(true);

				// this is attached on ready, therefore is not fired
				// immediately

				innerEditor.on( 'ready', innerEditorOnChange );
				innerEditor.on( 'addObjectProperty', innerEditorOnChange );
			};

			editor.on( 'ready', editorOnChange );
		} );
	} );

}( jQuery ) );
