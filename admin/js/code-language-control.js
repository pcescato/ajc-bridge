( function () {
	'use strict';

	var el                        = wp.element.createElement;
	var Fragment                  = wp.element.Fragment;
	var addFilter                 = wp.hooks.addFilter;
	var InspectorControls         = wp.blockEditor.InspectorControls;
	var PanelBody                 = wp.components.PanelBody;
	var SelectControl             = wp.components.SelectControl;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;

	var LANGUAGE_OPTIONS = [
		{ value: '',           label: '— (none)' },
		{ value: 'php',        label: 'PHP' },
		{ value: 'javascript', label: 'JavaScript' },
		{ value: 'typescript', label: 'TypeScript' },
		{ value: 'bash',       label: 'Bash' },
		{ value: 'shell',      label: 'Shell' },
		{ value: 'html',       label: 'HTML' },
		{ value: 'css',        label: 'CSS' },
		{ value: 'sql',        label: 'SQL' },
		{ value: 'python',     label: 'Python' },
		{ value: 'yaml',       label: 'YAML' },
		{ value: 'json',       label: 'JSON' },
		{ value: 'markdown',   label: 'Markdown' },
	];

	/**
	 * Rebuild className: strip all language-* classes, optionally add new one.
	 *
	 * @param {string|undefined} className Existing className attribute value.
	 * @param {string}           lang      Selected language (empty = none).
	 * @return {string} Updated className string.
	 */
	function updateLanguageClass( className, lang ) {
		var classes = ( className || '' ).split( ' ' ).filter( function ( cls ) {
			return cls.length > 0 && ! cls.match( /^language-/ );
		} );

		if ( lang ) {
			classes.push( 'language-' + lang );
		}

		return classes.join( ' ' );
	}

	/**
	 * Extract current language value from className.
	 *
	 * @param {string|undefined} className Existing className attribute value.
	 * @return {string} Language slug or empty string.
	 */
	function getLanguageFromClass( className ) {
		if ( ! className ) {
			return '';
		}

		var match = className.match( /\blanguage-([\w-]+)\b/ );

		return match ? match[ 1 ] : '';
	}

	var withCodeLanguageControl = createHigherOrderComponent(
		function ( BlockEdit ) {
			return function ( props ) {
				if ( props.name !== 'core/code' ) {
					return el( BlockEdit, props );
				}

				var className   = props.attributes.className;
				var currentLang = getLanguageFromClass( className );

				function onChangeLanguage( lang ) {
					props.setAttributes( {
						className: updateLanguageClass( className, lang ),
					} );
				}

				return el(
					Fragment,
					null,
					el( BlockEdit, props ),
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: 'Syntax highlighting' },
							el( SelectControl, {
								label:    'Language',
								value:    currentLang,
								options:  LANGUAGE_OPTIONS,
								onChange: onChangeLanguage,
							} )
						)
					)
				);
			};
		},
		'withCodeLanguageControl'
	);

	addFilter(
		'editor.BlockEdit',
		'ajc-bridge/code-language-control',
		withCodeLanguageControl
	);
} )();
