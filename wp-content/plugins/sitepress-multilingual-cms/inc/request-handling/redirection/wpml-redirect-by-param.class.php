<?php

class WPML_Redirect_By_Param {

	private $post_like_params = array( 'p' => 1, 'page_id' => 1 );
	private $term_like_params  = array( 'cat_ID' => 1, 'cat' => 1, 'tag' => 1 );
	private $get_lang;

	public function __construct( $tax_sync_option, $get_lang ) {
		global $wp_rewrite;
		$this->taxonomy_sync_option = $tax_sync_option;

		if ( !isset( $wp_rewrite ) ) {
			require_once ABSPATH . WPINC . '/rewrite.php';
			$wp_rewrite = new WP_Rewrite();
		}
		$this->get_lang = $get_lang;
		$this->term_like_params = array_merge ( $this->term_like_params, array_filter ( $tax_sync_option ) );

		define ( 'ICL_DOING_REDIRECT', true );
	}

	private function find_potential_translation( $query_params, $lang_code ){
		if ( count ( $translatable_params = array_intersect_key ( $query_params, $this->post_like_params ) ) === 1 ) {
			/** @var WPML_Post_Translation $wpml_post_translations */
			global $wpml_post_translations;
			$potential_translation = $wpml_post_translations->element_id_in (
				$query_params[ ( $parameter = key($translatable_params) ) ],
				$lang_code );
		} elseif( count ( $translatable_params = array_intersect_key ( $query_params, $this->term_like_params ) ) === 1 ) {
			/** @var WPML_Term_Translation $wpml_term_translations */
			global $wpml_term_translations;
			$potential_translation = $wpml_term_translations->term_id_in(
				$query_params[ ( $parameter = key($translatable_params) ) ],
				$lang_code );
		}
		/** @var String $parameter */
		return isset($potential_translation) ? array($parameter, $potential_translation) : false;
	}

	private function needs_redirect( $query_params_string, $lang_code ) {

		parse_str ( $query_params_string, $query_params );

		if ( isset( $query_params[ 'lang' ] ) ) {
			global $sitepress;
			if ( $sitepress->get_default_language () === $query_params[ 'lang' ] ) {
				unset( $query_params[ 'lang' ] );
				$changed = true;
			}
		}

		if ( ( $potential_translation = $this->find_potential_translation ( $query_params, $lang_code ) ) !== false
		     && (int) $query_params[ $potential_translation[ 0 ] ] !== (int) $potential_translation[ 1 ]
		) {
			$query_params[ $potential_translation[ 0 ] ] = $potential_translation[ 1 ];
			$changed                                     = true;
		}

		return isset( $changed ) ? $query_params : false;
	}

	private function get_target_link_querystring() {
		$query_string     = isset( $_SERVER[ 'QUERY_STRING' ] ) ? $_SERVER[ 'QUERY_STRING' ] : '';
		$query_params_new = $this->needs_redirect ( $query_string, $this->get_lang );


		return $query_params_new !== false ? http_build_query ( $query_params_new ) : false;
	}

	public function maybe_redirect() {
		if ( ( $new_qs = $this->get_target_link_querystring () ) !== false ) {
			wp_redirect ( '/?' . $new_qs, "301" );
			exit;
		}
	}
}