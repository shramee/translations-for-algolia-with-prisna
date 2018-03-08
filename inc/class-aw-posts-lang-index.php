<?php

if ( ! class_exists( 'AW_Products_Index' ) ) {
	require_once ALGOLIA_WOOCOMMERCE_PATH . '/includes/class-aw-products-index.php';
}

final class Translations_For_AW_With_Prisna_Index extends AW_Products_Index {

	/**
	 * @var string
	 */
	protected $post_type;

	/**
	 * @var string
	 */
	protected $lang;

	/** @var array Translation string */
	protected $translations_input = [];

	/** @var array Translation string */
	protected $translations = [];

	protected $contains_only = 'posts_lang';

	/**
	 * @param string $post_type
	 */
	public function __construct( $post_type, $lang ) {
		$this->post_type = (string) $post_type;
		$this->lang = (string) $lang;
	}

	/**
	 * @return string
	 */
	public function get_id() {
		return "posts_{$this->post_type}_{$this->lang}";
	}

	protected function get_translation( $key ) {
		$translation = $this->translations[ $key ];

		if ( ! $translation ) {
			$translation = $this->translations_input[ $key ];
		}

		return $translation;
	}

	/**
	 * Turns a WP_Post in a collection of records to be pushed to Algolia.
	 * Given every single post is splitted into several Algolia records,
	 * we also attribute an objectID that follows a naming convention for
	 * every record.
	 *
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	protected function get_post_records( WP_Post $post ) {
		$shared_attributes = $this->get_post_shared_attributes( $post );

		$shared_attributes['post_title']   = $this->get_translation( $post->post_title );
		$shared_attributes['post_excerpt'] = $this->get_translation( $post->post_excerpt );

		$removed = remove_filter( 'the_content', 'wptexturize', 10 );

		$parts = explode( ',', $post->post_content );

		if ( defined( 'ALGOLIA_SPLIT_POSTS' ) && false === ALGOLIA_SPLIT_POSTS ) {
			$parts = array( array_shift( $parts ) );
		}

		$records = array();
		foreach ( $parts as $i => $part ) {
			$record                 = $shared_attributes;
			$record['objectID']     = $this->get_post_object_id( $post->ID, $i );
			$record['content']      = $this->get_translation( $part );
			$record['record_index'] = $i;
			$records[]              = $record;
		}

		$records = apply_filters( 'algolia_post_records', $records, $post );
		$records = apply_filters( "algolia_post_{$post->post_type}_records", $records, $post );
		$records = apply_filters( "algolia_post_{$post->post_type}_{$this->lang}_records", $records, $post );

		return $records;
	}

	/**
	 * @param int $page
	 * @param int $batch_size
	 *
	 * @return array
	 */
	protected function get_items( $page, $batch_size ) {
		$query = new WP_Query(
			array(
				'post_type'        => $this->post_type,
				'posts_per_page'   => $batch_size,
				'post_status'      => 'any',
				'order'            => 'ASC',
				'orderby'          => 'ID',
				'paged'            => $page,
				'suppress_filters' => true,
			)
		);

		$posts = [];
		foreach ( $query->posts as $post ) {

			$removed = remove_filter( 'the_content', 'wptexturize', 10 );

			$post_content = apply_filters( 'algolia_post_content', $post->post_content );
			$post_content = apply_filters( 'the_content', $post_content );

			if ( true === $removed ) {
				add_filter( 'the_content', 'wptexturize', 10 );
			}

			$post_content = Algolia_Utils::prepare_content( $post_content );
			$post_content = Algolia_Utils::explode_content( $post_content );

			// Translation index (need this to retrieve translation later)
			$index = count( $this->translations_input );

			// Save content keys
			$this->translations_input = array_merge( $this->translations_input, $post_content );
			$post->post_content = implode( ',', range( $index, $index - 1 + count( $post_content ) ) );
			$index += count( $post_content ); // Merged post content array

			// Save title key
			$this->translations_input[] = apply_filters( 'the_excerpt', $post->post_excerpt );;
			$post->post_excerpt = $index++; // Added post_excerpt

			// Save excerpt key
			$this->translations_input[] = $post->post_title;
			$post->post_title = $index++; // Added post title

			$posts[] = $post;
		}

		$this->translations = Translations_For_AW_With_Prisna_Utils::translate(
			$this->translations_input,
			PrisnaWPTranslateConfig::getSettingValue( 'from' ),
			$this->lang
		);
		return $posts;
	}
}
