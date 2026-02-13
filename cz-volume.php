<?php
/**
 * Plugin Name: CZ Volume
 * Description: Gestione volumi e capitoli con numerazione per volume.
 * Version: 1.2.2
 * Author: CZ
 * Text Domain: cz-volume
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CZ_VOLUME_VERSION', '1.2.2' );
define( 'CZ_VOLUME_DB_VERSION', '1.2.2' );
define( 'CZ_VOLUME_PATH', plugin_dir_path( __FILE__ ) );
define( 'CZ_VOLUME_URL', plugin_dir_url( __FILE__ ) );

if ( ! function_exists( 'cz_volume_get_asset' ) ) {
	/**
	 * Restituisce URL e versione di un asset plugin, preferendo i file minificati.
	 *
	 * @param string $relative_path Percorso relativo alla root del plugin.
	 * @return array{0:string,1:string}
	 */
	function cz_volume_get_asset( $relative_path ) {
		$relative_path = ltrim( (string) $relative_path, '/' );
		$base_dir      = CZ_VOLUME_PATH;
		$base_url      = CZ_VOLUME_URL;
		$source_path   = $base_dir . $relative_path;
		$source_url    = $base_url . $relative_path;

		$min_path = preg_replace( '/(\\.js|\\.css)$/', '.min$1', $source_path );
		$min_url  = preg_replace( '/(\\.js|\\.css)$/', '.min$1', $source_url );
		$use_min  = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? false : true;

		$chosen_path = $source_path;
		$chosen_url  = $source_url;

		if ( $use_min && $min_path && file_exists( $min_path ) ) {
			$chosen_path = $min_path;
			$chosen_url  = $min_url;
		}

		$version = file_exists( $chosen_path ) ? (string) filemtime( $chosen_path ) : CZ_VOLUME_VERSION;

		return array( $chosen_url, $version );
	}
}

require_once CZ_VOLUME_PATH . 'includes/class-cz-volume-cpt.php';
require_once CZ_VOLUME_PATH . 'includes/class-cz-volume-manager.php';
require_once CZ_VOLUME_PATH . 'includes/class-cz-volume-admin.php';
require_once CZ_VOLUME_PATH . 'includes/class-cz-volume-rest.php';

class CZ_Volume_Plugin {
	/**
	 * @var CZ_Volume_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var CZ_Volume_Manager
	 */
	private $manager;

	/**
	 * @var CZ_Volume_Admin|null
	 */
	private $admin = null;

	/**
	 * @var CZ_Volume_REST
	 */
	private $rest;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( 'CZ_Volume_CPT', 'register' ) );
		add_action( 'plugins_loaded', array( $this, 'bootstrap' ) );
	}

	public function bootstrap() {
		$this->maybe_upgrade_schema();
		$this->manager = new CZ_Volume_Manager();
		$this->rest    = new CZ_Volume_REST( $this->manager );
		add_action( 'before_delete_post', array( $this, 'cleanup_volume_relations' ) );

		if ( is_admin() ) {
			$this->admin = new CZ_Volume_Admin( $this->manager );
		}
	}

	public function cleanup_volume_relations( $post_id ) {
		$post = get_post( $post_id );
		if ( $post && 'volume' === $post->post_type ) {
			$this->manager->remove_chapters_by_volume( $post_id );
		}
	}

	public static function activate() {
		CZ_Volume_CPT::register();
		CZ_Volume_Manager::create_table();
		update_option( 'cz_volume_db_version', CZ_VOLUME_DB_VERSION );
		flush_rewrite_rules();
	}

	public static function deactivate() {
		$default_cleanup = defined( 'CZ_VOLUME_DEACTIVATION_CLEANUP' ) && CZ_VOLUME_DEACTIVATION_CLEANUP;

		/**
		 * Se true, cancella anche la cache transient del plugin in deactivation.
		 *
		 * @param bool $cleanup_on_deactivate Default false (a meno di costante impostata a true).
		 */
		$cleanup_on_deactivate = (bool) apply_filters( 'cz_volume_cleanup_on_deactivate', $default_cleanup );

		if ( $cleanup_on_deactivate ) {
			global $wpdb;

			$like_key         = $wpdb->esc_like( '_transient_cz_volume_' ) . '%';
			$like_timeout_key = $wpdb->esc_like( '_transient_timeout_cz_volume_' ) . '%';

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$like_key,
					$like_timeout_key
				)
			);
		}

		flush_rewrite_rules();
	}

	private function maybe_upgrade_schema() {
		$current = get_option( 'cz_volume_db_version', '' );
		if ( CZ_VOLUME_DB_VERSION !== $current ) {
			CZ_Volume_Manager::create_table();
			update_option( 'cz_volume_db_version', CZ_VOLUME_DB_VERSION );
		}
	}
}

register_activation_hook( __FILE__, array( 'CZ_Volume_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CZ_Volume_Plugin', 'deactivate' ) );

CZ_Volume_Plugin::instance();
