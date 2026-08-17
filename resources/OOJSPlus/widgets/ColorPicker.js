// @credits https://www.mediawiki.org/wiki/Extension:OOJSPlus

( function ( mw, $ ) {
	function ColorPickerWidget( cfg ) {
		cfg = cfg || {};
		cfg = Object.assign( {
			title: mw.message( 'oojsplus-color-picker-label' ).text(),
			icon: 'textColor'
		}, cfg );

		this.$currentColorShow = $( '<div>' )
			.addClass( 'oojsplus-color-current' )
			.css( 'color', 'transparent' );

		ColorPickerWidget.parent.call( this, cfg );
		JsonForms.ColorPickerPopup.call( this, cfg );
		OO.EventEmitter.call( this );

		this.connect( this, {
			click: 'togglePicker',
			colorSelected: 'colorChange',
			clear: 'colorChange'
		} );

		if ( cfg.value ) {
			this.setValue( cfg.value );
		}
		this.$currentColorShow.insertBefore( this.$icon );

		this.$element.addClass( 'oojsplus-color-picker-widget' );
		this.$element.append( this.colorPickerPopup.$element );
	};

	OO.inheritClass( ColorPickerWidget, OO.ui.ButtonWidget );
	OO.mixinClass( ColorPickerWidget, JsonForms.ColorPickerPopup );
	OO.mixinClass( ColorPickerWidget, OO.EventEmitter );

	ColorPickerWidget.prototype.togglePicker = function ( val ) {
		if ( val ) {
			this.popup.toggle( val );
		} else {
			this.popup.toggle();
		}

		this.emit( 'togglePicker', this.popup.isVisible() );
	};

	ColorPickerWidget.prototype.setValue = function ( value ) {
		this.setPickerValue( value );
		this.setCurrentColor();
	};

	ColorPickerWidget.prototype.colorChange = function ( value ) { // eslint-disable-line no-unused-vars
		this.setCurrentColor();
	};

	ColorPickerWidget.prototype.getValue = function () {
		return this.getPickerValue();
	};

	ColorPickerWidget.prototype.setCurrentColor = function () {
		if ( $.isEmptyObject( this.getValue() ) ) {
			return this.$currentColorShow.css( 'color', 'transparent' );
		}

		if ( this.getValue().hasOwnProperty( 'code' ) && this.getValue().code !== '' ) {
			return this.$currentColorShow.css( 'color', this.getValue().code );
		} else if ( this.getValue().hasOwnProperty( 'class' ) && this.getValue().class !== '' ) {
			this.$currentColorShow.css( 'color', '' );
			return this.$currentColorShow.addClass( this.getValue().class ); // eslint-disable-line mediawiki/class-doc
		}
	};

	// attach to constructor
	JsonForms.ColorPickerWidget = ColorPickerWidget;
	
}( mediaWiki, jQuery ) );
