// @credits https://www.mediawiki.org/wiki/Extension:OOJSPlus

( function ( mw, $ ) {
	function ColorPickerPopup ( cfg ) {
		cfg = cfg || {};

		this.embeddable = new JsonForms.ColorPickerEmbeddable( cfg );
		this.id = 'oojsplus-color-picker-popup-' + Math.floor( Math.random() * 100000 );
		this.$element.attr( 'id', this.id );

		this.embeddable.connect( this, {
			colorSelected: 'onColorSelected',
			clear: 'onClear'
		} );

		const popupCfg = Object.assign( {
			width: '152px', // 150 for content and 2 for borders
			padded: cfg.padded || false,
			autoClose: false,
			$autoCloseIgnore: this.embeddable.$element
		}, cfg.popup || {} );

		ColorPickerPopup.parent.call( this, {
			popup: popupCfg
		} );

		this.popup.$body.append( this.embeddable.$element );

		this.colorPickerPopup = this.popup;

		$( 'body' ).on( 'click', ( e ) => {
			const $target = $( e.target );
			if (
				$target.attr( 'id' ) === this.id ||
				$target.parents( '#' + this.id ).length > 0
			) {
				return;
			}
			this.popup.toggle( false );
		} );

		this.$element.addClass( 'oojsplus-color-picker-popup' );
	};

	OO.inheritClass( ColorPickerPopup, OO.ui.mixin.PopupElement );

	ColorPickerPopup.prototype.setPickerValue = function ( value ) {
		this.embeddable.setValue( value );
		// this.emit( 'valueSet', value );
		this.emit('change', value.code);
	};

	ColorPickerPopup.prototype.getPickerValue = function () {
		return this.embeddable.getValue();
	};

	ColorPickerPopup.prototype.onColorSelected = function ( data ) {
		this.setPickerValue( data );
		this.emit( 'colorSelected', data );
		this.popup.toggle( false );
	};

	ColorPickerPopup.prototype.onClear = function () {
		this.setPickerValue( {} );
		this.emit( 'clear' );
		this.popup.toggle( false );
	};
	
	
	// attach to constructor
	JsonForms.ColorPickerPopup = ColorPickerPopup;

}( mediaWiki, jQuery ) );
