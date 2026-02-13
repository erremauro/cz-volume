<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CZ_VOLUME_PATH . 'includes/class-cz-volume-list-table.php';

class CZ_Volume_Admin {
	/**
	 * @var CZ_Volume_Manager
	 */
	private $manager;

	/**
	 * @var string
	 */
	private $menu_hook = '';

	public function __construct( CZ_Volume_Manager $manager ) {
		$this->manager = $manager;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_post_metabox' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_volume_metabox' ) );
		add_action( 'save_post_post', array( $this, 'save_post_volumes' ) );
		add_action( 'save_post_volume', array( $this, 'save_volume_details' ) );
		add_filter( 'post_row_actions', array( $this, 'add_volume_manage_row_action' ), 10, 2 );

		add_action( 'wp_ajax_cz_add_chapter', array( $this, 'ajax_add_chapter' ) );
		add_action( 'wp_ajax_cz_remove_chapter', array( $this, 'ajax_remove_chapter' ) );
		add_action( 'wp_ajax_cz_update_positions', array( $this, 'ajax_update_positions' ) );
		add_action( 'wp_ajax_cz_search_posts', array( $this, 'ajax_search_posts' ) );
	}

	public function register_menu() {
		$this->menu_hook = add_submenu_page(
			'edit.php?post_type=volume',
			__( 'Gestione Capitoli', 'cz-volume' ),
			__( 'Gestione Capitoli', 'cz-volume' ),
			'edit_posts',
			'cz-volume-chapters',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		$is_chapters_screen = ( $hook === $this->menu_hook );
		$screen             = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_post_editor     = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $screen && 'post' === $screen->post_type;
		$is_volume_editor   = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $screen && 'volume' === $screen->post_type;

		if ( ! $is_chapters_screen && ! $is_post_editor && ! $is_volume_editor ) {
			return;
		}

		list( $admin_css_url, $admin_css_ver ) = cz_volume_get_asset( 'assets/admin.css' );
		list( $admin_js_url, $admin_js_ver )   = cz_volume_get_asset( 'assets/admin.js' );

		wp_enqueue_style(
			'cz-volume-admin',
			$admin_css_url,
			array(),
			$admin_css_ver
		);

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_media();

		wp_enqueue_script(
			'cz-volume-admin',
			$admin_js_url,
			array( 'jquery', 'jquery-ui-sortable' ),
			$admin_js_ver,
			true
		);

		$volume_id = isset( $_GET['volume_id'] ) ? absint( wp_unslash( $_GET['volume_id'] ) ) : 0;

		wp_localize_script(
			'cz-volume-admin',
			'CZVolumeAdmin',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'editPostBaseUrl' => admin_url( 'post.php' ),
				'nonce'    => wp_create_nonce( 'cz_volume_admin' ),
				'volumeId' => $volume_id,
				'postEditor' => $is_post_editor,
				'volumeEditor' => $is_volume_editor,
				'i18n'     => array(
					'confirmRemove' => __( 'Vuoi rimuovere questo capitolo?', 'cz-volume' ),
					'error'         => __( 'Operazione non riuscita.', 'cz-volume' ),
					'selectPost'    => __( 'Seleziona un post dalla tabella elenco.', 'cz-volume' ),
					'searchEmpty'   => __( 'Nessun post trovato.', 'cz-volume' ),
					'dragHandle'    => __( 'Trascina per riordinare', 'cz-volume' ),
					'remove'        => __( 'Rimuovi', 'cz-volume' ),
					'yes'           => __( 'Si', 'cz-volume' ),
					'no'            => __( 'No', 'cz-volume' ),
					'postNotFound'  => __( '(post non trovato)', 'cz-volume' ),
					'noChapters'    => __( 'Nessun capitolo trovato per questo volume.', 'cz-volume' ),
					'alreadyAdded'  => __( 'Gia nel volume', 'cz-volume' ),
					'available'     => __( 'Disponibile', 'cz-volume' ),
					'selectedPost'  => __( 'Post selezionato:', 'cz-volume' ),
					'noneSelected'  => __( 'Nessun post selezionato.', 'cz-volume' ),
					'all'           => __( 'Tutti', 'cz-volume' ),
					'published'     => __( 'Pubblicati', 'cz-volume' ),
					'draft'         => __( 'Bozze', 'cz-volume' ),
					'title'         => __( 'Titolo', 'cz-volume' ),
					'author'        => __( 'Autore', 'cz-volume' ),
					'status'        => __( 'Stato', 'cz-volume' ),
					'availability'  => __( 'Disponibilita', 'cz-volume' ),
					'availableOnly' => __( 'Solo disponibili', 'cz-volume' ),
					'loading'       => __( 'Caricamento post...', 'cz-volume' ),
					'clearAuthor'   => __( 'Rimuovi filtro autore', 'cz-volume' ),
					'pageLabel'     => __( 'Pagina', 'cz-volume' ),
				),
			)
		);
	}

	public function register_post_metabox() {
		add_meta_box(
			'cz-post-volumes-metabox',
			__( 'Volumi', 'cz-volume' ),
			array( $this, 'render_post_metabox' ),
			'post',
			'side',
			'default'
		);
	}

	public function render_post_metabox( $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		wp_nonce_field( 'cz_post_volumes_save', 'cz_post_volumes_nonce' );

		$volumes = get_posts(
			array(
				'post_type'      => 'volume',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$assigned_volume_ids = array_map(
			'absint',
			wp_list_pluck( $this->manager->get_volumes_by_post( $post->ID ), 'volume_id' )
		);
		$assigned_lookup = array_fill_keys( $assigned_volume_ids, true );
		$most_used       = $this->manager->get_most_used_volumes( 10 );

		$all_volumes = array();
		foreach ( $volumes as $volume ) {
			$all_volumes[] = array(
				'id'    => (int) $volume->ID,
				'title' => (string) $volume->post_title,
			);
		}

		$volumes_json = wp_json_encode( $all_volumes );
		if ( ! $volumes_json ) {
			$volumes_json = '[]';
		}

		echo '<div class="cz-post-volumes-box">';
		echo '<p><strong>' . esc_html__( 'Aggiungi nuovo/a volume', 'cz-volume' ) . '</strong></p>';
		echo '<div id="cz-volume-hidden-inputs">';
		foreach ( $assigned_volume_ids as $assigned_volume_id ) {
			echo '<input type="hidden" class="cz-volume-hidden-input" name="cz_post_volumes[]" value="' . esc_attr( (string) $assigned_volume_id ) . '" />';
		}
		echo '</div>';

		echo '<div id="cz-volume-tokenbox" class="cz-volume-tokenbox" data-volumes="' . esc_attr( $volumes_json ) . '">';
		echo '<div id="cz-volume-chips" class="cz-volume-chips">';
		foreach ( $volumes as $volume ) {
			if ( ! isset( $assigned_lookup[ (int) $volume->ID ] ) ) {
				continue;
			}
			echo '<span class="cz-volume-chip" data-volume-id="' . esc_attr( (string) $volume->ID ) . '">';
			echo esc_html( $volume->post_title );
			echo ' <button type="button" class="cz-chip-remove" aria-label="' . esc_attr__( 'Rimuovi', 'cz-volume' ) . '">×</button>';
			echo '</span>';
		}
		echo '</div>';
		echo '<input type="search" id="cz-volume-token-input" class="cz-volume-token-input" placeholder="' . esc_attr__( 'Cerca volume...', 'cz-volume' ) . '" />';
		echo '<div id="cz-volume-suggestions" class="cz-volume-suggestions" hidden></div>';
		echo '</div>';

		echo '<p class="description">' . esc_html__( 'Separa con virgole o premendo il tasto Invio.', 'cz-volume' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Più utilizzati', 'cz-volume' ) . '</strong></p>';
		echo '<ul id="cz-volume-most-used" class="cz-volume-most-used">';

		if ( empty( $most_used ) ) {
			echo '<li>' . esc_html__( 'Nessun volume disponibile.', 'cz-volume' ) . '</li>';
		} else {
			foreach ( $most_used as $volume_row ) {
				$volume_id    = isset( $volume_row['volume_id'] ) ? absint( $volume_row['volume_id'] ) : 0;
				$volume_title = isset( $volume_row['post_title'] ) ? $volume_row['post_title'] : '';
				if ( ! $volume_id || '' === $volume_title ) {
					continue;
				}
				$is_selected = isset( $assigned_lookup[ $volume_id ] );
				echo '<li>';
				echo '<a href="#" class="cz-volume-most-used-link' . ( $is_selected ? ' is-selected' : '' ) . '" data-volume-id="' . esc_attr( (string) $volume_id ) . '" data-volume-title="' . esc_attr( $volume_title ) . '">';
				echo esc_html( $volume_title );
				echo '</a>';
				echo '</li>';
			}
		}

		echo '</ul>';
		echo '</div>';
	}

	public function register_volume_metabox() {
		add_meta_box(
			'cz-volume-details-metabox',
			__( 'Dettagli Volume', 'cz-volume' ),
			array( $this, 'render_volume_metabox' ),
			'volume',
			'side',
			'high'
		);
	}

	public function render_volume_metabox( $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		wp_nonce_field( 'cz_volume_details_save', 'cz_volume_details_nonce' );

		$cover_id     = absint( get_post_meta( $post->ID, '_cz_volume_cover_image_id', true ) );
		$epub_id      = absint( get_post_meta( $post->ID, '_cz_volume_epub_file_id', true ) );
		$pdf_id       = absint( get_post_meta( $post->ID, '_cz_volume_pdf_file_id', true ) );
		$subtitle     = (string) get_post_meta( $post->ID, '_cz_volume_subtitle', true );
		$is_completed = (int) get_post_meta( $post->ID, '_cz_volume_completed', true );

		$cover_url = $cover_id ? wp_get_attachment_image_url( $cover_id, 'medium' ) : '';
		$epub_url  = $epub_id ? wp_get_attachment_url( $epub_id ) : '';
		$pdf_url   = $pdf_id ? wp_get_attachment_url( $pdf_id ) : '';

		echo '<div class="cz-volume-meta-box">';

		echo '<div class="cz-volume-field">';
		echo '<p><strong>' . esc_html__( 'Sottotitolo', 'cz-volume' ) . '</strong></p>';
		echo '<input type="text" class="widefat" name="cz_volume_subtitle" value="' . esc_attr( $subtitle ) . '" placeholder="' . esc_attr__( 'Inserisci un sottotitolo', 'cz-volume' ) . '" />';
		echo '</div>';

		echo '<div class="cz-volume-field">';
		echo '<p><strong>' . esc_html__( 'Copertina del Volume', 'cz-volume' ) . '</strong></p>';
		echo '<input type="hidden" id="cz-volume-cover-id" name="cz_volume_cover_image_id" value="' . esc_attr( (string) $cover_id ) . '" />';
		echo '<div id="cz-volume-cover-preview" class="cz-media-preview">';
		if ( $cover_url ) {
			echo '<img src="' . esc_url( $cover_url ) . '" alt="" />';
		} else {
			echo '<span class="cz-media-placeholder">' . esc_html__( 'Nessuna immagine selezionata', 'cz-volume' ) . '</span>';
		}
		echo '</div>';
		echo '<p>';
		echo '<button type="button" class="button cz-media-upload" data-target-id="#cz-volume-cover-id" data-target-preview="#cz-volume-cover-preview" data-type="image">' . esc_html__( 'Carica Copertina', 'cz-volume' ) . '</button> ';
		echo '<button type="button" class="button cz-media-remove" data-target-id="#cz-volume-cover-id" data-target-preview="#cz-volume-cover-preview">' . esc_html__( 'Rimuovi', 'cz-volume' ) . '</button>';
		echo '</p>';
		echo '</div>';

		echo '<div class="cz-volume-field">';
		echo '<p><strong>' . esc_html__( 'File EPUB (.epub)', 'cz-volume' ) . '</strong></p>';
		echo '<input type="hidden" id="cz-volume-epub-id" name="cz_volume_epub_file_id" value="' . esc_attr( (string) $epub_id ) . '" />';
		echo '<div id="cz-volume-epub-preview" class="cz-file-preview">';
		if ( $epub_url ) {
			echo '<a href="' . esc_url( $epub_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( wp_basename( $epub_url ) ) . '</a>';
		} else {
			echo '<span class="cz-media-placeholder">' . esc_html__( 'Nessun file EPUB', 'cz-volume' ) . '</span>';
		}
		echo '</div>';
		echo '<p>';
		echo '<button type="button" class="button cz-media-upload" data-target-id="#cz-volume-epub-id" data-target-preview="#cz-volume-epub-preview" data-type="epub">' . esc_html__( 'Carica EPUB', 'cz-volume' ) . '</button> ';
		echo '<button type="button" class="button cz-media-remove" data-target-id="#cz-volume-epub-id" data-target-preview="#cz-volume-epub-preview">' . esc_html__( 'Rimuovi', 'cz-volume' ) . '</button>';
		echo '</p>';
		echo '</div>';

		echo '<div class="cz-volume-field">';
		echo '<p><strong>' . esc_html__( 'File PDF (.pdf)', 'cz-volume' ) . '</strong></p>';
		echo '<input type="hidden" id="cz-volume-pdf-id" name="cz_volume_pdf_file_id" value="' . esc_attr( (string) $pdf_id ) . '" />';
		echo '<div id="cz-volume-pdf-preview" class="cz-file-preview">';
		if ( $pdf_url ) {
			echo '<a href="' . esc_url( $pdf_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( wp_basename( $pdf_url ) ) . '</a>';
		} else {
			echo '<span class="cz-media-placeholder">' . esc_html__( 'Nessun file PDF', 'cz-volume' ) . '</span>';
		}
		echo '</div>';
		echo '<p>';
		echo '<button type="button" class="button cz-media-upload" data-target-id="#cz-volume-pdf-id" data-target-preview="#cz-volume-pdf-preview" data-type="pdf">' . esc_html__( 'Carica PDF', 'cz-volume' ) . '</button> ';
		echo '<button type="button" class="button cz-media-remove" data-target-id="#cz-volume-pdf-id" data-target-preview="#cz-volume-pdf-preview">' . esc_html__( 'Rimuovi', 'cz-volume' ) . '</button>';
		echo '</p>';
		echo '</div>';

		echo '<div class="cz-volume-field">';
		echo '<p><strong>' . esc_html__( 'Completato', 'cz-volume' ) . '</strong></p>';
		echo '<label class="cz-switch">';
		echo '<input type="checkbox" name="cz_volume_completed" value="1" ' . checked( $is_completed, 1, false ) . ' />';
		echo '<span class="cz-switch-slider" aria-hidden="true"></span>';
		echo '</label>';
		echo '</div>';

		echo '</div>';
	}

	public function add_volume_manage_row_action( $actions, $post ) {
		if ( ! $post || 'volume' !== $post->post_type || ! current_user_can( 'edit_posts' ) ) {
			return $actions;
		}

		$url = add_query_arg(
			array(
				'post_type' => 'volume',
				'page'      => 'cz-volume-chapters',
				'volume_id' => (int) $post->ID,
			),
			admin_url( 'edit.php' )
		);

		$actions['cz_manage_chapters'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Gestisci', 'cz-volume' ) . '</a>';

		return $actions;
	}

	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'cz-volume' ) );
		}

		$volume_id = isset( $_GET['volume_id'] ) ? absint( wp_unslash( $_GET['volume_id'] ) ) : 0;
		$volumes   = get_posts(
			array(
				'post_type'      => 'volume',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Gestione Capitoli Volume', 'cz-volume' ) . '</h1>';

		echo '<form method="get" class="cz-volume-selector">';
		echo '<input type="hidden" name="post_type" value="volume" />';
		echo '<input type="hidden" name="page" value="cz-volume-chapters" />';
		echo '<label for="cz-volume-id"><strong>' . esc_html__( 'Seleziona Volume:', 'cz-volume' ) . '</strong></label> ';
		echo '<select id="cz-volume-id" name="volume_id" onchange="this.form.submit()">';
		echo '<option value="0">' . esc_html__( '-- Seleziona --', 'cz-volume' ) . '</option>';

		foreach ( $volumes as $volume ) {
			echo '<option value="' . esc_attr( (string) $volume->ID ) . '" ' . selected( $volume_id, $volume->ID, false ) . '>' . esc_html( $volume->post_title ) . '</option>';
		}
		echo '</select>';
		echo '</form>';

		if ( $volume_id ) {
			$list_table = new CZ_Volume_List_Table( $this->manager, $volume_id );
			$list_table->prepare_items();

			echo '<hr />';
			echo '<h2>' . esc_html__( 'Aggiungi Capitolo', 'cz-volume' ) . '</h2>';
			echo '<form id="cz-add-chapter-form">';
			echo '<input type="hidden" name="action" value="cz_add_chapter" />';
			echo '<input type="hidden" name="nonce" value="' . esc_attr( wp_create_nonce( 'cz_volume_admin' ) ) . '" />';
			echo '<input type="hidden" name="volume_id" value="' . esc_attr( (string) $volume_id ) . '" />';
			echo '<input type="hidden" id="cz-post-id" name="post_id" value="" />';

			echo '<div class="cz-add-chapter-controls">';
			echo '<p id="cz-selected-post-label"><strong>' . esc_html__( 'Nessun post selezionato.', 'cz-volume' ) . '</strong></p>';
			echo '<p class="description">' . esc_html__( 'Seleziona un post dalla tabella sotto, poi clicca "Aggiungi Capitolo".', 'cz-volume' ) . '</p>';

			echo '<div class="cz-add-inline-fields">';
			echo '<label for="cz-chapter-number"><strong>' . esc_html__( 'Numero Capitolo', 'cz-volume' ) . '</strong></label>';
			echo '<input type="number" id="cz-chapter-number" name="chapter_number" min="1" step="1" required />';

			echo '<label for="cz-position"><strong>' . esc_html__( 'Posizione', 'cz-volume' ) . '</strong></label>';
			echo '<input type="number" id="cz-position" name="position" min="0" step="1" value="0" />';

			echo '<label for="cz-is-primary" class="cz-inline-checkbox"><input type="checkbox" id="cz-is-primary" name="is_primary" value="1" checked="checked" /> ';
			echo esc_html__( 'Volume Principale', 'cz-volume' ) . '</label>';
			echo '</div>';

			echo '<p class="description">' . esc_html__( 'Se attivo, disattiva automaticamente il Volume Principale negli altri volumi dello stesso capitolo.', 'cz-volume' ) . '</p>';
			echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Aggiungi Capitolo', 'cz-volume' ) . '</button></p>';
			echo '</div>';
			echo '</form>';

			echo '<h3>' . esc_html__( 'Elenco Post', 'cz-volume' ) . '</h3>';
			echo '<div id="cz-post-browser" class="cz-post-browser">';
			echo '<div class="cz-post-browser-toolbar">';
			echo '<div id="cz-post-views" class="subsubsub"></div>';
			echo '<div class="cz-post-filters-wrap">';
			echo '<label for="cz-post-availability-filter" class="screen-reader-text">' . esc_html__( 'Filtro disponibilita', 'cz-volume' ) . '</label>';
			echo '<select id="cz-post-availability-filter" class="cz-post-availability-filter">';
			echo '<option value="all">' . esc_html__( 'Tutti', 'cz-volume' ) . '</option>';
			echo '<option value="available">' . esc_html__( 'Solo disponibili', 'cz-volume' ) . '</option>';
			echo '</select>';
			echo '<div class="cz-post-search-wrap">';
			echo '<input type="search" id="cz-post-search-live" class="regular-text" placeholder="' . esc_attr__( 'Cerca articoli...', 'cz-volume' ) . '" />';
			echo '</div>';
			echo '</div>';
			echo '</div>';
			echo '<div id="cz-post-author-filter" class="cz-post-author-filter" hidden></div>';
			echo '<div class="cz-compact-list-wrap">';
			echo '<table class="wp-list-table widefat fixed striped posts cz-compact-posts-table">';
			echo '<thead><tr>';
			echo '<th scope="col" id="cz-col-title" class="manage-column column-title sortable desc">';
			echo '<a href="#" class="cz-sort-link" data-orderby="title" data-order="asc">';
			echo '<span>' . esc_html__( 'Titolo', 'cz-volume' ) . '</span>';
			echo '<span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span>';
			echo '</a>';
			echo '</th>';
			echo '<th scope="col" id="cz-col-author" class="manage-column column-author sortable desc">';
			echo '<a href="#" class="cz-sort-link" data-orderby="author" data-order="asc">';
			echo '<span>' . esc_html__( 'Autore', 'cz-volume' ) . '</span>';
			echo '<span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span>';
			echo '</a>';
			echo '</th>';
			echo '<th scope="col" class="manage-column">' . esc_html__( 'Stato', 'cz-volume' ) . '</th>';
			echo '<th scope="col" class="manage-column">' . esc_html__( 'Disponibilita', 'cz-volume' ) . '</th>';
			echo '</tr></thead>';
			echo '<tbody id="cz-search-results-body">';
			echo '<tr><td colspan="4">' . esc_html__( 'Caricamento post...', 'cz-volume' ) . '</td></tr>';
			echo '</tbody>';
			echo '</table>';
			echo '</div>';
			echo '<div id="cz-posts-pagination" class="cz-posts-pagination"></div>';
			echo '</div>';

			echo '<hr />';
			echo '<h2>' . esc_html__( 'Capitoli del Volume', 'cz-volume' ) . '</h2>';
			echo '<p>' . esc_html__( 'Trascina le righe per aggiornare l\'ordine.', 'cz-volume' ) . '</p>';
			$list_table->display();
		}

		echo '</div>';
	}

	public function ajax_add_chapter() {
		$this->assert_ajax_permissions();

		$volume_id      = isset( $_POST['volume_id'] ) ? absint( wp_unslash( $_POST['volume_id'] ) ) : 0;
		$post_id        = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$chapter_number = isset( $_POST['chapter_number'] ) ? intval( wp_unslash( $_POST['chapter_number'] ) ) : 0;
		$position_raw   = isset( $_POST['position'] ) ? trim( (string) wp_unslash( $_POST['position'] ) ) : '';
		$is_primary     = isset( $_POST['is_primary'] ) ? 1 : 0;

		if ( ! $this->is_valid_volume( $volume_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Volume non valido.', 'cz-volume' ) ), 400 );
		}

		if ( ! $this->is_valid_post( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Post non valido.', 'cz-volume' ) ), 400 );
		}

		if ( $chapter_number <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Numero capitolo non valido.', 'cz-volume' ) ), 400 );
		}

		$position = ( '' === $position_raw ) ? $chapter_number : intval( $position_raw );
		if ( $position < 0 ) {
			wp_send_json_error( array( 'message' => __( 'Posizione non valida.', 'cz-volume' ) ), 400 );
		}

		$ok = $this->manager->add_chapter( $volume_id, $post_id, $chapter_number, $position, $is_primary );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Impossibile aggiungere il capitolo.', 'cz-volume' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Capitolo aggiunto.', 'cz-volume' ),
				'chapters' => $this->manager->get_chapters( $volume_id ),
			)
		);
	}

	public function save_post_volumes( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['cz_post_volumes_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cz_post_volumes_nonce'] ) ), 'cz_post_volumes_save' ) ) {
			return;
		}

		$selected_volume_ids = isset( $_POST['cz_post_volumes'] ) ? (array) wp_unslash( $_POST['cz_post_volumes'] ) : array();
		$selected_volume_ids = array_values( array_unique( array_filter( array_map( 'absint', $selected_volume_ids ) ) ) );

		$current_relations  = $this->manager->get_volumes_by_post( $post_id );
		$current_volume_ids = array_values( array_unique( array_map( 'absint', wp_list_pluck( $current_relations, 'volume_id' ) ) ) );

		$to_remove = array_diff( $current_volume_ids, $selected_volume_ids );
		$to_add    = array_diff( $selected_volume_ids, $current_volume_ids );

		foreach ( $to_remove as $volume_id ) {
			if ( $this->is_valid_volume( (int) $volume_id ) ) {
				$this->manager->remove_chapter( (int) $volume_id, (int) $post_id );
			}
		}

		foreach ( $to_add as $volume_id ) {
			if ( ! $this->is_valid_volume( (int) $volume_id ) ) {
				continue;
			}

			$chapters       = $this->manager->get_chapters( (int) $volume_id );
			$next_position  = 0;
			$next_chapter_n = 1;

			if ( ! empty( $chapters ) ) {
				$max_position = max( array_map( 'intval', wp_list_pluck( $chapters, 'position' ) ) );
				$max_chapter  = max( array_map( 'intval', wp_list_pluck( $chapters, 'chapter_number' ) ) );
				$next_position  = $max_position + 1;
				$next_chapter_n = $max_chapter + 1;
			}

			$this->manager->add_chapter( (int) $volume_id, (int) $post_id, $next_chapter_n, $next_position, 0 );
		}

		$primary_volume_id = ! empty( $selected_volume_ids ) ? (int) $selected_volume_ids[0] : 0;
		$this->manager->set_primary_volume_for_post( (int) $post_id, $primary_volume_id );
	}

	public function save_volume_details( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['cz_volume_details_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cz_volume_details_nonce'] ) ), 'cz_volume_details_save' ) ) {
			return;
		}

		$cover_id = isset( $_POST['cz_volume_cover_image_id'] ) ? absint( wp_unslash( $_POST['cz_volume_cover_image_id'] ) ) : 0;
		$epub_id  = isset( $_POST['cz_volume_epub_file_id'] ) ? absint( wp_unslash( $_POST['cz_volume_epub_file_id'] ) ) : 0;
		$pdf_id   = isset( $_POST['cz_volume_pdf_file_id'] ) ? absint( wp_unslash( $_POST['cz_volume_pdf_file_id'] ) ) : 0;
		$subtitle = isset( $_POST['cz_volume_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['cz_volume_subtitle'] ) ) : '';

		if ( $cover_id && ! wp_attachment_is_image( $cover_id ) ) {
			$cover_id = 0;
		}

		if ( $epub_id ) {
			$epub_path = get_attached_file( $epub_id );
			$ext       = strtolower( pathinfo( (string) $epub_path, PATHINFO_EXTENSION ) );
			if ( 'epub' !== $ext ) {
				$epub_id = 0;
			}
		}

		if ( $pdf_id ) {
			$pdf_mime = get_post_mime_type( $pdf_id );
			if ( 'application/pdf' !== $pdf_mime ) {
				$pdf_id = 0;
			}
		}

		update_post_meta( $post_id, '_cz_volume_cover_image_id', $cover_id );
		update_post_meta( $post_id, '_cz_volume_epub_file_id', $epub_id );
		update_post_meta( $post_id, '_cz_volume_pdf_file_id', $pdf_id );
		update_post_meta( $post_id, '_cz_volume_subtitle', $subtitle );
		update_post_meta( $post_id, '_cz_volume_completed', isset( $_POST['cz_volume_completed'] ) ? 1 : 0 );

		if ( $cover_id ) {
			set_post_thumbnail( $post_id, $cover_id );
		} else {
			delete_post_thumbnail( $post_id );
		}
	}

	public function ajax_remove_chapter() {
		$this->assert_ajax_permissions();

		$volume_id = isset( $_POST['volume_id'] ) ? absint( wp_unslash( $_POST['volume_id'] ) ) : 0;
		$post_id   = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

		if ( ! $this->is_valid_volume( $volume_id ) || ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Dati non validi.', 'cz-volume' ) ), 400 );
		}

		$ok = $this->manager->remove_chapter( $volume_id, $post_id );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Impossibile rimuovere il capitolo.', 'cz-volume' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Capitolo rimosso.', 'cz-volume' ),
				'chapters' => $this->manager->get_chapters( $volume_id ),
			)
		);
	}

	public function ajax_update_positions() {
		$this->assert_ajax_permissions();

		$volume_id = isset( $_POST['volume_id'] ) ? absint( wp_unslash( $_POST['volume_id'] ) ) : 0;
		$positions = isset( $_POST['positions'] ) ? (array) wp_unslash( $_POST['positions'] ) : array();

		if ( ! $this->is_valid_volume( $volume_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Volume non valido.', 'cz-volume' ) ), 400 );
		}

		$ordered_post_ids = array();
		foreach ( $positions as $post_id ) {
			$post_id = absint( $post_id );
			if ( $post_id ) {
				$ordered_post_ids[] = $post_id;
			}
		}

		$ok = $this->manager->update_positions( $volume_id, $ordered_post_ids );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Impossibile aggiornare le posizioni.', 'cz-volume' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Posizioni aggiornate.', 'cz-volume' ),
				'chapters' => $this->manager->get_chapters( $volume_id ),
			)
		);
	}

	public function ajax_search_posts() {
		$this->assert_ajax_permissions();

		$volume_id = isset( $_POST['volume_id'] ) ? absint( wp_unslash( $_POST['volume_id'] ) ) : 0;
		$term      = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
		$status    = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'all';
		$author_id = isset( $_POST['author_id'] ) ? absint( wp_unslash( $_POST['author_id'] ) ) : 0;
		$page      = isset( $_POST['page'] ) ? max( 1, absint( wp_unslash( $_POST['page'] ) ) ) : 1;
		$per_page  = isset( $_POST['per_page'] ) ? max( 10, min( 50, absint( wp_unslash( $_POST['per_page'] ) ) ) ) : 20;
		$orderby   = isset( $_POST['orderby'] ) ? sanitize_key( wp_unslash( $_POST['orderby'] ) ) : 'date';
		$order     = isset( $_POST['order'] ) ? strtolower( sanitize_key( wp_unslash( $_POST['order'] ) ) ) : 'desc';
		$availability = isset( $_POST['availability'] ) ? sanitize_key( wp_unslash( $_POST['availability'] ) ) : 'all';

		if ( ! $this->is_valid_volume( $volume_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Volume non valido.', 'cz-volume' ) ), 400 );
		}

		$already_added_ids = array();
		$chapters          = $this->manager->get_chapters( $volume_id );
		foreach ( $chapters as $chapter ) {
			$chapter_post_id = isset( $chapter['post_id'] ) ? absint( $chapter['post_id'] ) : 0;
			if ( $chapter_post_id ) {
				$already_added_ids[ $chapter_post_id ] = true;
			}
		}

		$post_status = array( 'publish', 'draft', 'private', 'pending', 'future' );
		if ( 'publish' === $status ) {
			$post_status = array( 'publish' );
		} elseif ( 'draft' === $status ) {
			$post_status = array( 'draft' );
		}

		if ( 'available' !== $availability ) {
			$availability = 'all';
		}

		$allowed_orderby = array( 'date', 'title', 'author' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'date';
		}

		if ( ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
			$order = 'desc';
		}

		$args = array(
			'post_type'      => 'post',
			'post_status'    => $post_status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => $orderby,
			'order'          => strtoupper( $order ),
		);

		if ( '' !== $term ) {
			$args['cz_volume_title_author_search'] = $term;
			add_filter( 'posts_join', array( $this, 'filter_posts_join_title_author_search' ), 10, 2 );
			add_filter( 'posts_where', array( $this, 'filter_posts_where_title_author_search' ), 10, 2 );
		}

		if ( $author_id ) {
			$args['author'] = $author_id;
		}

		if ( 'available' === $availability && ! empty( $already_added_ids ) ) {
			$args['post__not_in'] = array_keys( $already_added_ids );
		}

		$query = new WP_Query( $args );
		if ( '' !== $term ) {
			remove_filter( 'posts_join', array( $this, 'filter_posts_join_title_author_search' ), 10 );
			remove_filter( 'posts_where', array( $this, 'filter_posts_where_title_author_search' ), 10 );
		}
		$items = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$items[] = array(
					'id'            => (int) $post->ID,
					'title'         => $post->post_title ? $post->post_title : __( '(senza titolo)', 'cz-volume' ),
					'status'        => (string) $post->post_status,
					'status_label'  => $this->get_post_status_label( (string) $post->post_status ),
					'author_id'     => (int) $post->post_author,
					'author_name'   => get_the_author_meta( 'display_name', (int) $post->post_author ),
					'has_primary_volume' => $this->manager->post_has_primary_volume( (int) $post->ID ),
					'already_added' => isset( $already_added_ids[ (int) $post->ID ] ),
				);
			}
		}

		$counts = wp_count_posts( 'post' );
		$count_all = 0;
		foreach ( array( 'publish', 'draft', 'private', 'pending', 'future' ) as $status_key ) {
			$count_all += isset( $counts->$status_key ) ? (int) $counts->$status_key : 0;
		}

		wp_send_json_success(
			array(
				'items' => $items,
				'pagination' => array(
					'current_page' => $page,
					'total_pages'  => max( 1, (int) $query->max_num_pages ),
					'total_items'  => (int) $query->found_posts,
				),
				'views' => array(
					'all'     => $count_all,
					'publish' => isset( $counts->publish ) ? (int) $counts->publish : 0,
					'draft'   => isset( $counts->draft ) ? (int) $counts->draft : 0,
				),
				'filters' => array(
					'status'    => $status,
					'author_id' => $author_id,
					'orderby'   => $orderby,
					'order'     => $order,
					'availability' => $availability,
				),
			)
		);
	}

	private function get_post_status_label( $status ) {
		$status = sanitize_key( (string) $status );
		if ( '' === $status ) {
			return '';
		}

		$status_object = get_post_status_object( $status );
		return ( $status_object && isset( $status_object->label ) ) ? $status_object->label : $status;
	}

	public function filter_posts_join_title_author_search( $join, $query ) {
		global $wpdb;

		$term = $query->get( 'cz_volume_title_author_search' );
		if ( ! is_string( $term ) || '' === $term ) {
			return $join;
		}

		$users_join = " LEFT JOIN {$wpdb->users} AS czv_users ON czv_users.ID = {$wpdb->posts}.post_author ";
		if ( false === strpos( $join, 'AS czv_users' ) ) {
			$join .= $users_join;
		}

		return $join;
	}

	public function filter_posts_where_title_author_search( $where, $query ) {
		global $wpdb;

		$term = $query->get( 'cz_volume_title_author_search' );
		if ( ! is_string( $term ) || '' === $term ) {
			return $where;
		}

		$like  = '%' . $wpdb->esc_like( $term ) . '%';
		$where .= $wpdb->prepare(
			" AND ( {$wpdb->posts}.post_title LIKE %s OR czv_users.display_name LIKE %s )",
			$like,
			$like
		);

		return $where;
	}

	private function assert_ajax_permissions() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'cz-volume' ) ), 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cz_volume_admin' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce non valido.', 'cz-volume' ) ), 403 );
		}
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
