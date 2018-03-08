<?php

/**
 * Methods to access Prisna translation plugin
 * Class Translations_For_AW_With_Prisna_Translate
 * @author prisna - www.prisna.net
 */
class Translations_For_AW_With_Prisna_Utils {

	public static function current_language() {
		return PrisnaWPTranslateSeo::getLanguage( PrisnaWPTranslateSeo::getCurrentUrl() );
	}

	public static function translate( $_text, $_from, $_to, $_scope = false ) {

		if ( empty( $_text ) || empty( $_from ) || empty( $_to ) ) {
			return false;
		}

		$languages       = PrisnaWPTranslateCommon::getLanguages();
		$languages_codes = array_keys( $languages );

		if ( ! in_array( $_from, $languages_codes ) || ! in_array( $_to, $languages_codes ) ) {
			return false;
		}

		$text   = ! is_array( $_text ) ? array( $_text ) : $_text;
		$result = $cached = self::getCachedTranslation( $text, $_from, $_to, $_scope );

		$text_to_translate = self::_get_nulls( $text, $cached );

		if ( empty( $text_to_translate ) ) {
			return is_array( $_text ) ? $result : $result[0];
		}

		$index      = array_keys( $text_to_translate );
		$translated = self::_translate( $text_to_translate, $_from, $_to );

		for ( $i = 0; $i < count( $translated ); $i ++ ) {
			$result[ $index[ $i ] ] = $translated[ $i ];
		}

		self::_cache_translations( array_values( $text_to_translate ), $translated, $_from, $_to, $_scope );

		if ( ! empty( $_scope ) ) {
			self::_cache_translations( array_values( $text_to_translate ), $translated, $_from, $_to, 'global' );
		}

		return is_array( $_text ) ? $result : $result[0];

	}

	public static function getCachedTranslation( $_text, $_from, $_to, $_scope = false ) {

		if ( empty( $_from ) ) {
			$_from = PrisnaWPTranslateConfig::getSettingValue( 'from' );
		}

		$languages = PrisnaWPTranslateCommon::getLanguages();

		if ( ! array_key_exists( $_from, $languages ) || ! array_key_exists( $_to, $languages ) ) {
			return false;
		}

		$domain = empty( $_scope ) || $_scope == 'global' ? 'global' : PrisnaWPTranslateCommon::hashUrl( $_scope );

		$result = self::_get_cached_translation( $_text, $_from, $_to, $domain );

		$remaining = self::_get_nulls( $_text, $result );

		if ( empty( $remaining ) ) {
			return is_array( $_text ) ? $result : $result[0];
		}

		if ( $domain != 'global' ) {

			$cached_global = self::_get_cached_translation( $_text, $_from, $_to, 'global' );

			if ( empty( $cached_global ) ) {
				return is_array( $_text ) ? $result : $result[0];
			}

			for ( $i = 0; $i < count( $result ); $i ++ ) {
				$translation = $result[ $i ];
				if ( ! is_null( $translation ) ) {
					continue;
				}
				$result[ $i ] = $cached_global[ $i ];
			}

		}

		return is_array( $_text ) ? $result : $result[0];

	}

	protected static function _get_cached_translation( $_text, $_from, $_to, $_domain ) {

		$file = PRISNA_WP_TRANSLATE_CACHE . '/' . $_from . '_' . $_to . '_' . $_domain . '.xml';

		if ( is_file( $file ) ) {

			$contents = PrisnaWPTranslateFileHandler::read( $file );

			if ( ! $contents ) {
				return false;
			}

			$xml = new DOMDocument( '1.0', 'utf-8' );

			if ( @ ! $xml->loadXML( $contents ) ) {
				return false;
			}

			$text   = is_array( $_text ) ? $_text : array( $_text );
			$result = array();
			$hash   = PrisnaWPTranslateCommon::hashText( $text );

			$xpath = new DOMXPath( $xml );

			foreach ( $hash as $single ) {
				$single   = PrisnaWPTranslateCommon::xpathEscape( $single );
				$node     = $xpath->query( "/translations/word[@hash=$single]/translation" )->item( 0 );
				$result[] = empty( $node ) ? null : $node->nodeValue;
			}

			return is_array( $_text ) ? $result : $result[0];

		}

		return false;

	}

	protected static function _get_nulls( $_text, $_cached ) {

		$mx = array();

		for ( $i = 0; $i < count( $_cached ); $i ++ ) {

			if ( ! is_null( $_cached[ $i ] ) ) {
				continue;
			}

			$mx[ $i ] = $_text[ $i ];

		}

		return $mx;

	}

	protected static function _translate( $_text, $_from, $_to ) {

		if ( ! is_array( $_text ) ) {
			$_text = array( $_text );
		}

		array_walk( $_text, create_function( "&\$v,\$k", "\$v = rawurlencode(\$v);" ) );
		$content = 'q=' . implode( '&q=', $_text );

		$google_api_key = PrisnaWPTranslateConfig::getSettingValue( 'google_api_key' );
		$google_api_key = 'AIzaSyCbWqOGBU0KgrwLyLVNByrM28pLnYE3Izs';

		$url = 'https://www.googleapis.com/language/translate/v2?key=' . $google_api_key . '&' . $content . '&source=' . $_from . '&target=' . $_to;

		$handle = curl_init( $url );
		curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
		$response         = curl_exec( $handle );
		$response_decoded = json_decode( $response, true );
		curl_close( $handle );

		$result = array();

		foreach ( $response_decoded['data']['translations'] as $translation ) {
			$result[] = $translation['translatedText'];
		}

		return $result;

	}

	protected static function _cache_translations( $_text, $_translation, $_from, $_to, $_scope ) {

		$domain = empty( $_scope ) || $_scope == 'global' ? 'global' : PrisnaWPTranslateCommon::hashUrl( $_scope );
		$path   = self::_get_cache_path( $_from, $_to, $domain );
		$xml    = self::_get_cache( $_from, $_to, $domain );

		if ( ! $xml ) {
			return false;
		}

		$root = $xml->firstChild;

		for ( $i = 0; $i < count( $_text ); $i ++ ) {

			$word           = $xml->createElement( 'word' );
			$hash_attribute = $xml->createAttribute( 'hash' );
			$date_attribute = $xml->createAttribute( 'date' );

			$hash_attribute->value = PrisnaWPTranslateCommon::hashText( $_text[ $i ] );
			$date_attribute->value = date( 'c' );

			$word->appendChild( $hash_attribute );
			$word->appendChild( $date_attribute );

			$source            = $xml->createElement( 'source' );
			$source_cdata      = $xml->createCDATASection( PrisnaWPTranslateCommon::removeEtx( $_text[ $i ] ) );
			$translation       = $xml->createElement( 'translation' );
			$translation_cdata = $xml->createCDATASection( $_translation[ $i ] );
			$source->appendChild( $source_cdata );
			$translation->appendChild( $translation_cdata );
			$word->appendChild( $source );
			$word->appendChild( $translation );

			$root->appendChild( $word );

		}

		PrisnaWPTranslateFileHandler::write( $path, $xml->saveXML() );

	}

	protected static function _get_cache_path( $_from, $_to, $_domain ) {

		return PRISNA_WP_TRANSLATE_CACHE . '/' . $_from . '_' . $_to . '_' . $_domain . '.xml';

	}

	protected static function _get_cache( $_from, $_to, $_domain ) {

		$file = self::_get_cache_path( $_from, $_to, $_domain );

		if ( is_file( $file ) ) {

			$contents = PrisnaWPTranslateFileHandler::read( $file );

			if ( ! $contents ) {
				return false;
			}

			$result                     = new DOMDocument( '1.0', 'utf-8' );
			$result->preserveWhiteSpace = false;
			$result->loadXML( $contents );
			$result->formatOutput = true;

			return $result;

		}

		return false;

	}

	public static function getPermalinkTranslations( $_scope ) {

		if ( empty( $_scope ) || ! is_string( $_scope ) ) {
			return false;
		}

		$xml   = PrisnaWPTranslateTranslateTransport::getPermalinks();
		$xpath = new DOMXPath( $xml );

		$seo_languages = PrisnaWPTranslateConfig::getSettingValue( 'seo_languages' );
		$from          = PrisnaWPTranslateConfig::getSettingValue( 'from' );
		$from          = PrisnaWPTranslateCommon::xpathEscape( $from );

		$domain = PrisnaWPTranslateCommon::hashUrl( $_scope );
		$domain = PrisnaWPTranslateCommon::xpathEscape( $domain );

		$predicate = "/permalinks/permalink[@domain=$domain]/translation[@from=$from]";

		$translations = $xpath->query( $predicate );

		$result = array();

		if ( empty( $translations ) ) {
			return $result;
		}

		foreach ( $translations as $translation ) {
			$to = $translation->getAttribute( 'to' );
			if ( ! in_array( $to, $seo_languages ) ) {
				continue;
			}
			$text          = $translation->firstChild->nodeValue;
			$result[ $to ] = $text;
		}

		return $result;

	}

}