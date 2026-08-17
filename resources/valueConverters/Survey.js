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
// use IIFE, this ensure name is scoped
(function () {
	function isObject(item) {
		return !!item && typeof item === 'object' && !Array.isArray(item);
	}

	function convertMatrixBuilderToSurvey(data) {
		if (!data || !data.items || !data.items.length) {
			return data;
		}

		const { items, cell } = data;
		const levelCount = items.length;

		const inputSchema = {
			type: typeof cell === 'string' ? 'boolean' : cell?.type || 'number',
			'x-input': typeof cell === 'string' ? cell : cell?.type || 'number',
			'x-input-config': cell?.['x-input-config'] || {},
		};

		// do not use definitions, otherwise a survery cannot
		// be inserted within a property with the schema builder
		// const definitions = {
		// 	input: inputSchema,
		// };

		const schema = {
			$schema: 'https://json-schema.org/draft/2020-12/schema',
			type: 'object',
			'x-layout': 'survey',
			'x-border': false,
			'x-data': data,
			'x-ui-schema': 'JsonSchema:SurveyBuilderLinear',
			// definitions,
			properties: {},
			required: [],
		};

		let current = schema.properties;
		let currentSchema = schema;

		for (let i = 0; i < items.length; i++) {
			const currentItem = items[i];
			const rows = currentItem.rows || [];
			const cols = currentItem.columns || [];
			const nextlevel = {};
			let lastSchema;

			for (let ii = 0; ii < rows.length; ii++) {
				const row = rows[ii];
				if (!row) {
					continue;
				}

				current[row.name] = {
					'x-layout': 'row',
					type: 'object',
					title: row.title,
					properties: nextlevel,
					required: [],
				};

				lastSchema = current[row.name];

				if (row.required) {
					currentSchema.required.push(row.name);
				}
			}

			current = nextlevel;
			currentSchema = lastSchema;
		}

		for (let i = 0; i < items.length; i++) {
			const currentItem = items[i];
			const cols = currentItem.columns || [];
			const isLast = i === levelCount - 1;
			const nextlevel = {};
			let lastSchema;

			for (let ii = 0; ii < cols.length; ii++) {
				const col = cols[ii];
				if (!col) {
					continue;
				}

				if (!isLast) {
					current[col.name] = {
						'x-layout': 'column',
						type: 'object',
						title: col.title,
						properties: nextlevel,
						required: [],
					};

					lastSchema = current[col.name];

					if (col.required) {
						currentSchema.required.push(col.name);
					}
				} else {
					// current[col.name] = {
					// 	$ref: '#/definitions/input',
					// };
					current[col.name] = inputSchema;
				}
			}

			current = nextlevel;
			currentSchema = lastSchema;
		}

		return schema;
	}

	function rebuildDataFromXData(surveySchema) {
		if (!surveySchema || !surveySchema['x-data']) {
			console.warn('No x-data found in schema');
			return null;
		}

		const sourceData = surveySchema['x-data'];

		if (!sourceData.items || !Array.isArray(sourceData.items)) {
			console.warn('Invalid source data in x-data');
			return null;
		}

		return JSON.parse(JSON.stringify(sourceData));
	}

	function validateConversion(originalData, rebuiltData) {
		const originalStr = JSON.stringify(originalData) || '';
		const rebuiltStr = JSON.stringify(rebuiltData) || '';

		return {
			match: originalStr === rebuiltStr,
			originalLength: originalStr.length,
			rebuiltLength: rebuiltStr.length,
			original: originalData,
			rebuilt: rebuiltData,
		};
	}

	function Survey(el, data) {}

	Survey.prototype.convertFrom = function (key, value) {
		// console.log('convertFrom', key, value);
		const rebuiltData = rebuildDataFromXData(value);
		const validation = validateConversion(value, rebuiltData);

		// if (validation.match) {
		//	return rebuiltData;
		// }
		return rebuiltData;
	};

	Survey.prototype.convertTo = function (key, value) {
		// console.log('convertTo', key, value);
		return convertMatrixBuilderToSurvey(value);
	};

	// attach to constructor
	JsonForms.ValueConverters.Survey = Survey;
})();

