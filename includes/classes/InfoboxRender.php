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

use MediaWiki\Extension\JsonForms\Aliases\Linker as LinkerClass;
use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\MediaWikiServices;
use User;

class InfoboxRender extends BaseRender {

	/**
	 * @param array $node
	 * @param string $path
	 * @param string $pathNoIndex
	 * @param int $level
	 * @return string
	 */
	public function renderNode( $node, $path, $pathNoIndex, $level = 0 ) {
		$ret = "";
		$nextLevel = $level + 1;

		// Handle both objects and arrays
		if ( is_object( $node ) ) {
			$nodeArray = get_object_vars( $node );
		} elseif ( is_array( $node ) ) {
			$nodeArray = $node;
		} else {
			return $ret;
		}

		foreach ( $nodeArray as $key => $value ) {
			$newPath = $path;
			$newPathNoIndex = $pathNoIndex;

			$newPath[] = $key;
			if ( !is_numeric( $key ) && is_string( $key ) ) {
				$newPathNoIndex[] = $key;
			}

			$pathStr = implode( ".", $newPath );

			if ( is_array( $value ) || is_object( $value ) ) {
				$childrenHtml = $this->renderNode(
					$value,
					$newPath,
					$newPathNoIndex,
					$nextLevel,
				);
				$ret .= $this->renderContainer(
					$key,
					$value,
					$childrenHtml,
					$level,
					$pathStr,
				);
			} else {
				$ret .= $this->renderLeaf(
					$key,
					$value,
					gettype( $value ),
					$pathStr,
					is_array( $node )
				);
			}
		}

		return $ret;
	}

	/**
	 * Get display label for a key (unified logic)
	 *
	 * @param string $key
	 * @param string $path
	 * @return string
	 */
	private function getDisplayKey( $path, $key ) {
		$schemaInfo = $this->getSchemaInfo( $path, !is_numeric( $key ) ? $key : null );

		// $schemaInfo are already escaped
		if ( !empty( $schemaInfo["title"] ) ) {
			return $schemaInfo["title"];
		}

		// For numeric keys (array items)
		if ( is_numeric( $key ) ) {
			return "Item " . ( $key + 1 );
		}

		return htmlspecialchars( $key );
	}

	/**
	 * @param array $processedData
	 * @return string
	 */
	public function render( $processedData ) {
		$childrenHtml = $this->renderNode( $processedData, [], [], 0 );
		return $this->rootContainer( $processedData, $childrenHtml );
	}

	/**
	 * @param stdClass $value
	 * @param string $childrenHtml
	 * @return string
	 */
	private function rootContainer( $value, $childrenHtml ) {
		$type = gettype( $value );
		$isArray = $type === "array";
		$schemaName = $this->parameters['schema'];

		$schema = \JsonForms::getSourceSchema( $schemaName, 'JsonSchema' );
		$count = is_array( $value ) ? count( $value ) : null;

		$countDisplay = "";
		if ( $count !== null ) {
			$countDisplay = '<span class="infobox-count">(' . $count . ")</span>";
		}

		if ( $this->user->isAllowed( 'jsonforms-caneditdata' ) ) {
			$editUrl = $this->title->getLocalURL( "action=jsonedit" );
			$edit = new \OOUI\ButtonWidget( [
				"icon" => "edit",
				'flags' => [ 'progressive' ],
				'framed' => false,
				"href" => $editUrl
			] );
		}

		$title = TitleClass::newFromText( 'JsonSchema:' . $schemaName );
		$link = LinkerClass::link( $title, $schema->title ?? $schemaName );

		if ( $isArray ) {
			$ret =
			'<div class="toccolours infobox-array">
<div class="infobox-array-header infobox-root"><span class="infobox-header-left">' .
'<span class="infobox-key">' .
				$link .
			'</span>' .
			'<span class="infobox-meta"><span class="infobox-type">array</span>' .
				$countDisplay .
			'</span></span><span class="infobox-header-right"><span class="infobox-edit">' .
				$edit .
			'</span>
</span>
</div>
<div class="infobox-array-content">' .
				$childrenHtml .
			'</div>
		</div>';

		} else {
			$ret =
			'<div class="toccolours infobox-object">
<div class="infobox-object-header infobox-root"><span class="infobox-header-left">' .
	'<span class="infobox-key">' .
					$link .
				'</span>' .
			'<span class="infobox-meta"><span class="infobox-type">object</span>' .
				$countDisplay .
			'</span></span><span class="infobox-header-right"><span class="infobox-edit">' .
				$edit .
			'</span>
</span>
</div>
<div class="infobox-object-content">' .
				$childrenHtml .
			'</div>
		</div>';
		}

		return $ret;
	}

	/**
	 * @param string $key
	 * @param stdClass $value
	 * @param string $childrenHtml
	 * @param int $level
	 * @param string $path
	 * @return string
	 */
	private function renderContainer( $key, $value, $childrenHtml, $level, $path ) {
		$displayKey = $this->getDisplayKey( $path, $key );
		$count = is_array( $value ) ? count( $value ) : null;
		$type = gettype( $value );
		$isArray = $type === "array";

		$schemaInfo = $this->getSchemaInfo( $path, $key );

		$layoutHint = "";
		if ( !empty( $schemaInfo["layout"] ) ) {
			$layoutHint = '<span class="infobox-layout-hint" title="Layout: ' .
				htmlspecialchars( $schemaInfo["layout"] ) .
				'">📐</span>';
		}

		$uniqueHint = "";
		if ( $isArray && $schemaInfo["uniqueItems"] ) {
			$uniqueHint =
			'<span class="infobox-unique-hint" title="Unique values only">🔒</span>';
		}

		$countDisplay = "";
		if ( $count !== null ) {
			$countDisplay =
			'<span class="infobox-count">(' . $count . ")</span>";
		}

		// Only add collapsible classes and toggle for level > 0
		$isCollapsible = $level > 0;
		$collapsibleClass = $isCollapsible ? "mw-collapsible mw-collapsed" : "";
		$collapsibleContentClass = $isCollapsible ? "mw-collapsible-content" : "";

		// Add expand/collapse toggle for collapsible sections
		$toggleHtml = "";
		if ( $isCollapsible ) {
			$toggleHtml = '';
		}

		if ( $isArray ) {
			$ret =
			'<div class="toccolours infobox-array ' . $collapsibleClass . '">
<div class="infobox-array-header">' . $toggleHtml .
	'<span class="infobox-key">' .
					$displayKey .
				'</span>' .
				$layoutHint .
				$uniqueHint .
				'<span class="infobox-meta"><span class="infobox-type">array</span>' .
					$countDisplay .
				'</span>
</div>
<ul class="infobox-array-content ' . $collapsibleContentClass . '">' .
				$childrenHtml .
			'</ul>' .
		'</div>';

		} else {
			$ret =
			'<div class="toccolours infobox-object ' . $collapsibleClass . '">
<div class="infobox-object-header">' . $toggleHtml .
	'<span class="infobox-key">' .
					$displayKey .
				'</span>' .
				$layoutHint .
				'<span class="infobox-meta"><span class="infobox-type">object</span>' .
					$countDisplay .
				'</span>
</div>
<div class="infobox-object-content ' . $collapsibleContentClass . '">' .
				$childrenHtml .
			'</div>
		</div>';
		}

		return $ret;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @param string $type
	 * @param string $path
	 * @param bool $isArrayItem
	 * @return string
	 */
	private function renderLeaf( $key, $value, $type, $path, $isArrayItem ) {
		$displayKey = $this->getDisplayKey( $path, $key );
		$escapedType = htmlspecialchars( $type );

		$schemaInfo = $this->getSchemaInfo( $path, $key );
		if ( !$schemaInfo["type"] ) {
			$schemaInfo["type"] = strtolower( $type );
		}

		if ( !empty( $schemaInfo["x-hidden"] ) || $schemaInfo["format"] === 'hidden' ) {
			return '';
		}

		$displayValue = $this->formatValue( $value, $schemaInfo );
		$descriptionAttr = "";

		// @TODO strip tags if format is html
		if ( !empty( $schemaInfo["description"] ) ) {
			$descriptionAttr = ' title="' . $schemaInfo["description"] . '" ';
		}
		$format = !empty( $schemaInfo["format"] ) ? $schemaInfo["format"] : $schemaInfo["type"];

		$formatHint = "";
		// if ( !empty( $schemaInfo["format"] ) ) {
			$icon = $this->getFormatIcon( $format );
			
			if ( $icon ) {
				$iconWidget = new \OOUI\IconWidget( [
					'icon' => $icon,
					'classes' => [ 'infobox-format-hint' ],
					'title' => 'Format: ' . htmlspecialchars( $format )
				] );
				$formatHint = $iconWidget;
			} else {
				$icon = $this->getFormatIconHtml( $format );
				$formatHint = '<span class="infobox-format-hint">' . $icon . '</span>';
			}
		// }

		if( $isArrayItem ) {
			return
'<li class="infobox-value" ' . $descriptionAttr . '>' .
			$formatHint .
			$displayValue .	'</li>';
		}

		return '<div class="infobox-row"' .
			$descriptionAttr .
			'><div class="infobox-label">' .
			$formatHint .
			'<span class="infobox-key-label">' .
			$displayKey .
			'</span>' .
			'</div>
<div class="infobox-value">' .
			$displayValue .
			'
</div>
</div>';
	}

	/**
	 * Get OOUI icon for format https://doc.wikimedia.org/oojs-ui/master/demos/?page=icons&theme=wikimediaui&direction=ltr&platform=desktop
	 *
	 * @param string $format
	 * @return string|null
	 */
	private function getFormatIcon( $format ) {
		switch ( $format ) {
			case "email":
			case 'idn-email':
				return 'message';

			case "url":
			case 'uri-reference':
				return 'link';

			case 'uri':
			case 'iri':
				return 'linkExternal';

			case 'ipv4':
			case 'ipv6':
				return 'network';

			case 'json-pointer':
			case 'relative-json-pointer':
				return 'article';

			case "autocomplete":
				return 'search';

			case "captcha":
				return 'lock';

			case "color":
				return 'palette';

			case "json":
			case 'uri-template':
				return 'code';

			case "hidden":
				return 'eyeClosed';

			case "file":
				return 'attachment';

			case "date":
			case "month":
				return 'calendar';

			case "pagename":
				return 'article';

			case "rating":
				return 'star';

			case "user":
				return 'userAvatarOutline';

			case "wikitext":
				return 'wikiText';

			case "category":
				return 'tag';
		}
		
		return null;
	}
	
	/**
	 * @param string $format
	 * @return string
	 */
	private function getFormatIconHtml( $format ) {
		switch ( $format ) {
			case "textarea":
			case "html":
			case "markdown":
				return "📄";

			case "string":
			case "text":
				return "🔤";

			case "email":
			case 'idn-email':
				return "✉️";

			case 'hostname':
			case 'idn-hostname':
				return '🖥️';

			case 'ipv4':
			case 'ipv6':
				return '🌐'; // U+1F310 Globe with Meridians

			case "url":
			case "uri":
			case "iri":
				return "🔗";

			case 'uri-reference':
				return '↗️';

			case 'uri-template':
				return '⚙️';

			case 'json-pointer':
				return '👉';

			case 'relative-json-pointer':
				 return '↪️';

			case "tel":
				return "📞";

			case "number":
			case "integer":
				return "#️⃣";	// 🔢

			case "autocomplete":
				return "🔍";

			case "captcha":
				return "🤖";

			case "color":
				return "🎨";

			case "json":
				return "📋";

			case "hidden":
				return "🙈";

			case "file":
				return "📎";

			case "month":
				return "📅";

			case "pagename":
				return "📄";

			case "rating":
				return "⭐";

			case "user":
				return "👤";

			case "uuid":
				return "🆔";

			case "wikitext":
				return "📝";

			default:
				return "▪️";
		}
	}

	/**
	 * Format a value based on its type and schema format
	 *
	 * @param mixed $value
	 * @param array $schemaInfo
	 * @return string
	 */
	private function formatValue( $value, $schemaInfo ) {
		$format = $schemaInfo["format"] ?? "";
		
		// Handle file format - display thumbnail
		if ( $format === "file" && is_string( $value ) && !empty( $value ) ) {
			return $this->renderFileThumbnail( $value );
		}

		// Handle user format - display link to user page
		if ( $format === "user" && is_string( $value ) && !empty( $value ) ) {
			return $this->renderUserLink( $value );
		}

		// Handle category format - display link to category
		if ( $format === "category" && is_string( $value ) && !empty( $value ) ) {
			return $this->renderCategoryLink( $value );
		}

		// Handle pagename format - display link to page
		if ( $format === "pagename" && is_string( $value ) && !empty( $value ) ) {
			return $this->renderPageLink( $value );
		}

		if ( ( $format === "url" || $format === "uri" ) && is_string( $value ) && !empty( $value ) ) {
			return $this->renderLink( $value );
		}

		// Default formatting by type
		switch ( $schemaInfo['type'] ) {
			case "boolean":
				return '<span class="value-boolean">' .
					( $value ? "true" : "false" ) .
					"</span>";

			case "null":
				return '<span class="value-null">null</span>';

			case "integer":
			case "number":
			case "double":
				return '<span class="value-number">' .
					htmlspecialchars( (string)$value ) .
					"</span>";

			case "string":
				if ( $this->isHtml( $value ) ) {
					$value = $this->truncateTextUtf8( $value, 260 );
					return '<div class="value-html">' . $value . "</div>";
				}
				if ( $format === "textarea" ) {
					$value = $this->truncateTextUtf8( $value, 260 );
					return '<div class="value-textarea">' .
						nl2br( htmlspecialchars( $value ) ) .
						"</div>";
				}
				if ( $value === "" ) {
					return '<span class="value-empty"></span>';
				}

				$value = $this->truncateTextUtf8( $value );
				return '<span class="value-string">' .
					nl2br( htmlspecialchars( $value ) ) .
					"</span>";

			default:
				if ( is_object( $value ) ) {
					if ( method_exists( $value, "__toString" ) ) {
						return '<span class="value-object">' .
							htmlspecialchars( $value->__toString() ) .
							"</span>";
					}
					return '<span class="value-object">Object (' .
						htmlspecialchars( get_class( $value ) ) .
						")</span>";
				}
				return '<span class="value-default">' .
					htmlspecialchars( (string)$value ) .
					"</span>";
		}
	}

	/**
	 * Render a file thumbnail using OOUI
	 *
	 * @param string $filename
	 * @param int $width
	 * @param int $height
	 * @return string
	 */
	private function renderFileThumbnail( string $filename, int $width = 100 ): string {
		$title = TitleClass::newFromText( $filename, NS_FILE );
		
		if ( !$title || !$title->exists() ) {
			return (string)new \OOUI\LabelWidget( [
				'label' => $filename,
				'classes' => [ 'value-file' ]
			] );
		}

		$file = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->findFile( $title );
		
		if ( !$file ) {
			return (string)new \OOUI\LabelWidget( [
				'label' => $filename,
				'classes' => [ 'value-file' ]
			] );
		}

		$thumbParams = [ 'width' => $width ];
		$thumbnail = $file->transform( $thumbParams );
		
		if ( !$thumbnail ) {
			return (string)new \OOUI\LabelWidget( [
				'label' => $filename,
				'classes' => [ 'value-file' ]
			] );
		}

		$thumbUrl = $thumbnail->getUrl();
		$fullUrl = $file->getUrl();

		// Create a hyperlink widget with the thumbnail
		$link = new \OOUI\ButtonWidget( [
			'label' => new \OOUI\HtmlSnippet(
				'<img src="' . htmlspecialchars( $thumbUrl ) . '" ' .
				'alt="' . htmlspecialchars( $filename ) . '" ' .
				'width="' . $width . '" ' .
				'class="infobox-file-thumbnail"/>'
			),
			'href' => $fullUrl,
			'target' => '_blank',
			'framed' => false,
			'classes' => [ 'infobox-file-link' ]
		] );

		$fileName = new \OOUI\LabelWidget( [
			'label' => $filename,
			'classes' => [ 'infobox-file-name' ]
		] );

		return (string)new \OOUI\FieldsetLayout( [
			'items' => [
				new \OOUI\FieldLayout( $link, [
					'align' => 'top',
					'label' => '',
					'classes' => [ 'infobox-file-field' ]
				] ),
				$fileName
			],
			'classes' => [ 'value-file-thumbnail' ]
		] );
	}

	/**
	 * Render a user link using OOUI
	 *
	 * @param string $username
	 * @return string
	 */
	private function renderUserLink( string $username ): string {
		$userTitle = TitleClass::newFromText( $username, NS_USER );
		
		if ( !$userTitle ) {
			return (string)new \OOUI\LabelWidget( [
				'label' => $username,
				'classes' => [ 'value-user' ]
			] );
		}

		$user = User::newFromName( $username );
		$exists = $user && $user->getId() > 0;

		$link = new \OOUI\ButtonWidget( [
			'label' => $username,
			'href' => $userTitle->getLocalURL(),
			'target' => '_blank',
			'framed' => false,
			'flags' => [ 'progressive' ],
			'classes' => [ 'infobox-user-link' ]
		] );
		
		return $link;

/*
		if ( !$exists ) {
			return (string)new \OOUI\FieldLayout( $link, [
				'align' => 'inline',
				'help' => new \OOUI\HtmlSnippet( '<span class="infobox-user-unknown-badge">(unknown)</span>' ),
				'classes' => [ 'value-user' ]
			] );
		}

		return (string)new \OOUI\FieldLayout( $link, [
			'align' => 'inline',
			'classes' => [ 'value-user' ]
		] );
*/
	}

	/**
	 * Render a category link using OOUI
	 *
	 * @param string $categoryName
	 * @return string
	 */
	private function renderCategoryLink( string $categoryName ): string {
		$categoryTitle = TitleClass::newFromText( $categoryName, NS_CATEGORY );
		
		if ( !$categoryTitle ) {
			return (string)new \OOUI\LabelWidget( [
				'label' => $categoryName,
				'classes' => [ 'value-category' ]
			] );
		}

		$link = new \OOUI\ButtonWidget( [
			'label' => $categoryName,
			'href' => $categoryTitle->getLocalURL(),
			'target' => '_blank',
			'framed' => false,
			'flags' => [ 'progressive' ],
			'classes' => [ 'infobox-category-link' ]
		] );
	
/*
		return (string)new \OOUI\FieldLayout( $link, [
			'align' => 'inline',
			'classes' => [ 'value-category' ]
		] );
*/
		return $link;
	}

	/**
	 * Render a page link using OOUI
	 *
	 * @param string $pageName
	 * @return string
	 */
	private function renderLink( string $url ): string {
		$link = new \OOUI\ButtonWidget( [
			'label' => $url,
			'href' => $url,
			'target' => '_blank',
			'framed' => false,
			'flags' => [ 'progressive' ],
			'classes' => [ 'infobox-page-link' ]
		] );

		return (string)new \OOUI\FieldLayout( $link, [
			'align' => 'inline',
			'classes' => [ 'value-link' ]
		] );
	}

	/**
	 * Render a page link using OOUI
	 *
	 * @param string $pageName
	 * @return string
	 */
	private function renderPageLink( string $pageName ): string {
		$pageTitle = TitleClass::newFromText( $pageName );
		
		if ( !$pageTitle ) {
			return (string)new \OOUI\LabelWidget( [
				'label' => $pageName,
				'classes' => [ 'value-page' ]
			] );
		}

		$link = new \OOUI\ButtonWidget( [
			'label' => $pageName,
			'href' => $pageTitle->getLocalURL(),
			'target' => '_blank',
			'framed' => false,
			'flags' => [ 'progressive' ],
			'classes' => [ 'infobox-page-link' ]
		] );

		return (string)new \OOUI\FieldLayout( $link, [
			'align' => 'inline',
			'classes' => [ 'value-page' ]
		] );
	}

}
