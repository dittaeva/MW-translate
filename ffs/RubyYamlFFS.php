<?php

/**
 * Extends YamlFFS with Ruby (on Rails) style plural support. Supports subkeys
 * zero, one, many, few, other and two for each message using plural with
 * {{count}} variable.
 * @ingroup FFS
 */
class RubyYamlFFS extends YamlFFS {
	static $pluralWords = array(
		'zero' => 1,
		'one' => 1,
		'many' => 1,
		'few' => 1,
		'other' => 1,
		'two' => 1
	);

	/**
	 * Flattens ruby plural arrays into special plural syntax.
	 *
	 * @param $messages array
	 *
	 * @return bool|string
	 */
	public function flattenPlural( $messages ) {

		$plurals = false;
		foreach ( array_keys( $messages ) as $key ) {
			if ( isset( self::$pluralWords[$key] ) ) {
				$plurals = true;
			} elseif ( $plurals ) {
				throw new MWException( "Reserved plural keywords mixed with other keys: $key." );
			}
		}

		if ( !$plurals ) {
			return false;
		}

		$pls = '{{PLURAL';
		foreach ( $messages as $key => $value ) {
			if ( $key === 'other' ) {
				continue;
			}

			$pls .= "|$key=$value";
		}

		// Put the "other" alternative last, without other= prefix.
		$other = isset( $messages['other'] ) ? '|' . $messages['other'] : '';
		$pls .= "$other}}";
		return $pls;
	}

	/**
	 * Converts the special plural syntax to array or ruby style plurals
	 *
	 * @param $key string
	 * @param $message string
	 *
	 * @return bool|array
	 */
	public function unflattenPlural( $key, $message ) {
		// Quick escape.
		if ( strpos( $message, '{{PLURAL' ) === false ) {
			return array( $key => $message );
		}

		/*
		 * Replace all variables with placeholders. Possible source of bugs
		 * if other characters that given below are used.
		 */
		$regex = '~\{[a-zA-Z_-]+}~';
		$placeholders = array();
		$match = null;

		while ( preg_match( $regex, $message, $match ) ) {
			$uniqkey = TranslateUtils::getPlaceholder();
			$placeholders[$uniqkey] = $match[0];
			$search = preg_quote( $match[0], '~' );
			$message = preg_replace( "~$search~", $uniqkey, $message );
		}

		// Then replace (possible multiple) plural instances into placeholders.
		$regex = '~\{\{PLURAL\|(.*?)}}~s';
		$matches = array();
		$match = null;

		while ( preg_match( $regex, $message, $match ) ) {
			$uniqkey = TranslateUtils::getPlaceholder();
			$matches[$uniqkey] = $match;
			$message = preg_replace( $regex, $uniqkey, $message );
		}

		// No plurals, should not happen.
		if ( !count( $matches ) ) {
			return false;
		}

		// The final array of alternative plurals forms.
		$alts = array();

		/*
		 * Then loop trough each plural block and replacing the placeholders
		 * to construct the alternatives. Produces invalid output if there is
		 * multiple plural bocks which don't have the same set of keys.
		 */
		$pluralChoice = implode( '|', array_keys( self::$pluralWords ) );
		$regex = "~($pluralChoice)\s*=\s*(.+)~s";
		foreach ( $matches as $ph => $plu ) {
			$forms = explode( '|', $plu[1] );

			foreach ( $forms as $form ) {
				if ( $form === '' ) {
					continue;
				}

				$match = array();
				if ( preg_match( $regex, $form, $match ) ) {
					$formWord = "$key.{$match[1]}";
					$value = $match[2];
				} else {
					$formWord = "$key.other";
					$value = $form;
				}

				if ( !isset( $alts[$formWord] ) ) {
					$alts[$formWord] = $message;
				}

				$string = $alts[$formWord];
				$alts[$formWord] = str_replace( $ph, $value, $string );
			}
		}

		// Replace other variables.
		foreach ( $alts as &$value ) {
			$value = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $value );
		}

		if ( !isset( $alts["$key.other"] ) ) {
			wfWarn( "Other not set for key $key" );
			return false;
		}

		return $alts;
	}

}
