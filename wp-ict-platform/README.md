# ICT Platform - WordPress Plugin

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)
![React](https://img.shields.io/badge/React-18.2-blue.svg)
![TypeScript](https://img.shields.io/badge/TypeScript-5.2-blue.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-green.svg)

A comprehensive operations management platform for ICT/electrical contracting businesses with complete Zoho integration (CRM, FSM, Books, People, Desk).

## 🚀 Features

### Core Functionality
- **Project Management** - Full project lifecycle tracking with Gantt charts and budget monitoring
- **Time Tracking** - Mobile-friendly time clock with GPS support and offline capability
- **Resource Management** - Technician scheduling, equipment allocation, and availability tracking
- **Inventory Control** - Real-time stock tracking with low stock alerts and barcode scanning
- **Procurement** - Purchase order workflow integrated with Zoho Books
- **Client Portal** - Customer-facing project dashboard
- **Mobile PWA** - Progressive web app for field technicians

### Zoho Integration
- **Bidirectional Sync** - Automatic synchronization with all 5 Zoho services
- **OAuth 2.0 Authentication** - Secure token management with auto-refresh
- **Webhook Support** - Real-time updates from Zoho
- **Rate Limiting** - Respects Zoho's API limits (60 req/min per service)
- **Conflict Resolution** - Smart handling of sync conflicts with comprehensive logging

### Technical Stack
- **Backend**: PHP 8.1+, WordPress 6.4+
- **Frontend**: React 18, TypeScript 5.2, Redux Toolkit
- **Build System**: Webpack 5, Babel, SASS
- **Database**: MySQL 5.7+ with 7 custom tables
- **APIs**: WordPress REST API + Custom endpoints
- **Testing**: PHPUnit, Jest, React Testing Library

## 📋 Requirements

- WordPress 6.4 or higher
- PHP 8.1 or higher
- MySQL 5.7 or higher (or MariaDB 10.3+)
- Node.js 18+ (for development)
- Composer 2.0+ (for development)

## 📦 Installation

### Quick Install

1. **Download the plugin** and upload to `/wp-content/plugins/`
2. **Activate** the plugin through the WordPress 'Plugins' menu
3. **Navigate** to ICT Platform > Settings
4. **Configure** Zoho OAuth credentials for each service
5. **Run initial sync** from ICT Platform > Sync

### Development Install

```bash
# Clone the repository
cd wp-content/plugins/
git clone https://github.com/yourusername/ict-platform.git
cd ict-platform

# Install dependencies
npm install
composer install

# Build assets
npm run build

# For development with watch mode
npm run dev
```

## ⚙️ Configuration

### Zoho OAuth Setup

For each Zoho service (CRM, FSM, Books, People, Desk):

1. Go to [Zoho API Console](https://api-console.zoho.com/)
2. Create a new "Server-based Application"
3. Set Homepage URL: Your WordPress site URL
4. Set Redirect URI: `https://yoursite.com/wp-admin/admin.php?page=ict-settings&tab=zoho&service=[service]`
5. Copy Client ID and Client Secret
6. Enter credentials in **ICT Platform > Settings > Zoho Integration**
7. Click "Connect to Zoho" and authorize
8. Test the connection

### Database Tables

The plugin automatically creates 7 custom tables:

- `wp_ict_projects` - Project data synced with Zoho CRM deals
- `wp_ict_time_entries` - Time tracking synced with Zoho People
- `wp_ict_inventory_items` - Inventory synced with Zoho Books
- `wp_ict_purchase_orders` - PO workflow synced with Zoho Books
- `wp_ict_project_resources` - Resource allocation
- `wp_ict_sync_queue` - Pending sync operations
- `wp_ict_sync_log` - Sync history and debugging

### User Roles

Three custom roles are created on activation:

- **ICT Project Manager** - Full project and time management access
- **ICT Technician** - Can clock in/out, view assigned projects
- **ICT Inventory Manager** - Inventory and PO management

## 🏗️ Architecture

### Plugin Structure

```
wp-ict-platform/
├── ict-platform.php          # Main plugin file
├── includes/                 # Core PHP classes
│   ├── class-ict-*          # Core, Loader, Activator
│   ├── post-types/          # Custom post types
│   ├── taxonomies/          # Custom taxonomies
│   ├── integrations/        # Zoho adapters
│   │   └── zoho/           # Service-specific adapters
│   └── sync/               # Sync engine
├── admin/                   # Admin functionality
├── public/                  # Public-facing functionality
├── api/                     # REST API & webhooks
│   ├── rest/               # REST controllers
│   └── webhooks/           # Webhook receivers
├── src/                    # React/TypeScript source
│   ├── components/         # React components
│   ├── store/             # Redux store
│   ├── services/          # API services
│   └── types/             # TypeScript definitions
├── assets/                # Compiled assets
└── tests/                 # PHPUnit & Jest tests
```

### Sync Architecture

The plugin uses a queue-based sync system:

1. **Queue Creation** - Changes trigger sync queue items
2. **Background Processing** - WordPress cron processes queue every 15 minutes
3. **Rate Limiting** - Respects 60 requests/minute per service
4. **Error Handling** - Exponential backoff retry (30s → 240s)
5. **Logging** - All sync operations logged for debugging

## 🔌 API Endpoints

### Projects
- `GET /wp-json/ict/v1/projects` - List projects
- `GET /wp-json/ict/v1/projects/{id}` - Get project
- `POST /wp-json/ict/v1/projects` - Create project
- `PUT /wp-json/ict/v1/projects/{id}` - Update project
- `DELETE /wp-json/ict/v1/projects/{id}` - Delete project
- `POST /wp-json/ict/v1/projects/{id}/sync` - Sync to Zoho

### Time Tracking
- `GET /wp-json/ict/v1/time-entries` - List time entries
- `POST /wp-json/ict/v1/time/clock-in` - Clock in
- `POST /wp-json/ict/v1/time/clock-out` - Clock out
- `POST /wp-json/ict/v1/time-entries/{id}/approve` - Approve time

### Webhooks
- `POST /wp-json/ict/v1/webhooks/crm` - Zoho CRM webhook
- `POST /wp-json/ict/v1/webhooks/fsm` - Zoho FSM webhook
- `POST /wp-json/ict/v1/webhooks/books` - Zoho Books webhook
- `POST /wp-json/ict/v1/webhooks/people` - Zoho People webhook
- `POST /wp-json/ict/v1/webhooks/desk` - Zoho Desk webhook

## 🧪 Development

### Build Commands

```bash
# Development build with watch
npm run dev

# Production build
npm run build

# Type checking
npm run type-check
```

### Code Quality

```bash
# Lint JavaScript/TypeScript
npm run lint
npm run lint:fix

# Format code
npm run format

# PHP standards check
composer phpcs

# Fix PHP standards
composer phpcbf
```

### Testing

```bash
# Run JavaScript tests
npm test
npm run test:coverage

# Run PHP tests
composer test
```

## 📊 Project Status

### ✅ Completed (Phases 1-3)

**Phase 1: Foundation** (100%)
- Plugin structure and activation system
- Database schema with 7 custom tables
- User roles and capabilities
- Core classes (Loader, Activator, Core)
- Build system (Webpack, TypeScript, SASS)
- Coding standards (ESLint, Prettier, PHPCS)

**Phase 2: Zoho Integration** (100%)
- Base API client with OAuth 2.0
- Token manager with auto-refresh & encryption
- Rate limiter (60 req/min per service)
- All 5 Zoho service adapters:
  - CRM (Deals ↔ Projects)
  - People (Timesheets ↔ Time Entries)
  - Books (Items ↔ Inventory, POs)
  - FSM (Work Orders ↔ Tasks)
  - Desk (Tickets ↔ Support)
- Webhook receiver with signature verification
- Enhanced sync queue processor

**Phase 3: Project Management** (100%)
- TypeScript type definitions
- Redux store with projectsSlice
- API service layer
- React components:
  - ProjectDashboard
  - ProjectList (with search/filter)
  - ProjectForm (create/edit)
  - ProjectStats
- REST API controller
- Admin entry points
- SASS styling

**Phase 4: Time & Task Management** (100%)
- TimeTracker component with live timer
- TimeClock component with mobile-friendly UI
- TimesheetList with search/filter/pagination
- TimesheetApproval workflow for managers
- GPS tracking support for clock in/out
- Time entry CRUD operations
- REST API controller with 10 endpoints
- Redux timeEntriesSlice with 8 async thunks

**Phase 5: Resource Management** (100%)
- ResourceCalendar with FullCalendar integration
- Drag-and-drop event scheduling
- Conflict detection and prevention
- AvailabilityMatrix heatmap visualization
- ResourceAllocation form with conflict checking
- SkillMatrix with 5-star rating system
- Batch operations (create/update/delete)
- REST API controller with 13 endpoints
- Redux resourcesSlice with 12 async thunks
- Conflict detection utility functions

**Phase 6: Inventory & Procurement** (100%)
- InventoryDashboard with metrics and charts
- StockAdjustment with 8 adjustment types
- PurchaseOrderForm with line items
- LowStockAlerts widget with priority indicators
- Auto PO number generation (PO-YYYYMMDD-XXX)
- Stock history tracking
- Low stock detection and alerts
- REST API controllers (inventory + PO, 23 endpoints total)
- Redux slices (inventory + purchaseOrders)
- Comprehensive SASS styling

**Phase 7: Reports & Analytics** (100%)
- ReportsDashboard with key metrics
- Chart components (Bar, Line, Pie/Donut)
- Project reports with status/priority breakdown
- Time reports with flexible grouping
- Budget reports with cost analysis
- Inventory reports with valuation tracking
- Dashboard summary endpoint
- Export functionality (CSV/Excel/PDF)
- REST API controller with 6 endpoints
- Redux reportsSlice with 6 async thunks

### 🎯 Next Steps (Phase 8)

**Phase 8: Testing & Deployment** (Planned)
- Unit test coverage >80%
- Integration tests
- E2E tests
- Comprehensive documentation
- Plugin repository submission
- Production deployment

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the GPL-2.0 License - see the [LICENSE](LICENSE) file for details.

## 🙏 Credits

- Built with React, TypeScript, and WordPress REST API
- Zoho integration using OAuth 2.0 and REST APIs
- Icons from WordPress Dashicons

## 📧 Support

For issues and bug reports:
https://github.com/yourusername/ict-platform/issues

## 🗺️ Roadmap

- [ ] Complete time tracking module (Phase 4)
- [ ] Complete resource management (Phase 5)
- [ ] Complete inventory & procurement (Phase 6)
- [ ] Add reporting and analytics (Phase 7)
- [ ] Mobile app (React Native)
- [ ] AI-powered project insights
- [ ] Multi-language support
- [ ] White-label capabilities

---

**Built with ❤️ for ICT/electrical contracting businesses**
