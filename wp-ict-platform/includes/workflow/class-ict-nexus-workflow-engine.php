<?php
/**
 * Nexus Workflow Engine
 *
 * Manages project workflow state, phase transitions, and stage completions.
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Nexus_Workflow_Engine
 *
 * Engine for managing Nexus workflow processes on projects.
 */
class ICT_Nexus_Workflow_Engine {

	/**
	 * Project ID being processed.
	 *
	 * @var int
	 */
	private $project_id;

	/**
	 * Constructor.
	 *
	 * @param int $project_id Project ID to manage workflow for.
	 */
	public function __construct( $project_id = 0 ) {
		$this->project_id = absint( $project_id );
	}

	/**
	 * Set the project ID.
	 *
	 * @param int $project_id Project ID.
	 * @return self
	 */
	public function set_project( $project_id ) {
		$this->project_id = absint( $project_id );
		return $this;
	}

	/**
	 * Initialize workflow for a new project.
	 *
	 * @param int $project_id Project ID.
	 * @return array|WP_Error Initial workflow state or error.
	 */
	public function initialize_workflow( $project_id = 0 ) {
		if ( $project_id ) {
			$this->project_id = absint( $project_id );
		}

		if ( ! $this->project_id ) {
			return new WP_Error( 'missing_project_id', __( 'Project ID is required.', 'ict-platform' ) );
		}

		// Check if workflow already exists
		$existing = $this->get_workflow_state();
		if ( ! empty( $existing ) ) {
			return new WP_Error( 'workflow_exists', __( 'Workflow already initialized for this project.', 'ict-platform' ) );
		}

		// Initialize workflow state
		$initial_state = array(
			'current_phase'    => 'project_initiation',
			'phase_history'    => array(
				array(
					'phase'     => 'project_initiation',
					'action'    => 'started',
					'timestamp' => current_time( 'mysql' ),
					'user_id'   => get_current_user_id(),
				),
			),
			'completed_phases' => array(),
			'completed_stages' => array(),
			'stage_data'       => array(),
			'workflow_started' => current_time( 'mysql' ),
			'workflow_updated' => current_time( 'mysql' ),
			'overall_progress' => 0,
		);

		// Save workflow state
		$saved = $this->save_workflow_state( $initial_state );

		if ( ! $saved ) {
			return new WP_Error( 'save_failed', __( 'Failed to initialize workflow state.', 'ict-platform' ) );
		}

		// Trigger action hook
		do_action( 'ict_nexus_workflow_initialized', $this->project_id, $initial_state );

		return $initial_state;
	}

	/**
	 * Get the current workflow state for the project.
	 *
	 * @return array|null Workflow state or null if not initialized.
	 */
	public function get_workflow_state() {
		if ( ! $this->project_id ) {
			return null;
		}

		$state = get_post_meta( $this->project_id, '_ict_nexus_workflow_state', true );

		if ( empty( $state ) ) {
			return null;
		}

		// Ensure all keys exist
		$defaults = array(
			'current_phase'    => 'project_initiation',
			'phase_history'    => array(),
			'completed_phases' => array(),
			'completed_stages' => array(),
			'stage_data'       => array(),
			'workflow_started' => null,
			'workflow_updated' => null,
			'overall_progress' => 0,
		);

		return wp_parse_args( $state, $defaults );
	}

	/**
	 * Save workflow state.
	 *
	 * @param array $state Workflow state to save.
	 * @return bool Success status.
	 */
	private function save_workflow_state( $state ) {
		if ( ! $this->project_id ) {
			return false;
		}

		$state['workflow_updated'] = current_time( 'mysql' );
		$state['overall_progress'] = $this->calculate_overall_progress( $state );

		return update_post_meta( $this->project_id, '_ict_nexus_workflow_state', $state );
	}

	/**
	 * Get the current phase.
	 *
	 * @return string|null Current phase ID.
	 */
	public function get_current_phase() {
		$state = $this->get_workflow_state();
		return $state ? $state['current_phase'] : null;
	}

	/**
	 * Get current phase details.
	 *
	 * @return array|null Phase details with completion info.
	 */
	public function get_current_phase_details() {
		$state = $this->get_workflow_state();

		if ( ! $state ) {
			return null;
		}

		$phase_def = ICT_Nexus_Workflow::get_phase( $state['current_phase'] );

		if ( ! $phase_def ) {
			return null;
		}

		// Add completion status for each stage
		$stages = array();
		foreach ( $phase_def['stages'] as $stage ) {
			$stage_key          = $state['current_phase'] . ':' . $stage['id'];
			$stage['completed'] = in_array( $stage_key, $state['completed_stages'], true );
			$stage['data']      = isset( $state['stage_data'][ $stage_key ] ) ? $state['stage_data'][ $stage_key ] : null;
			$stages[]           = $stage;
		}

		$phase_def['stages']                = $stages;
		$phase_def['completion_percentage'] = ICT_Nexus_Workflow::calculate_phase_completion(
			$state['current_phase'],
			$this->get_phase_completed_stages( $state['current_phase'] )
		);

		return $phase_def;
	}

	/**
	 * Get completed stages for a specific phase.
	 *
	 * @param string $phase_id Phase ID.
	 * @return array List of completed stage IDs (without phase prefix).
	 */
	private function get_phase_completed_stages( $phase_id ) {
		$state = $this->get_workflow_state();

		if ( ! $state ) {
			return array();
		}

		$prefix    = $phase_id . ':';
		$completed = array();

		foreach ( $state['completed_stages'] as $stage_key ) {
			if ( strpos( $stage_key, $prefix ) === 0 ) {
				$completed[] = str_replace( $prefix, '', $stage_key );
			}
		}

		return $completed;
	}

	/**
	 * Complete a stage in the current phase.
	 *
	 * @param string $stage_id   Stage ID to complete.
	 * @param array  $stage_data Optional data to store with the stage.
	 * @return array|WP_Error Updated workflow state or error.
	 */
	public function complete_stage( $stage_id, $stage_data = array() ) {
		$state = $this->get_workflow_state();

		if ( ! $state ) {
			return new WP_Error( 'no_workflow', __( 'Workflow not initialized for this project.', 'ict-platform' ) );
		}

		$current_phase = $state['current_phase'];
		$phase_def     = ICT_Nexus_Workflow::get_phase( $current_phase );

		// Verify stage exists in current phase
		$stage_exists = false;
		foreach ( $phase_def['stages'] as $stage ) {
			if ( $stage['id'] === $stage_id ) {
				$stage_exists = true;
				break;
			}
		}

		if ( ! $stage_exists ) {
			return new WP_Error(
				'invalid_stage',
				sprintf( __( 'Stage "%1$s" does not exist in phase "%2$s".', 'ict-platform' ), $stage_id, $current_phase )
			);
		}

		$stage_key = $current_phase . ':' . $stage_id;

		// Check if already completed
		if ( in_array( $stage_key, $state['completed_stages'], true ) ) {
			return new WP_Error( 'already_completed', __( 'This stage is already completed.', 'ict-platform' ) );
		}

		// Mark stage as completed
		$state['completed_stages'][] = $stage_key;

		// Store stage data if provided
		if ( ! empty( $stage_data ) ) {
			$state['stage_data'][ $stage_key ] = array_merge(
				$stage_data,
				array(
					'completed_at' => current_time( 'mysql' ),
					'completed_by' => get_current_user_id(),
				)
			);
		} else {
			$state['stage_data'][ $stage_key ] = array(
				'completed_at' => current_time( 'mysql' ),
				'completed_by' => get_current_user_id(),
			);
		}

		// Add to history
		$state['phase_history'][] = array(
			'phase'     => $current_phase,
			'stage'     => $stage_id,
			'action'    => 'stage_completed',
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
		);

		$this->save_workflow_state( $state );

		// Trigger action hook
		do_action( 'ict_nexus_stage_completed', $this->project_id, $current_phase, $stage_id, $stage_data );

		return $state;
	}

	/**
	 * Uncomplete a stage (revert completion).
	 *
	 * @param string $stage_id Stage ID to uncomplete.
	 * @return array|WP_Error Updated workflow state or error.
	 */
	public function uncomplete_stage( $stage_id ) {
		$state = $this->get_workflow_state();

		if ( ! $state ) {
			return new WP_Error( 'no_workflow', __( 'Workflow not initialized for this project.', 'ict-platform' ) );
		}

		$current_phase = $state['current_phase'];
		$stage_key     = $current_phase . ':' . $stage_id;

		// Check if completed
		$index = array_search( $stage_key, $state['completed_stages'], true );
		if ( false === $index ) {
			return new WP_Error( 'not_completed', __( 'This stage is not marked as completed.', 'ict-platform' ) );
		}

		// Remove from completed stages
		array_splice( $state['completed_stages'], $index, 1 );

		// Keep stage data but mark as uncompleted
		if ( isset( $state['stage_data'][ $stage_key ] ) ) {
			$state['stage_data'][ $stage_key ]['uncompleted_at'] = current_time( 'mysql' );
			$state['stage_data'][ $stage_key ]['uncompleted_by'] = get_current_user_id();
		}

		// Add to history
		$state['phase_history'][] = array(
			'phase'     => $current_phase,
			'stage'     => $stage_id,
			'action'    => 'stage_uncompleted',
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
		);

		$this->save_workflow_state( $state );

		// Trigger action hook
		do_action( 'ict_nexus_stage_uncompleted', $this->project_id, $current_phase, $stage_id );

		return $state;
	}

	/**
	 * Advance to the next phase.
	 *
	 * @param bool $force Force transition even if required stages not complete.
	 * @return array|WP_Error Updated workflow state or error.
	 */
	public function advance_phase( $force = false ) {
		$state = $this->get_workflow_state();

		if ( ! $state ) {
			return new WP_Error( 'no_workflow', __( 'Workflow not initialized for this project.', 'ict-platform' ) );
		}

		$current_phase = $state['current_phase'];
		$next_phase    = ICT_Nexus_Workflow::get_next_phase( $current_phase );

		if ( ! $next_phase ) {
			return new WP_Error( 'no_next_phase', __( 'Already at the final phase.', 'ict-platform' ) );
		}

		// Validate transition
		if ( ! $force ) {
			$completed_stages = $this->get_phase_completed_stages( $current_phase );
			$validation       = ICT_Nexus_Workflow::validate_phase_transition( $current_phase, $next_phase, $completed_stages );

			if ( ! $validation['valid'] ) {
				return new WP_Error(
					'transition_blocked',
					implode( ' ', $validation['errors'] ),
					$validation['errors']
				);
			}
		}

		// Mark current phase as completed
		if ( ! in_array( $current_phase, $state['completed_phases'], true ) ) {
			$state['completed_phases'][] = $current_phase;
		}

		// Update to next phase
		$state['current_phase'] = $next_phase;

		// Add to history
		$state['phase_history'][] = array(
			'phase'     => $current_phase,
			'action'    => 'phase_completed',
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
		);

		$state['phase_history'][] = array(
			'phase'     => $next_phase,
			'action'    => 'phase_started',
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
		);

		$this->save_workflow_state( $state );

		// Sync to Zoho CRM if applicable
		$this->sync_phase_to_zoho( $next_phase );

		// Trigger action hooks
		do_action( 'ict_nexus_phase_completed', $this->project_id, $current_phase );
		do_action( 'ict_nexus_phase_started', $this->project_id, $next_phase );
		do_action( 'ict_nexus_phase_transitioned', $this->project_id, $current_phase, $next_phase );

		return $state;
	}

	/**
	 * Move to a previous phase.
	 *
	 * @return array|WP_Error Updated workflow state or error.
	 */
	public function revert_phase() {
		$state = $this->get_workflow_state();

		if ( ! $state ) {
			return new WP_Error( 'no_workflow', __( 'Workflow not initialized for this project.', 'ict-platform' ) );
		}

		$current_phase = $state['current_phase'];
		$prev_phase    = ICT_Nexus_Workflow::get_previous_phase( $current_phase );

		if ( ! $prev_phase ) {
			return new WP_Error( 'no_prev_phase', __( 'Already at the first phase.', 'ict-platform' ) );
		}

		// Remove previous phase from completed list
		$index = array_search( $prev_phase, $state['completed_phases'], true );
		if ( false !== $index ) {
			array_splice( $state['completed_phases'], $index, 1 );
		}

		// Update to previous phase
		$state['current_phase'] = $prev_phase;

		// Add to history
		$state['phase_history'][] = array(
			'phase'     => $current_phase,
			'action'    => 'phase_reverted',
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
		);

		$state['phase_history'][] = array(
			'phase'     => $prev_phase,
			'action'    => 'phase_resumed',
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
		);

		$this->save_workflow_state( $state );

		// Sync to Zoho CRM if applicable
		$this->sync_phase_to_zoho( $prev_phase );

		// Trigger action hooks
		do_action( 'ict_nexus_phase_reverted', $this->project_id, $current_phase, $prev_phase );

		return $state;
	}

	/**
	 * Jump to a specific phase.
	 *
	 * @param string $target_phase Target phase ID.
	 * @param bool   $force        Force transition even if requirements not met.
	 * @return array|WP_Error Updated workflow state or error.
	 */
	public function jump_to_phase( $target_phase, $force = false ) {
		$state = $this->get_workflow_state();

		if ( ! $state ) {
			return new WP_Error( 'no_workflow', __( 'Workflow not initialized for this project.', 'ict-platform' ) );
		}

		$target_def = ICT_Nexus_Workflow::get_phase( $target_phase );
		if ( ! $target_def ) {
			return new WP_Error( 'invalid_phase', __( 'Invalid target phase.', 'ict-platform' ) );
		}

		$current_phase = $state['current_phase'];

		if ( $current_phase === $target_phase ) {
			return new WP_Error( 'same_phase', __( 'Already in the target phase.', 'ict-platform' ) );
		}

		$current_def = ICT_Nexus_Workflow::get_phase( $current_phase );

		// If moving forward, validate all intermediate phases
		if ( ! $force && $target_def['order'] > $current_def['order'] ) {
			$phases    = ICT_Nexus_Workflow::get_phase_ids();
			$start_idx = array_search( $current_phase, $phases, true );
			$end_idx   = array_search( $target_phase, $phases, true );

			for ( $i = $start_idx; $i < $end_idx; $i++ ) {
				$phase_id         = $phases[ $i ];
				$completed_stages = $this->get_phase_completed_stages( $phase_id );
				$validation       = ICT_Nexus_Workflow::validate_phase_transition(
					$phase_id,
					$phases[ $i + 1 ],
					$completed_stages
				);

				if ( ! $validation['valid'] ) {
					return new WP_Error(
						'transition_blocked',
						sprintf(
							__( 'Cannot skip to %1$s. Blocked at %2$s: %3$s', 'ict-platform' ),
							$target_def['name'],
							ICT_Nexus_Workflow::get_phase( $phase_id )['name'],
							implode( ' ', $validation['errors'] )
						)
					);
				}
			}

			// Mark all intermediate phases as completed
			for ( $i = $start_idx; $i < $end_idx; $i++ ) {
				if ( ! in_array( $phases[ $i ], $state['completed_phases'], true ) ) {
					$state['completed_phases'][] = $phases[ $i ];
				}
			}
		}

		// Update to target phase
		$state['current_phase'] = $target_phase;

		// Add to history
		$state['phase_history'][] = array(
			'phase'     => $current_phase,
			'action'    => 'phase_jumped_from',
			'target'    => $target_phase,
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
		);

		$state['phase_history'][] = array(
			'phase'     => $target_phase,
			'action'    => 'phase_jumped_to',
			'from'      => $current_phase,
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
		);

		$this->save_workflow_state( $state );

		// Sync to Zoho CRM
		$this->sync_phase_to_zoho( $target_phase );

		// Trigger action hook
		do_action( 'ict_nexus_phase_jumped', $this->project_id, $current_phase, $target_phase );

		return $state;
	}

	/**
	 * Calculate overall workflow progress.
	 *
	 * @param array $state Workflow state.
	 * @return float Progress percentage (0-100).
	 */
	private function calculate_overall_progress( $state ) {
		$phases           = ICT_Nexus_Workflow::get_phase_ids();
		$total_stages     = 0;
		$completed_stages = 0;

		foreach ( $phases as $phase_id ) {
			$phase_stages  = ICT_Nexus_Workflow::get_phase_stages( $phase_id );
			$total_stages += count( $phase_stages );

			foreach ( $phase_stages as $stage ) {
				$stage_key = $phase_id . ':' . $stage['id'];
				if ( in_array( $stage_key, $state['completed_stages'], true ) ) {
					++$completed_stages;
				}
			}
		}

		if ( 0 === $total_stages ) {
			return 0;
		}

		return round( ( $completed_stages / $total_stages ) * 100, 2 );
	}

	/**
	 * Get workflow progress summary.
	 *
	 * @return array Progress summary by phase.
	 */
	public function get_progress_summary() {
		$state = $this->get_workflow_state();

		if ( ! $state ) {
			return array();
		}

		$phases  = ICT_Nexus_Workflow::get_phase_ids();
		$summary = array();

		foreach ( $phases as $phase_id ) {
			$phase_def        = ICT_Nexus_Workflow::get_phase( $phase_id );
			$completed_stages = $this->get_phase_completed_stages( $phase_id );

			$summary[] = array(
				'id'                    => $phase_id,
				'name'                  => $phase_def['name'],
				'order'                 => $phase_def['order'],
				'icon'                  => $phase_def['icon'],
				'color'                 => $phase_def['color'],
				'is_current'            => ( $state['current_phase'] === $phase_id ),
				'is_completed'          => in_array( $phase_id, $state['completed_phases'], true ),
				'total_stages'          => count( $phase_def['stages'] ),
				'completed_stages'      => count( $completed_stages ),
				'completion_percentage' => ICT_Nexus_Workflow::calculate_phase_completion( $phase_id, $completed_stages ),
			);
		}

		return $summary;
	}

	/**
	 * Get workflow timeline/history.
	 *
	 * @param int $limit Maximum number of entries to return.
	 * @return array History entries.
	 */
	public function get_workflow_history( $limit = 50 ) {
		$state = $this->get_workflow_state();

		if ( ! $state || empty( $state['phase_history'] ) ) {
			return array();
		}

		$history = array_slice(
			array_reverse( $state['phase_history'] ),
			0,
			$limit
		);

		// Enrich with user info
		foreach ( $history as &$entry ) {
			if ( ! empty( $entry['user_id'] ) ) {
				$user = get_user_by( 'id', $entry['user_id'] );
				if ( $user ) {
					$entry['user_name']  = $user->display_name;
					$entry['user_email'] = $user->user_email;
				}
			}

			// Add phase name
			if ( ! empty( $entry['phase'] ) ) {
				$phase_def           = ICT_Nexus_Workflow::get_phase( $entry['phase'] );
				$entry['phase_name'] = $phase_def ? $phase_def['name'] : $entry['phase'];
			}

			// Add stage name if applicable
			if ( ! empty( $entry['stage'] ) ) {
				$stages = ICT_Nexus_Workflow::get_phase_stages( $entry['phase'] );
				foreach ( $stages as $stage ) {
					if ( $stage['id'] === $entry['stage'] ) {
						$entry['stage_name'] = $stage['name'];
						break;
					}
				}
			}
		}

		return $history;
	}

	/**
	 * Sync phase to Zoho CRM.
	 *
	 * @param string $phase_id Phase ID.
	 * @return void
	 */
	private function sync_phase_to_zoho( $phase_id ) {
		$crm_stage = ICT_Nexus_Workflow::get_zoho_crm_stage( $phase_id );

		if ( ! $crm_stage ) {
			return;
		}

		// Queue sync operation
		if ( class_exists( 'ICT_Helper' ) && method_exists( 'ICT_Helper', 'queue_sync' ) ) {
			ICT_Helper::queue_sync(
				array(
					'entity_type'  => 'project',
					'entity_id'    => $this->project_id,
					'action'       => 'update',
					'zoho_service' => 'crm',
					'priority'     => 3,
					'payload'      => array(
						'stage' => $crm_stage,
					),
				)
			);
		}
	}

	/**
	 * Reset workflow to initial state.
	 *
	 * @return array|WP_Error Reset workflow state or error.
	 */
	public function reset_workflow() {
		$state = $this->get_workflow_state();

		if ( ! $state ) {
			return new WP_Error( 'no_workflow', __( 'Workflow not initialized for this project.', 'ict-platform' ) );
		}

		// Keep history but reset progress
		$reset_state = array(
			'current_phase'    => 'project_initiation',
			'phase_history'    => $state['phase_history'],
			'completed_phases' => array(),
			'completed_stages' => array(),
			'stage_data'       => array(),
			'workflow_started' => $state['workflow_started'],
			'workflow_updated' => current_time( 'mysql' ),
			'overall_progress' => 0,
		);

		// Add reset to history
		$reset_state['phase_history'][] = array(
			'phase'     => $state['current_phase'],
			'action'    => 'workflow_reset',
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
		);

		$this->save_workflow_state( $reset_state );

		// Trigger action hook
		do_action( 'ict_nexus_workflow_reset', $this->project_id );

		return $reset_state;
	}

	/**
	 * Export workflow data.
	 *
	 * @return array Complete workflow export data.
	 */
	public function export_workflow_data() {
		$state = $this->get_workflow_state();

		if ( ! $state ) {
			return array();
		}

		return array(
			'project_id'       => $this->project_id,
			'workflow_state'   => $state,
			'progress_summary' => $this->get_progress_summary(),
			'history'          => $this->get_workflow_history( 1000 ),
			'exported_at'      => current_time( 'mysql' ),
		);
	}

	/**
	 * Import workflow data.
	 *
	 * @param array $data Workflow data to import.
	 * @return bool|WP_Error Success status or error.
	 */
	public function import_workflow_data( $data ) {
		if ( empty( $data['workflow_state'] ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid workflow import data.', 'ict-platform' ) );
		}

		$state = $data['workflow_state'];

		// Validate state structure
		$required_keys = array( 'current_phase', 'completed_phases', 'completed_stages' );
		foreach ( $required_keys as $key ) {
			if ( ! isset( $state[ $key ] ) ) {
				return new WP_Error( 'missing_key', sprintf( __( 'Missing required key: %s', 'ict-platform' ), $key ) );
			}
		}

		// Add import record to history
		$state['phase_history'][] = array(
			'phase'     => $state['current_phase'],
			'action'    => 'workflow_imported',
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
		);

		return $this->save_workflow_state( $state );
	}

	/**
	 * Check if workflow can advance to next phase.
	 *
	 * @return array Check result with 'can_advance', 'next_phase', and 'blockers'.
	 */
	public function can_advance() {
		$state = $this->get_workflow_state();

		if ( ! $state ) {
			return array(
				'can_advance' => false,
				'next_phase'  => null,
				'blockers'    => array( __( 'Workflow not initialized.', 'ict-platform' ) ),
			);
		}

		$current_phase = $state['current_phase'];
		$next_phase    = ICT_Nexus_Workflow::get_next_phase( $current_phase );

		if ( ! $next_phase ) {
			return array(
				'can_advance' => false,
				'next_phase'  => null,
				'blockers'    => array( __( 'Already at the final phase.', 'ict-platform' ) ),
			);
		}

		$completed_stages = $this->get_phase_completed_stages( $current_phase );
		$validation       = ICT_Nexus_Workflow::validate_phase_transition( $current_phase, $next_phase, $completed_stages );

		$next_phase_def = ICT_Nexus_Workflow::get_phase( $next_phase );

		return array(
			'can_advance' => $validation['valid'],
			'next_phase'  => array(
				'id'   => $next_phase,
				'name' => $next_phase_def['name'],
			),
			'blockers'    => $validation['errors'],
		);
	}

	/**
	 * Get checklist for current phase.
	 *
	 * @return array Checklist items with completion status.
	 */
	public function get_current_checklist() {
		$state = $this->get_workflow_state();

		if ( ! $state ) {
			return array();
		}

		$phase_def = ICT_Nexus_Workflow::get_phase( $state['current_phase'] );

		if ( ! $phase_def ) {
			return array();
		}

		$checklist = array();

		foreach ( $phase_def['stages'] as $stage ) {
			$stage_key    = $state['current_phase'] . ':' . $stage['id'];
			$is_completed = in_array( $stage_key, $state['completed_stages'], true );

			$item = array(
				'id'           => $stage['id'],
				'name'         => $stage['name'],
				'description'  => $stage['description'],
				'required'     => $stage['required'],
				'completed'    => $is_completed,
				'deliverables' => $stage['deliverables'],
				'template'     => $stage['template'],
			);

			if ( $is_completed && isset( $state['stage_data'][ $stage_key ] ) ) {
				$item['completed_at'] = $state['stage_data'][ $stage_key ]['completed_at'] ?? null;
				$item['completed_by'] = $state['stage_data'][ $stage_key ]['completed_by'] ?? null;

				if ( ! empty( $item['completed_by'] ) ) {
					$user                      = get_user_by( 'id', $item['completed_by'] );
					$item['completed_by_name'] = $user ? $user->display_name : __( 'Unknown', 'ict-platform' );
				}
			}

			$checklist[] = $item;
		}

		return $checklist;
	}
}
