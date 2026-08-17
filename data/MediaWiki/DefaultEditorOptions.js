export default {
	validation: 'onsubmit', // onsubmit, always
	template: 'default',
	max_depth: 16,
	path_separator: '.',
	default_additional_properties: false,
	use_lazy_properties: 'threshold', // never, always, threshold
	lazy_properties_threshold: 6,
	remove_empty_properties: false,
	remove_false_properties: false,
	debug: false,
	callbacks: {
		enum_providers: {
			wikiList: function () {
				let cache = null;
				let pending = null;

				function parseBulletList(content) {
					const regex = /^\*\s+(.+)$/gm;
					const items = [];
					let match;

					while ((match = regex.exec(content)) !== null) {
						items.push(match[1]);
					}

					return items;
				}

				return {
					source: (jseditor, { item, watched }) => {
						if (cache) return cache;
						if (pending) return pending;

						if (
							!jseditor.schema['x-data'] ||
							!jseditor.schema['x-data'].article
						) {
							console.log(
								'A key "article" must be specified in an object with key "x-data" in the enum schema',
							);

							cache = [];
							pending = null;
							return cache;
						}

						const pageTitle = jseditor.schema['x-data'].article;

						pending = new mw.Api().get({
								action: 'query',
								prop: 'revisions',
								revslots: 'main',
								titles: pageTitle,
								formatversion: 2,
								rvprop: 'content',
							})
							.then((data) => {
								const pages = data.query?.pages || [];
								if (pages.length === 0 || pages[0].missing) {
									cache = [];
									pending = null;
									return cache;
								}
								const content = pages[0].revisions?.[0]?.content;
								cache = content ? parseBulletList(content) : [];
								pending = null;
								return cache;
							})
							.catch((error) => {
								console.error('Failed to get page content:', error);
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

			jsonSchemas: function () {
				let cache = null;
				let pending = null;

				return {
					source: (jseditor, { item, watched }) => {
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
		button: {},
		upload: {},
		converters: {},
	},
};
