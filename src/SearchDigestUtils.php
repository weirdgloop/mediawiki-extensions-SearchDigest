<?php

namespace MediaWiki\Extension\SearchDigest;


class SearchDigestUtils {
	/**
	 * @param string $lang - should be provided by MediaWikiServices::getInstance()->getContentLanguage()->getCode()
	 */
	public static function getCharactersForStatsLookup( string $lang ): array {
		$chars = range( 'A', 'Z' );

		switch ( $lang ) {
			case 'ru':
				$chars = array_merge( $chars, [
					'А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С',
					'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я'
				] );
				break;
			case 'uk':
				$chars = array_merge( $chars, [
					'А', 'Б', 'В', 'Г', 'Ґ', 'Д', 'Е', 'Є', 'Ж', 'З', 'И', 'І', 'Ї', 'Й', 'К', 'Л', 'М', 'Н', 'О',
					'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ь', 'Ю', 'Я'
				] );
				break;
		}

		return $chars;
	}
}
