<?php
/**
 * Nexus Workflow Process Model
 *
 * Comprehensive project management workflow based on PM documentation templates.
 * Defines 15 phases with associated stages, deliverables, and templates.
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Nexus_Workflow
 *
 * Defines the complete Nexus workflow process with all phases and stages.
 */
class ICT_Nexus_Workflow {

	/**
	 * Workflow phases ordered by typical project lifecycle.
	 *
	 * @var array
	 */
	const PHASES = array(
		'project_initiation',
		'project_planning',
		'procurement',
		'project_tracking',
		'change_management',
		'project_execution',
		'pm_office',
		'quality_management',
		'costing',
		'risk_management',
		'task_management',
		'project_timeline',
		'pm_essentials',
		'stakeholder_management',
		'project_closure',
	);

	/**
	 * Get all workflow phases with their definitions.
	 *
	 * @return array Complete workflow definition.
	 */
	public static function get_workflow_definition() {
		return array(
			// Phase 1: Project Initiation
			'project_initiation'     => array(
				'id'             => 'project_initiation',
				'order'          => 1,
				'name'           => __( 'Project Initiation', 'ict-platform' ),
				'description'    => __( 'Initial phase to establish project foundation and authorization.', 'ict-platform' ),
				'icon'           => 'play-circle',
				'color'          => '#6B21A8',
				'stages'         => array(
					array(
						'id'           => 'business_case',
						'name'         => __( 'Business Case', 'ict-platform' ),
						'description'  => __( 'Document the business justification for the project.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Business case document',
							'Cost-benefit analysis',
							'Strategic alignment statement',
							'Executive summary',
						),
						'template'     => 'business_case_template',
					),
					array(
						'id'           => 'project_proposal',
						'name'         => __( 'Project Proposal', 'ict-platform' ),
						'description'  => __( 'Formal proposal outlining project scope and approach.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Project proposal document',
							'High-level requirements',
							'Preliminary timeline',
							'Resource requirements',
						),
						'template'     => 'project_proposal_template',
					),
					array(
						'id'           => 'initial_resource_plan',
						'name'         => __( 'Initial Resource Plan', 'ict-platform' ),
						'description'  => __( 'Preliminary allocation of resources needed.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Resource matrix',
							'Team structure',
							'Skills assessment',
							'Availability chart',
						),
						'template'     => 'resource_plan_template',
					),
					array(
						'id'           => 'project_charter',
						'name'         => __( 'Project Charter', 'ict-platform' ),
						'description'  => __( 'Formal authorization document for project execution.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Signed project charter',
							'Project objectives',
							'Success criteria',
							'Stakeholder authorization',
						),
						'template'     => 'project_charter_template',
					),
				),
				'entry_criteria' => array(
					'Project request received',
					'Sponsor identified',
					'Initial budget available',
				),
				'exit_criteria'  => array(
					'Project charter approved',
					'Initial team assigned',
					'Kick-off meeting scheduled',
				),
				'zoho_crm_stage' => 'Qualification',
			),

			// Phase 2: Project Planning
			'project_planning'       => array(
				'id'             => 'project_planning',
				'order'          => 2,
				'name'           => __( 'Project Planning', 'ict-platform' ),
				'description'    => __( 'Develop detailed project plans and schedules.', 'ict-platform' ),
				'icon'           => 'calendar',
				'color'          => '#7C3AED',
				'stages'         => array(
					array(
						'id'           => 'gantt_chart',
						'name'         => __( 'Gantt Chart', 'ict-platform' ),
						'description'  => __( 'Create visual project schedule with dependencies.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Gantt chart document',
							'Task dependencies',
							'Milestone markers',
							'Resource assignments',
						),
						'template'     => 'gantt_chart_template',
					),
					array(
						'id'           => 'swot_analysis',
						'name'         => __( 'SWOT Analysis', 'ict-platform' ),
						'description'  => __( 'Analyze Strengths, Weaknesses, Opportunities, Threats.', 'ict-platform' ),
						'required'     => false,
						'deliverables' => array(
							'SWOT matrix',
							'Strategic recommendations',
							'Risk mitigation strategies',
						),
						'template'     => 'swot_analysis_template',
					),
					array(
						'id'           => 'action_plan',
						'name'         => __( 'Action Plan', 'ict-platform' ),
						'description'  => __( 'Define specific actions, responsibilities, and deadlines.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Action plan document',
							'Task assignments',
							'Due dates',
							'Success metrics',
						),
						'template'     => 'action_plan_template',
					),
					array(
						'id'           => 'one_page_project_manager',
						'name'         => __( 'One Page Project Manager', 'ict-platform' ),
						'description'  => __( 'Summarized project overview on single page.', 'ict-platform' ),
						'required'     => false,
						'deliverables' => array(
							'One-page summary',
							'Key milestones',
							'Budget summary',
							'Team contacts',
						),
						'template'     => 'one_page_pm_template',
					),
				),
				'entry_criteria' => array(
					'Project charter approved',
					'Initial team in place',
					'Requirements gathered',
				),
				'exit_criteria'  => array(
					'Project plan approved',
					'Schedule baselined',
					'Resources confirmed',
				),
				'zoho_crm_stage' => 'Needs Analysis',
			),

			// Phase 3: Procurement
			'procurement'            => array(
				'id'                => 'procurement',
				'order'             => 3,
				'name'              => __( 'Procurement', 'ict-platform' ),
				'description'       => __( 'Manage purchasing and vendor relationships.', 'ict-platform' ),
				'icon'              => 'shopping-cart',
				'color'             => '#2563EB',
				'stages'            => array(
					array(
						'id'           => 'purchase_order',
						'name'         => __( 'Purchase Order', 'ict-platform' ),
						'description'  => __( 'Create and manage purchase orders.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Purchase order documents',
							'Vendor selection',
							'Pricing agreements',
							'Delivery schedules',
						),
						'template'     => 'purchase_order_template',
					),
					array(
						'id'           => 'recovery_policy',
						'name'         => __( 'Recovery Policy', 'ict-platform' ),
						'description'  => __( 'Define procurement recovery and contingency plans.', 'ict-platform' ),
						'required'     => false,
						'deliverables' => array(
							'Recovery policy document',
							'Backup vendor list',
							'Emergency procedures',
						),
						'template'     => 'recovery_policy_template',
					),
					array(
						'id'           => 'catalogue',
						'name'         => __( 'Catalogue', 'ict-platform' ),
						'description'  => __( 'Maintain approved vendor and product catalogue.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Vendor catalogue',
							'Product specifications',
							'Pricing matrix',
							'Lead times',
						),
						'template'     => 'catalogue_template',
					),
					array(
						'id'           => 'problem_management',
						'name'         => __( 'Problem Management', 'ict-platform' ),
						'description'  => __( 'Track and resolve procurement issues.', 'ict-platform' ),
						'required'     => false,
						'deliverables' => array(
							'Problem log',
							'Resolution procedures',
							'Escalation matrix',
						),
						'template'     => 'problem_management_template',
					),
				),
				'entry_criteria'    => array(
					'Budget approved',
					'Requirements defined',
					'Vendor list available',
				),
				'exit_criteria'     => array(
					'All POs issued',
					'Vendors confirmed',
					'Delivery dates set',
				),
				'zoho_books_module' => 'purchase_orders',
			),

			// Phase 4: Project Tracking
			'project_tracking'       => array(
				'id'             => 'project_tracking',
				'order'          => 4,
				'name'           => __( 'Project Tracking', 'ict-platform' ),
				'description'    => __( 'Monitor project progress and performance.', 'ict-platform' ),
				'icon'           => 'chart-line',
				'color'          => '#059669',
				'stages'         => array(
					array(
						'id'           => 'raci_matrix',
						'name'         => __( 'RACI Matrix', 'ict-platform' ),
						'description'  => __( 'Define Responsible, Accountable, Consulted, Informed roles.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'RACI matrix document',
							'Role assignments',
							'Communication paths',
						),
						'template'     => 'raci_matrix_template',
					),
					array(
						'id'           => 'gap_analysis',
						'name'         => __( 'Gap Analysis', 'ict-platform' ),
						'description'  => __( 'Identify gaps between current and desired state.', 'ict-platform' ),
						'required'     => false,
						'deliverables' => array(
							'Gap analysis report',
							'Current state assessment',
							'Future state definition',
							'Remediation plan',
						),
						'template'     => 'gap_analysis_template',
					),
					array(
						'id'           => 'root_cause_analysis',
						'name'         => __( 'Root Cause Analysis', 'ict-platform' ),
						'description'  => __( 'Analyze and address root causes of issues.', 'ict-platform' ),
						'required'     => false,
						'deliverables' => array(
							'RCA report',
							'5 Whys analysis',
							'Fishbone diagram',
							'Corrective actions',
						),
						'template'     => 'root_cause_analysis_template',
					),
					array(
						'id'           => 'raid_log',
						'name'         => __( 'RAID Log', 'ict-platform' ),
						'description'  => __( 'Track Risks, Assumptions, Issues, Dependencies.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'RAID log document',
							'Risk register',
							'Issue tracker',
							'Dependency matrix',
						),
						'template'     => 'raid_log_template',
					),
				),
				'entry_criteria' => array(
					'Project execution started',
					'Baseline established',
					'Team mobilized',
				),
				'exit_criteria'  => array(
					'Progress documented',
					'Issues addressed',
					'Status reports delivered',
				),
				'zoho_crm_stage' => 'Proposal/Price Quote',
			),

			// Phase 5: Change Management
			'change_management'      => array(
				'id'               => 'change_management',
				'order'            => 5,
				'name'             => __( 'Change Management', 'ict-platform' ),
				'description'      => __( 'Control and manage project changes.', 'ict-platform' ),
				'icon'             => 'exchange-alt',
				'color'            => '#DC2626',
				'stages'           => array(
					array(
						'id'           => 'change_request',
						'name'         => __( 'ITIL Change Request', 'ict-platform' ),
						'description'  => __( 'Formal process for requesting changes.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Change request form',
							'Impact assessment',
							'Approval workflow',
							'Implementation plan',
						),
						'template'     => 'change_request_template',
					),
					array(
						'id'           => 'change_log',
						'name'         => __( 'Change Log', 'ict-platform' ),
						'description'  => __( 'Record all project changes and their status.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Change log document',
							'Version history',
							'Change history',
							'Approval records',
						),
						'template'     => 'change_log_template',
					),
					array(
						'id'           => 'impact_assessment',
						'name'         => __( 'Impact Assessment', 'ict-platform' ),
						'description'  => __( 'Evaluate the impact of proposed changes.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Impact assessment report',
							'Schedule impact',
							'Budget impact',
							'Resource impact',
						),
						'template'     => 'impact_assessment_template',
					),
				),
				'entry_criteria'   => array(
					'Change requested',
					'Stakeholders identified',
					'Current baseline documented',
				),
				'exit_criteria'    => array(
					'Change approved/rejected',
					'Impact documented',
					'Baseline updated if approved',
				),
				'zoho_desk_module' => 'tickets',
			),

			// Phase 6: Project Execution
			'project_execution'      => array(
				'id'              => 'project_execution',
				'order'           => 6,
				'name'            => __( 'Project Execution', 'ict-platform' ),
				'description'     => __( 'Execute project work and produce deliverables.', 'ict-platform' ),
				'icon'            => 'cogs',
				'color'           => '#EA580C',
				'stages'          => array(
					array(
						'id'           => 'execution_plan',
						'name'         => __( 'Execution Plan', 'ict-platform' ),
						'description'  => __( 'Detailed plan for executing project work.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Execution plan document',
							'Work packages',
							'Daily schedules',
							'Resource allocation',
						),
						'template'     => 'execution_plan_template',
					),
					array(
						'id'           => 'test_coverage',
						'name'         => __( 'Test Coverage Document', 'ict-platform' ),
						'description'  => __( 'Define testing scope and coverage requirements.', 'ict-platform' ),
						'required'     => false,
						'deliverables' => array(
							'Test coverage matrix',
							'Test cases',
							'Test results',
							'Defect log',
						),
						'template'     => 'test_coverage_template',
					),
					array(
						'id'           => 'implementation',
						'name'         => __( 'Implementation', 'ict-platform' ),
						'description'  => __( 'Execute the implementation phase.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Implementation records',
							'Progress reports',
							'Completion certificates',
							'As-built documentation',
						),
						'template'     => 'implementation_template',
					),
					array(
						'id'           => 'requirement_traceability',
						'name'         => __( 'Requirement Traceability', 'ict-platform' ),
						'description'  => __( 'Trace requirements through delivery.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Traceability matrix',
							'Requirement status',
							'Verification records',
						),
						'template'     => 'requirement_traceability_template',
					),
				),
				'entry_criteria'  => array(
					'Planning complete',
					'Resources available',
					'Environment ready',
				),
				'exit_criteria'   => array(
					'Deliverables completed',
					'Testing passed',
					'Quality verified',
				),
				'zoho_fsm_module' => 'work_orders',
			),

			// Phase 7: PM Office
			'pm_office'              => array(
				'id'             => 'pm_office',
				'order'          => 7,
				'name'           => __( 'PMO Office', 'ict-platform' ),
				'description'    => __( 'Project Management Office governance and oversight.', 'ict-platform' ),
				'icon'           => 'building',
				'color'          => '#7C3AED',
				'stages'         => array(
					array(
						'id'           => 'pmo_action_plan',
						'name'         => __( 'PMO Action Plan', 'ict-platform' ),
						'description'  => __( 'PMO-level action items and initiatives.', 'ict-platform' ),
						'required'     => false,
						'deliverables' => array(
							'PMO action plan',
							'Governance actions',
							'Process improvements',
						),
						'template'     => 'pmo_action_plan_template',
					),
					array(
						'id'           => 'pmo_business_case',
						'name'         => __( 'PMO Business Case', 'ict-platform' ),
						'description'  => __( 'Business case for PMO initiatives.', 'ict-platform' ),
						'required'     => false,
						'deliverables' => array(
							'PMO business case',
							'ROI analysis',
							'Value proposition',
						),
						'template'     => 'pmo_business_case_template',
					),
					array(
						'id'           => 'pmo_kpi_dashboard',
						'name'         => __( 'PMO KPI Dashboard', 'ict-platform' ),
						'description'  => __( 'Track key performance indicators across projects.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'KPI dashboard',
							'Performance metrics',
							'Trend analysis',
							'Executive reports',
						),
						'template'     => 'pmo_kpi_dashboard_template',
					),
				),
				'entry_criteria' => array(
					'Project portfolio defined',
					'PMO structure established',
					'Reporting requirements set',
				),
				'exit_criteria'  => array(
					'KPIs measured',
					'Reports delivered',
					'Governance maintained',
				),
			),

			// Phase 8: Quality Management
			'quality_management'     => array(
				'id'             => 'quality_management',
				'order'          => 8,
				'name'           => __( 'Quality Management', 'ict-platform' ),
				'description'    => __( 'Ensure project deliverables meet quality standards.', 'ict-platform' ),
				'icon'           => 'check-double',
				'color'          => '#16A34A',
				'stages'         => array(
					array(
						'id'           => 'scalable_results',
						'name'         => __( 'Scalable Results', 'ict-platform' ),
						'description'  => __( 'Define and measure scalable quality outcomes.', 'ict-platform' ),
						'required'     => false,
						'deliverables' => array(
							'Scalability analysis',
							'Performance benchmarks',
							'Growth projections',
						),
						'template'     => 'scalable_results_template',
					),
					array(
						'id'           => 'control_chart',
						'name'         => __( 'Control Chart', 'ict-platform' ),
						'description'  => __( 'Statistical process control monitoring.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Control charts',
							'Process limits',
							'Variation analysis',
							'Trend identification',
						),
						'template'     => 'control_chart_template',
					),
					array(
						'id'           => 'quality_log',
						'name'         => __( 'Quality Log Guideline', 'ict-platform' ),
						'description'  => __( 'Document quality checks and findings.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Quality log',
							'Inspection records',
							'Non-conformance reports',
							'Corrective actions',
						),
						'template'     => 'quality_log_template',
					),
				),
				'entry_criteria' => array(
					'Quality standards defined',
					'Inspection criteria set',
					'QA team assigned',
				),
				'exit_criteria'  => array(
					'Quality metrics met',
					'Issues resolved',
					'Sign-off obtained',
				),
			),

			// Phase 9: Costing
			'costing'                => array(
				'id'                => 'costing',
				'order'             => 9,
				'name'              => __( 'Costing', 'ict-platform' ),
				'description'       => __( 'Manage project budget and costs.', 'ict-platform' ),
				'icon'              => 'dollar-sign',
				'color'             => '#CA8A04',
				'stages'            => array(
					array(
						'id'           => 'cost_benefit_analysis',
						'name'         => __( 'Cost Benefits Analysis', 'ict-platform' ),
						'description'  => __( 'Analyze costs versus benefits of project.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Cost-benefit analysis',
							'ROI calculation',
							'Payback period',
							'NPV analysis',
						),
						'template'     => 'cost_benefit_template',
					),
					array(
						'id'           => 'project_budget',
						'name'         => __( 'Project Budget', 'ict-platform' ),
						'description'  => __( 'Detailed project budget breakdown.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Budget document',
							'Cost breakdown structure',
							'Contingency reserve',
							'Funding schedule',
						),
						'template'     => 'project_budget_template',
					),
					array(
						'id'           => 'earned_value',
						'name'         => __( 'Earned Value', 'ict-platform' ),
						'description'  => __( 'Track earned value metrics (EV, PV, AC).', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'EVM report',
							'CPI and SPI calculations',
							'Variance analysis',
							'Forecasts (EAC, ETC)',
						),
						'template'     => 'earned_value_template',
					),
				),
				'entry_criteria'    => array(
					'Scope defined',
					'Resources estimated',
					'Baseline approved',
				),
				'exit_criteria'     => array(
					'Budget tracked',
					'Variances explained',
					'Forecasts updated',
				),
				'zoho_books_module' => 'projects',
			),

			// Phase 10: Risk Management
			'risk_management'        => array(
				'id'             => 'risk_management',
				'order'          => 10,
				'name'           => __( 'Risk Management', 'ict-platform' ),
				'description'    => __( 'Identify, assess, and mitigate project risks.', 'ict-platform' ),
				'icon'           => 'exclamation-triangle',
				'color'          => '#DC2626',
				'stages'         => array(
					array(
						'id'           => 'incident_priority',
						'name'         => __( 'Incident Priority', 'ict-platform' ),
						'description'  => __( 'Define incident priority and response procedures.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Priority matrix',
							'Response procedures',
							'Escalation paths',
							'SLA definitions',
						),
						'template'     => 'incident_priority_template',
					),
					array(
						'id'           => 'cause_effect',
						'name'         => __( 'Cause/Effect Diagram', 'ict-platform' ),
						'description'  => __( 'Analyze cause and effect relationships.', 'ict-platform' ),
						'required'     => false,
						'deliverables' => array(
							'Ishikawa diagram',
							'Cause categories',
							'Effect analysis',
							'Prevention strategies',
						),
						'template'     => 'cause_effect_template',
					),
					array(
						'id'           => 'issue_resolution',
						'name'         => __( 'Issue Resolution Process', 'ict-platform' ),
						'description'  => __( 'Structured process for resolving issues.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Issue resolution workflow',
							'Resolution log',
							'Escalation matrix',
							'Closure criteria',
						),
						'template'     => 'issue_resolution_template',
					),
				),
				'entry_criteria' => array(
					'Risk assessment needed',
					'Risk team assigned',
					'Risk tolerance defined',
				),
				'exit_criteria'  => array(
					'Risks identified',
					'Mitigation plans in place',
					'Monitoring active',
				),
			),

			// Phase 11: Task Management
			'task_management'        => array(
				'id'                 => 'task_management',
				'order'              => 11,
				'name'               => __( 'Task Management', 'ict-platform' ),
				'description'        => __( 'Manage and track project tasks.', 'ict-platform' ),
				'icon'               => 'tasks',
				'color'              => '#0891B2',
				'stages'             => array(
					array(
						'id'           => 'single_project_tasks',
						'name'         => __( 'Single Project Tasks', 'ict-platform' ),
						'description'  => __( 'Manage tasks for individual project.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Task list',
							'Task assignments',
							'Due dates',
							'Progress tracking',
						),
						'template'     => 'single_project_tasks_template',
					),
					array(
						'id'           => 'daily_task_tracker',
						'name'         => __( 'Daily Task Tracker', 'ict-platform' ),
						'description'  => __( 'Track daily task completion and progress.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Daily task log',
							'Completion status',
							'Blockers',
							'Daily summary',
						),
						'template'     => 'daily_task_tracker_template',
					),
					array(
						'id'           => 'multi_project_task',
						'name'         => __( 'Multi Project Task', 'ict-platform' ),
						'description'  => __( 'Manage tasks across multiple projects.', 'ict-platform' ),
						'required'     => false,
						'deliverables' => array(
							'Cross-project task matrix',
							'Resource conflicts',
							'Priority rankings',
							'Workload balancing',
						),
						'template'     => 'multi_project_task_template',
					),
				),
				'entry_criteria'     => array(
					'Work breakdown complete',
					'Resources assigned',
					'Dependencies mapped',
				),
				'exit_criteria'      => array(
					'Tasks tracked',
					'Progress reported',
					'Blockers resolved',
				),
				'zoho_people_module' => 'tasks',
			),

			// Phase 12: Project Timeline
			'project_timeline'       => array(
				'id'             => 'project_timeline',
				'order'          => 12,
				'name'           => __( 'Project Timeline', 'ict-platform' ),
				'description'    => __( 'Manage project schedule and milestones.', 'ict-platform' ),
				'icon'           => 'clock',
				'color'          => '#4F46E5',
				'stages'         => array(
					array(
						'id'           => 'milestone_template',
						'name'         => __( 'Milestone Template', 'ict-platform' ),
						'description'  => __( 'Define and track project milestones.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Milestone list',
							'Milestone dates',
							'Milestone criteria',
							'Completion evidence',
						),
						'template'     => 'milestone_template',
					),
					array(
						'id'           => 'production_schedule',
						'name'         => __( 'Production Schedule', 'ict-platform' ),
						'description'  => __( 'Detailed production/work schedule.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Production schedule',
							'Resource calendar',
							'Shift planning',
							'Capacity planning',
						),
						'template'     => 'production_schedule_template',
					),
				),
				'entry_criteria' => array(
					'Scope finalized',
					'Resources confirmed',
					'Dependencies identified',
				),
				'exit_criteria'  => array(
					'Schedule baselined',
					'Milestones set',
					'Resources scheduled',
				),
			),

			// Phase 13: PM Essentials
			'pm_essentials'          => array(
				'id'             => 'pm_essentials',
				'order'          => 13,
				'name'           => __( 'PM Essentials', 'ict-platform' ),
				'description'    => __( 'Essential project management tools and trackers.', 'ict-platform' ),
				'icon'           => 'clipboard-list',
				'color'          => '#0D9488',
				'stages'         => array(
					array(
						'id'           => 'multiple_project_tracker',
						'name'         => __( 'Multiple Project Tracker', 'ict-platform' ),
						'description'  => __( 'Track multiple projects simultaneously.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Portfolio dashboard',
							'Project status summary',
							'Resource utilization',
							'Risk heatmap',
						),
						'template'     => 'multiple_project_tracker_template',
					),
					array(
						'id'           => 'project_scope',
						'name'         => __( 'Project Scope', 'ict-platform' ),
						'description'  => __( 'Define and manage project scope.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Scope statement',
							'In/out of scope',
							'Assumptions',
							'Constraints',
						),
						'template'     => 'project_scope_template',
					),
					array(
						'id'           => 'project_pipeline',
						'name'         => __( 'Project Pipeline Tracker', 'ict-platform' ),
						'description'  => __( 'Track projects through delivery pipeline.', 'ict-platform' ),
						'required'     => false,
						'deliverables' => array(
							'Pipeline view',
							'Stage gates',
							'Conversion metrics',
							'Forecasting',
						),
						'template'     => 'project_pipeline_template',
					),
				),
				'entry_criteria' => array(
					'Projects defined',
					'PM processes established',
					'Tools configured',
				),
				'exit_criteria'  => array(
					'Projects tracked',
					'Scope controlled',
					'Pipeline managed',
				),
			),

			// Phase 14: Stakeholder Management
			'stakeholder_management' => array(
				'id'             => 'stakeholder_management',
				'order'          => 14,
				'name'           => __( 'Stakeholder Management', 'ict-platform' ),
				'description'    => __( 'Manage stakeholder relationships and communications.', 'ict-platform' ),
				'icon'           => 'users',
				'color'          => '#BE185D',
				'stages'         => array(
					array(
						'id'           => 'communication_template',
						'name'         => __( 'Communication Template', 'ict-platform' ),
						'description'  => __( 'Define communication plans and templates.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Communication plan',
							'Message templates',
							'Distribution lists',
							'Communication calendar',
						),
						'template'     => 'communication_template',
					),
					array(
						'id'           => 'stakeholder_reporting',
						'name'         => __( 'Stakeholder Reporting', 'ict-platform' ),
						'description'  => __( 'Regular reporting to stakeholders.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Status reports',
							'Executive summaries',
							'Dashboards',
							'Meeting minutes',
						),
						'template'     => 'stakeholder_reporting_template',
					),
					array(
						'id'           => 'project_closure_meeting',
						'name'         => __( 'Project Closure Meeting', 'ict-platform' ),
						'description'  => __( 'Conduct project closure with stakeholders.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Closure meeting agenda',
							'Final report',
							'Lessons learned',
							'Sign-off documentation',
						),
						'template'     => 'project_closure_meeting_template',
					),
				),
				'entry_criteria' => array(
					'Stakeholders identified',
					'Communication needs assessed',
					'Channels established',
				),
				'exit_criteria'  => array(
					'Communications delivered',
					'Stakeholders informed',
					'Feedback collected',
				),
				'zoho_crm_stage' => 'Negotiation/Review',
			),

			// Phase 15: Project Closure
			'project_closure'        => array(
				'id'             => 'project_closure',
				'order'          => 15,
				'name'           => __( 'Project Closure', 'ict-platform' ),
				'description'    => __( 'Formally close the project and document outcomes.', 'ict-platform' ),
				'icon'           => 'flag-checkered',
				'color'          => '#14B8A6',
				'stages'         => array(
					array(
						'id'           => 'final_deliverables',
						'name'         => __( 'Final Deliverables', 'ict-platform' ),
						'description'  => __( 'Complete and hand over all project deliverables.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Deliverable checklist',
							'Acceptance certificates',
							'Handover documentation',
							'Training materials',
						),
						'template'     => 'final_deliverables_template',
					),
					array(
						'id'           => 'lessons_learned',
						'name'         => __( 'Lessons Learned', 'ict-platform' ),
						'description'  => __( 'Document project lessons and best practices.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Lessons learned document',
							'What went well',
							'Areas for improvement',
							'Recommendations',
						),
						'template'     => 'lessons_learned_template',
					),
					array(
						'id'           => 'project_archive',
						'name'         => __( 'Project Archive', 'ict-platform' ),
						'description'  => __( 'Archive all project documentation.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Document archive',
							'Index and catalog',
							'Retention schedule',
							'Access permissions',
						),
						'template'     => 'project_archive_template',
					),
					array(
						'id'           => 'team_release',
						'name'         => __( 'Team Release', 'ict-platform' ),
						'description'  => __( 'Release project resources and team members.', 'ict-platform' ),
						'required'     => true,
						'deliverables' => array(
							'Resource release forms',
							'Performance evaluations',
							'Recognition and rewards',
							'Transition plans',
						),
						'template'     => 'team_release_template',
					),
				),
				'entry_criteria' => array(
					'All deliverables complete',
					'Testing passed',
					'Client acceptance obtained',
				),
				'exit_criteria'  => array(
					'Project closed',
					'Team released',
					'Documentation archived',
				),
				'zoho_crm_stage' => 'Closed Won',
			),
		);
	}

	/**
	 * Get all phase IDs.
	 *
	 * @return array List of phase IDs.
	 */
	public static function get_phase_ids() {
		return self::PHASES;
	}

	/**
	 * Get a specific phase definition.
	 *
	 * @param string $phase_id Phase identifier.
	 * @return array|null Phase definition or null if not found.
	 */
	public static function get_phase( $phase_id ) {
		$workflow = self::get_workflow_definition();
		return isset( $workflow[ $phase_id ] ) ? $workflow[ $phase_id ] : null;
	}

	/**
	 * Get all stages for a phase.
	 *
	 * @param string $phase_id Phase identifier.
	 * @return array List of stages.
	 */
	public static function get_phase_stages( $phase_id ) {
		$phase = self::get_phase( $phase_id );
		return $phase ? $phase['stages'] : array();
	}

	/**
	 * Get required stages for a phase.
	 *
	 * @param string $phase_id Phase identifier.
	 * @return array List of required stages.
	 */
	public static function get_required_stages( $phase_id ) {
		$stages = self::get_phase_stages( $phase_id );
		return array_filter(
			$stages,
			function ( $stage ) {
				return $stage['required'];
			}
		);
	}

	/**
	 * Get the next phase in sequence.
	 *
	 * @param string $current_phase_id Current phase ID.
	 * @return string|null Next phase ID or null if at end.
	 */
	public static function get_next_phase( $current_phase_id ) {
		$phases = self::PHASES;
		$index  = array_search( $current_phase_id, $phases, true );

		if ( false === $index || $index >= count( $phases ) - 1 ) {
			return null;
		}

		return $phases[ $index + 1 ];
	}

	/**
	 * Get the previous phase in sequence.
	 *
	 * @param string $current_phase_id Current phase ID.
	 * @return string|null Previous phase ID or null if at beginning.
	 */
	public static function get_previous_phase( $current_phase_id ) {
		$phases = self::PHASES;
		$index  = array_search( $current_phase_id, $phases, true );

		if ( false === $index || 0 === $index ) {
			return null;
		}

		return $phases[ $index - 1 ];
	}

	/**
	 * Get workflow summary for display.
	 *
	 * @return array Simplified workflow summary.
	 */
	public static function get_workflow_summary() {
		$workflow = self::get_workflow_definition();
		$summary  = array();

		foreach ( $workflow as $phase_id => $phase ) {
			$summary[] = array(
				'id'              => $phase_id,
				'order'           => $phase['order'],
				'name'            => $phase['name'],
				'description'     => $phase['description'],
				'icon'            => $phase['icon'],
				'color'           => $phase['color'],
				'stage_count'     => count( $phase['stages'] ),
				'required_stages' => count(
					array_filter(
						$phase['stages'],
						function ( $s ) {
							return $s['required'];
						}
					)
				),
			);
		}

		return $summary;
	}

	/**
	 * Validate if a phase transition is allowed.
	 *
	 * @param string $from_phase From phase ID.
	 * @param string $to_phase   To phase ID.
	 * @param array  $completed_stages Completed stage IDs.
	 * @return array Validation result with 'valid' and 'errors' keys.
	 */
	public static function validate_phase_transition( $from_phase, $to_phase, $completed_stages = array() ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		// Check if phases exist
		$from_def = self::get_phase( $from_phase );
		$to_def   = self::get_phase( $to_phase );

		if ( ! $from_def ) {
			$result['valid']    = false;
			$result['errors'][] = sprintf( __( 'Invalid from phase: %s', 'ict-platform' ), $from_phase );
			return $result;
		}

		if ( ! $to_def ) {
			$result['valid']    = false;
			$result['errors'][] = sprintf( __( 'Invalid to phase: %s', 'ict-platform' ), $to_phase );
			return $result;
		}

		// Check if all required stages are completed (moving forward)
		if ( $to_def['order'] > $from_def['order'] ) {
			$required_stages = self::get_required_stages( $from_phase );

			foreach ( $required_stages as $stage ) {
				if ( ! in_array( $stage['id'], $completed_stages, true ) ) {
					$result['valid']    = false;
					$result['errors'][] = sprintf(
						__( 'Required stage "%1$s" not completed in phase "%2$s"', 'ict-platform' ),
						$stage['name'],
						$from_def['name']
					);
				}
			}
		}

		return $result;
	}

	/**
	 * Get Zoho CRM stage mapping for a workflow phase.
	 *
	 * @param string $phase_id Phase identifier.
	 * @return string|null Zoho CRM stage or null if not mapped.
	 */
	public static function get_zoho_crm_stage( $phase_id ) {
		$phase = self::get_phase( $phase_id );
		return isset( $phase['zoho_crm_stage'] ) ? $phase['zoho_crm_stage'] : null;
	}

	/**
	 * Find phase by Zoho CRM stage.
	 *
	 * @param string $crm_stage Zoho CRM stage name.
	 * @return string|null Phase ID or null if not found.
	 */
	public static function find_phase_by_crm_stage( $crm_stage ) {
		$workflow = self::get_workflow_definition();

		foreach ( $workflow as $phase_id => $phase ) {
			if ( isset( $phase['zoho_crm_stage'] ) && $phase['zoho_crm_stage'] === $crm_stage ) {
				return $phase_id;
			}
		}

		return null;
	}

	/**
	 * Calculate phase completion percentage.
	 *
	 * @param string $phase_id        Phase identifier.
	 * @param array  $completed_stages List of completed stage IDs.
	 * @return float Completion percentage (0-100).
	 */
	public static function calculate_phase_completion( $phase_id, $completed_stages = array() ) {
		$stages = self::get_phase_stages( $phase_id );

		if ( empty( $stages ) ) {
			return 100.0;
		}

		$completed_count = 0;

		foreach ( $stages as $stage ) {
			if ( in_array( $stage['id'], $completed_stages, true ) ) {
				++$completed_count;
			}
		}

		return round( ( $completed_count / count( $stages ) ) * 100, 2 );
	}

	/**
	 * Calculate overall workflow completion.
	 *
	 * @param array $phase_completions Array of phase_id => completion_percentage.
	 * @return float Overall completion percentage.
	 */
	public static function calculate_workflow_completion( $phase_completions ) {
		if ( empty( $phase_completions ) ) {
			return 0.0;
		}

		$total_phases     = count( self::PHASES );
		$total_completion = array_sum( $phase_completions );

		return round( $total_completion / $total_phases, 2 );
	}
}
