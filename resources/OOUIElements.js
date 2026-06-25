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

// use IIFE, this ensure name is scoped
( function ( $ ) {
	function createWindowManager() {
		const windowManager = new OO.ui.WindowManager( {
			classes: [ 'jsonforms-ooui-window' ]
		} );
		$( document.body ).append( windowManager.$element );

		return windowManager;
	}

	// STANDARD DIALOG

	function Dialog( config, callbacks, editor ) {
		config = Object.assign(
			{ size: 'large', classes: [ 'jsonforms-form-dialog' ] },
			config
		);
		this.config = config;
		this.callbacks = callbacks;
		this.editor = editor;

		this.title = config.title;

		Dialog.super.call( this, config );
	}

	OO.inheritClass( Dialog, OO.ui.ProcessDialog );
	Dialog.static.name = 'myDialog';

	Dialog.static.actions = [
		{
			action: 'delete',
			label: 'delete',
			flags: 'destructive',
			modes: [ 'validate-delete', 'submit-single-delete' ]
		},
		{
			action: 'validate',
			modes: [ 'validate', 'validate-delete' ],
			label: 'validate',
			flags: [ 'primary', 'progressive' ]
		},
		{
			action: 'back',
			label: 'back',
			flags: [ 'safe', 'back' ],
			modes: [ 'submit', 'submit-delete' ]
		},
		{
			action: 'submit',
			label: 'submit',
			flags: [ 'primary', 'progressive' ],
			modes: [ 'submit', 'submit-delete' ]
		},
		{
			action: 'validate&submit',
			label: 'submit',
			flags: [ 'primary', 'progressive' ],
			modes: [ 'submit-single', 'submit-single-delete' ]
		},

		// https://gerrit.wikimedia.org/r/plugins/gitiles/oojs/ui/+/refs/heads/master/demos/classes/BookletDialog.js

		{
			label: 'close',
			flags: [ 'safe', 'close' ],
			modes: [
				'validate',
				'submit-single',
				'validate-delete',
				'submit-single-delete'
			]
		}
	];

	// Customize the initialize() function to add content and layouts:
	Dialog.prototype.initialize = function () {
		Dialog.super.prototype.initialize.call( this );
		this.callbacks.initialize( this );
	};

	Dialog.prototype.getBodyHeight = function () {
		return this.content.$element.outerHeight( true );
	};

	Dialog.prototype.getSetupProcess = function ( data ) {
		data = data || {};
		const self = this;

		return Dialog.super.prototype.getSetupProcess
			.call( this, data )
			.next( function () {
				self.callbacks.setupProcess( this, data );
			}, this );
	};

	// Specify processes to handle the actions.
	Dialog.prototype.getActionProcess = function ( action ) {
		const ret = this.callbacks.actionProcess(
			this,
			Dialog.super.prototype.getActionProcess,
			action
		);
		if ( ret ) {
			return ret;
		}

		return Dialog.super.prototype.getActionProcess.call( this, action );
	};

	Dialog.prototype.getTeardownProcess = function ( data ) {
		return Dialog.super.prototype.getTeardownProcess
			.call( this, data )
			.first( () => {
				// Perform any cleanup as needed
			}, this );
	};

	Dialog.prototype.open = function () {
		const windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );

		const myDialog = this;
		const title = this.title;
		windowManager.addWindows( [ myDialog ] );
		windowManager.openWindow( myDialog, { title } ).opening.then( ( promise ) => {
			this.callbacks.onOpen( this, promise );
		} );
	};

	// NON-MODAL DIALOG

	function SimpleDialog( config ) {
		this.config = config;
		SimpleDialog.super.call( this, config );
	}

	OO.inheritClass( SimpleDialog, OO.ui.Dialog );
	SimpleDialog.static.title = 'Non modal dialog';

	SimpleDialog.prototype.initialize = function () {
		SimpleDialog.super.prototype.initialize.apply( this, arguments );
		this.content = new OO.ui.PanelLayout( { padded: false, expanded: false } );

		const messageWidget = new OO.ui.MessageWidget( {
			type: this.config.type,
			label: new OO.ui.HtmlSnippet( this.config.htmlMessage ),
			classes: [],
			showClose: true
		} );

		this.content.$element.append( messageWidget.$element );

		messageWidget.on( 'toggle', ( visible ) => {
			if ( !visible ) {
				this.close();
			}
		} );

		// this.content.$element.append($('<p>').html(this.config.htmlMessage));

		// const closeButton = new OO.ui.ButtonWidget({
		// label: OO.ui.msg('ooui-dialog-process-dismiss'),
		// });

		// closeButton.on('click', () => {
		// this.close();
		// });

		// this.content.$element.append($('<p>'));
		// this.content.$element.append(closeButton.$element);
		this.$body.append( this.content.$element );
	};

	/*
SimpleDialog.prototype.getSetupProcess = function (data) {

	return SimpleDialog.super.prototype.getSetupProcess.call( this, data )
		.next( () => {
			// this.content.$element.empty();
			this.content.$element.append( data.html );
		} );
};
*/

	SimpleDialog.prototype.getBodyHeight = function () {
		return this.content.$element.outerHeight( true );
	};

	function NonModalDialog( config ) {}

	NonModalDialog.prototype.open = function ( dialogConfig ) {
		const manager = new OO.ui.WindowManager( {
			modal: false,
			forceTrapFocus: true,
			classes: [ 'jsonforms-dialogs-non-modal' ]
		} );

		$( document.body ).append( manager.$element );

		// const dialogConfig = {
		// size: 'large'
		// };

		const name = 'window_nonmodaldialog';
		const windows = {};
		windows[ name ] = new SimpleDialog( dialogConfig );

		manager.addWindows( windows );

		manager.openWindow( name );
	};

	// ALERT

	function Alert( text, options, callback ) {
		const windowManager = createWindowManager();

		const dialog = new OO.ui.MessageDialog( { padded: false } );
		windowManager.addWindows( [ dialog ] );

		const obj = { message: text };

		// do not pass a callback to show only the accept button
		if ( !callback ) {
			obj.actions = [ OO.ui.MessageDialog.static.actions[ 0 ] ];
		}

		return (
			windowManager
				.openWindow( 'message', $.extend( obj, options ) )
				// closed.then((action) => action.action === 'accept');
				.closed.then( function ( action ) {
					return action.action === 'accept' && callback ?
						callback.apply( this ) :
						undefined;
				} )
		);
	}

	// attach to constructor
	JsonForms.Dialog = Dialog;
	JsonForms.Alert = Alert;
	JsonForms.NonModalDialog = NonModalDialog;
}( jQuery ) );
