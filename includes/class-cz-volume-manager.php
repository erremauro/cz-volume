<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CZ_Volume_Manager {
	const CACHE_TTL = 43200;

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'cz_volume_items';
	}

	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'cz_volume_items';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			volume_id BIGINT UNSIGNED NOT NULL,
			post_id BIGINT UNSIGNED NOT NULL,
			chapter_number INT NOT NULL,
			entry_type VARCHAR(20) NOT NULL DEFAULT 'chapter',
			section_label VARCHAR(191) NOT NULL DEFAULT '',
			is_primary TINYINT(1) NOT NULL DEFAULT 0,
			position INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY volume_id (volume_id),
			KEY post_id (post_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	public function get_chapters( $volume_id ) {
		$volume_id = absint( $volume_id );
		if ( ! $volume_id ) {
			return array();
		}

		$cache_key = 'cz_volume_' . $volume_id;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$sql = $this->wpdb->prepare(
			"SELECT i.id, i.volume_id, i.post_id, i.chapter_number, i.entry_type, i.section_label, i.is_primary, i.position, p.post_title
			FROM {$this->table_name} i
			LEFT JOIN {$this->wpdb->posts} p ON p.ID = i.post_id
			WHERE i.volume_id = %d
			ORDER BY i.position ASC, i.id ASC",
			$volume_id
		);

		$results = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $results ) ) {
			$results = array();
		}
		foreach ( $results as &$row ) {
			$row = $this->normalize_chapter_row( $row );
		}
		unset( $row );

		set_transient( $cache_key, $results, self::CACHE_TTL );

		return $results;
	}

	public function get_volumes_by_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return array();
		}

		$sql = $this->wpdb->prepare(
			"SELECT i.volume_id, i.post_id, i.chapter_number, i.entry_type, i.section_label, i.is_primary, i.position, v.post_title AS volume_title
			FROM {$this->table_name} i
			INNER JOIN {$this->wpdb->posts} v ON v.ID = i.volume_id
			WHERE i.post_id = %d
			AND v.post_type = %s
			ORDER BY i.position ASC, i.id ASC",
			$post_id,
			'volume'
		);

		$results = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $results ) ) {
			return array();
		}
		foreach ( $results as &$row ) {
			$row = $this->normalize_chapter_row( $row );
		}
		unset( $row );

		return $results;
	}

	public function add_chapter( $volume_id, $post_id, $chapter_number, $position, $is_primary = 0, $entry_type = 'chapter', $section_label = '' ) {
		$volume_id      = absint( $volume_id );
		$post_id        = absint( $post_id );
		$chapter_number = intval( $chapter_number );
		$position       = intval( $position );
		$is_primary     = $is_primary ? 1 : 0;
		$entry_type     = $this->sanitize_entry_type( $entry_type );
		$section_label  = sanitize_text_field( (string) $section_label );

		if ( ! $volume_id || ! $post_id ) {
			return false;
		}
		if ( 'chapter' !== $entry_type ) {
			$chapter_number = 0;
		}

		$affected_volume_ids = array( $volume_id );
		if ( $is_primary ) {
			$affected_volume_ids = array_merge( $affected_volume_ids, $this->get_volume_ids_by_post( $post_id ) );
			$this->wpdb->query(
				$this->wpdb->prepare(
					"UPDATE {$this->table_name} SET is_primary = 0 WHERE post_id = %d",
					$post_id
				)
			);
		}

		$existing_sql = $this->wpdb->prepare(
			"SELECT id FROM {$this->table_name} WHERE volume_id = %d AND post_id = %d LIMIT 1",
			$volume_id,
			$post_id
		);
		$existing_id  = (int) $this->wpdb->get_var( $existing_sql );

		if ( $existing_id ) {
			$updated = $this->wpdb->query(
				$this->wpdb->prepare(
					"UPDATE {$this->table_name}
					SET chapter_number = %d, entry_type = %s, section_label = %s, is_primary = %d, position = %d
					WHERE id = %d",
					$chapter_number,
					$entry_type,
					$section_label,
					$is_primary,
					$position,
					$existing_id
				)
			);
			$this->clear_cache_many( $affected_volume_ids );

			return false !== $updated;
		}

		$inserted = $this->wpdb->query(
			$this->wpdb->prepare(
				"INSERT INTO {$this->table_name} (volume_id, post_id, chapter_number, entry_type, section_label, is_primary, position) VALUES (%d, %d, %d, %s, %s, %d, %d)",
				$volume_id,
				$post_id,
				$chapter_number,
				$entry_type,
				$section_label,
				$is_primary,
				$position
			)
		);

		$this->clear_cache_many( $affected_volume_ids );

		return false !== $inserted;
	}

	public function update_positions( $volume_id, array $ordered_post_ids ) {
		$volume_id = absint( $volume_id );
		if ( ! $volume_id ) {
			return false;
		}

		$position = 0;
		foreach ( $ordered_post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( ! $post_id ) {
				continue;
			}

			$this->wpdb->query(
				$this->wpdb->prepare(
					"UPDATE {$this->table_name}
					SET position = %d
					WHERE volume_id = %d AND post_id = %d",
					$position,
					$volume_id,
					$post_id
				)
			);
			$position++;
		}

		$this->clear_cache( $volume_id );

		return true;
	}

	public function remove_chapter( $volume_id, $post_id ) {
		$volume_id = absint( $volume_id );
		$post_id   = absint( $post_id );
		if ( ! $volume_id || ! $post_id ) {
			return false;
		}

		$deleted = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE volume_id = %d AND post_id = %d",
				$volume_id,
				$post_id
			)
		);

		$this->clear_cache( $volume_id );

		return false !== $deleted;
	}

	public function update_chapter_number( $volume_id, $post_id, $chapter_number ) {
		$volume_id      = absint( $volume_id );
		$post_id        = absint( $post_id );
		$chapter_number = intval( $chapter_number );
		if ( ! $volume_id || ! $post_id || $chapter_number <= 0 ) {
			return false;
		}

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id, entry_type FROM {$this->table_name} WHERE volume_id = %d AND post_id = %d LIMIT 1",
				$volume_id,
				$post_id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) || empty( $row['id'] ) ) {
			return false;
		}

		$entry_type = isset( $row['entry_type'] ) ? $this->sanitize_entry_type( $row['entry_type'] ) : 'chapter';
		if ( 'chapter' !== $entry_type ) {
			return false;
		}

		$updated = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table_name} SET chapter_number = %d WHERE id = %d",
				$chapter_number,
				(int) $row['id']
			)
		);

		$this->clear_cache( $volume_id );
		return false !== $updated;
	}

	public function remove_chapters_by_volume( $volume_id ) {
		$volume_id = absint( $volume_id );
		if ( ! $volume_id ) {
			return false;
		}

		$deleted = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE volume_id = %d",
				$volume_id
			)
		);

		$this->clear_cache( $volume_id );

		return false !== $deleted;
	}

	public function clear_cache( $volume_id ) {
		$volume_id = absint( $volume_id );
		if ( $volume_id ) {
			delete_transient( 'cz_volume_' . $volume_id );
		}
	}

	public function set_primary_volume_for_post( $post_id, $primary_volume_id = 0 ) {
		$post_id           = absint( $post_id );
		$primary_volume_id = absint( $primary_volume_id );
		if ( ! $post_id ) {
			return false;
		}

		$affected_volume_ids = $this->get_volume_ids_by_post( $post_id );

		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table_name} SET is_primary = 0 WHERE post_id = %d",
				$post_id
			)
		);

		if ( $primary_volume_id ) {
			$this->wpdb->query(
				$this->wpdb->prepare(
					"UPDATE {$this->table_name} SET is_primary = 1 WHERE post_id = %d AND volume_id = %d",
					$post_id,
					$primary_volume_id
				)
			);
			$affected_volume_ids[] = $primary_volume_id;
		}

		$this->clear_cache_many( $affected_volume_ids );

		return true;
	}

	public function post_has_primary_volume( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return false;
		}

		$sql = $this->wpdb->prepare(
			"SELECT id FROM {$this->table_name} WHERE post_id = %d AND is_primary = 1 LIMIT 1",
			$post_id
		);

		return (bool) $this->wpdb->get_var( $sql );
	}

	private function sanitize_entry_type( $entry_type ) {
		$entry_type = sanitize_key( (string) $entry_type );
		if ( ! in_array( $entry_type, array( 'chapter', 'front_matter', 'back_matter' ), true ) ) {
			return 'chapter';
		}

		return $entry_type;
	}

	private function normalize_chapter_row( array $row ) {
		$row['entry_type']    = isset( $row['entry_type'] ) ? $this->sanitize_entry_type( $row['entry_type'] ) : 'chapter';
		$row['section_label'] = isset( $row['section_label'] ) ? (string) $row['section_label'] : '';

		return $row;
	}

	private function clear_cache_many( array $volume_ids ) {
		$volume_ids = array_unique( array_map( 'absint', $volume_ids ) );
		foreach ( $volume_ids as $volume_id ) {
			if ( $volume_id ) {
				$this->clear_cache( $volume_id );
			}
		}
	}

	private function get_volume_ids_by_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return array();
		}

		$sql = $this->wpdb->prepare(
			"SELECT DISTINCT volume_id FROM {$this->table_name} WHERE post_id = %d",
			$post_id
		);
		$ids = $this->wpdb->get_col( $sql );

		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_map( 'absint', $ids );
	}

	public function get_most_used_volumes( $limit = 10 ) {
		$limit = absint( $limit );
		if ( $limit < 1 ) {
			$limit = 10;
		}

		$sql = $this->wpdb->prepare(
			"SELECT v.ID AS volume_id, v.post_title, COUNT(i.id) AS usage_count
			FROM {$this->wpdb->posts} v
			LEFT JOIN {$this->table_name} i ON i.volume_id = v.ID
			WHERE v.post_type = %s
			AND v.post_status IN ('publish', 'draft', 'private')
			GROUP BY v.ID, v.post_title
			ORDER BY usage_count DESC, v.post_title ASC
			LIMIT %d",
			'volume',
			$limit
		);

		$results = $this->wpdb->get_results( $sql, ARRAY_A );
		return is_array( $results ) ? $results : array();
	}
}
