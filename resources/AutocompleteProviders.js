// use IIFE, this ensure name is scoped

/* eslint-disable no-underscore-dangle */
( function () {
	'use strict';
	function AutocompleteProviders() {
	}

	function stripHtml( str ) {
		const tmp = document.createElement( 'div' );
		tmp.innerHTML = str;
		return tmp.textContent || tmp.innerText || '';
	}

	function escapeHtml( str ) {
		if ( str === null || str === undefined ) {
			return '';
		}

		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	/*
	AutocompleteProviders.prototype.jsonSchemas = function () {
		let cache = null;
		let pending = null;

		return {
			search: async (editor, input) => {
				// If we have cached results, filter them based on input
				if (cache) {
					if (!input) return cache;
					const lowerInput = input.toLowerCase();
					return cache.filter((item) =>
						item.text.toLowerCase().includes(lowerInput),
					);
				}

				if (pending) return pending;

				const api = new mw.Api();

				pending = api
					.get({
						action: 'query',
						list: 'allpages',
						apnamespace: 2100,
						aplimit: 'max',
						formatversion: 2,
					})
					.then((res) => {
						cache = res.query.allpages.map((page) => {
							const titleObj = new mw.Title(page.title);
							const baseTitle = titleObj.getMainText();
							return {
								text: baseTitle,
								value: baseTitle,
							};
						});
						pending = null;

						// Filter after caching if input exists
						if (input) {
							const lowerInput = input.toLowerCase();
							return cache.filter((item) =>
								item.text.toLowerCase().includes(lowerInput),
							);
						}
						return cache;
					});

				return pending;
			},
			getResultValue: (editor, result) => result.value,
			renderResult: (editor, result, props) => result.text,
		};
	};
*/

	AutocompleteProviders.prototype._renderByInputType = function (
		editor,
		props,
		innerHtml
	) {
		const name = ( editor.customOptions.inputName || '' ).toLowerCase();

		switch ( name ) {
			case 'autocomplete':
				return [ '<li ' + props + '>', innerHtml, '</li>' ].join( '' );

			case 'lookupelement':
			default:
				return [
					'<div class="oo-ui-labelElement-label">',
					innerHtml,
					'</div>'
				].join( '' );
		}
	};

	// @source https://pmk65.github.io/jedemov2/dist/demo.html autocomplete demo, javascript tab
	AutocompleteProviders.prototype.wikipedia = function () {
		return {
			search: ( editor, input ) => {
				if ( input.length < 3 ) {
					return Promise.resolve( [] );
				}

				const url =
					'https://en.wikipedia.org/w/api.php' +
					'?action=query' +
					'&list=search' +
					'&format=json' +
					'&origin=*' +
					'&srsearch=' +
					encodeURIComponent( input );

				return fetch( url )
					.then( ( res ) => res.json() )
					.then( ( data ) => data.query && data.query.search ? data.query.search : []
					);
			},

			getResultValue: ( editor, result ) => result.title || '',

			renderResult: ( editor, result, props ) => {
				const title = escapeHtml( result.title || '' );
				const snippet = escapeHtml( stripHtml( result.snippet || '' ) );

				const inner = [
					'<div class="wiki-title">',
					title,
					'</div>',
					snippet ?
						'<div class="wiki-snippet"><small>' + snippet + '</small></div>' :
						''
				].join( '' );

				return this._renderByInputType( editor, props, inner );
			}
		};
	};

	// @source https://pmk65.github.io/jedemov2/dist/demo.html autocomplete demo, javascript tab
	AutocompleteProviders.prototype.dawa = function () {
		return {
			search: ( editor, input ) => {
				if ( input.length < 3 ) {
					return Promise.resolve( [] );
				}

				const url =
					'https://dawa.aws.dk/vejnavne/autocomplete' +
					'?q=' +
					encodeURIComponent( input );

				return fetch( url )
					.then( ( res ) => res.json() )
					.then( ( data ) => ( Array.isArray( data ) ? data : [] ) );
			},

			getResultValue: ( editor, result ) => result.tekst || '',

			renderResult: ( editor, result, props ) => {
				const text = escapeHtml( result.tekst || '' );
				const inner = [ '<div class="wiki-title">', text, '</div>' ].join( '' );
				return this._renderByInputType( editor, props, inner );
			}
		};
	};

	AutocompleteProviders.prototype.wikidata = function () {
		return {
			search: ( editor, input ) => {
				if ( input.length < 3 ) {
					return Promise.resolve( [] );
				}

				const url =
					'https://www.wikidata.org/w/api.php' +
					'?action=wbsearchentities' +
					'&language=en' +
					'&format=json' +
					'&origin=*' +
					'&search=' +
					encodeURIComponent( input );

				return fetch( url )
					.then( ( res ) => res.json() )
					.then( ( data ) => data.search || [] );
			},

			getResultValue: ( editor, result ) => result.id,
			renderResult: ( editor, result, props ) => {
				const label = escapeHtml( result.label || '' );
				const desc = escapeHtml( result.description || '' );
				const id = escapeHtml( result.id || '' );

				const inner = [
					'<div class="wiki-title">',
					label,
					' <small class="muted">(',
					id,
					')</small>',
					'</div>',
					desc ?
						'<div class="wiki-snippet"><small>' + desc + '</small></div>' :
						''
				].join( '' );

				return this._renderByInputType( editor, props, inner );
			}
		};
	};

	// eslint-disable-next-line no-undef
	JsonForms.autocompleteProviders = new AutocompleteProviders();
}() );
