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

function JsonFormsTests(el, data) {
	JsonFormsTests.super.call(this, el, data);

	this.previousKey = null;
	this.testContent = null;
	this.previousEditorKey = null;
}

// eslint-disable-next-line no-undef
OO.inheritClass(JsonFormsTests, JsonForms);

JsonFormsTests.prototype.initialize = async function () {
	await JsonFormsTests.super.prototype.initialize.call(this);

	if (!this.defaultOptions) {
		this.defaultOptions = {};
	}
	if (!this.defaultOptions.callbacks) {
		this.defaultOptions.callbacks = {};
	}

	this.defaultOptions.callbacks.enum_providers = Object.assign(
		{},
		this.defaultOptions.callbacks.enum_providers || {},
		{
			keywords: () => {
				return {
					source: async (jseditor, { item, watched }) => {
						// console.log('watched', watched);
						const path = watched['draft'];

						if (!path) {
							return [];
						}
						
						let contents = [];
						try {
							contents = await this.getGitHubContents({
								owner: 'json-schema-org',
								repo: 'JSON-Schema-Test-Suite',
								path: 'tests/' + path,
							});
						} catch (error) {
							console.error(error);
						}

						return contents.map((x) => x.name.split('.json')[0]);
					},
				};
			},
			testCase: () => {
				return {
					source: async (jseditor, { item, watched }) => {
						// console.log('testcase watched', watched);
						const path = watched['keyword'];

						await this.getTestData();
						// console.log('this.testContent', this.testContent);

						// console.log('this.testContent', this.testContent);
						if (!this.testContent) {
							return [];
						}

						return this.testContent.map((x) => x.description);
					},
				};
			},
			test: () => {
				return {
					source: async (jseditor, { item, watched }) => {
						// console.log('testcase watched', watched);
						const testCase = watched['testcase'];

						// console.log('testCase', testCase);

						// console.log('this.testContent', this.testContent);
						if (!this.testContent) {
							return [];
						}

						const test = (() => {
							for (const test of this.testContent) {
								if (test.description === testCase) {
									return test;
								}
							}

							return null;
						})();

						return test.tests.map((x) => x.description);
					},
				};
			},
		},
	);
};

JsonFormsTests.prototype.getTestData = async function () {
	const draftEditor = this.editor.getEditor('root.draft');
	const draftValue = draftEditor.getValue();

	const keywordEditor = this.editor.getEditor('root.keyword');
	const keywordValue = keywordEditor.getValue();
	
	if (!keywordValue) {
		return;
	}

	const key = draftValue + '.' + keywordValue;

	// console.log('key', key);

	if (key === this.previousKey) {
		return;
	}

	this.previousKey = key;

	let contents = [];
	try {
		contents = await this.getGitHubContents({
			owner: 'json-schema-org',
			repo: 'JSON-Schema-Test-Suite',
			path: 'tests/' + draftValue + '/' + keywordValue + '.json',
		});
	} catch (error) {
		console.error(error);
	}

	// console.log('contents', contents);

	const decodedContent = atob(contents.content);

	// console.log('decodedContent', decodedContent);

	// Parse JSON
	const jsonData = JSON.parse(decodedContent);

	// console.log('jsonData', jsonData);

	this.testContent = jsonData;
};

JsonFormsTests.prototype.onReady = async function (editor) {
	const draftEditor = editor.getEditor('root.draft');
	const keywordEditor = editor.getEditor('root.keyword');
	const testcaseEditor = editor.getEditor('root.testcase');
	const testEditor = editor.getEditor('root.test');

	this.editor.watch('root.draft', (editor) => {
		// console.log('^^draft', editor.getValue());
	});
	this.editor.watch('root.keyword', (editor) => {
		// console.log('^^keyword', editor.getValue());
	});

	this.editor.watch('root.testcase', async (editor) => {
		// console.log('^^testcase', editor.getValue());
	});
	this.editor.watch('root.test', (editor) => {
		// console.log('^^test', editor.getValue());
	});
};

JsonFormsTests.prototype.onChange = async function (editor) {
	const draftEditor = editor.getEditor('root.draft');
	const draftValue = draftEditor.getValue();

	const keywordEditor = editor.getEditor('root.keyword');
	const keywordValue = keywordEditor.getValue();

	const testcasedEditor = editor.getEditor('root.testcase');
	const testcasedValue = testcasedEditor.getValue();

	const testEditor = editor.getEditor('root.test');
	const testValue = testEditor.getValue();

	if (!draftValue || !keywordValue || !testcasedValue || !testValue) {
		return;
	}

	const key = [draftValue, keywordValue, testcasedValue, keywordValue].join(
		'.',
	);

	// console.log('key', key);

	if (key === this.previousEditorKey) {
		return;
	}
	const testcase = (() => {
		for (const testcase of this.testContent) {
			if (testcase.description === testcasedValue) {
				return testcase;
			}
		}

		return null;
	})();

	if ( !testcase ) {
		return;
	}

	const test = (() => {
		for (const test of testcase.tests) {
			if (test.description === testValue) {
				return test;
			}
		}

		return null;
	})();
	
	if ( !test ) {
		return;
	}

	// console.log('test', test);

	const editorEditor = editor.getEditor('root.editor');

	// console.log('editorEditor', editorEditor);
	//console.log('editorEditor', editorEditor.input);

	// const el = document.createElement('div')

	$(editorEditor.container).empty();
	const el = $(editorEditor.container).get(0);
	// console.log('el', el);

	const initCconfig = {
		schema: testcase.schema,
		schemaName: null,
		startval: test.data,
	};
	

	const jsonForms = new JsonForms(el, initCconfig);

	const config = {
		validation: 'always',
		debug: true
	};
	const newEditor = jsonForms.createDefaultEditor(config);
};

JsonFormsTests.prototype.getGitHubContents = async function (config) {
	const url = `https://api.github.com/repos/${config.owner}/${config.repo}/contents/${config.path}`;

	// console.log(url);
	try {
		const response = await fetch(url, {
			headers: {
				Accept: 'application/vnd.github.v3+json',
			},
		});

		if (!response.ok) {
			throw new Error(`HTTP error! status: ${response.status}`);
		}

		const data = await response.json();
		return data;
	} catch (error) {
		console.error('Error fetching repository contents:', url, error);
		throw error;
	}
};

(function ($) {
	$(() => {
		// console.log(' mw.config', mw.config);

		$('.jsonforms-form-wrapper').each(async function (index, el) {
			this.el = el;
			const data = $(el).data().formData;

			// console.log('data', data);

			const jsonFormsTests = new JsonFormsTests(el, data);

			await jsonFormsTests.initialize();
			const editor = jsonFormsTests.createDefaultEditor();

			editor.on('ready', () => {
				jsonFormsTests.onReady(editor);
			});

			editor.on('change', () => {
				jsonFormsTests.onChange(editor);
			});
		});
	});
	// eslint-disable-next-line no-undef
})(jQuery);

