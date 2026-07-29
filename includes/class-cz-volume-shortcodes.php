<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CZ_Volume_Shortcodes {
	const NAV_MARKER_CLASS = 'cz-volume-chapters-nav';

	/**
	 * @var CZ_Volume_Manager
	 */
	private $manager;

	public function __construct( CZ_Volume_Manager $manager ) {
		$this->manager = $manager;

		add_shortcode( 'cz_volume_chapters_nav', array( $this, 'render_chapters_nav' ) );

		// Agganciato a wp_link_pages (non a the_content) cosi la navigazione
		// compare sempre dopo il blocco "Pagine" del tema, qualunque sia il
		// tema attivo, senza dover intervenire sui template.
		add_filter( 'wp_link_pages', array( $this, 'maybe_append_nav' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
	}

	public function maybe_enqueue_assets() {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$has_manual_shortcode = $this->has_manual_shortcode( $post_id );

		if ( ! $has_manual_shortcode ) {
			if ( ! $this->is_auto_append_enabled( $post_id ) ) {
				return;
			}
			if ( null === $this->resolve_navigation( $post_id, 0 ) ) {
				return;
			}
		}

		list( $css_url, $css_ver ) = cz_volume_get_asset( 'assets/frontend.css' );

		wp_enqueue_style( 'cz-volume-frontend', $css_url, array(), $css_ver );
	}

	public function render_chapters_nav( $atts ) {
		$atts = shortcode_atts(
			array(
				'volume_id'  => 0,
				'post_id'    => 0,
				'prev_label' => __( 'Capitolo Precedente', 'cz-volume' ),
				'next_label' => __( 'Capitolo Successivo', 'cz-volume' ),
			),
			$atts,
			'cz_volume_chapters_nav'
		);

		$post_id = absint( $atts['post_id'] );
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( ! $post_id ) {
			return '';
		}

		$navigation = $this->resolve_navigation( $post_id, absint( $atts['volume_id'] ) );
		if ( null === $navigation ) {
			return '';
		}

		return $this->build_nav_html( $navigation, $atts['prev_label'], $atts['next_label'] );
	}

	/**
	 * Appende automaticamente la navigazione subito dopo il blocco "Pagine"
	 * (wp_link_pages) dei post che appartengono a un Volume, salvo che lo
	 * shortcode sia gia presente manualmente nel contenuto (per evitare il
	 * doppione) o l'append automatico sia disattivato via filtro
	 * `cz_volume_auto_append_nav`.
	 */
	public function maybe_append_nav( $links ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $links;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $links;
		}

		if ( $this->has_manual_shortcode( $post_id ) || ! $this->is_auto_append_enabled( $post_id ) ) {
			return $links;
		}

		$navigation = $this->resolve_navigation( $post_id, 0 );
		if ( null === $navigation ) {
			return $links;
		}

		$nav_html = $this->build_nav_html(
			$navigation,
			__( 'Capitolo Precedente', 'cz-volume' ),
			__( 'Capitolo Successivo', 'cz-volume' )
		);

		return $links . $nav_html;
	}

	private function has_manual_shortcode( $post_id ) {
		$raw_content = get_post_field( 'post_content', $post_id );

		return is_string( $raw_content ) && has_shortcode( $raw_content, 'cz_volume_chapters_nav' );
	}

	/**
	 * @param bool $default Se true (default), l'append automatico e attivo a meno di filtro.
	 */
	private function is_auto_append_enabled( $post_id ) {
		return (bool) apply_filters( 'cz_volume_auto_append_nav', true, $post_id );
	}

	/**
	 * Determina capitolo precedente/successivo per un post all'interno del
	 * suo Volume (Principale se non specificato).
	 *
	 * @return array{prev:array|null,next:array|null}|null
	 */
	private function resolve_navigation( $post_id, $volume_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return null;
		}

		$volume_id = absint( $volume_id );
		if ( ! $volume_id ) {
			$volume_id = $this->get_primary_volume_id( $post_id );
		}
		if ( ! $volume_id ) {
			return null;
		}

		$chapters = $this->get_navigable_chapters( $volume_id );
		if ( count( $chapters ) < 2 ) {
			return null;
		}

		$current_index = null;
		foreach ( $chapters as $index => $chapter ) {
			if ( absint( $chapter['post_id'] ) === $post_id ) {
				$current_index = $index;
				break;
			}
		}

		if ( null === $current_index ) {
			return null;
		}

		$prev_chapter = ( $current_index > 0 ) ? $chapters[ $current_index - 1 ] : null;
		$next_chapter = ( $current_index < count( $chapters ) - 1 ) ? $chapters[ $current_index + 1 ] : null;

		if ( ! $prev_chapter && ! $next_chapter ) {
			return null;
		}

		return array(
			'prev' => $prev_chapter,
			'next' => $next_chapter,
		);
	}

	private function build_nav_html( array $navigation, $prev_label, $next_label ) {
		ob_start();

		echo '<nav class="' . esc_attr( self::NAV_MARKER_CLASS ) . '" aria-label="' . esc_attr__( 'Navigazione capitoli del volume', 'cz-volume' ) . '">';

		echo '<div class="cz-volume-nav-item cz-volume-nav-prev">';
		if ( $navigation['prev'] ) {
			$this->render_nav_link( $navigation['prev'], $prev_label );
		}
		echo '</div>';

		echo '<div class="cz-volume-nav-item cz-volume-nav-next">';
		if ( $navigation['next'] ) {
			$this->render_nav_link( $navigation['next'], $next_label );
		}
		echo '</div>';

		echo '</nav>';

		return ob_get_clean();
	}

	private function render_nav_link( array $chapter, $label ) {
		$chapter_post_id = absint( $chapter['post_id'] );
		$permalink        = get_permalink( $chapter_post_id );
		if ( ! $permalink ) {
			return;
		}

		$title = $this->get_chapter_display_title( $chapter );

		echo '<a class="cz-volume-nav-link" href="' . esc_url( $permalink ) . '">';
		echo '<span class="cz-volume-nav-label">' . esc_html( $label ) . '</span>';
		echo '<span class="cz-volume-nav-title">' . esc_html( $title ) . '</span>';
		echo '</a>';
	}

	private function get_chapter_display_title( array $chapter ) {
		$post_id       = absint( $chapter['post_id'] );
		$display_title = (string) get_post_meta( $post_id, '_cz_post_display_title', true );
		if ( '' !== $display_title ) {
			return $display_title;
		}

		if ( ! empty( $chapter['post_title'] ) ) {
			return $chapter['post_title'];
		}

		return get_the_title( $post_id );
	}

	/**
	 * Elenco capitoli del volume filtrato ai soli post effettivamente
	 * raggiungibili (esistenti e pubblicati), nell'ordine di lettura.
	 */
	private function get_navigable_chapters( $volume_id ) {
		$chapters  = $this->manager->get_chapters( $volume_id );
		$navigable = array();

		foreach ( $chapters as $chapter ) {
			$chapter_post_id = isset( $chapter['post_id'] ) ? absint( $chapter['post_id'] ) : 0;
			if ( ! $chapter_post_id || 'publish' !== get_post_status( $chapter_post_id ) ) {
				continue;
			}

			$navigable[] = $chapter;
		}

		return $navigable;
	}

	private function get_primary_volume_id( $post_id ) {
		$volumes = $this->manager->get_volumes_by_post( $post_id );
		if ( empty( $volumes ) ) {
			return 0;
		}

		foreach ( $volumes as $volume ) {
			if ( ! empty( $volume['is_primary'] ) ) {
				return absint( $volume['volume_id'] );
			}
		}

		return absint( $volumes[0]['volume_id'] );
	}
}
