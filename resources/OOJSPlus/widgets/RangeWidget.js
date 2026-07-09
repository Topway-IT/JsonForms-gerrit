// @credits https://www.mediawiki.org/wiki/Extension:OOJSPlus

( function ( mw, $ ) {
	/**
	 * Usage:
	 * new RangeWidget( {
	 * min: 0,
	 * max: 100,
	 * value: 30,
	 * step: 5,
	 * valueMask: '%v %', // %v will be replaced with actual value
	 * nullValue: 'auto' // What to show when value is 0
	 * } );
	 *
	 * @param {Object} cfg
	 * @constructor
	 */
	function RangeWidget ( cfg ) {
		this.min = cfg.min || 0;
		this.max = cfg.max || 999999999;
		this.step = cfg.step || 1;
		this.valueMask = cfg.valueMask || '%v';
		this.nullValue = cfg.nullValue || false;
		this.value = cfg.value || 0;

		this.createValuePanel();

		RangeWidget.parent.call( this, cfg );
		OO.EventEmitter.call( this );

		this.createSliderContainer();
		this.setValue( cfg.value );

		this.mainContainer = new OO.ui.HorizontalLayout();
		this.mainContainer.$element.append( this.$sliderContainer, this.$valueContainer );

		this.$element.addClass( 'oojsplus-range-widget' );
		this.$element.html( this.mainContainer.$element );
	};

	OO.inheritClass( RangeWidget, OO.ui.InputWidget );

	RangeWidget.prototype.onChange = function ( e ) {
		this.setValue( e.target.value );
		this.emit( 'change', this.value );
	};

	RangeWidget.prototype.getValue = function () {
		return this.value;
	};

	RangeWidget.prototype.setValue = function ( raw ) {
		RangeWidget.parent.prototype.setValue.call( this, raw );

		if ( raw === null ) {
			return;
		}
		this.value = parseInt( raw );
		if ( this.value === 0 && this.nullValue ) {
			return this.$value.html( this.nullValue );
		}
		const value = this.parseValue();
		this.$value.html( value );
	};

	RangeWidget.prototype.parseValue = function () {
		if ( this.valueMask.includes( '%v' ) ) {
			return this.valueMask.replace( '%v', this.value.toString() );
		}
		return this.value;
	};

	RangeWidget.prototype.createSliderContainer = function () {
		this.$sliderContainer = $( '<div>' ).addClass( 'oojsplus-range-widget-input' );
		this.$input = $( '<input>' )
			.attr( 'type', 'range' )
			.attr( 'min', this.min )
			.attr( 'max', this.max )
			.attr( 'step', this.step );

		this.$input.on( 'input', this.onChange.bind( this ) );
		this.$sliderContainer.append( this.$input );
	};

	RangeWidget.prototype.createValuePanel = function () {
		this.$valueContainer = $( '<div>' ).addClass( 'oojsplus-range-widget-value' );
		this.$value = $( '<span>' );
		this.$valueContainer.append( this.$value );
	};
	
	
	// attach to constructor
	JsonForms.RangeWidget = RangeWidget;

}( mediaWiki, jQuery ) );
