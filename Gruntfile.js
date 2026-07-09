/* eslint-env node, es6 */
module.exports = function ( grunt ) {
	const conf = grunt.file.readJSON( 'extension.json' );

	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-eslint' );

	grunt.initConfig( {
		banana: conf.MessagesDirs,
		eslint: {
			options: {
				cache: true
			},
			all: [
				'**/*.js{,on}',
				'!node_modules/**',
				'!vendor/**',
				'!data/**',
				'!resources/datatables/**',
				'!resources/editor/**',
				'!resources/jsoneditor/**',
				'!resources/libs/**',
				'!resources/OOJSPlus/**'
			]
		},
		stylelint: {
			options: {
				cache: true
			},
			all: [
				'**/*.{css,less}',
				'!node_modules/**',
				'!vendor/**',
				'!resources/datatables/**',
				'!resources/editor/**',
				'!resources/jsoneditor/**',
				'!resources/libs/**',
				'!resources/OOJSPlus/**'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
