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

function JsonFormsPageForm(el, data) {
	JsonFormsPageForm.super.call(this, el, data);

	this.formDescriptor = data.formDescriptor;
	this.isPopup = this.formDescriptor.view === 'popup';
	this.editor = null;
	this.debug = this.formDescriptor.editor_options.debug;
}

OO.inheritClass(JsonFormsPageForm, JsonForms);

// ***redefine enum provider and callbacks
JsonFormsPageForm.prototype.initialize = async function () {
	await JsonFormsPageForm.super.prototype.initialize.call(this);

	let defaultOptions = this.defaultOptions || {};

	if (!defaultOptions) {
		defaultOptions = {};
	}
	if (!defaultOptions.callbacks) {
		defaultOptions.callbacks = {};
	}
	if (!defaultOptions.callbacks.button) {
		defaultOptions.callbacks.button = {};
	}

	this.defaultOptions = {
		...defaultOptions,
		callbacks: {
			...defaultOptions.callbacks,
			button: {
				...defaultOptions.callbacks.button,
				outerFormNavButton: function (editor) {
					this.onNavButton(editor);
				}.bind(this),
			},
		},
	};

	this.schema = this.adjustFormSchema();
};

JsonFormsPageForm.prototype.createPopupButtonInfo = function (dialog) {
	const formUrl = mw.config
		.get( 'wgArticlePath' )
		.replace( '$1', 'JsonForm:' + this.formDescriptor.name );

	const schemaUrl = mw.config
		.get( 'wgArticlePath' )
		.replace( '$1', 'JsonSchema:' + this.formDescriptor.schema );

	const infoMessage = `Using schema <a target="_blank" href="${schemaUrl}">${this.formDescriptor.schema}</a> via form descriptor <a target="_blank" href="${formUrl}">${this.formDescriptor.name}</a>`;

	return	new OO.ui.PopupButtonWidget( {
		icon: 'info',
		framed: false,
		invisibleLabel: true,
		$overlay: dialog ? dialog.$overlay : true,
		popup: {
			head: true,
			label: new OO.ui.HtmlSnippet( infoMessage ),
			// $content: infoMessage,
			padded: true,
			autoFlip: false,
		}
	} );	
};


// adjust form schema based on form descriptor
JsonFormsPageForm.prototype.adjustFormSchema = function () {
	const formDescriptor = this.formDescriptor;

	const ret = structuredClone(this.schema);
	ret.properties.header.title = formDescriptor.title || formDescriptor.name;

	const options = ret.properties.form.properties.options.properties;
	const footer = ret.properties.footer.properties;
	const buttons = ret.properties.footer.properties.buttons.properties;
	const required = ret.properties.form.properties.options.required;

	if (formDescriptor.pagename_formula || formDescriptor.edit) {
		delete options.title;
		JsonForms.Utilities.removeArrayItem(required, 'title');
	} else {
		required.push('title');
	}

	if (!formDescriptor.captcha) {
		delete ret.properties.captcha;
	} else {
		ret.properties.captcha['x-captcha-sitekey'] =
			mw.config.get('jsonforms').captchaSiteKey;
	}

	if (!formDescriptor.edit_categories) {
		delete options.categories;
	}

	if (!formDescriptor.edit_freetext) {
		delete options.freetext;
		delete options.editor;
		delete options.freetext_content_model;
		delete footer.summary;
		delete footer.minor;
	}

	this.hasOptions = Object.keys(options).length;

	if (!this.hasOptions) {
		delete buttons.validate;
		delete buttons.goback;
	}
	
	if ( formDescriptor.hide_title === true ) {
		delete ret.properties.header.title;
	}

	return ret;
};


JsonFormsPageForm.prototype.addPopupButtonInfo = function (jsonEditor) {
	const headerEditor = jsonEditor.getEditor('root.header');

	$header = $( headerEditor.container ).find( '.jsonforms-header:first' );
	if ( this.formDescriptor.hide_title === true ) {
		 $header.remove();
		 $( headerEditor.container ).removeClass( [ 'jsonforms-outerform-header', 'jsonforms-panel-border' ] );
	}

	const popupButtonInfo = this.createPopupButtonInfo();
	$header = $( headerEditor.container ).find( '.jsonforms-header:first' );
	const $container = $( '<div class="jsonforms-header-container"></div>' );

	$container.css( {
		'display': 'flex',
		'justify-content': 'space-between',
		'align-items': 'center',
		'width': '100%'
	} );

	$header.wrap( $container );
	$right = $( '<div class="jsonforms-header-right"></div>' );
	$right.append( popupButtonInfo.$element );
	$header.after( $right );
}


JsonFormsPageForm.prototype.initButtons = function (jsonEditor) {
	this.addPopupButtonInfo( jsonEditor );

	const optionsEditor = jsonEditor.getEditor('root.form.options');
	const validateButton = jsonEditor.getEditor('root.footer.buttons.validate');
	const submitButton = jsonEditor.getEditor('root.footer.buttons.submit');
	const gobackButton = jsonEditor.getEditor('root.header.buttons.goback');
	const summaryInput = jsonEditor.getEditor('root.footer.summary');
	const minorInput = jsonEditor.getEditor('root.footer.minor');

	if ( this.formDescriptor.action === 'none' ) {
		const editors = [ submitButton, gobackButton, validateButton, summaryInput, minorInput ]

		for ( const editor of editors ) {
			if ( editor ) {
				editor.theme.toggle(editor.container, false);
			}
		}
		const footerEditor = jsonEditor.getEditor('root.footer');
		$( footerEditor.container ).removeClass( [ 'jsonforms-outerform-footer' ] ); 
		return;
	}

	if (Object.keys(optionsEditor.editors).length) {
		if (submitButton) {
			submitButton.theme.toggle(submitButton.container, false);
		}

		if (gobackButton) {
			gobackButton.theme.toggle(gobackButton.container, false);
		}

		validateButton.theme.toggle(validateButton.container, true);
	} else {
		if (validateButton) {
			validateButton.theme.toggle(validateButton.container, false);
		}
		submitButton.theme.toggle(submitButton.container, true);
	}

	if (gobackButton) {
		gobackButton.theme.toggle(gobackButton.container, false);
	}

	if (summaryInput) {
		summaryInput.theme.toggle(summaryInput.container, false);
	}

	if (minorInput) {
		minorInput.theme.toggle(minorInput.container, false);
	}
};

JsonFormsPageForm.prototype.createDefaultEditor = async function (config = {}) {
	config = {
		...config,
		schema: this.schema,
		schemaName: this.schemaName,
		startval: this.startval,
		...((this.data.formDescriptor || {}).editor_options || {}),
		// the user-defined start_path is declared inside
		// the config object in the jsonform widget from phpn
		// so we don't need to handle it here
		// start_path: ...
	};

	if (!this.isPopup) {
		// this is returned as resolved promise
		// return JsonFormsPageForm.super.prototype.createDefaultEditor.call(this);
		const editor = this.createEditor(this.el, config);
		editor.on('ready', this.initButtons.bind( this ) );
		return editor;
	}

	return await this.createPopup(config);
};

JsonFormsPageForm.prototype.createPopup = async function (config) {
	let _resolveEditorReady = null;

	const prepareEditor = async () => {
		// use a separate JsonForms instance
		// keep asynchronous functions outside callbacks.initialize
		const jsonForms = new JsonForms(null, {
			...this.data,
			schema: config.schema,
			startval: null,
		});
		await jsonForms.initialize();
		return jsonForms;
	};

	const jsonFormsOptions = await prepareEditor();

	const callbacks = {
		initialize: (dialog) => {
			const popupButtonInfo = this.createPopupButtonInfo( dialog );
			dialog.$primaryActions.prepend( popupButtonInfo.$element );

			const panelA = new OO.ui.PanelLayout({
				expanded: false,
				padded: false,
				framed: false,
				data: { name: 'editor' },
			});

			const el = document.createElement('div');

			// const hasData = JsonForms.Utilities.getNestedProp(
			// [ 'form', 'editor' ],
			// this.startval
			// );

			// @ATTENTION, we don't use editor.getEditor('root.form.editor')
			// etc. since it's not synchronous !!
			const editor = this.createEditor(el, {
				...config,
				// startval: hasData ? this.startval.form.editor : undefined,
				// used by this.theme $overlay
				dialog,
				startval: this.startval,
				display_path: 'form.editor',
			});

			dialog.editor = editor;

			panelA.$element.append(el);

			const panelB = new OO.ui.PanelLayout({
				expanded: false,
				padded: false,
				framed: false,
				data: { name: 'options' },
			});

			// @ATTENTION, we don't use editor.getEditor('root.form.options')
			// etc. since it's not synchronous !!
			const elOptions = document.createElement('div');
			dialog.optionsEditor = jsonFormsOptions.createEditor(elOptions, {
				display_path: 'form.options',
				startval: this.startval,
				// used by this.theme $overlay
				dialog,
				schema: config.schema,
			});
			panelB.$element.append(elOptions);

			// expanded false is necessary to make getBodyHeight work
			const layout = new OO.ui.StackLayout({
				items: [panelA, panelB],
				expanded: false,
				continuous: false,
				padded: false,
				// The following classes are used here:
				// * PanelPropertiesStack
				// * PanelPropertiesStack-empty
				// classes: classes
			});

			dialog.content = dialog.layout = layout;

			dialog.$body.append(layout.$element);

			_resolveEditorReady(editor);
		},
		setupProcess: (dialog) => {
			const hasData = JsonForms.Utilities.getNestedProp(
				['form', 'editor'],
				this.startval,
			);

			const getMode = () => {
				if ( this.formDescriptor.action === 'none' ) {
					return 'none';
				}

				let ret = ( this.hasOptions ? 'validate' : 'submit-single' );

				if ( this.hasData ) {
					ret += '-delete';
				}

				return ret;
			};

			dialog.actions.setMode(getMode());
		},
		onOpen: () => {},
		actionProcess: (dialog, getActionProcess, action) => {
			const panels = dialog.layout.getItems();

			switch (action) {
				case 'back':
					dialog.layout.setItem(panels[0]);
					dialog.actions.setMode('validate' + (!this.hasData ? '' : '-delete'));
					return;

				case 'validate':
					{
						const innerformEditor = dialog.editor.getEditor('root.form.editor');
						const innerEditor = innerformEditor.input.editor;
						const innerEditorValidationResults = innerEditor.validate();

						if (innerEditorValidationResults.length) {
							if ( this.debug ) {
								console.log(
									'innerEditorValidationResults',
									innerEditorValidationResults,
								);
							}
							JsonForms.Alert('there are errors');
							return;
						} else {
							dialog.layout.setItem(panels[1]);
							dialog.setSize('medium');
							dialog.actions.setMode(
								'submit' + (!this.hasData ? '' : '-delete'),
							);
						}
					}
					return;

				case 'validate&submit':
				case 'submit':
				case 'delete': {
					const optionsEditor = dialog.optionsEditor;
					const optionsEditorValidationResults = optionsEditor.validate();

					if (optionsEditorValidationResults.length) {
						if ( this.debug ) {
							console.log(
								'optionsEditorValidationResults',
								optionsEditorValidationResults,
							);
						}
						JsonForms.Alert('there are errors');
						return;
					}

					const innerformEditor = dialog.editor.getEditor('root.form.editor');
					const innerEditor = innerformEditor.input.editor;

					const innerEditorValidationResults = innerEditor.validate();

					if (innerEditorValidationResults.length) {
						if ( this.debug ) {
							console.log(
								'innerEditorValidationResults',
								innerEditorValidationResults,
							);
						}
						JsonForms.Alert('there are errors');
						return;
					}
					return getActionProcess.call(this, action).next(() => {
						// return promise
						const optionsEditorOptions =
							optionsEditor.getEditor('root.form.options');
						return this.submitForm(innerEditor, optionsEditorOptions).then(
							(res) => {
								if (res !== false) {
									dialog.close({ action });
								}
							},
						);
					});
				}
			}
		},
	};

	const button = new OO.ui.ButtonWidget({
		icon: this.formDescriptor.action === 'create' ? 'add' : 'edit',
		flags: [],
		classes: [],
		...(this.formDescriptor.popup_button_config || {}),
		label:
			JsonForms.Utilities.getNestedProp( [ 'popup_button_config', 'label' ], this.formDescriptor ) ||
			this.formDescriptor.title ||
			this.formDescriptor.name,
	});

	button.on('click', () => {
		this.popup = new JsonForms.Dialog(
			{
				size: this.formDescriptor.popup_size,
				title: this.formDescriptor.title || this.formDescriptor.name,
			},
			callbacks,
			this,
		);
		this.popup.open();
	});

	$(this.el).empty().append(button.$element);

	return new Promise((resolve) => {
		_resolveEditorReady = (value) => {
			resolve(value);
		};
	});
};

// inline form only
JsonFormsPageForm.prototype.onNavButton = function (editor) {
	const jsonEditor = editor.jsoneditor;
	const formEditor = jsonEditor.getEditor('root.form');

	// defined in the PageFormUI.json schema
	const booklet = formEditor.groupWidget.layout;

	const validateButton = jsonEditor.getEditor('root.footer.buttons.validate');
	const submitButton = jsonEditor.getEditor('root.footer.buttons.submit');
	const gobackButton = jsonEditor.getEditor('root.header.buttons.goback');

	const innerformEditor = jsonEditor.getEditor('root.form.editor');
	const innerEditor = innerformEditor.input.editor;
	
	if ( !innerEditor ) {
		console.error('editor not ready');
		return;
	}

	switch (editor.key) {
		case 'submit':
			{
				const jsonEditorValidationResults = jsonEditor.validate();
				const innerEditorValidationResults = innerEditor.validate();

				if (
					jsonEditorValidationResults.length ||
					innerEditorValidationResults.length
				) {
					if ( this.debug ) {
						console.log(
							'jsonEditorValidationResults',
							jsonEditorValidationResults,
						);
						console.log(
							'innerEditorValidationResults',
							innerEditorValidationResults,
						);
					}
					JsonForms.Alert('there are errors');
					return;
				} else {
					editor.disable();
					const optionsEditor = jsonEditor.getEditor('root.form.options');
					this.submitForm(innerEditor, optionsEditor)
						.then(() => editor.enable())
						.catch((err) => {
							console.error('API error:', err);
							editor.enable();
						});
				}
			}
			break;
		case 'goback':
			{
				booklet.setPage('root.form.main');
				validateButton.theme.toggle(validateButton.container, true);
				submitButton.theme.toggle(submitButton.container, false);
				gobackButton.theme.toggle(gobackButton.container, false);

				const summaryInput = jsonEditor.getEditor('root.footer.summary');
				const minorInput = jsonEditor.getEditor('root.footer.minor');

				if (summaryInput) {
					summaryInput.theme.toggle(summaryInput.container, false);
				}

				if (minorInput) {
					minorInput.theme.toggle(minorInput.container, false);
				}
			}
			break;

		case 'validate': {
			const innerEditorValidationResults = innerEditor.validate();
			if ( this.debug ) {
				console.log('innerEditorValidationResults', innerEditorValidationResults);
			}
			// the inner editor
			if (innerEditorValidationResults.length) {
				if ( this.debug ) {
					console.log(
						'innerEditorValidationResults',
						innerEditorValidationResults,
					);
				}
				JsonForms.Alert('there are errors');
				return;
			} else {
				booklet.setPage('root.form.options');
				validateButton.theme.toggle(validateButton.container, false);
				submitButton.theme.toggle(submitButton.container, true);
				gobackButton.theme.toggle(gobackButton.container, true);

				const summaryInput = jsonEditor.getEditor('root.footer.summary');
				const minorInput = jsonEditor.getEditor('root.footer.minor');

				if (summaryInput) {
					summaryInput.theme.toggle(summaryInput.container, true);
				}

				if (minorInput) {
					minorInput.theme.toggle(minorInput.container, true);
				}
			}
		}
	}
};

JsonFormsPageForm.prototype.submitForm = function (innerEditor, optionsEditor) {
	const structuredValue = innerEditor.getStructuredValue();

	const vars = {};
	for (const path in structuredValue.values) {
		vars[path] = structuredValue.values[path].value;
	}

	// Create a shallow copy to avoid mutating the original
	const formDescriptor = { ...this.formDescriptor };

	const substitutions = [
		'pagename_formula',
		'preload_wikitext',
		'return_url',
		'return_page',
	];
	for (const field of substitutions) {
		if (formDescriptor[field]) {
			formDescriptor[field] = this.processTemplate(
				formDescriptor[field],
				vars,
				{ replaceAngularBrackets: true },
			);
		}
	}

	if (!formDescriptor.return) {
		if (formDescriptor.return_url) {
			formDescriptor.return = 'url';
		} else if (formDescriptor.return_page) {
			formDescriptor.return = 'article';
		} else {
			formDescriptor.return = 'target';
		}
	}

	const metadata = {};
	const metadataKeys = ['show_infobox', 'infobox_position', 'infobox_template'];

	if (!formDescriptor.infobox) {
		formDescriptor.infobox = {};
	}

	for (const i of metadataKeys) {
		if (formDescriptor.infobox[i]) {
			metadata[i] = formDescriptor.infobox[i];
			delete formDescriptor.infobox[i];
		}
	}

	// *** submission data are arbitrary and depend on the
	// SubmitProcessor
	const data = {
		value: innerEditor.getValue(),
		options: optionsEditor.getValue(),
		structuredValue,
		formDescriptor,
		schemaId: innerEditor.schema.$id,
		config: mw.config.get('jsonforms'),
		metadata,

		// submit processor
		processor: 'PageForms',
	};

	const captchaEditor = this.editor.getEditor('root.captcha');
	if (captchaEditor) {
		data.options.captcha = captchaEditor.getValue();
	}
	if ( this.debug ) {
		console.log('data', data);
	}
	
/*
console.log('data', data);
	return new Promise( ( resolve, reject ) => {
resolve()
})
*/
	const payload = {
		data: JSON.stringify(data),
		action: 'jsonforms-submit-form',
	};

	// console.log('payload', payload);
	return new Promise((resolve, reject) => {
		new mw.Api()
			.postWithToken('csrf', payload)
			.done((thisRes) => {
				if ( this.debug ) {
					console.log('thisRes', thisRes);
				}
				let result = thisRes[payload.action].result;
				result = JSON.parse(result);
				if (result.errors && result.errors.length) {
					const config = {
						htmlMessage: mw.msg(
							'jsonforms-jsmodule-return-errors',
							result.errors.join(' ,'),
						),
						type: 'error',
					};
					resolve(false);
					if ( !this.isPopup ) {
						const nonModalDialog = new JsonForms.NonModalDialog();
						nonModalDialog.open(config);
					} else {
						JsonForms.Alert(config.htmlMessage)
					}
				} else {
					if (!formDescriptor.return) {
						formDescriptor.return = 'target';
					}
					switch (formDescriptor.return) {
						case 'none':
							{
								if ( this.isPopup ) {
									this.popup.close();
								}
								const nonModalDialog = new JsonForms.NonModalDialog();
								nonModalDialog.open({
									htmlMessage: result.message || result.returnMessage,
									type: 'success',
								});
								resolve(result);
								this.editor.destroy();
								this.createDefaultEditor().then((editor) => {});
							}
							break;
						case 'target':
						case 'article':
						case 'url':
							if (result.returnUrl === window.location.href) {
								window.location.reload();
							} else {
								window.location.href = result.returnUrl;
							}
							resolve(result);
							break;
					}
				}
			})
			.fail((thisRes) => {
				// eslint-disable-next-line no-console
				console.error('jsonforms-submit-form', thisRes);
				reject(thisRes);
			});
	});
};

(function ($) {
	$(() => {
		$('.jsonforms-form-wrapper').each(async function (index, el) {
			this.el = el;
			const data = $(el).data().formData;
			const jsonFormsPageForm = new JsonFormsPageForm(el, data);
			await jsonFormsPageForm.initialize();
			const editor = await jsonFormsPageForm.createDefaultEditor();
		});
	});
	// eslint-disable-next-line no-undef
})(jQuery);

