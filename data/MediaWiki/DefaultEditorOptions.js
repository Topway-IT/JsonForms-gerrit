export default {
	show_errors: 'never',
	template: 'default',
	max_depth: 16,
	use_default_values: true,
	lazyPropertiesThreshold: 6,
	remove_empty_properties: true,
	remove_false_properties: false,
	callbacks: {
		enum_providers: {
			jsonSchemas: function () {
				let cache = null;
				let pending = null;

				return {
					source: async () => {
						if (cache) return cache;
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
								return cache;
							});

						return pending;
					},
					filter: (jseditor, { item, watched }) => {
						return true;
					},
					title: (jseditor, { item, watched }) => item.text,
					value: (jseditor, { item, watched }) => item.value,
				};
			},
		},
		autocomplete_providers: {
			jsonSchemas: function () {
				let cache = null;
				let pending = null;

				return {
					search: async (jseditor_editor, input) => {
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
					getResultValue: (jseditor_editor, result) => result.value,
					renderResult: (jseditor_editor, result, props) => result.text,
				};
			},
		},
		template: {},
		upload: {},
		ui_schema_converters: {},
	},
};

