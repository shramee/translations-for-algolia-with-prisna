<?php
/**
 * @wordpress-plugin
 * Plugin Name: Translation for Algolia with Prisna
 * Plugin URI: http://shramee.me
 * Description: Adds translation indices in Algolia with Prisna powering translations
 * Version: 0.7.0
 * Author: Shramee
 * Author URI: http://shramee.me
 *
 * Text Domain: tfawp
 * Domain Path: /languages/
 */
class Translations_For_Algolia_With_Prisna {

	// region Singleton and contructor

	/** @var self Instance */
	private static $_instance;

	/**
	 * Returns instance of current calss
	 * @return self Instance
	 */
	public static function instance() {
		if ( ! self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Translations_For_AW_With_Prisna constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'check_dependencies' ] );
	}

	// endregion

	// region Check dependencies and notify

	/**
	 * Initiate the plugin when dependencies are met
	 */
	public function check_dependencies() {
		if ( ! class_exists( 'PrisnaWPTranslateCommon' ) ) {
			add_action( 'admin_notices', [ $this, 'prisna_required_error' ] );
		} else if ( ! class_exists( 'Algolia_Plugin' ) ) {
			add_action( 'admin_notices', [ $this, 'algolia_required_error' ] );
		} else {
			$this->init();
		}
	}

	public function prisna_required_error() {
		$this->plugin_required_error( 'Prisna WP Translate' );
	}

	public function algolia_required_error() {
		$this->plugin_required_error( 'Search by Algolia – Instant & Relevant results' );
	}

	public function plugin_required_error( $plugin ) {
		echo '<div class="error notice"><p>' .
		     sprintf( __( 'Translation for Algolia with Prisna: %s plugin should be enabled.', 'tfawp' ), $plugin ) .
		     '</p></div>';
	}

	// endregion

	/**
	 * Initiate the plugin
	 */
	public function init() {
		require_once 'inc/prisna-utils.php';
		add_filter( 'algolia_indices', [ $this, 'indices' ] );
		add_filter( 'aw_search_index_name', [ $this, 'search_index_name' ] );
		add_action( 'algolia_wc_reindex_button', [ $this, 'reindex_buttons' ] );
	}

	/**
	 * Adds languages indices
	 * @param array $indices
	 * @return array
	 * @filter algolia_indices
	 */
	public function indices( $indices ) {
		$languages = PrisnaWPTranslateConfig::getSettingValue( 'languages' );

		if ( $languages ) {
			require_once 'inc/class-aw-posts-lang-index.php';

			foreach ( $languages as $language ) {
				$indices[] = new Translations_For_AW_With_Prisna_Index( 'product', $language );
			}
		}
		return $indices;
	}

	/**
	 * Set's index according to current active index
	 * @param string $index
	 * @return string
	 * @filter aw_search_index_name
	 */
	public function search_index_name( $index ) {
		$current_language = Translations_For_AW_With_Prisna_Utils::current_language();
		if ( $current_language && $current_language !== PrisnaWPTranslateConfig::getSettingValue( 'from' ) ) {
			$index = "{$index}_$current_language";
		}
		return $index;
	}

	/**
	 * Adds language re-index buttons
	 * @action algolia_wc_reindex_button
	 */
	public function reindex_buttons() {
		$languages = PrisnaWPTranslateConfig::getSettingValue( 'languages' );

		foreach ( $languages as $language ) {
			?>
			<button class="algolia-reindex-button button button-primary" data-index="posts_product_<?php echo $language ?>">
				<?php printf( __( 'Re-index %s products', 'algolia-woocommerce' ), strtoupper( $language ) ); ?></button>
			<?php
		}
	}
}

Translations_For_Algolia_With_Prisna::instance();