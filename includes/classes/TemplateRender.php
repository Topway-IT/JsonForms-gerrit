<?php

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
 * along with JsonForms.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2026, https://wikisphere.org
 */

namespace MediaWiki\Extension\JsonForms;

use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Extension\Scribunto\Engines\LuaStandalone\LuaStandaloneEngine;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;

class TemplateRender extends BaseRender {
	private Parser $parser;

	/**
	 * @param Parser $parser
	 */
	public function setParser( $parser ) {
		$this->parser = $parser;
	}

	/**
	 * @param stdClass $data
	 * @param string $templatePrefix
	 * @return string
	 */
	public function render( $data ) {
		$templatePrefix = 'Template:' . $this->parameters['template'];

		if ( is_array( $data ) || is_object( $data ) ) {
			$children = $this->renderNode( $data, [], [], $templatePrefix );
		} else {
			$children = [];
		}

		$params = $this->createParams( '', $data, $children, '', $templatePrefix );

		return $this->processTemplate( $templatePrefix, $params );
	}

	/**
	 * @param string $text
	 * @return string
	 */
	public function recursiveTagParseFully( $text ) {
		return Parser::stripOuterParagraph(
			$this->parser->recursiveTagParseFully( $text ) );
	}

	/**
	 * @param string $key
	 * @param array $value
	 * @param array $children
	 * @param string $path
	 * @param string $templateName
	 * @return string
	 */
	public function createParams( $key, $value, $children, $path, $templateName ) {
		$params = [
			"key" => $key,
			"properties" => implode( ', ', array_keys( $children ) ),
			"count" => is_array( $value )
				? count( $value )
				: count( (array)$value ),
			"hasChildren" => count( $children ),
			"path" => $path,
			'templateName' => $templateName
		];

		$dataArray = is_array( $value ) ? $value : (array)$value;
		$paramsDisplay = $params;
		$paramsDisplay[ 'data' ] = $value;
		$params[ 'children' ] = implode( $children );

		foreach ( $children as $k => $v ) {
			if ( array_key_exists( $k, $params ) ) {
				$params[ '_' . $k ] = $params[$k];
				$paramsDisplay[ '_' . $k ] = $params[$k];
			}
			$params[ $k ] = $v;
			$paramsDisplay[ $k ] = is_scalar( $dataArray[$k] ) ? (string)$v : '<subitem>';
		}

		$params["params"] = "<details><pre>" .
			json_encode(
				$paramsDisplay,
				JSON_PRETTY_PRINT |
				JSON_UNESCAPED_UNICODE |
				JSON_UNESCAPED_SLASHES,
			) .
			"</pre></details>";

		return $params;
	}

	/**
	 * @param array $node
	 * @param array $path
	 * @param array $pathNoIndex
	 * @param string $templatePrefix
	 * @return array
	 */
	public function renderNode( $node, $path, $pathNoIndex, $templatePrefix ) {
		$ret = [];

		$schemaInfo = $this->getSchemaInfo( null, implode( '.', $path ) );

		foreach ( $node as $key => $value ) {
			$newPath = $path;
			$newPathNoIndex = $pathNoIndex;

			// Only add non-numeric keys to the path
			$newPath[] = $key;

			if ( !is_numeric( $key ) && is_string( $key ) ) {
				$newPathNoIndex[] = $key;
			} else {
				$newPathNoIndex[] = 'Item';
			}

			$pathStr = implode( ".", $newPath );
			$pathStrNoIndex = implode( ".", $newPathNoIndex );

			if ( empty( $schemaInfo['x-render-template'] ) ) {
				$templateName = $templatePrefix;
				if ( !empty( $newPath ) ) {
					$templateName .= "." . $pathStrNoIndex;
				}

			} else {
				$templateName = 'Template: ' . $schemaInfo['x-render-template'];
			}

			if ( is_array( $value ) || is_object( $value ) ) {
				$children = $this->renderNode(
					$value,
					$newPath,
					$newPathNoIndex,
					$templatePrefix,
				);

				$params = $this->createParams( $key, $value, $children, $pathStr, $templateName );
				$ret[$key] = $this->processTemplate( $templateName, $params );

			// Leaf
			} else {
				$params = [
					"key" => $key,
					"path" => $pathStr,
					"value" => $value,
					"type" => gettype( $value ),
					"hasChildren" => false,
					'templateName' => $templateName
				];
				$params[$key] = $value;

				$titleTemplate = TitleClass::newFromText(
					$templateName,
					NS_TEMPLATE,
				);

				if ( !$this->parameters['print_scalar'] ) {
					$ret[$key] = $this->processTemplate( $templateName, $params );

				} elseif ( $titleTemplate && $titleTemplate->isKnown() ) {
					$ret[$key] = $this->processTemplate( $templateName, $params );

				} else {
					$ret[$key] = $value;
				}
			}
		}

		return $ret;
	}

	/**
	 * credits Filburt
	 * Processes a template call with parameters for VisualData extension.
	 *
	 * This method extracts template parameters from a string, merges them with existing parameters,
	 * and expands the template using either Scribunto's Lua engine or the fallback method.
	 *
	 * @param string $titleStr The template name and additional parameters in the format
	 * "|template=My Template{{!}}param1=value1{{!}}param2=value2" for named parameters or
	 * "|template=My Template{{!}}value1{{!}}value2" for unnamed parameters.
	 * @param array $params Associative array of parameters from the VisualData query.
	 * @return string The expanded template content or a link to the template if it doesn't exist.
	 *
	 * details
	 * - Supports both named parameters (param=value) and unnamed parameters (value).
	 * - Named parameters are passed as-is, unnamed parameters are assigned numeric keys starting from 1.
	 * - If the template doesn't exist, a simple wiki link is returned.
	 * - Uses Scribunto's Lua engine if available, otherwise falls back to expandTemplate.
	 * - All parameter keys are cast to strings to ensure consistency.
	 */
	public function processTemplate( $titleStr, $params ) {
		$templateParts = explode( "|", $titleStr, 2 );
		$templateName = trim( $templateParts[0] );
		$additionalParamsString = $templateParts[1] ?? "";

		$additionalParams = [];
		if ( !empty( $additionalParamsString ) ) {
			$paramPairs = explode( "|", $additionalParamsString );
			foreach ( $paramPairs as $index => $pair ) {
				// Named parameters
				if ( strpos( $pair, "=" ) !== false ) {
					$kv = explode( "=", $pair, 2 );
					$additionalParams[trim( $kv[0] )] = trim( $kv[1] );

				// Unnamed parameters
				} else {
					$additionalParams[(string)( $index + 1 )] = trim( $pair );
				}
			}
		}

		$mergedParams = [];
		foreach ( $params as $key => $value ) {
			$mergedParams[(string)$key] = $value;
		}

		foreach ( $additionalParams as $key => $value ) {
			$mergedParams[(string)$key] = $value;
		}

		$titleTemplate = TitleClass::newFromText( $templateName, NS_TEMPLATE );

		if ( !$titleTemplate || !$titleTemplate->isKnown() ) {
			return "[[$templateName]]";
		}

		$args = $mergedParams;
		$titleText = $titleTemplate->getText();

		// @see \MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine::expandTemplate
		if (
			class_exists(
				"MediaWiki\Extension\Scribunto\Engines\LuaStandalone\LuaStandaloneEngine",
			)
		) {
			$luaStandaloneEngine = new LuaStandaloneEngine( [
				"parser" => $this->parser,
				"title" => $this->parser->getTitle(),
			] );
			$frameId = "empty";
			$text = $luaStandaloneEngine->expandTemplate(
				$frameId,
				$titleText,
				$args,
			);
			return $text[0] ?? "";
		}

		return $this->expandTemplate( $titleTemplate, $args );
	}

	/**
	 * @see \MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine
	 * @param Title|MediaWiki\Title\Title $title
	 * @param array $args
	 * @return array
	 */
	public function expandTemplate( $title, $args ) {
		$titleText = $title->getText();
		$frame = $this->parser->getPreprocessor()->newFrame();

		if (
			$frame->depth >= $this->parser->getOptions()->getMaxTemplateDepth()
		) {
			throw new MWException(
				"expandTemplate: template depth limit exceeded",
			);
		}

		if (
			MediaWikiServices::getInstance()
				->getNamespaceInfo()
				->isNonincludable( $title->getNamespace() )
		) {
			throw new MWException( "expandTemplate: template inclusion denied" );
		}

		[ $dom, $finalTitle ] = $this->parser->getTemplateDom( $title );
		if ( $dom === false ) {
			throw new MWException(
				"expandTemplate: template \"$titleText\" does not exist",
			);
		}

		if ( !$frame->loopCheck( $finalTitle ) ) {
			throw new MWException( "expandTemplate: template loop detected" );
		}

		$fargs = $this->parser->getPreprocessor()->newPartNodeArray( $args );
		$newFrame = $frame->newChild( $fargs, $finalTitle );
		// $text = $this->doCachedExpansion( $newFrame, $dom,
		// 	[
		// 		'frameId' => $frameId,
		// 		'frameId' => 'empty',
		// 		'template' => $finalTitle->getPrefixedDBkey(),
		// 		'args' => $fargs
		// 	]
		// );
		$text = $newFrame->expand( $dom );
		return $text;
	}
}
