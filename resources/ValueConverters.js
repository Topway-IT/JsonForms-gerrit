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
 
// use IIFE, this ensure name is scoped

/* global JsonForms */
(function () {
	function Converters() {
		// key is lower case
		this.converters = {
			survey: new JsonForms.ValueConverters.Survey(),
			newPropertyWithOptions: this.newPropertyWithOptions.bind(this)(),
			newProperty: this.newProperty.bind(this)(),
			newPropertyMeta: this.newPropertyMeta.bind(this)(),
			newSlot: this.newSlot.bind(this)(),
			newSchema: this.newSchema.bind(this)(),
		};
	}

	Converters.prototype.parseTargetSchema = function (value) {
		if (!value) {
			return {};
		}
		const ret = JSON.parse(value);
		if (!ret) {
			return {};
		}
		return ret;
	};

	Converters.prototype.newSchema = function () {
		const self = this;

		return {
			convertFrom: function (key, value) {},
			convertTo: function (key, value) {
				// console.log('value', value);
				// targetSchema is determined by additionalProperties schema
				const targetSchema = value.targetSchema;
				return {
					'x-data': { ...value, name: 'selectedSchema' },
					...self.parseTargetSchema(targetSchema),
					default: {
						schemaName: value.schema,
						// editor: value.selectedSchema?.editor ?? undefined
					},
				};
			},
		};
	};

	Converters.prototype.newSlot = function () {
		const self = this;

		return {
			convertFrom: function (key, value) {},
			convertTo: function (key, value) {
				const targetSchema = value.targetSchema;
				return {
					...self.parseTargetSchema(targetSchema),
					'x-data': value,
					'x-rename': false,
					default: {
						role: value.name,
					},
				};
			},
		};
	};

	Converters.prototype.newPropertyMeta = function () {
		const self = this;

		return {
			convertFrom: function (key, value) {},
			convertTo: function (key, value) {
				if (value.type === 'survey') {
					/*
				OR:
				return {
						'x-data': value,
						allOf: [
							{
								$ref: '#/definitions/survey',
							},
						],
					};
				*/
					return {
						'x-data': value,
						allOf: [
							{
								$ref: 'JsonSchema:SurveyBuilderLinear',
							},
						],
					};
				}
				return {
					'x-data': value,
					allOf: [
						{
							$ref: '#/definitions/schema',
						},
						{
							default: {
								type: value.type !== 'multiple' ? value.type : [],
							},
						},
					],
				};
			},
		};
	};

	Converters.prototype.newProperty = function () {
		const self = this;

		return {
			convertFrom: function (key, value) {},
			convertTo: function (key, value) {
				const targetSchema = value.targetSchema;
				return {
					...self.parseTargetSchema(targetSchema),
					'x-data': value,
				};
			},
		};
	};

	Converters.prototype.newPropertyWithOptions = function () {
		const self = this;

		return {
			convertFrom: function (key, value) {},
			convertTo: function (key, value) {
				const type = value.type || 'text';
				const options = value.options || {};

				if (value.multiple) {
					let inputName = null;
					switch (type) {
						case 'text':
							inputName = 'tagmultiselect';
							break;
					}

					const thisOptions = { 'x-input': inputName };
					return {
						'x-data': value,
						type: 'array',
						items: self.convertTo(key, {
							...value,
							type,
							multiple: false,
							options: thisOptions,
						}),
					};
				}

				switch (type) {
					case 'time':
					case 'email':
					case 'date':
						return {
							'x-data': value,
							type: 'string',
							format: type,
							...options,
						};

					case 'text':
					case 'textarea':
					case 'tel':
					case 'url':
					case 'color':
					case 'datetime-local':
					case 'json':
					case 'range':
						return {
							'x-data': value,
							type: 'string',
							'x-format': type,
							...options,
						};

					case 'number':
					case 'integer':
					case 'boolean':
						return { 'x-data': value, type };

					case 'object':
					case 'subitem':
						return {
							'x-data': value,
							type: 'object',
							additionalProperties: true,
						};

					default:
						throw new Error(`Unsupported type: ${type}`);
				}
			},
		};
	};

	JsonForms.ValueConverters = Converters;
})();

