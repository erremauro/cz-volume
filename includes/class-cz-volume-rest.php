<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CZ_Volume_REST {
	/**
	 * @var CZ_Volume_Manager
	 */
	private $manager;

	public function __construct( CZ_Volume_Manager $manager ) {
		$this->manager = $manager;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'cz-volume/v1',
			'/volume/(?P<id>\d+)/chapters',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_volume_chapters' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'cz-volume/v1',
			'/post/(?P<id>\d+)/volumes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_post_volumes' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'cz-volume/v1',
			'/volume/(?P<id>\d+)/chapter',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_volume_chapter' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			'cz-volume/v1',
			'/volume/(?P<id>\d+)/chapter/(?P<post_id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_volume_chapter' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
	}

	public function get_volume_chapters( WP_REST_Request $request ) {
		$volume_id = absint( $request['id'] );
		if ( ! $this->is_valid_volume( $volume_id ) ) {
			return new WP_Error( 'cz_volume_not_found', __( 'Volume non trovato.', 'cz-volume' ), array( 'status' => 404 ) );
		}

		$chapters = $this->manager->get_chapters( $volume_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'volume_id' => $volume_id,
					'count'     => count( $chapters ),
					'chapters'  => $chapters,
				),
			)
		);
	}

	public function get_post_volumes( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] );
		if ( ! get_post( $post_id ) ) {
			return new WP_Error( 'cz_post_not_found', __( 'Post non trovato.', 'cz-volume' ), array( 'status' => 404 ) );
		}

		$volumes = $this->manager->get_volumes_by_post( $post_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'post_id' => $post_id,
					'count'   => count( $volumes ),
					'volumes' => $volumes,
				),
			)
		);
	}

	public function add_volume_chapter( WP_REST_Request $request ) {
		$volume_id      = absint( $request['id'] );
		$post_id        = absint( $request->get_param( 'post_id' ) );
		$chapter_number = intval( $request->get_param( 'chapter_number' ) );
		$position       = intval( $request->get_param( 'position' ) );
		$is_primary     = $request->get_param( 'is_primary' ) ? 1 : 0;

		if ( ! $this->is_valid_volume( $volume_id ) || ! $this->is_valid_post( $post_id ) || $chapter_number <= 0 ) {
			return new WP_Error( 'cz_invalid_data', __( 'Dati non validi.', 'cz-volume' ), array( 'status' => 400 ) );
		}

		$ok = $this->manager->add_chapter( $volume_id, $post_id, $chapter_number, $position, $is_primary );
		if ( ! $ok ) {
			return new WP_Error( 'cz_add_failed', __( 'Impossibile aggiungere il capitolo.', 'cz-volume' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'volume_id' => $volume_id,
					'post_id'   => $post_id,
					'chapters'  => $this->manager->get_chapters( $volume_id ),
				),
			)
		);
	}

	public function delete_volume_chapter( WP_REST_Request $request ) {
		$volume_id = absint( $request['id'] );
		$post_id   = absint( $request['post_id'] );

		if ( ! $this->is_valid_volume( $volume_id ) || ! $post_id ) {
			return new WP_Error( 'cz_invalid_data', __( 'Dati non validi.', 'cz-volume' ), array( 'status' => 400 ) );
		}

		$ok = $this->manager->remove_chapter( $volume_id, $post_id );
		if ( ! $ok ) {
			return new WP_Error( 'cz_delete_failed', __( 'Impossibile rimuovere il capitolo.', 'cz-volume' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'volume_id' => $volume_id,
					'post_id'   => $post_id,
				),
			)
		);
	}

	public function can_edit() {
		return current_user_can( 'edit_posts' );
	}

	private function is_valid_volume( $volume_id ) {
		$post = get_post( $volume_id );
		return $post && 'volume' === $post->post_type;
	}

	private function is_valid_post( $post_id ) {
		$post = get_post( $post_id );
		return $post && 'post' === $post->post_type;
	}
}
