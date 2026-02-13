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

class CZ_Volume_Available_Posts_List_Table extends WP_List_Table {
	/**
	 * @var CZ_Volume_Manager
	 */
	private $manager;

	/**
	 * @var int
	 */
	private $volume_id;

	/**
	 * @var array<int,bool>
	 */
	private $added_lookup = array();

	/**
	 * @var int
	 */
	private $current_author = 0;

	/**
	 * @var string
	 */
	private $current_search = '';

	/**
	 * @var int
	 */
	private $per_page = 20;

	public function __construct( CZ_Volume_Manager $manager, $volume_id ) {
		$this->manager   = $manager;
		$this->volume_id = absint( $volume_id );

		parent::__construct(
			array(
				'singular' => 'available_post',
				'plural'   => 'available_posts',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'post_title'  => __( 'Titolo', 'cz-volume' ),
			'author_name' => __( 'Autore', 'cz-volume' ),
			'post_status' => __( 'Stato', 'cz-volume' ),
			'actions'     => __( 'Azione', 'cz-volume' ),
		);
	}

	protected function get_sortable_columns() {
		return array();
	}

	public function prepare_items() {
		$chapters = $this->manager->get_chapters( $this->volume_id );
		foreach ( $chapters as $chapter ) {
			$post_id = isset( $chapter['post_id'] ) ? absint( $chapter['post_id'] ) : 0;
			if ( $post_id ) {
				$this->added_lookup[ $post_id ] = true;
			}
		}

		$this->current_author = isset( $_GET['cz_post_author'] ) ? absint( wp_unslash( $_GET['cz_post_author'] ) ) : 0;
		$this->current_search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$current_page         = max( 1, $this->get_pagenum() );

		$args = array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => $this->per_page,
			'paged'          => $current_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $this->current_author ) {
			$args['author'] = $this->current_author;
		}

		if ( '' !== $this->current_search ) {
			$args['s'] = $this->current_search;
		}

		$query = new WP_Query( $args );
		$items = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$post_id   = (int) $post->ID;
				$author_id = (int) $post->post_author;
				$items[]   = array(
					'post_id'      => $post_id,
					'post_title'   => $post->post_title ? $post->post_title : __( '(senza titolo)', 'cz-volume' ),
					'author_id'    => $author_id,
					'author_name'  => get_the_author_meta( 'display_name', $author_id ),
					'post_status'  => (string) $post->post_status,
					'already_added'=> isset( $this->added_lookup[ $post_id ] ),
				);
			}
		}

		$this->items = $items;
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
			'post_title',
		);

		$this->set_pagination_args(
			array(
				'total_items' => (int) $query->found_posts,
				'per_page'    => $this->per_page,
				'total_pages' => (int) $query->max_num_pages,
			)
		);
	}

	public function get_views() {
		$base_args = array(
			'post_type' => 'volume',
			'page'      => 'cz-volume-chapters',
			'volume_id' => $this->volume_id,
		);
		if ( '' !== $this->current_search ) {
			$base_args['s'] = $this->current_search;
		}

		$all_count = (int) wp_count_posts( 'post' )->publish + (int) wp_count_posts( 'post' )->draft + (int) wp_count_posts( 'post' )->pending + (int) wp_count_posts( 'post' )->private + (int) wp_count_posts( 'post' )->future;
		$views     = array();

		$all_url = add_query_arg( $base_args, admin_url( 'edit.php' ) );
		$all_cls = $this->current_author ? '' : ' class="current"';
		$views['all'] = '<a href="' . esc_url( $all_url ) . '"' . $all_cls . '>' . esc_html__( 'Tutti', 'cz-volume' ) . ' <span class="count">(' . esc_html( (string) $all_count ) . ')</span></a>';

		$authors = get_users(
			array(
				'who'     => 'authors',
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name' ),
			)
		);

		$max_author_links = 6;
		$index = 0;
		foreach ( $authors as $author ) {
			if ( $index >= $max_author_links ) {
				break;
			}
			$author_url = add_query_arg(
				array_merge(
					$base_args,
					array(
						'cz_post_author' => (int) $author->ID,
					)
				),
				admin_url( 'edit.php' )
			);
			$cls = ( $this->current_author === (int) $author->ID ) ? ' class="current"' : '';
			$key = 'author_' . (int) $author->ID;
			$views[ $key ] = '<a href="' . esc_url( $author_url ) . '"' . $cls . '>' . esc_html( $author->display_name ) . '</a>';
			$index++;
		}

		return $views;
	}

	public function no_items() {
		echo esc_html__( 'Nessun post trovato.', 'cz-volume' );
	}

	public function column_post_title( $item ) {
		$post_id = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : 0;
		$title   = isset( $item['post_title'] ) ? (string) $item['post_title'] : '';
		$link    = $post_id ? get_edit_post_link( $post_id ) : '';

		if ( ! $link ) {
			return esc_html( $title );
		}

		return '<a href="' . esc_url( $link ) . '"><strong>' . esc_html( $title ) . '</strong></a>';
	}

	public function column_author_name( $item ) {
		$author_id   = isset( $item['author_id'] ) ? absint( $item['author_id'] ) : 0;
		$author_name = isset( $item['author_name'] ) ? (string) $item['author_name'] : '';
		if ( ! $author_id || '' === $author_name ) {
			return '&mdash;';
		}

		$args = array(
			'post_type'      => 'volume',
			'page'           => 'cz-volume-chapters',
			'volume_id'      => $this->volume_id,
			'cz_post_author' => $author_id,
		);
		if ( '' !== $this->current_search ) {
			$args['s'] = $this->current_search;
		}

		$url = add_query_arg( $args, admin_url( 'edit.php' ) );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $author_name ) . '</a>';
	}

	public function column_post_status( $item ) {
		$status = isset( $item['post_status'] ) ? (string) $item['post_status'] : '';
		$obj    = get_post_status_object( $status );
		$label  = $obj && isset( $obj->label ) ? $obj->label : $status;
		return '<span class="cz-status-pill">' . esc_html( $label ) . '</span>';
	}

	public function column_actions( $item ) {
		$post_id       = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : 0;
		$post_title    = isset( $item['post_title'] ) ? (string) $item['post_title'] : '';
		$already_added = ! empty( $item['already_added'] );

		if ( ! $post_id ) {
			return '';
		}

		if ( $already_added ) {
			return '<span class="cz-tag-added">' . esc_html__( 'Gia nel volume', 'cz-volume' ) . '</span>';
		}

		return '<button type="button" class="button button-small cz-available-select" data-post-id="' . esc_attr( (string) $post_id ) . '" data-post-title="' . esc_attr( $post_title ) . '">' . esc_html__( 'Seleziona', 'cz-volume' ) . '</button>';
	}

	public function single_row( $item ) {
		$post_id       = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : 0;
		$already_added = ! empty( $item['already_added'] );
		$row_class     = $already_added ? '' : ' class="is-selectable"';
		echo '<tr data-post-id="' . esc_attr( (string) $post_id ) . '"' . $row_class . '>';
		$this->single_row_columns( $item );
		echo '</tr>';
	}
}
