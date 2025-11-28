<?php
/**
 * Global Search
 *
 * Unified search across all platform entities.
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICT_Global_Search {

	private static $instance = null;
	private $search_history_table;
	private $search_index_table;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->search_history_table = $wpdb->prefix . 'ict_search_history';
		$this->search_index_table   = $wpdb->prefix . 'ict_search_index';
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
		add_action( 'ict_project_saved', array( $this, 'index_project' ), 10, 2 );
		add_action( 'ict_inventory_saved', array( $this, 'index_inventory' ), 10, 2 );
		add_action( 'ict_document_saved', array( $this, 'index_document' ), 10, 2 );
	}

	public function register_routes() {
		register_rest_route(
			'ict/v1',
			'/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/search/quick',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'quick_search' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/search/suggestions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_suggestions' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/search/history',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_history' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_history' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/search/reindex',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reindex_all' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	public function check_permission() {
		return is_user_logged_in();
	}

	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	public function maybe_create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql1 = "CREATE TABLE IF NOT EXISTS {$this->search_history_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            query varchar(255) NOT NULL,
            entity_type varchar(50),
            result_count int DEFAULT 0,
            clicked_result_id bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY query (query),
            KEY created_at (created_at)
        ) {$charset_collate};";

		$sql2 = "CREATE TABLE IF NOT EXISTS {$this->search_index_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            content longtext,
            keywords text,
            metadata text,
            searchable_text longtext,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY entity (entity_type, entity_id),
            FULLTEXT KEY searchable (searchable_text),
            KEY entity_type (entity_type)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
	}

	public function search( $request ) {
		global $wpdb;

		$query  = sanitize_text_field( $request->get_param( 'q' ) );
		$types  = $request->get_param( 'types' );
		$limit  = (int) $request->get_param( 'limit' ) ?: 50;
		$offset = (int) $request->get_param( 'offset' ) ?: 0;

		if ( strlen( $query ) < 2 ) {
			return new WP_Error( 'query_too_short', 'Query must be at least 2 characters', array( 'status' => 400 ) );
		}

		$results = array(
			'query'   => $query,
			'results' => array(),
			'total'   => 0,
			'facets'  => array(),
		);

		// Search in index first
		$indexed_results = $this->search_index( $query, $types, $limit, $offset );

		// If no index results, fall back to direct search
		if ( empty( $indexed_results ) ) {
			$indexed_results = $this->search_direct( $query, $types, $limit );
		}

		$results['results'] = $indexed_results;
		$results['total']   = count( $indexed_results );

		// Get facets (counts by type)
		$results['facets'] = $this->get_facets( $query );

		// Log search
		$this->log_search( $query, null, $results['total'] );

		return rest_ensure_response( $results );
	}

	public function quick_search( $request ) {
		global $wpdb;

		$query = sanitize_text_field( $request->get_param( 'q' ) );

		if ( strlen( $query ) < 2 ) {
			return rest_ensure_response( array() );
		}

		$results = array();
		$search  = '%' . $wpdb->esc_like( $query ) . '%';

		// Search projects
		$projects = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, project_number, name, 'project' as type
             FROM {$wpdb->prefix}ict_projects
             WHERE name LIKE %s OR project_number LIKE %s
             LIMIT 5",
				$search,
				$search
			)
		);

		foreach ( $projects as $p ) {
			$results[] = array(
				'id'       => $p->id,
				'type'     => 'project',
				'title'    => $p->name,
				'subtitle' => $p->project_number,
				'url'      => admin_url( "admin.php?page=ict-projects&id={$p->id}" ),
				'icon'     => 'folder',
			);
		}

		// Search inventory
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, sku, name, 'inventory' as type
             FROM {$wpdb->prefix}ict_inventory_items
             WHERE name LIKE %s OR sku LIKE %s
             LIMIT 5",
				$search,
				$search
			)
		);

		foreach ( $items as $i ) {
			$results[] = array(
				'id'       => $i->id,
				'type'     => 'inventory',
				'title'    => $i->name,
				'subtitle' => $i->sku,
				'url'      => admin_url( "admin.php?page=ict-inventory&id={$i->id}" ),
				'icon'     => 'box',
			);
		}

		// Search users
		$users = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, display_name, user_email
             FROM {$wpdb->users}
             WHERE display_name LIKE %s OR user_email LIKE %s
             LIMIT 5",
				$search,
				$search
			)
		);

		foreach ( $users as $u ) {
			$results[] = array(
				'id'       => $u->ID,
				'type'     => 'user',
				'title'    => $u->display_name,
				'subtitle' => $u->user_email,
				'url'      => admin_url( "user-edit.php?user_id={$u->ID}" ),
				'icon'     => 'user',
			);
		}

		// Search documents
		$docs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, category
             FROM {$wpdb->prefix}ict_documents
             WHERE name LIKE %s
             LIMIT 5",
				$search
			)
		);

		foreach ( $docs as $d ) {
			$results[] = array(
				'id'       => $d->id,
				'type'     => 'document',
				'title'    => $d->name,
				'subtitle' => $d->category,
				'url'      => admin_url( "admin.php?page=ict-documents&id={$d->id}" ),
				'icon'     => 'file',
			);
		}

		// Search suppliers
		$suppliers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, email
             FROM {$wpdb->prefix}ict_suppliers
             WHERE name LIKE %s OR email LIKE %s
             LIMIT 5",
				$search,
				$search
			)
		);

		foreach ( $suppliers as $s ) {
			$results[] = array(
				'id'       => $s->id,
				'type'     => 'supplier',
				'title'    => $s->name,
				'subtitle' => $s->email,
				'url'      => admin_url( "admin.php?page=ict-suppliers&id={$s->id}" ),
				'icon'     => 'building',
			);
		}

		return rest_ensure_response( array_slice( $results, 0, 10 ) );
	}

	public function get_suggestions( $request ) {
		global $wpdb;

		$query   = sanitize_text_field( $request->get_param( 'q' ) );
		$user_id = get_current_user_id();

		$suggestions = array();

		// Recent searches
		$recent = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT query FROM {$this->search_history_table}
             WHERE user_id = %d AND query LIKE %s
             ORDER BY created_at DESC LIMIT 5",
				$user_id,
				$query . '%'
			)
		);

		foreach ( $recent as $q ) {
			$suggestions[] = array(
				'text' => $q,
				'type' => 'recent',
				'icon' => 'clock',
			);
		}

		// Popular searches
		$popular = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT query FROM {$this->search_history_table}
             WHERE query LIKE %s
             GROUP BY query
             ORDER BY COUNT(*) DESC LIMIT 5",
				$query . '%'
			)
		);

		foreach ( $popular as $q ) {
			if ( ! in_array( $q, $recent, true ) ) {
				$suggestions[] = array(
					'text' => $q,
					'type' => 'popular',
					'icon' => 'trending-up',
				);
			}
		}

		return rest_ensure_response( array_slice( $suggestions, 0, 10 ) );
	}

	public function get_history( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$limit   = (int) $request->get_param( 'limit' ) ?: 20;

		$history = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query, entity_type, result_count, MAX(created_at) as last_searched
             FROM {$this->search_history_table}
             WHERE user_id = %d
             GROUP BY query, entity_type, result_count
             ORDER BY last_searched DESC LIMIT %d",
				$user_id,
				$limit
			)
		);

		return rest_ensure_response( $history );
	}

	public function clear_history( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$wpdb->delete( $this->search_history_table, array( 'user_id' => $user_id ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function reindex_all( $request ) {
		global $wpdb;

		// Clear existing index
		$wpdb->query( "TRUNCATE TABLE {$this->search_index_table}" );

		$indexed = 0;

		// Index projects
		$projects = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}ict_projects"
		);
		foreach ( $projects as $project ) {
			$this->index_project( $project->id, (array) $project );
			++$indexed;
		}

		// Index inventory
		$items = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}ict_inventory_items"
		);
		foreach ( $items as $item ) {
			$this->index_inventory( $item->id, (array) $item );
			++$indexed;
		}

		// Index documents
		$docs = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}ict_documents"
		);
		foreach ( $docs as $doc ) {
			$this->index_document( $doc->id, (array) $doc );
			++$indexed;
		}

		// Index suppliers
		$suppliers = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}ict_suppliers"
		);
		foreach ( $suppliers as $supplier ) {
			$this->add_to_index(
				'supplier',
				$supplier->id,
				array(
					'title'    => $supplier->name,
					'content'  => $supplier->notes,
					'keywords' => "{$supplier->email} {$supplier->phone}",
					'metadata' => wp_json_encode( array( 'type' => $supplier->type ) ),
				)
			);
			++$indexed;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'indexed' => $indexed,
			)
		);
	}

	public function index_project( $project_id, $data ) {
		$this->add_to_index(
			'project',
			$project_id,
			array(
				'title'    => $data['name'],
				'content'  => $data['description'] ?? '',
				'keywords' => $data['project_number'] . ' ' . ( $data['client_name'] ?? '' ),
				'metadata' => wp_json_encode(
					array(
						'status' => $data['status'],
						'client' => $data['client_id'] ?? null,
					)
				),
			)
		);
	}

	public function index_inventory( $item_id, $data ) {
		$this->add_to_index(
			'inventory',
			$item_id,
			array(
				'title'    => $data['name'],
				'content'  => $data['description'] ?? '',
				'keywords' => $data['sku'] . ' ' . ( $data['barcode'] ?? '' ),
				'metadata' => wp_json_encode(
					array(
						'category' => $data['category'] ?? null,
						'quantity' => $data['quantity'] ?? 0,
					)
				),
			)
		);
	}

	public function index_document( $doc_id, $data ) {
		$this->add_to_index(
			'document',
			$doc_id,
			array(
				'title'    => $data['name'],
				'content'  => $data['description'] ?? '',
				'keywords' => $data['tags'] ?? '',
				'metadata' => wp_json_encode(
					array(
						'category' => $data['category'] ?? null,
						'type'     => $data['file_type'] ?? null,
					)
				),
			)
		);
	}

	private function add_to_index( $entity_type, $entity_id, $data ) {
		global $wpdb;

		$searchable = strtolower(
			implode(
				' ',
				array(
					$data['title'],
					$data['content'],
					$data['keywords'],
				)
			)
		);

		$wpdb->replace(
			$this->search_index_table,
			array(
				'entity_type'     => $entity_type,
				'entity_id'       => $entity_id,
				'title'           => $data['title'],
				'content'         => $data['content'],
				'keywords'        => $data['keywords'],
				'metadata'        => $data['metadata'],
				'searchable_text' => $searchable,
			)
		);
	}

	private function search_index( $query, $types, $limit, $offset ) {
		global $wpdb;

		$where  = 'MATCH(searchable_text) AGAINST(%s IN NATURAL LANGUAGE MODE)';
		$values = array( $query );

		if ( $types ) {
			$type_array   = is_array( $types ) ? $types : explode( ',', $types );
			$placeholders = implode( ',', array_fill( 0, count( $type_array ), '%s' ) );
			$where       .= " AND entity_type IN ({$placeholders})";
			$values       = array_merge( $values, $type_array );
		}

		$values[] = $limit;
		$values[] = $offset;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT entity_type, entity_id, title, content, keywords, metadata,
                    MATCH(searchable_text) AGAINST(%s IN NATURAL LANGUAGE MODE) as relevance
             FROM {$this->search_index_table}
             WHERE {$where}
             ORDER BY relevance DESC
             LIMIT %d OFFSET %d",
				$values
			)
		);

		return array_map(
			function ( $row ) {
				return array(
					'type'      => $row->entity_type,
					'id'        => $row->entity_id,
					'title'     => $row->title,
					'excerpt'   => wp_trim_words( $row->content, 20 ),
					'keywords'  => $row->keywords,
					'metadata'  => json_decode( $row->metadata, true ),
					'relevance' => (float) $row->relevance,
					'url'       => $this->get_entity_url( $row->entity_type, $row->entity_id ),
					'icon'      => $this->get_entity_icon( $row->entity_type ),
				);
			},
			$results
		);
	}

	private function search_direct( $query, $types, $limit ) {
		global $wpdb;

		$results = array();
		$search  = '%' . $wpdb->esc_like( $query ) . '%';

		$type_array = $types ? ( is_array( $types ) ? $types : explode( ',', $types ) ) : null;

		// Projects
		if ( ! $type_array || in_array( 'project', $type_array, true ) ) {
			$projects = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, project_number, name, description, status
                 FROM {$wpdb->prefix}ict_projects
                 WHERE name LIKE %s OR project_number LIKE %s OR description LIKE %s
                 LIMIT %d",
					$search,
					$search,
					$search,
					$limit
				)
			);

			foreach ( $projects as $p ) {
				$results[] = array(
					'type'     => 'project',
					'id'       => $p->id,
					'title'    => $p->name,
					'excerpt'  => wp_trim_words( $p->description, 20 ),
					'metadata' => array(
						'status' => $p->status,
						'number' => $p->project_number,
					),
					'url'      => $this->get_entity_url( 'project', $p->id ),
					'icon'     => 'folder',
				);
			}
		}

		// Inventory
		if ( ! $type_array || in_array( 'inventory', $type_array, true ) ) {
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, sku, name, description, quantity
                 FROM {$wpdb->prefix}ict_inventory_items
                 WHERE name LIKE %s OR sku LIKE %s OR description LIKE %s
                 LIMIT %d",
					$search,
					$search,
					$search,
					$limit
				)
			);

			foreach ( $items as $i ) {
				$results[] = array(
					'type'     => 'inventory',
					'id'       => $i->id,
					'title'    => $i->name,
					'excerpt'  => wp_trim_words( $i->description, 20 ),
					'metadata' => array(
						'sku'      => $i->sku,
						'quantity' => $i->quantity,
					),
					'url'      => $this->get_entity_url( 'inventory', $i->id ),
					'icon'     => 'box',
				);
			}
		}

		// Suppliers
		if ( ! $type_array || in_array( 'supplier', $type_array, true ) ) {
			$suppliers = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, name, email, phone, type
                 FROM {$wpdb->prefix}ict_suppliers
                 WHERE name LIKE %s OR email LIKE %s
                 LIMIT %d",
					$search,
					$search,
					$limit
				)
			);

			foreach ( $suppliers as $s ) {
				$results[] = array(
					'type'     => 'supplier',
					'id'       => $s->id,
					'title'    => $s->name,
					'excerpt'  => $s->email,
					'metadata' => array( 'type' => $s->type ),
					'url'      => $this->get_entity_url( 'supplier', $s->id ),
					'icon'     => 'building',
				);
			}
		}

		// Documents
		if ( ! $type_array || in_array( 'document', $type_array, true ) ) {
			$docs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, name, description, category
                 FROM {$wpdb->prefix}ict_documents
                 WHERE name LIKE %s OR description LIKE %s
                 LIMIT %d",
					$search,
					$search,
					$limit
				)
			);

			foreach ( $docs as $d ) {
				$results[] = array(
					'type'     => 'document',
					'id'       => $d->id,
					'title'    => $d->name,
					'excerpt'  => wp_trim_words( $d->description, 20 ),
					'metadata' => array( 'category' => $d->category ),
					'url'      => $this->get_entity_url( 'document', $d->id ),
					'icon'     => 'file',
				);
			}
		}

		return $results;
	}

	private function get_facets( $query ) {
		global $wpdb;

		$facets = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT entity_type, COUNT(*) as count
             FROM {$this->search_index_table}
             WHERE MATCH(searchable_text) AGAINST(%s IN NATURAL LANGUAGE MODE)
             GROUP BY entity_type",
				$query
			),
			OBJECT_K
		);

		return array(
			'project'   => isset( $facets['project'] ) ? (int) $facets['project']->count : 0,
			'inventory' => isset( $facets['inventory'] ) ? (int) $facets['inventory']->count : 0,
			'document'  => isset( $facets['document'] ) ? (int) $facets['document']->count : 0,
			'supplier'  => isset( $facets['supplier'] ) ? (int) $facets['supplier']->count : 0,
		);
	}

	private function get_entity_url( $type, $id ) {
		$urls = array(
			'project'   => "admin.php?page=ict-projects&id={$id}",
			'inventory' => "admin.php?page=ict-inventory&id={$id}",
			'document'  => "admin.php?page=ict-documents&id={$id}",
			'supplier'  => "admin.php?page=ict-suppliers&id={$id}",
			'user'      => "user-edit.php?user_id={$id}",
		);

		return admin_url( $urls[ $type ] ?? '' );
	}

	private function get_entity_icon( $type ) {
		$icons = array(
			'project'   => 'folder',
			'inventory' => 'box',
			'document'  => 'file',
			'supplier'  => 'building',
			'user'      => 'user',
		);

		return $icons[ $type ] ?? 'search';
	}

	private function log_search( $query, $entity_type, $result_count ) {
		global $wpdb;

		$wpdb->insert(
			$this->search_history_table,
			array(
				'user_id'      => get_current_user_id(),
				'query'        => $query,
				'entity_type'  => $entity_type,
				'result_count' => $result_count,
			)
		);
	}
}
