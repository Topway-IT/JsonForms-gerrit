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
 * @copyright Copyright ©2025, https://wikisphere.org
 */

$magicWords = [];

// see here
// https://www.mediawiki.org/wiki/Manual:Magic_words
// '0' stands for 'case insensitive'

$magicWords['en'] = [
	'jsonforms' => [ 0, 'jsonform', 'jsonforms' ],
	'jsonformsrender' => [ 0, 'jsonformsrender', 'jsonformrender' ],
	'jsonformsquerylink' => [ 0, 'jsonformsquerylink', 'querylink' ],
];
