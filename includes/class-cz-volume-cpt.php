<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CZ_Volume_CPT {
	public static function register() {
		$labels = array(
			'name'               => __( 'Volumi', 'cz-volume' ),
			'singular_name'      => __( 'Volume', 'cz-volume' ),
			'menu_name'          => __( 'Volumi', 'cz-volume' ),
			'add_new'            => __( 'Aggiungi Volume', 'cz-volume' ),
			'add_new_item'       => __( 'Aggiungi Nuovo Volume', 'cz-volume' ),
			'edit_item'          => __( 'Modifica Volume', 'cz-volume' ),
			'new_item'           => __( 'Nuovo Volume', 'cz-volume' ),
			'view_item'          => __( 'Visualizza Volume', 'cz-volume' ),
			'search_items'       => __( 'Cerca Volumi', 'cz-volume' ),
			'not_found'          => __( 'Nessun volume trovato', 'cz-volume' ),
			'not_found_in_trash' => __( 'Nessun volume nel cestino', 'cz-volume' ),
		);

		register_post_type(
			'volume',
			array(
				'labels'       => $labels,
				'public'       => true,
				'menu_icon'    => 'dashicons-book',
				'supports'     => array( 'title', 'editor', 'thumbnail', 'author' ),
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'volumi' ),
				'show_in_rest' => true,
			)
		);
	}
}
