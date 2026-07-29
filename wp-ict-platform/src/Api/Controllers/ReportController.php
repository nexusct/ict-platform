<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Report REST API Controller
 *
 * @package ICT_Platform\Api\Controllers
 * @since   2.0.0
 */
class ReportController extends AbstractController {

	protected string $rest_base = 'reports';

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<type>[\w-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getReport' ),
				'permission_callback' => array( $this, 'permissionsCheck' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getDashboard' ),
				'permission_callback' => array( $this, 'permissionsCheck' ),
			)
		);
	}

	public function getReport( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$type     = $request->get_param( 'type' );
		$dateFrom = $request->get_param( 'date_from' ) ?? date( 'Y-m-01' );
		$dateTo   = $request->get_param( 'date_to' ) ?? date( 'Y-m-d' );

		$report = match ( $type ) {
			'projects'    => $this->getProjectsReport( $dateFrom, $dateTo ),
			'time'        => $this->getTimeReport( $dateFrom, $dateTo ),
			'inventory'   => $this->getInventoryReport(),
			'revenue'     => $this->getRevenueReport( $dateFrom, $dateTo ),
			'technicians' => $this->getTechniciansReport( $dateFrom, $dateTo ),
			default       => null,
		};

		if ( $report === null ) {
			return $this->error( 'invalid_report', __( 'Invalid report type.', 'ict-platform' ), 400 );
		}

		return $this->success( $report );
	}

	public function getDashboard( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$activeProjects     = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ICT_PROJECTS_TABLE . " WHERE status = 'active'" );
		$pendingTimeEntries = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ICT_TIME_ENTRIES_TABLE . " WHERE status = 'pending'" );
		$lowStockItems      = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ICT_INVENTORY_ITEMS_TABLE . ' WHERE quantity_available <= reorder_level AND is_active = 1' );
		$pendingPOs         = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ICT_PURCHASE_ORDERS_TABLE . " WHERE status = 'pending'" );

		$recentProjects = $wpdb->get_results( 'SELECT id, project_name, project_number, status FROM ' . ICT_PROJECTS_TABLE . ' ORDER BY created_at DESC LIMIT 5' );

		$todayHours = (float) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(total_hours) FROM ' . ICT_TIME_ENTRIES_TABLE . ' WHERE DATE(clock_in) = %s',
				date( 'Y-m-d' )
			)
		);

		return $this->success(
			array(
				'active_projects'         => $activeProjects,
				'pending_time_entries'    => $pendingTimeEntries,
				'low_stock_items'         => $lowStockItems,
				'pending_purchase_orders' => $pendingPOs,
				'recent_projects'         => $recentProjects,
				'today_hours'             => $todayHours ?? 0,
			)
		);
	}

	public function permissionsCheck(): bool {
		return $this->canViewReports();
	}

	private function getProjectsReport( string $dateFrom, string $dateTo ): array {
		global $wpdb;

		$projects = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) as count FROM ' . ICT_PROJECTS_TABLE . '
            WHERE created_at BETWEEN %s AND %s GROUP BY status',
				$dateFrom,
				$dateTo . ' 23:59:59'
			)
		);

		$totalBudget = (float) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(budget) FROM ' . ICT_PROJECTS_TABLE . ' WHERE created_at BETWEEN %s AND %s',
				$dateFrom,
				$dateTo . ' 23:59:59'
			)
		);

		return array(
			'type'         => 'projects',
			'date_from'    => $dateFrom,
			'date_to'      => $dateTo,
			'by_status'    => $projects,
			'total_budget' => $totalBudget,
		);
	}

	private function getTimeReport( string $dateFrom, string $dateTo ): array {
		global $wpdb;

		$totalHours = (float) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(total_hours) FROM ' . ICT_TIME_ENTRIES_TABLE . '
            WHERE DATE(clock_in) BETWEEN %s AND %s',
				$dateFrom,
				$dateTo
			)
		);

		$byTechnician = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT technician_id, SUM(total_hours) as hours FROM ' . ICT_TIME_ENTRIES_TABLE . '
            WHERE DATE(clock_in) BETWEEN %s AND %s GROUP BY technician_id',
				$dateFrom,
				$dateTo
			)
		);

		$byProject = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT project_id, SUM(total_hours) as hours FROM ' . ICT_TIME_ENTRIES_TABLE . '
            WHERE DATE(clock_in) BETWEEN %s AND %s AND project_id IS NOT NULL GROUP BY project_id',
				$dateFrom,
				$dateTo
			)
		);

		return array(
			'type'          => 'time',
			'date_from'     => $dateFrom,
			'date_to'       => $dateTo,
			'total_hours'   => $totalHours ?? 0,
			'by_technician' => $byTechnician,
			'by_project'    => $byProject,
		);
	}

	private function getInventoryReport(): array {
		global $wpdb;

		$totalValue = (float) $wpdb->get_var( 'SELECT SUM(quantity_available * unit_cost) FROM ' . ICT_INVENTORY_ITEMS_TABLE . ' WHERE is_active = 1' );
		$totalItems = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ICT_INVENTORY_ITEMS_TABLE . ' WHERE is_active = 1' );
		$lowStock   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ICT_INVENTORY_ITEMS_TABLE . ' WHERE quantity_available <= reorder_level AND is_active = 1' );

		$byCategory = $wpdb->get_results( 'SELECT category, COUNT(*) as count, SUM(quantity_available * unit_cost) as value FROM ' . ICT_INVENTORY_ITEMS_TABLE . ' WHERE is_active = 1 GROUP BY category' );

		return array(
			'type'            => 'inventory',
			'total_value'     => $totalValue ?? 0,
			'total_items'     => $totalItems,
			'low_stock_count' => $lowStock,
			'by_category'     => $byCategory,
		);
	}

	private function getRevenueReport( string $dateFrom, string $dateTo ): array {
		global $wpdb;

		$totalRevenue = (float) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(budget) FROM ' . ICT_PROJECTS_TABLE . " WHERE status = 'completed' AND end_date BETWEEN %s AND %s",
				$dateFrom,
				$dateTo
			)
		);

		$byMonth = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(end_date, '%%Y-%%m') as month, SUM(budget) as revenue FROM " . ICT_PROJECTS_TABLE . "
            WHERE status = 'completed' AND end_date BETWEEN %s AND %s GROUP BY month ORDER BY month",
				$dateFrom,
				$dateTo
			)
		);

		return array(
			'type'          => 'revenue',
			'date_from'     => $dateFrom,
			'date_to'       => $dateTo,
			'total_revenue' => $totalRevenue ?? 0,
			'by_month'      => $byMonth,
		);
	}

	private function getTechniciansReport( string $dateFrom, string $dateTo ): array {
		global $wpdb;

		$technicians = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT technician_id, COUNT(*) as entries, SUM(total_hours) as hours,
                    SUM(CASE WHEN status = 'approved' THEN total_hours ELSE 0 END) as approved_hours
             FROM " . ICT_TIME_ENTRIES_TABLE . '
             WHERE DATE(clock_in) BETWEEN %s AND %s GROUP BY technician_id',
				$dateFrom,
				$dateTo
			)
		);

		// Add names
		foreach ( $technicians as &$tech ) {
			$tech->name = $this->helper->getUserDisplayName( (int) $tech->technician_id );
		}

		return array(
			'type'        => 'technicians',
			'date_from'   => $dateFrom,
			'date_to'     => $dateTo,
			'technicians' => $technicians,
		);
	}
}
