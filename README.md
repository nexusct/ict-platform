# ICT Platform

**Enterprise Operations Management for ICT & Electrical Contractors**

[![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)](https://github.com/nexusct/ict-platform/releases)
[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-21759b.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4.svg)](https://php.net)
[![React](https://img.shields.io/badge/React-18.2-61dafb.svg)](https://reactjs.org)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.2-3178c6.svg)](https://typescriptlang.org)
[![License](https://img.shields.io/badge/license-GPL%20v2-green.svg)](LICENSE)

---

A complete business operations platform built for ICT and electrical contracting companies. Manage projects, track time with GPS, handle inventory and procurement, and synchronize everything with Zoho's business suite.

## Why ICT Platform?

| Challenge | Solution |
|-----------|----------|
| Disconnected systems | Unified platform with bidirectional Zoho sync |
| Manual time tracking | GPS-enabled clock in/out with mobile app |
| Quote-to-project gaps | QuoteWerks integration with automatic project creation |
| Field team visibility | Real-time location tracking and task management |
| Inventory chaos | Stock levels, reorder alerts, and PO workflows |

---

## Quick Start

```bash
# Clone and enter directory
git clone https://github.com/nexusct/ict-platform.git
cd ict-platform

# Set up environment
cp .env.example .env

# Install dependencies and start
make install
make dev
```

**Access Points:**
| Service | URL | Purpose |
|---------|-----|---------|
| WordPress | http://localhost:8080 | Main application |
| phpMyAdmin | http://localhost:8081 | Database management |
| MailHog | http://localhost:8025 | Email testing |

---

## Platform Components

### WordPress Plugin (`wp-ict-platform/`)

The core application—a feature-rich WordPress plugin with a React-powered admin interface.

**Core Modules:**

| Module | Capabilities |
|--------|-------------|
| **Projects** | Lifecycle management, milestones, templates, recurring tasks |
| **Time Tracking** | Clock in/out, GPS coordinates, approval workflows, overtime calculation |
| **Resources** | Technician scheduling, skills matrix, availability calendar |
| **Inventory** | Stock levels, barcode scanning, reorder alerts, supplier management |
| **Procurement** | Purchase orders, multi-level approval, vendor integration |
| **Reporting** | KPI dashboards, custom reports, data export |

**Enterprise Features (40+ modules):**
- Two-factor authentication & audit logging
- Client portal & satisfaction surveys
- Document management & e-signatures
- Push notifications & announcements
- Multi-currency support
- SLA tracking & warranty management
- Equipment & fleet management

### Mobile App (`ict-mobile-app/`)

React Native application for field technicians.

**Capabilities:**
- Biometric login with JWT authentication
- Live time tracking with background GPS (5-min intervals)
- Smart reminders when away from job site
- Expense submission with camera receipts
- Offline mode with automatic sync
- Push notifications for assignments

### Integrations

| Service | Sync Direction | Data |
|---------|---------------|------|
| **Zoho CRM** | Bidirectional | Deals ↔ Projects, Contacts |
| **Zoho FSM** | Bidirectional | Work Orders ↔ Projects, Tasks |
| **Zoho Books** | Bidirectional | Inventory, Purchase Orders, Invoices |
| **Zoho People** | Bidirectional | Time entries, Attendance |
| **Zoho Desk** | Bidirectional | Support tickets |
| **QuoteWerks** | Inbound + Webhooks | Quotes → Projects |
| **Microsoft Teams** | Outbound | Notifications |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        ICT Platform                              │
├─────────────────────────────────────────────────────────────────┤
│  WordPress Plugin                    │  Mobile App              │
│  ┌─────────────────────────────┐    │  ┌──────────────────┐    │
│  │  React Admin Dashboard      │    │  │  React Native    │    │
│  │  (TypeScript + Redux)       │    │  │  (Expo + Redux)  │    │
│  └─────────────────────────────┘    │  └──────────────────┘    │
│  ┌─────────────────────────────┐    │           │              │
│  │  REST API Layer             │◄───┼───────────┘              │
│  │  (19 Controllers)           │    │                          │
│  └─────────────────────────────┘    │                          │
│  ┌─────────────────────────────┐    │                          │
│  │  Sync Engine                │    │                          │
│  │  (Queue + Rate Limiting)    │────┼──► Zoho Services         │
│  └─────────────────────────────┘    │                          │
│  ┌─────────────────────────────┐    │                          │
│  │  MySQL Database             │    │                          │
│  │  (15 Custom Tables)         │    │                          │
│  └─────────────────────────────┘    │                          │
└─────────────────────────────────────────────────────────────────┘
```

---

## Development

### Prerequisites

- **Docker** & Docker Compose
- **Node.js** 18+ with npm
- **PHP** 8.1+ with Composer
- **Make** (build automation)

### Project Structure

```
ict-platform/
├── .github/                    # CI/CD workflows
│   └── workflows/
│       ├── ci.yml              # Test & build pipeline
│       └── release.yml         # Automated releases
├── docker/                     # Container configuration
│   ├── wordpress/              # PHP-FPM + extensions
│   ├── mysql/                  # Database initialization
│   └── nginx/                  # Web server + SSL
├── scripts/                    # Automation
│   ├── build.sh                # Production build
│   ├── release.sh              # Package creation
│   ├── deploy.sh               # Server deployment
│   └── test.sh                 # Test runner
├── docs/                       # Documentation
│   ├── api/                    # API & integration guides
│   ├── deployment/             # Setup & launch guides
│   └── development/            # Developer resources
├── wp-ict-platform/            # WordPress plugin source
│   ├── admin/                  # Admin PHP classes
│   ├── api/                    # REST controllers
│   ├── includes/               # Core functionality
│   │   ├── features/           # 40+ feature modules
│   │   ├── integrations/       # Zoho adapters
│   │   └── sync/               # Sync engine
│   ├── src/                    # React/TypeScript frontend
│   └── tests/                  # PHPUnit & Jest tests
├── ict-mobile-app/             # React Native mobile app
├── docker-compose.yml          # Development environment
├── Makefile                    # Build commands
└── .env.example                # Configuration template
```

### Commands

```bash
# Development
make install          # Install npm + composer dependencies
make dev              # Start Docker containers + webpack watch
make build            # Production build

# Testing
make test             # Run all tests (PHP + JS)
make test-php         # PHPUnit only
make test-js          # Jest only
make test-coverage    # Generate coverage reports

# Code Quality
make lint             # Run PHPCS + ESLint
make lint-fix         # Auto-fix issues
make format           # Prettier formatting
make type-check       # TypeScript validation

# Docker
make docker-up        # Start containers
make docker-down      # Stop containers
make docker-logs      # Stream logs
make docker-shell     # SSH into WordPress container

# Release
make release V=2.1.0  # Create versioned release package
make deploy           # Deploy to production server
```

### Docker Services

| Container | Port | Purpose |
|-----------|------|---------|
| `ict-wordpress` | 8080 | WordPress + PHP-FPM 8.2 |
| `ict-nginx` | 8080/8443 | Web server with SSL |
| `ict-mysql` | 3306 | MySQL 8.0 database |
| `ict-phpmyadmin` | 8081 | Database admin UI |
| `ict-mailhog` | 8025 | Email capture for testing |
| `ict-redis` | 6379 | Object caching |

---

## API Reference

Base URL: `/wp-json/ict/v1/`

### Endpoints

| Endpoint | Methods | Description |
|----------|---------|-------------|
| `/auth/login` | POST | JWT authentication |
| `/auth/refresh` | POST | Token refresh |
| `/projects` | GET, POST | Project management |
| `/projects/{id}` | GET, PUT, DELETE | Single project |
| `/time` | GET, POST | Time entries |
| `/time/clock-in` | POST | Start time tracking |
| `/time/clock-out` | POST | Stop time tracking |
| `/inventory` | GET, POST | Stock items |
| `/purchase-orders` | GET, POST | PO management |
| `/resources` | GET, POST | Resource allocation |
| `/schedule` | GET, POST | Calendar events |
| `/expenses` | GET, POST | Expense tracking |
| `/location` | GET, POST | GPS coordinates |
| `/reports/{type}` | GET | Generate reports |
| `/health` | GET | System status |

### Authentication

```bash
# Login
curl -X POST https://yoursite.com/wp-json/ict/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "user", "password": "pass"}'

# Authenticated request
curl -X GET https://yoursite.com/wp-json/ict/v1/projects \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Health Check

```bash
curl -X GET https://yoursite.com/wp-json/ict/v1/health \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Response:
```json
{
  "status": "healthy",
  "database": "connected",
  "integrations": {
    "zoho_crm": "connected",
    "zoho_books": "connected",
    "quotewerks": "connected"
  },
  "sync_queue": 3,
  "version": "2.0.0"
}
```

---

## CI/CD Pipeline

### Continuous Integration

Every push triggers:

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ PHP Lint │───►│ JS Lint  │───►│  Tests   │───►│  Build   │
│  PHPCS   │    │  ESLint  │    │ PHPUnit  │    │ Webpack  │
│          │    │  TSCheck │    │   Jest   │    │          │
└──────────┘    └──────────┘    └──────────┘    └──────────┘
```

### Automated Releases

```bash
# Tag a release
git tag v2.1.0
git push origin v2.1.0

# GitHub Actions automatically:
# 1. Builds production assets
# 2. Removes dev dependencies
# 3. Creates optimized zip package
# 4. Generates changelog from commits
# 5. Publishes GitHub Release
```

---

## Deployment

### Server Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| PHP | 8.1 | 8.2+ |
| WordPress | 6.4 | Latest |
| MySQL | 5.7 | 8.0+ |
| Memory | 256MB | 512MB+ |
| SSL | Required | Required |

### Production Setup

1. **Configure Environment**
   ```bash
   cp .env.example .env
   # Edit .env with production values
   ```

2. **Build Assets**
   ```bash
   make build
   ```

3. **Create Release**
   ```bash
   make release V=2.0.0
   ```

4. **Deploy**
   ```bash
   make deploy
   # Or upload releases/ict-platform-2.0.0.zip manually
   ```

5. **Verify**
   ```bash
   curl https://yoursite.com/wp-json/ict/v1/health
   ```

### Zoho OAuth Setup

1. Visit [Zoho API Console](https://api-console.zoho.com/)
2. Create "Server-based Application"
3. Set redirect URI: `https://yoursite.com/wp-admin/`
4. Copy Client ID and Secret to plugin settings
5. Complete OAuth authorization flow

---

## Testing

### Coverage Targets

| Category | Target | Current |
|----------|--------|---------|
| PHP Unit Tests | 70% | Building |
| JavaScript Tests | 70% | Building |
| Integration Tests | Critical paths | Building |

### Running Tests

```bash
# All tests
make test

# With coverage reports
make test-coverage

# Individual suites
cd wp-ict-platform
npm test                    # Jest (React components)
composer test               # PHPUnit (PHP classes)
```

---

## Documentation

| Guide | Description |
|-------|-------------|
| [Installation Guide](docs/deployment/MASTER_INSTALLATION_GUIDE.md) | Complete setup instructions |
| [Deployment Checklist](docs/deployment/DEPLOYMENT_CHECKLIST.md) | Pre-launch validation |
| [Troubleshooting](docs/deployment/TROUBLESHOOTING_GUIDE.md) | Common issues & solutions |
| [Zoho Integration](docs/api/ZOHO_SYNC_ENHANCEMENTS.md) | Sync configuration |
| [QuoteWerks Setup](docs/api/QUOTEWERKS_INTEGRATION.md) | Quote management |
| [API Reference](docs/api/) | Endpoint documentation |

---

## Contributing

We welcome contributions! Please follow these steps:

1. **Fork** the repository
2. **Create** a feature branch: `git checkout -b feature/your-feature`
3. **Commit** changes: `git commit -m 'Add your feature'`
4. **Push** to branch: `git push origin feature/your-feature`
5. **Open** a Pull Request

See [CONTRIBUTING.md](wp-ict-platform/CONTRIBUTING.md) for coding standards and guidelines.

---

## License

This project is licensed under the **GPL v2 or later**. See [LICENSE](LICENSE) for details.

---

## Support

- **Issues**: [GitHub Issues](https://github.com/nexusct/ict-platform/issues)
- **Discussions**: [GitHub Discussions](https://github.com/nexusct/ict-platform/discussions)
- **Wiki**: [Project Wiki](https://github.com/nexusct/ict-platform/wiki)

---

<p align="center">
  <strong>Built for the trades. Powered by integration.</strong>
  <br>
  <sub>ICT Platform &copy; 2024 NexusCT</sub>
</p>
