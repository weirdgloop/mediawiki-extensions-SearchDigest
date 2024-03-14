<?php

namespace MediaWiki\Extension\SearchDigest;


class SearchDigestUtils {
	/**
	 * @param string $lang - should be provided by MediaWikiServices::getInstance()->getContentLanguage()->getCode()
	 */
	public static function getCharactersForStatsLookup( string $lang ): array {
		switch ( $lang ) {
			case 'uk':
				$chars = [
					'А', 'Б', 'В', 'Г', 'Ґ', 'Д', 'Е', 'Є', 'Ж', 'З', 'И', 'І', 'Ї', 'Й', 'К', 'Л', 'М', 'Н', 'О',
					'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ь', 'Ю', 'Я'
				];
				break;
			case 'ru':
				$chars = [
					'A', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С',
					'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я'
				];
				break;
			default:
				$chars = range( 'A', 'Z' );
				break;
		}

		return $chars;
	}
}
