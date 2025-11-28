<?php
/**
 * Project Budget & Cost Tracking
 *
 * Manages project budgets, expenses, and cost analysis.
 *
 * @package    suspended_ict_platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ICT_Budget_Tracker
 *
 * Handles project budget and expense tracking.
 */
class ICT_Budget_Tracker {

    /**
     * Singleton instance.
     *
     * @var ICT_Budget_Tracker|null
     */
    private static $instance = null;

    /**
     * Expenses table name.
     *
     * @var string
     */
    private $expenses_table;

    /**
     * Budget items table name.
     *
     * @var string
     */
    private $budget_items_table;

    /**
     * Get singleton instance.
     *
     * @return ICT_Budget_Tracker
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        global $wpdb;
        $this->expenses_table = $wpdb->prefix . 'ict_project_expenses';
        $this->budget_items_table = $wpdb->prefix . 'ict_budget_items';
    }

    /**
     * Initialize the feature.
     */
    public function init() {
        add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // Expenses
        register_rest_route( 'ict/v1', '/projects/(?P<project_id>\d+)/expenses', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_expenses' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_expense' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/expenses/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_expense' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_expense' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_expense' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        // Budget items
        register_rest_route( 'ict/v1', '/projects/(?P<project_id>\d+)/budget', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_budget' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_budget_item' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/projects/(?P<project_id>\d+)/budget/summary', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_budget_summary' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/budget-items/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_budget_item' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_budget_item' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        // Expense categories
        register_rest_route( 'ict/v1', '/expense-categories', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_expense_categories' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        // Cost analysis
        register_rest_route( 'ict/v1', '/projects/(?P<project_id>\d+)/cost-analysis', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_cost_analysis' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        // Budget alerts
        register_rest_route( 'ict/v1', '/budget-alerts', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_budget_alerts' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    /**
     * Check permission.
     *
     * @return bool
     */
    public function check_permission() {
        return current_user_can( 'edit_ict_projects' ) || current_user_can( 'manage_options' );
    }

    /**
     * Check edit permission.
     *
     * @return bool
     */
    public function check_edit_permission() {
        return current_user_can( 'manage_ict_projects' ) || current_user_can( 'manage_options' );
    }

    /**
     * Create database tables if needed.
     */
    public function maybe_create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Expenses table
        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->expenses_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            category varchar(100) NOT NULL,
            description text NOT NULL,
            amount decimal(15,2) NOT NULL,
            currency varchar(3) DEFAULT 'USD',
            expense_date date NOT NULL,
            vendor varchar(255),
            invoice_number varchar(100),
            receipt_url varchar(500),
            is_billable tinyint(1) DEFAULT 1,
            is_approved tinyint(1) DEFAULT 0,
            approved_by bigint(20) unsigned,
            approved_at datetime,
            po_id bigint(20) unsigned,
            notes text,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY category (category),
            KEY expense_date (expense_date),
            KEY is_approved (is_approved)
        ) {$charset_collate};";

        // Budget items table
        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->budget_items_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            category varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            budgeted_amount decimal(15,2) NOT NULL,
            actual_amount decimal(15,2) DEFAULT 0,
            variance decimal(15,2) DEFAULT 0,
            currency varchar(3) DEFAULT 'USD',
            is_fixed tinyint(1) DEFAULT 0,
            sort_order int DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY category (category)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql1 );
        dbDelta( $sql2 );
    }

    /**
     * Get expenses for a project.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_expenses( $request ) {
        global $wpdb;

        $project_id = (int) $request->get_param( 'project_id' );
        $category = $request->get_param( 'category' );
        $from_date = $request->get_param( 'from_date' );
        $to_date = $request->get_param( 'to_date' );
        $is_approved = $request->get_param( 'is_approved' );

        $where = array( 'e.project_id = %d' );
        $values = array( $project_id );

        if ( $category ) {
            $where[] = 'e.category = %s';
            $values[] = $category;
        }

        if ( $from_date ) {
            $where[] = 'e.expense_date >= %s';
            $values[] = $from_date;
        }

        if ( $to_date ) {
            $where[] = 'e.expense_date <= %s';
            $values[] = $to_date;
        }

        if ( null !== $is_approved ) {
            $where[] = 'e.is_approved = %d';
            $values[] = (int) $is_approved;
        }

        $where_clause = implode( ' AND ', $where );

        $expenses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*, u.display_name as created_by_name,
                        a.display_name as approved_by_name
                 FROM {$this->expenses_table} e
                 LEFT JOIN {$wpdb->users} u ON e.created_by = u.ID
                 LEFT JOIN {$wpdb->users} a ON e.approved_by = a.ID
                 WHERE {$where_clause}
                 ORDER BY e.expense_date DESC",
                $values
            )
        );

        return rest_ensure_response( $expenses );
    }

    /**
     * Get single expense.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_expense( $request ) {
        global $wpdb;

        $id = (int) $request->get_param( 'id' );

        $expense = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT e.*, u.display_name as created_by_name,
                        a.display_name as approved_by_name
                 FROM {$this->expenses_table} e
                 LEFT JOIN {$wpdb->users} u ON e.created_by = u.ID
                 LEFT JOIN {$wpdb->users} a ON e.approved_by = a.ID
                 WHERE e.id = %d",
                $id
            )
        );

        if ( ! $expense ) {
            return new WP_Error( 'not_found', 'Expense not found', array( 'status' => 404 ) );
        }

        return rest_ensure_response( $expense );
    }

    /**
     * Create an expense.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function create_expense( $request ) {
        global $wpdb;

        $project_id = (int) $request->get_param( 'project_id' );

        $data = array(
            'project_id'     => $project_id,
            'category'       => sanitize_text_field( $request->get_param( 'category' ) ),
            'description'    => sanitize_textarea_field( $request->get_param( 'description' ) ),
            'amount'         => (float) $request->get_param( 'amount' ),
            'currency'       => sanitize_text_field( $request->get_param( 'currency' ) ?: 'USD' ),
            'expense_date'   => sanitize_text_field( $request->get_param( 'expense_date' ) ),
            'vendor'         => sanitize_text_field( $request->get_param( 'vendor' ) ),
            'invoice_number' => sanitize_text_field( $request->get_param( 'invoice_number' ) ),
            'receipt_url'    => esc_url_raw( $request->get_param( 'receipt_url' ) ),
            'is_billable'    => (int) $request->get_param( 'is_billable' ),
            'po_id'          => (int) $request->get_param( 'po_id' ) ?: null,
            'notes'          => sanitize_textarea_field( $request->get_param( 'notes' ) ),
            'created_by'     => get_current_user_id(),
        );

        $wpdb->insert( $this->expenses_table, $data );
        $expense_id = $wpdb->insert_id;

        // Update budget actuals
        $this->update_budget_actuals( $project_id, $data['category'] );

        // Check budget alerts
        $this->check_budget_threshold( $project_id );

        do_action( 'ict_expense_created', $expense_id, $project_id, $data );

        return rest_ensure_response( array(
            'success' => true,
            'id'      => $expense_id,
            'message' => 'Expense created successfully',
        ) );
    }

    /**
     * Update an expense.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function update_expense( $request ) {
        global $wpdb;

        $id = (int) $request->get_param( 'id' );

        $expense = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->expenses_table} WHERE id = %d", $id )
        );

        if ( ! $expense ) {
            return new WP_Error( 'not_found', 'Expense not found', array( 'status' => 404 ) );
        }

        $data = array();
        $fields = array(
            'category', 'description', 'vendor', 'invoice_number', 'notes'
        );

        foreach ( $fields as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = sanitize_text_field( $value );
            }
        }

        $numeric_fields = array( 'amount', 'is_billable', 'is_approved' );
        foreach ( $numeric_fields as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = $field === 'amount' ? (float) $value : (int) $value;
            }
        }

        if ( isset( $data['is_approved'] ) && $data['is_approved'] && ! $expense->is_approved ) {
            $data['approved_by'] = get_current_user_id();
            $data['approved_at'] = current_time( 'mysql' );
        }

        $date_value = $request->get_param( 'expense_date' );
        if ( null !== $date_value ) {
            $data['expense_date'] = sanitize_text_field( $date_value );
        }

        $url_value = $request->get_param( 'receipt_url' );
        if ( null !== $url_value ) {
            $data['receipt_url'] = esc_url_raw( $url_value );
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
        }

        $wpdb->update( $this->expenses_table, $data, array( 'id' => $id ) );

        // Update budget actuals
        $this->update_budget_actuals( $expense->project_id, $expense->category );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Expense updated successfully',
        ) );
    }

    /**
     * Delete an expense.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function delete_expense( $request ) {
        global $wpdb;

        $id = (int) $request->get_param( 'id' );

        $expense = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->expenses_table} WHERE id = %d", $id )
        );

        if ( ! $expense ) {
            return new WP_Error( 'not_found', 'Expense not found', array( 'status' => 404 ) );
        }

        $wpdb->delete( $this->expenses_table, array( 'id' => $id ) );

        // Update budget actuals
        $this->update_budget_actuals( $expense->project_id, $expense->category );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Expense deleted successfully',
        ) );
    }

    /**
     * Get budget items for a project.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_budget( $request ) {
        global $wpdb;

        $project_id = (int) $request->get_param( 'project_id' );

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->budget_items_table}
                 WHERE project_id = %d
                 ORDER BY category, sort_order",
                $project_id
            )
        );

        // Calculate variance for each item
        foreach ( $items as &$item ) {
            $item->variance = $item->budgeted_amount - $item->actual_amount;
            $item->variance_percent = $item->budgeted_amount > 0
                ? round( ( $item->variance / $item->budgeted_amount ) * 100, 2 )
                : 0;
        }

        return rest_ensure_response( $items );
    }

    /**
     * Get budget summary for a project.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_budget_summary( $request ) {
        global $wpdb;

        $project_id = (int) $request->get_param( 'project_id' );

        // Get project info
        $project = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT budget, name FROM {$wpdb->prefix}ict_projects WHERE id = %d",
                $project_id
            )
        );

        // Get budget breakdown by category
        $by_category = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT category,
                        SUM(budgeted_amount) as budgeted,
                        SUM(actual_amount) as actual
                 FROM {$this->budget_items_table}
                 WHERE project_id = %d
                 GROUP BY category",
                $project_id
            )
        );

        // Get total expenses
        $total_expenses = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount) FROM {$this->expenses_table}
                 WHERE project_id = %d AND is_approved = 1",
                $project_id
            )
        ) ?: 0;

        // Get labor costs (from time entries)
        $labor_costs = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(te.hours * COALESCE(um.meta_value, 50))
                 FROM {$wpdb->prefix}ict_time_entries te
                 LEFT JOIN {$wpdb->usermeta} um ON te.user_id = um.user_id
                     AND um.meta_key = 'ict_hourly_rate'
                 WHERE te.project_id = %d",
                $project_id
            )
        ) ?: 0;

        $total_budget = $project ? (float) $project->budget : 0;
        $total_spent = $total_expenses + $labor_costs;
        $remaining = $total_budget - $total_spent;
        $percent_used = $total_budget > 0 ? round( ( $total_spent / $total_budget ) * 100, 2 ) : 0;

        return rest_ensure_response( array(
            'project_name'    => $project ? $project->name : '',
            'total_budget'    => $total_budget,
            'total_spent'     => $total_spent,
            'remaining'       => $remaining,
            'percent_used'    => $percent_used,
            'expenses'        => (float) $total_expenses,
            'labor_costs'     => (float) $labor_costs,
            'by_category'     => $by_category,
            'is_over_budget'  => $remaining < 0,
            'warning_level'   => $this->get_warning_level( $percent_used ),
        ) );
    }

    /**
     * Create a budget item.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function create_budget_item( $request ) {
        global $wpdb;

        $project_id = (int) $request->get_param( 'project_id' );

        $data = array(
            'project_id'      => $project_id,
            'category'        => sanitize_text_field( $request->get_param( 'category' ) ),
            'name'            => sanitize_text_field( $request->get_param( 'name' ) ),
            'description'     => sanitize_textarea_field( $request->get_param( 'description' ) ),
            'budgeted_amount' => (float) $request->get_param( 'budgeted_amount' ),
            'currency'        => sanitize_text_field( $request->get_param( 'currency' ) ?: 'USD' ),
            'is_fixed'        => (int) $request->get_param( 'is_fixed' ),
            'created_by'      => get_current_user_id(),
        );

        $wpdb->insert( $this->budget_items_table, $data );
        $item_id = $wpdb->insert_id;

        return rest_ensure_response( array(
            'success' => true,
            'id'      => $item_id,
            'message' => 'Budget item created successfully',
        ) );
    }

    /**
     * Update a budget item.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function update_budget_item( $request ) {
        global $wpdb;

        $id = (int) $request->get_param( 'id' );

        $data = array();
        $fields = array( 'category', 'name', 'description' );

        foreach ( $fields as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = sanitize_text_field( $value );
            }
        }

        $numeric = array( 'budgeted_amount', 'is_fixed', 'sort_order' );
        foreach ( $numeric as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = $field === 'budgeted_amount' ? (float) $value : (int) $value;
            }
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
        }

        $wpdb->update( $this->budget_items_table, $data, array( 'id' => $id ) );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Budget item updated successfully',
        ) );
    }

    /**
     * Delete a budget item.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function delete_budget_item( $request ) {
        global $wpdb;

        $id = (int) $request->get_param( 'id' );
        $wpdb->delete( $this->budget_items_table, array( 'id' => $id ) );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Budget item deleted successfully',
        ) );
    }

    /**
     * Get expense categories.
     *
     * @return WP_REST_Response
     */
    public function get_expense_categories() {
        $categories = array(
            'labor'       => 'Labor',
            'materials'   => 'Materials',
            'equipment'   => 'Equipment Rental',
            'subcontract' => 'Subcontractor',
            'permits'     => 'Permits & Fees',
            'travel'      => 'Travel & Transportation',
            'utilities'   => 'Utilities',
            'insurance'   => 'Insurance',
            'overhead'    => 'Overhead',
            'misc'        => 'Miscellaneous',
        );

        return rest_ensure_response( $categories );
    }

    /**
     * Get cost analysis for a project.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_cost_analysis( $request ) {
        global $wpdb;

        $project_id = (int) $request->get_param( 'project_id' );

        // Monthly breakdown
        $monthly = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(expense_date, '%%Y-%%m') as month,
                        category,
                        SUM(amount) as total
                 FROM {$this->expenses_table}
                 WHERE project_id = %d
                 GROUP BY month, category
                 ORDER BY month",
                $project_id
            )
        );

        // Top vendors
        $top_vendors = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT vendor, SUM(amount) as total, COUNT(*) as count
                 FROM {$this->expenses_table}
                 WHERE project_id = %d AND vendor IS NOT NULL AND vendor != ''
                 GROUP BY vendor
                 ORDER BY total DESC
                 LIMIT 10",
                $project_id
            )
        );

        // Billable vs non-billable
        $billable_split = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    SUM(CASE WHEN is_billable = 1 THEN amount ELSE 0 END) as billable,
                    SUM(CASE WHEN is_billable = 0 THEN amount ELSE 0 END) as non_billable
                 FROM {$this->expenses_table}
                 WHERE project_id = %d",
                $project_id
            )
        );

        // Approval status
        $approval_status = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    SUM(CASE WHEN is_approved = 1 THEN amount ELSE 0 END) as approved,
                    SUM(CASE WHEN is_approved = 0 THEN amount ELSE 0 END) as pending
                 FROM {$this->expenses_table}
                 WHERE project_id = %d",
                $project_id
            )
        );

        return rest_ensure_response( array(
            'monthly_breakdown'  => $monthly,
            'top_vendors'        => $top_vendors,
            'billable_split'     => $billable_split,
            'approval_status'    => $approval_status,
        ) );
    }

    /**
     * Get budget alerts.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_budget_alerts( $request ) {
        global $wpdb;

        $threshold = (int) $request->get_param( 'threshold' ) ?: 80;

        $projects_table = $wpdb->prefix . 'ict_projects';

        $alerts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.id, p.name, p.budget,
                        COALESCE(SUM(e.amount), 0) as spent,
                        ROUND((COALESCE(SUM(e.amount), 0) / p.budget) * 100, 2) as percent_used
                 FROM {$projects_table} p
                 LEFT JOIN {$this->expenses_table} e ON p.id = e.project_id
                 WHERE p.budget > 0 AND p.status NOT IN ('completed', 'cancelled')
                 GROUP BY p.id
                 HAVING percent_used >= %d
                 ORDER BY percent_used DESC",
                $threshold
            )
        );

        foreach ( $alerts as &$alert ) {
            $alert->remaining = $alert->budget - $alert->spent;
            $alert->warning_level = $this->get_warning_level( $alert->percent_used );
        }

        return rest_ensure_response( $alerts );
    }

    /**
     * Update budget actuals based on expenses.
     *
     * @param int    $project_id Project ID.
     * @param string $category   Expense category.
     */
    private function update_budget_actuals( $project_id, $category ) {
        global $wpdb;

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount) FROM {$this->expenses_table}
                 WHERE project_id = %d AND category = %s AND is_approved = 1",
                $project_id,
                $category
            )
        ) ?: 0;

        $wpdb->update(
            $this->budget_items_table,
            array( 'actual_amount' => $total ),
            array(
                'project_id' => $project_id,
                'category'   => $category,
            )
        );
    }

    /**
     * Check budget threshold and trigger alerts.
     *
     * @param int $project_id Project ID.
     */
    private function check_budget_threshold( $project_id ) {
        global $wpdb;

        $projects_table = $wpdb->prefix . 'ict_projects';

        $data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT p.budget, COALESCE(SUM(e.amount), 0) as spent
                 FROM {$projects_table} p
                 LEFT JOIN {$this->expenses_table} e ON p.id = e.project_id
                 WHERE p.id = %d
                 GROUP BY p.id",
                $project_id
            )
        );

        if ( ! $data || $data->budget <= 0 ) {
            return;
        }

        $percent = ( $data->spent / $data->budget ) * 100;

        if ( $percent >= 100 ) {
            do_action( 'ict_budget_exceeded', $project_id, $data->spent, $data->budget );
        } elseif ( $percent >= 90 ) {
            do_action( 'ict_budget_critical', $project_id, $data->spent, $data->budget );
        } elseif ( $percent >= 75 ) {
            do_action( 'ict_budget_warning', $project_id, $data->spent, $data->budget );
        }
    }

    /**
     * Get warning level based on percent used.
     *
     * @param float $percent Percent used.
     * @return string
     */
    private function get_warning_level( $percent ) {
        if ( $percent >= 100 ) {
            return 'critical';
        } elseif ( $percent >= 90 ) {
            return 'danger';
        } elseif ( $percent >= 75 ) {
            return 'warning';
        }
        return 'normal';
    }
}
