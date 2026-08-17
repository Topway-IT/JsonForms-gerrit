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

use MediaWiki\Extension\JsonForms\Aliases\Html as HtmlClass;
use MediaWiki\Extension\JsonForms\Aliases\Linker as LinkerClass;
use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\MediaWikiServices;
use OutputPage;
use User;
use stdClass;

class BaseRender {
	protected string $schemaName;
	protected User $user;

	/** @var Title|TitleClass */
	protected $title;

	protected array $parameters = [];
	protected array $schemaMap = [];
	protected array $jsonPaths = [];

	public static array $schemaInfo = [
		'title' => '',
		'description' => '',
		'type' => '',
		'required' => false,
		'format' => '',
		'x-format' => '',
		'default' => null,
		'enum' => [],
		'pattern' => '',
		'uniqueItems' => false,
		'minimum' => null,
		'maximum' => null,
		'minLength' => null,
		'maxLength' => null,
		'readOnly' => false,
		'x-render-template' => '',
		'x-value-formula' => '',
		'x-hidden' => '',
		'x-runtime-only' => '',
	];				

	/**
	 * param User $user
	 * param Title $title
	 * param array $parameters
	 * param stdClass $slotMetadata null
	 */
	public function __construct( $user, $title, $parameters, $slotMetadata ) {
		$this->user = $user;
		$this->title = $title;
		$this->parameters = $parameters;
		$this->jsonPaths = $this->getJsonPaths( $slotMetadata );
		$this->schemaMap = $this->getSchemaMap( $slotMetadata );
	}

	/**
	 * @param stdClass $slotMetadata
	 * @return array
	 */
	public static function getJsonPaths( $slotMetadata ) {
		if (
			!$slotMetadata ||
			!isset( $slotMetadata->jsonPaths )
		) {
			return [];
		}

		return (array)$slotMetadata->jsonPaths;
	}

	/**
	 * @param stdClass $slotMetadata
	 * @return array
	 */
	public static function getSchemaMap( $slotMetadata ) {
		if (
			!$slotMetadata ||
			!isset( $slotMetadata->schema )
		) {
			return [];
		}

		$schema = $slotMetadata->schema;
		$title = TitleClass::newFromText( 'JsonSchema:' . $schema );

		if ( !$title || !$title->exists() ) {
			return [];
		}

		// @TODO get revision based on schema_revision and use as a fallback
		$wikiPage = \JsonForms::getWikiPage( $title );

		if ( !$wikiPage ) {
			return [];
		}

		$metadata = \JsonForms::getMetadata( $wikiPage );

		if (
			!$metadata ||
			!is_object( $metadata ) ||
			!isset( $metadata->processedSchema )
		) {
			return [];
		}

		return json_decode( json_encode( $metadata->processedSchema ), true );
	}

	/**
	 * @param string $path
	 * @return string|null
	 */
	protected function getJsonPath( $path ) {
		return $this->jsonPaths[$path] ?? null;
	}

	/**
	 * @param string $path
	 * @param string $key
	 * @return array
	 */
	protected function getSchemaFromPath( $path, $key = '' ) {
		$jsonPath = $this->getJsonPath ( $path );
		if ( !$jsonPath ) {
			return [];
		}
		return $this->schemaMap[$jsonPath] ?? [];
	}

	/**
	 * @param array $schemaInfo
	 * @return array
	 */
	protected function getComputedFormat( $schemaInfo ) {
		if ( !empty( $schemaInfo['x-format'] ) ) {
			return $schemaInfo['x-format'];
		}
		
		if ( !empty( $schemaInfo['format'] ) ) {
			return $schemaInfo['format'];
		}
		
		return $schemaInfo['type'];
	}

	/**
	 * Get schema info for a property path
	 *
	 * @param string $key
	 * @param string $path
	 * @return array
	 */
	protected function getSchemaInfo( $path, $key = '' ) {
		$fullPath = $path;
		$schemaInfo = $this->getSchemaFromPath( $path );

		$returnSchema = static function ( $schema ) use ( $schemaInfo, $key ) {
			$filtered = array_filter( $schemaInfo, static function( $value ) {
				return is_scalar( $value );
			} );
   			return array_merge( self::$schemaInfo, $filtered );
		};

		$isItems = is_numeric( $key );

		if ( $isItems && isset( $schemaInfo['items'] ) ) {
			return $returnSchema( $schemaInfo['items'] );
		}

		return $returnSchema( $schemaInfo );
	}

	/**
	 * @param string $html
	 * @param int $length
	 * @param string $ellipsis
	 * @return string
	 */
	protected function truncateTextUtf8( $html, $length = 100, $ellipsis = '…' ) {
		$text = strip_tags( $html );
    
		if ( mb_strlen( $text ) > $length ) {
			$text = mb_substr( $text, 0, $length ) . $ellipsis;
		}
    
		return $text;
	}

	/**
	 * Check if content contains HTML
	 *
	 * @param mixed $content
	 * @return bool
	 */
	protected function isHtml( $content ) {
		if ( !is_string( $content ) ) {
			return false;
		}
		return preg_match( '/<[a-z][a-z0-9]*[^>]*>/i', $content ) === 1;
	}

	/**
	 * @param OutputPage $outputPage
	 * @return void
	 */
	public static function printInfobox( OutputPage $outputPage ): void {
		$wikiPage = $outputPage->getWikiPage();
		if ( !$wikiPage ) {
			return;
		}

		$title = $outputPage->getTitle();
		$ns = $title->getNamespace();
		$user = $outputPage->getUser();

		// Handle JSON Schema namespace
		if ( $ns === NS_JSONSCHEMA && $user->isAllowed( 'jsonforms-canmanageschemas' ) ) {
			self::renderNamespaceMessage(
				$outputPage,
				'Schemas',
				'jsonforms-jsonschema-namespace-schema-message',
				$title
			);
			return;
		}

		// Handle JSON Form namespace
		if ( $ns === NS_JSONFORM && $user->isAllowed( 'jsonforms-canmanageforms' ) ) {
			self::renderNamespaceMessage(
				$outputPage,
				'Forms',
				'jsonforms-jsonschema-namespace-form-message',
				$title
			);
			return;
		}

		// Check if namespace is configured for JSON Forms editing
		$configuredNamespaces = \JsonForms::getConfigValue( 'JsonFormsEditSchemaNamespaces' );
		if ( !in_array( $ns, $configuredNamespaces ) ) {
			return;
		}

		// Get metadata
		$metadata = \JsonForms::getMetadata( $wikiPage );
		if ( !$metadata ) {
			return;
		}

		// Validate metadata structure
		if ( !self::isValidMetadata( $metadata ) ) {
			return;
		}

		$slotMetadata = $metadata->slots->{SLOT_ROLE_JSONFORMS_DATA};

		// Check if infobox should be shown
		if ( empty( $slotMetadata->showInfobox ) || empty( $slotMetadata->schema ) ) {
			return;
		}

		// Get content
		$text = \JsonForms::getSlotContent( $wikiPage, SLOT_ROLE_JSONFORMS_DATA );
		if ( !$text ) {
			return;
		}

		$data = json_decode( $text, false );
		if ( !$data ) {
			return;
		}

		$position = $slotMetadata->infoboxPosition ?? 'right';
		$outputPage->enableOOUI();

		$parameters = [ 'schema' => $slotMetadata->schema ];

		// Render infobox
		$renderedContent = self::renderInfobox(
			$outputPage,
			$user,
			$title,
			$data,
			$slotMetadata,
			$parameters,
		);

		if ( !$renderedContent ) {
			return;
		}

		// display OOUI icons
		$outputPage->addModuleStyles( [
			'oojs-ui.styles.icons-alerts',
			'oojs-ui.styles.icons-interactions',
			'oojs-ui.styles.icons-editing-core',
			'oojs-ui.styles.icons-editing-advanced',
			'oojs-ui.styles.icons-editing-functions',
			'oojs-ui.styles.icons-layout',
			'oojs-ui.styles.icons-movement',
			'oojs-ui.styles.icons-moderation',
			'oojs-ui.styles.icons-accessibility',
		] );

		// Add modules and append content
		$outputPage->addModules( 'ext.JsonForms.infobox' );
		self::appendInfoboxToPage( $outputPage, $renderedContent, $position );
	}

	/**
	 * Render namespace message for JSON Schema/Form namespaces
	 *
	 * @param OutputPage $outputPage
	 * @param string $pageType "Schemas" or "Forms"
	 * @param string $messageKey
	 * @param Title $title
	 * @return void
	 */
	private static function renderNamespaceMessage(
		OutputPage $outputPage,
		string $pageType,
		string $messageKey,
		$title
	): void {
		$outputPage->enableOOUI();
		$outputPage->addModules( 'ext.JsonForms.infobox' );

		$specialPageTitle = \SpecialPage::getTitleFor( 'JsonFormsManage', $pageType );
		$url = $specialPageTitle->getLinkURL( [
			'action' => 'edit',
			'pageid' => $title->getID()
		] );

		$html = new \OOUI\MessageWidget( [
			'type' => 'info',
			'icon' => 'edit',
			'label' => new \OOUI\HtmlSnippet(
				wfMessage( $messageKey, $url )->text()
			),
		] );

		$outputPage->prependHTML( (string)$html );
	}

	/**
	 * Check if metadata has valid structure
	 *
	 * @param stdClass $metadata
	 * @return bool
	 */
	private static function isValidMetadata( stdClass $metadata ): bool {
		return property_exists( $metadata, 'slots' ) &&
			is_object( $metadata->slots ) &&
			property_exists( $metadata->slots, SLOT_ROLE_JSONFORMS_DATA );
	}

	/**
	 * Render infobox content
	 *
	 * @param OutputPage $outputPage
	 * @param User $user
	 * @param Title $title
	 * @param stdClass $data
	 * @param stdClass $slotMetadata
	 * @param array $parameters
	 * @param array $schemaMap
	 * @return string|null
	 */
	private static function renderInfobox(
		OutputPage $outputPage,
		User $user,
		$title,
		stdClass|array $data,
		stdClass $slotMetadata,
		array $parameters,
	): ?string {
		try {
			// Custom template
			if ( !empty( $slotMetadata->infoboxTemplate ) ) {
				return self::renderWithTemplate(
					$outputPage,
					$user,
					$title,
					$data,
					$slotMetadata,
					$parameters,
				);
			}

			// Automatic template
			return self::renderWithInfobox(
				$user,
				$title,
				$parameters,
				$data,
				$slotMetadata,
			);
		} catch ( \Exception $e ) {
			// Log error and return null
			return null;
		}
	}

	/**
	 * Render with custom template
	 *
	 * @param OutputPage $outputPage
	 * @param User $user
	 * @param Title $title
	 * @param stdClass $data
	 * @param stdClass $slotMetadata
	 * @param array $parameters
	 * @param stdClass $schemaMap
	 * @return string
	 */
	private static function renderWithTemplate(
		OutputPage $outputPage,
		User $user,
		$title,
		stdClass|array $data,
		stdClass $slotMetadata,
		array $parameters
	): string {
		$parser = MediaWikiServices::getInstance()
			->getParserFactory()
			->create();

		$context = $outputPage->getContext();
		$parserOptions = ParserOptions::newFromContext( $context );

		$parser->startExternalParse(
			$title,
			$parserOptions,
			Parser::OT_PREPROCESS
		);

		$parameters['template'] = $slotMetadata->infoboxTemplate;
		$templateRender = new TemplateRender(
			$user,
			$title,
			$parameters,
			$slotMetadata
		);
		$templateRender->setParser( $parser );

		return $templateRender->render( $data );
	}

	/**
	 * Render with automatic infobox
	 *
	 * @param User $user
	 * @param Title $title
	 * @param array $parameters
	 * @param array $schemaMap
	 * @param stdClass $data
	 * @return string
	 */
	private static function renderWithInfobox(
		User $user,
		$title,
		array $parameters,
		stdClass|array $data,
		stdClass $slotMetadata
	): string {
		$infoboxRender = new InfoboxRender(
			$user,
			$title,
			$parameters,
			$slotMetadata
		);

		return $infoboxRender->render( $data );
	}

	/**
	 * Append infobox to page based on position
	 *
	 * @param OutputPage $outputPage
	 * @param string $content
	 * @param string $position
	 * @return void
	 */
	private static function appendInfoboxToPage(
		OutputPage $outputPage,
		string $content,
		string $position
	): void {
		$html = HtmlClass::rawElement(
			'div',
			[
				'class' => "jsonforms-infobox jsonforms-infobox-$position",
			],
			$content
		);

		if ( $position === 'bottom' ) {
			$outputPage->addHTML( $html );
		} else {
			$outputPage->prependHTML( $html );
		}
	}

}
