<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CZ_Volume_List_Table extends WP_List_Table {
	/**
	 * @var CZ_Volume_Manager
	 */
	private $manager;

	/**
	 * @var int
	 */
	private $volume_id;

	public function __construct( CZ_Volume_Manager $manager, $volume_id ) {
		$this->manager   = $manager;
		$this->volume_id = absint( $volume_id );

		parent::__construct(
			array(
				'singular' => 'chapter',
				'plural'   => 'chapters',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'position'       => __( 'Posizione', 'cz-volume' ),
			'chapter_number' => __( 'Capitolo', 'cz-volume' ),
			'post_title'     => __( 'Titolo Post', 'cz-volume' ),
			'is_primary'     => __( 'Volume Principale', 'cz-volume' ),
			'actions'        => __( 'Azioni', 'cz-volume' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'position'       => array( 'position', true ),
			'chapter_number' => array( 'chapter_number', false ),
			'post_title'     => array( 'post_title', false ),
			'is_primary'     => array( 'is_primary', false ),
		);
	}

	public function prepare_items() {
		$items = $this->manager->get_chapters( $this->volume_id );

		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'position';
		$order   = isset( $_GET['order'] ) ? strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'asc';

		$allowed_orderby = array( 'position', 'chapter_number', 'post_title', 'is_primary' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'position';
		}

		if ( 'desc' !== $order ) {
			$order = 'asc';
		}

		usort(
			$items,
			function ( $a, $b ) use ( $orderby, $order ) {
				$av = isset( $a[ $orderby ] ) ? $a[ $orderby ] : '';
				$bv = isset( $b[ $orderby ] ) ? $b[ $orderby ] : '';

				if ( 'post_title' === $orderby ) {
					$cmp = strcasecmp( (string) $av, (string) $bv );
				} else {
					$cmp = intval( $av ) <=> intval( $bv );
				}

				return 'desc' === $order ? -$cmp : $cmp;
			}
		);

		$this->items = $items;

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
			'position',
		);
	}

	public function column_position( $item ) {
		$position = isset( $item['position'] ) ? intval( $item['position'] ) : 0;

		return '<span class="cz-drag-handle" title="' . esc_attr__( 'Trascina per riordinare', 'cz-volume' ) . '">&#9776;</span> ' . esc_html( (string) $position );
	}

	public function column_chapter_number( $item ) {
		return esc_html( isset( $item['chapter_number'] ) ? (string) intval( $item['chapter_number'] ) : '' );
	}

	public function column_post_title( $item ) {
		$post_id = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : 0;
		$title   = isset( $item['post_title'] ) ? $item['post_title'] : '';

		if ( ! $post_id ) {
			return esc_html__( '(post non trovato)', 'cz-volume' );
		}

		$edit_link = get_edit_post_link( $post_id );

		if ( ! $edit_link ) {
			return esc_html( $title );
		}

		return '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ) . '</a>';
	}

	public function column_is_primary( $item ) {
		$is_primary = ! empty( $item['is_primary'] );
		if ( $is_primary ) {
			return '<span class="cz-tag-available">' . esc_html__( 'Si', 'cz-volume' ) . '</span>';
		}

		return '<span class="cz-tag-added">' . esc_html__( 'No', 'cz-volume' ) . '</span>';
	}

	public function column_actions( $item ) {
		$post_id = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : 0;
		if ( ! $post_id ) {
			return '';
		}

		return '<button type="button" class="button button-small cz-remove-chapter" data-post-id="' . esc_attr( (string) $post_id ) . '">' . esc_html__( 'Rimuovi', 'cz-volume' ) . '</button>';
	}

	public function no_items() {
		echo esc_html__( 'Nessun capitolo trovato per questo volume.', 'cz-volume' );
	}

	public function single_row( $item ) {
		$post_id = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : 0;
		echo '<tr data-post-id="' . esc_attr( (string) $post_id ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}
}
