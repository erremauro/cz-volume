<?php
/**
 * Uninstall handler for CZ Volume.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$default_remove_data = defined( 'CZ_VOLUME_UNINSTALL_REMOVE_DATA' ) && CZ_VOLUME_UNINSTALL_REMOVE_DATA;

/**
 * Abilita cleanup completo dati in uninstall.
 *
 * @param bool $remove_data Default false (a meno di costante impostata a true).
 */
$remove_data = (bool) apply_filters( 'cz_volume_uninstall_remove_data', $default_remove_data );

if ( ! $remove_data ) {
	return;
}

global $wpdb;

$table_name = $wpdb->prefix . 'cz_volume_items';

// Rimuove tabella relazioni.
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Rimuove transient cache del plugin.
$like_key         = $wpdb->esc_like( '_transient_cz_volume_' ) . '%';
$like_timeout_key = $wpdb->esc_like( '_transient_timeout_cz_volume_' ) . '%';

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$like_key,
		$like_timeout_key
	)
);

/**
 * Se true, elimina anche i post di tipo volume in uninstall.
 *
 * @param bool $remove_volumes Default false.
 */
$remove_volumes = (bool) apply_filters( 'cz_volume_uninstall_remove_volumes', false );

if ( $remove_volumes ) {
	$volume_ids = get_posts(
		array(
			'post_type'      => 'volume',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	if ( ! empty( $volume_ids ) ) {
		foreach ( $volume_ids as $volume_id ) {
			wp_delete_post( (int) $volume_id, true );
		}
	}
}
