<?php

namespace MediaWiki\Extension\JsonForms;

use MediaWiki\Content\JsonContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentityValue;

class SlotHelper {

	/** @var array<string,string>|null Cached role → model map */
	private static ?array $roleContentModelMap = null;

	/**
	 * @return string[]
	 */
	public static function getSlotRoles(): array {
		$roles = MediaWikiServices::getInstance()
			->getSlotRoleRegistry()
			->getKnownRoles();

		// @IMPORTANT !!
		// otherwise EnumProviders will return an object
		// with numeric keys (options-values)
		return array_values( $roles );
	}

	/**
	 * @return array<string,string>
	 */
	public static function getRoleContentModelMap(): array {
		if ( self::$roleContentModelMap !== null ) {
			return self::$roleContentModelMap;
		}

		$roles = self::getSlotRoles();
		$slotRegistry = MediaWikiServices::getInstance()->getSlotRoleRegistry();
		$page = new PageIdentityValue( 0, NS_MAIN, 'Dummy', false );

		$map = [];
		foreach ( $roles as $role ) {
			$map[$role] = $slotRegistry->getRoleHandler( $role )->getDefaultModel( $page );
		}

		self::$roleContentModelMap = $map;
		return $map;
	}

	/**
	 * @return string[]
	 */
	public static function getJsonContentModels(): array {
		$contentHandlerFactory = MediaWikiServices::getInstance()->getContentHandlerFactory();
		$models = self::getRoleContentModelMap();

		$jsonModels = [];
		foreach ( $models as $model ) {
			$content = $contentHandlerFactory->getContentHandler( $model )->makeEmptyContent();
			if ( $content instanceof JsonContent ) {
				$jsonModels[] = $model;
			}
		}
		return $jsonModels;
	}

	/**
	 * @return string[]
	 */
	public static function getJsonSlots(): array {
		$contentHandlerFactory = MediaWikiServices::getInstance()->getContentHandlerFactory();
		$models = self::getRoleContentModelMap();

		$jsonSlots = [];
		foreach ( $models as $role => $model ) {
			$content = $contentHandlerFactory->getContentHandler( $model )->makeEmptyContent();
			if ( $content instanceof JsonContent ) {
				$jsonSlots[] = $role;
			}
		}
		return $jsonSlots;
	}

}
