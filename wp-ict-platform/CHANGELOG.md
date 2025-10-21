# Changelog

All notable changes to the ICT Platform WordPress plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-15

### Added

#### Phase 1: Foundation
- Plugin structure and activation system
- Database schema with 9 custom tables
- User roles and capabilities (3 custom roles)
- Core classes (Loader, Activator, Deactivator, Core)
- Build system (Webpack 5, TypeScript 5.2, SASS)
- Coding standards (ESLint, Prettier, PHPCS)

#### Phase 2: Zoho Integration
- Base API client with OAuth 2.0 authentication
- Token manager with auto-refresh & encryption
- Rate limiter (60 requests/minute per service)
- All 5 Zoho service adapters:
  - CRM (Deals ↔ Projects)
  - People (Timesheets ↔ Time Entries)
  - Books (Items ↔ Inventory, POs)
  - FSM (Work Orders ↔ Tasks)
  - Desk (Tickets ↔ Support)
- Webhook receiver with signature verification
- Enhanced sync queue processor with retry logic

#### Phase 3: Project Management
- TypeScript type definitions
- Redux store with projectsSlice
- API service layer with Axios
- React components:
  - ProjectDashboard
  - ProjectList (with search/filter/pagination)
  - ProjectForm (create/edit modal)
  - ProjectStats cards
- REST API controller with 6 endpoints
- Admin entry points and routing
- Comprehensive SASS styling with BEM methodology

#### Phase 4: Time & Task Management
- TimeTracker component with live timer
- TimeClock component with mobile-friendly UI
- TimesheetList with search/filter/pagination
- TimesheetApproval workflow for managers
- GPS tracking support for clock in/out
- Time entry CRUD operations
- REST API controller with 10 endpoints
- Redux timeEntriesSlice with 8 async thunks
- Offline-ready architecture

#### Phase 5: Resource Management
- ResourceCalendar with FullCalendar v6 integration
- Drag-and-drop event scheduling with conflict detection
- AvailabilityMatrix heatmap visualization
- ResourceAllocation form with real-time conflict checking
- SkillMatrix with 5-star rating system
- Batch operations (create/update/delete multiple allocations)
- REST API controller with 13 endpoints
- Redux resourcesSlice with 12 async thunks
- Conflict detection utility functions
- Comprehensive resource styling (900+ lines SASS)

#### Phase 6: Inventory & Procurement
- InventoryDashboard with metrics, charts, and alerts
- StockAdjustment component with 8 adjustment types
- PurchaseOrderForm with dynamic line items
- LowStockAlerts widget with priority indicators and auto-refresh
- Auto PO number generation (format: PO-YYYYMMDD-XXX)
- Stock history tracking with audit trail
- Low stock detection and reorder alerts
- REST API controllers:
  - Inventory controller with 13 endpoints
  - Purchase Orders controller with 10 endpoints
- Redux slices:
  - inventorySlice with 10 async thunks
  - purchaseOrdersSlice with 10 async thunks
- Comprehensive inventory styling (2000+ lines SASS)

#### Phase 7: Reports & Analytics
- ReportsDashboard with cross-module key metrics
- Custom chart components (no external dependencies):
  - BarChart (horizontal and vertical)
  - LineChart with area fill and grid
  - PieChart with donut mode and legends
- Project reports with status/priority breakdown and timeline
- Time reports with flexible grouping (day/week/month/technician/project)
- Budget reports with labor/material cost analysis
- Inventory reports with valuation and movement tracking
- Dashboard summary endpoint with aggregated metrics
- Export functionality (CSV/Excel/PDF placeholders)
- REST API controller with 6 endpoints
- Redux reportsSlice with 6 async thunks
- Chart and report styling (550+ lines SASS)

### Technical Details

**Backend:**
- 60+ REST API endpoints
- 9 custom database tables
- 8 REST controllers
- OAuth 2.0 integration with 5 Zoho services
- Queue-based sync system with retry logic

**Frontend:**
- 35+ React components
- 8 Redux slices with 66+ async thunks
- TypeScript throughout (100% coverage)
- 4500+ lines of SASS with BEM methodology
- Responsive design with mobile breakpoints

**Performance:**
- Lazy loading for admin pages
- Optimized bundle splitting
- Debounced search inputs
- Paginated API responses
- Efficient Redux state management

## [Unreleased]

### Planned for 1.1.0
- Additional detailed report components
- Enhanced export functionality (actual file generation)
- PWA features for offline support
- Push notifications
- Mobile app (React Native)

### Planned for 2.0.0
- Multi-language support (i18n/l10n)
- White-label capabilities
- AI-powered project insights
- Custom field types and forms
- Advanced scheduling algorithms

---

[1.0.0]: https://github.com/yourusername/wp-ict-platform/releases/tag/v1.0.0
