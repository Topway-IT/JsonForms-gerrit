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

/* eslint-disable no-console */

function JsonFormsDemo( el, data ) {
	JsonFormsDemo.super.call( this, el, data );
}

// eslint-disable-next-line no-undef
OO.inheritClass( JsonFormsDemo, JsonForms );

( function ( $ ) {
	$( () => {
		console.log( ' mw.config', mw.config );

		$( '.jsonforms-form-wrapper' ).each( async function ( index, el ) {
			this.el = el;
			const data = $( el ).data().formData;

			console.log( 'data', data );

			const jsonForms = new JsonFormsDemo( el, data );
			await jsonForms.initialize();
			const editor = jsonForms.createDefaultEditor();

			// console.log('editor',editor)

			const textarea = $( '<textarea>', {
				class: 'form-control',
				id: 'value',
				rows: 12,
				style: 'font-size: 12px; font-family: monospace;'
			} );
			$( el ).append( textarea );

			const textareaB = $( '<textarea>', {
				class: 'form-control',
				id: 'value',
				rows: 12,
				style: 'font-size: 12px; font-family: monospace;'
			} );
			$( el ).append( textareaB );

			editor.on( 'change', () => {
			// console.log('editor.on change')

				textarea.val( JSON.stringify( editor.getValue(), null, 2 ) );
				textareaB.val( JSON.stringify( Object.keys( editor.editors ), null, 2 ) );
			} );

			editor.on( 'ready', () => {} );
		} );
	} );

// eslint-disable-next-line no-undef
}( jQuery ) );
