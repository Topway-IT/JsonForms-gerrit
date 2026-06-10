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
 * @copyright Copyright ©2025-2026, https://wikisphere.org
 */

/* eslint-disable es-x/no-rest-spread-properties */
/* eslint-disable camelcase */
/* eslint-disable no-console */
/* eslint-disable no-unused-vars */

function JsonForms(el, data) {
	this.moduleCache = new Map();
	this.el = el;
	this.schema = data.schema;
	this.schemaName = data.schemaName;
	this.startval = data.startval;
	this.editor = null;

	this.data = data;
	// @TODO add upload providers
}

JsonForms.prototype.initialize = async function () {
	// console.log('this.data.editorOptions',defaultOptions)
	this.editorOptions = await this.getModule(this.data.editorOptions);
	this.editorScript = await this.getModule(this.data.editorScript);

	// console.log('this.editorOptions',this.editorOptions)

	// this.enumProviders = this.formatProviders(JsonForms.enumProviders);
	this.enumProviders = JsonForms.enumProviders;

	// console.log('this.enumProviders',this.enumProviders)

	this.autocompleteProviders = JsonForms.autocompleteProviders;

	const UISchemaConverters = new JsonForms.UISchemaConverters();

	// console.log('defaultOptions',defaultOptions)

	const defaultOptions = this.editorOptions || {};

	// const defaultOptions = JSON.parse(JSON.stringify(this.editorOptions));

	// console.log('defaultOptions',defaultOptions)

	defaultOptions.callbacks = defaultOptions.callbacks || {};

	defaultOptions.callbacks.enum_providers = {
		...this.enumProviders,
		...((defaultOptions.callbacks && defaultOptions.callbacks.enum_providers) ||
			{}),
	};

	defaultOptions.callbacks.autocomplete_providers = {
		...this.autocompleteProviders,
		...((defaultOptions.callbacks &&
			defaultOptions.callbacks.autocomplete_providers) ||
			{}),
	};

	defaultOptions.callbacks.ui_schema_converters = {
		...UISchemaConverters.converters,
		...((defaultOptions.callbacks &&
			defaultOptions.callbacks.ui_schema_converters) ||
			{}),
	};

	this.defaultOptions = defaultOptions;
};

JsonForms.prototype.createDefaultEditor = function (config = {}) {
	this.createEditor(this.el, {
		jsonFormsInstance: this,
		schema: this.schema,
		schemaName: this.schemaName,
		startval: this.startval,
		...config,
	});

	return this.editor;
};

/*
// @see KnowledgeGraph.js
JsonForms.prototype.getModule = async function (str) {
	if (this.moduleCache.has(str)) {
		return this.moduleCache.get(str);
	}

	try {
		const module = await import(`data:text/javascript;base64,${btoa(str)}`);
		const result = module.default ?? null;
		this.moduleCache.set(str, result);
		return result;
	} catch (err) {
		console.error('Failed to load module:', err);
		return null;
	}
}
*/
JsonForms.prototype.getModule = async function (str) {
	const cacheKey = typeof str === 'string' ? str.slice(0, 100) : str;

	if (this.moduleCache.has(cacheKey)) {
		return this.moduleCache.get(cacheKey);
	}

	if (typeof str !== 'string') {
		return str;
	}

	let url = null;
	try {
		const blob = new Blob([str], { type: 'application/javascript' });
		url = URL.createObjectURL(blob);

		// eslint-disable-next-line es-x/no-dynamic-import
		const module = await import(url);
		const result = module.default || null;
		this.moduleCache.set(cacheKey, result);
		return result;
	} catch (err) {
		console.error('Failed to load module:', err);
		return null;
	} finally {
		if (url) {
			URL.revokeObjectURL(url);
		}
	}
};

// use as schema loader - location
JsonForms.prototype.getBasePath = function () {
    const server = mw.config.get('wgServer');

    // "/wiki/$1" or "/index.php/$1"
    const articlePath = mw.config.get('wgArticlePath');
    
    // "/wiki/" or "/index.php/"
    const basePath = articlePath.replace('$1', '');
    return server + basePath;    
}

// use as schema loader - fetchUrl
JsonForms.prototype.MWSchemaUrl = function (schemaName) {
	if (schemaName.indexOf('#') === -1) {
		schemaName = schemaName.split('#')[0]
	}

	// OR
	// return mw.config.get('jsonforms.schemaPath') + schemaName;
	const mwBaseUrl = mw.config.get('wgServer') + mw.config.get('wgScript');
	return `${mwBaseUrl}?title=${schemaName}&action=raw`;
};

JsonForms.prototype.isMWSchema = function (maybeUrl, fileBase) {
// filebase is from core -> location

	// console.log('isMWSchema config', mw.config);
	// console.log('maybeUrl', maybeUrl);
	// console.log('fileBase', fileBase);

	if (JsonForms.Utilities.hasProtocol(maybeUrl)) {
		return false;
	}
	if (!fileBase) {
		return true;
	}
	
	const basePath = this.getBasePath();
	
	// console.log('basePath', basePath);
	
	return fileBase.startsWith(basePath) || basePath.startsWith(fileBase);

};

JsonForms.prototype.processSchema = function (schema) {
	//  console.log('fetchSchema',schema)
	const payload = {
		action: 'jsonforms-process-schema',
		format: 'json',
		schema: JSON.stringify(schema),
	};

	return new Promise((resolve, reject) => {
		new mw.Api()
			.post(payload)
			.done((thisRes) => {
				let result = thisRes[payload.action].result;
				result = JSON.parse(result);
				resolve(result);
			})
			.fail((error, errorCode) => {
				console.error('API call failed - error:', error);
				console.error('Error code:', errorCode);
				reject(error);
			});
	}).catch((err) => {
		console.error('API call failed:', err);
		throw err;
	});
};

JsonForms.prototype.fetchSchema = function (schema) {
	//  console.log('fetchSchema',schema)
	const payload = {
		action: 'jsonforms-fetch-schema',
		format: 'json',
		schema,
	};

	// console.log('payload',payload)
	return new Promise((resolve, reject) => {
		new mw.Api()
			.get(payload)
			.done((thisRes) => {
				// console.log('thisRes', thisRes);
				let result = thisRes[payload.action].result;
				result = JSON.parse(result);
				// console.log('result', result);
				resolve(result);
			})
			.fail((error, errorCode) => {
				console.error('API call failed - error:', error);
				console.error('Error code:', errorCode);
				reject(error);
			});
	}).catch((err) => {
		console.error('API call failed:', err);
		throw err;
	});
};

JsonForms.prototype.getEditor = function () {
	return this.editor;
};

JsonForms.prototype.createEditor = function (el, config) {
	// eslint-disable-next-line no-undef
	JFEditor.defaults.options = this.defaultOptions;

	// eslint-disable-next-line no-undef
	this.editor = new JFEditor(el, {
		schemaSelector: null,
		...config,
		ajax: true,
		jsonFormsInstance: this,
	});

	if (typeof this.editorScript === 'function') {
		const updateEditorCallBack = (thisConfig) => {
			this.createEditor(this.el, { ...config, ...thisConfig });
		};
		this.editorScript(this.editor, this.config, updateEditorCallBack);
	}

	return this.editor;
};

JsonForms.prototype.processTemplate = function (str, vars, options = {}) {
	// Match patterns like <user.name> or <count>
	const regex = /<([^>]+)>/g;

	return str.replace(regex, (match, path) => {
		const trimmedPath = path.trim();
		return vars[trimmedPath] !== undefined ? vars[trimmedPath] : '';
	});
};

/*
JsonForms.prototype.processTemplate = function (str, vars, options = {}) {
	if ( options.replaceAngularBrackets ) {
		str = str.replace('<', '{{').replace('>', '}}');
	}

	const template = this.editor.compileTemplate( str );
	return this.editor.getTemplateResult(
		template,
		vars,
	);
};
*/

window.JsonForms = JsonForms;

(function ($) {
	$(() => {
		function resizeTreeSidePanel() {
			// const actualHeight = secondColumnContent.scrollHeight;

			const leftSelector =
				'.jsonforms-treewidget.oo-ui-menuLayout-showMenu .oo-ui-menuLayout-menu';
			const rightSelector =
				'.jsonforms-treewidget.oo-ui-menuLayout-showMenu .oo-ui-menuLayout-content';

			const $left = $(leftSelector);
			const $right = $(rightSelector);

			if (!$left[0] || !$right[0]) {
				return;
			}

			const leftRect = $left[0].getBoundingClientRect();
			const containerRect = $right[0].getBoundingClientRect();

			const viewportHeight = $(window).height();
			const toViewport = viewportHeight - leftRect.top;
			const toContainer = containerRect.bottom - leftRect.top;
			let available = Math.min(toViewport, toContainer);
			available = Math.max(0, available);

			$left.css('max-height', available + 'px');
		}

		$(window).on('scroll resize', resizeTreeSidePanel);
		resizeTreeSidePanel();
	});

	// eslint-disable-next-line no-undef
})(jQuery);

