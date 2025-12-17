# ICT Platform - Complete Operations Management System

![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)
![React Native](https://img.shields.io/badge/React%20Native-0.72.6-blue.svg)
![CI](https://img.shields.io/badge/CI-GitHub%20Actions-green.svg)
![License](https://img.shields.io/badge/license-GPL%20v2-green.svg)

A comprehensive operations management platform for ICT/electrical contracting businesses. Includes a WordPress plugin with React frontend and a React Native mobile app. Integrates bidirectionally with Zoho's suite of services (CRM, FSM, Books, People, Desk) and QuoteWerks.

## Quick Start

```bash
# Clone the repository
git clone https://github.com/nexusct/ict-platform.git
cd ict-platform

# Install dependencies
make install

# Start development environment
make dev

# WordPress available at: http://localhost:8080
# phpMyAdmin at: http://localhost:8081
# MailHog at: http://localhost:8025
```

## Repository Structure

```
ict-platform/
├── .github/                    # GitHub Actions & templates
│   ├── workflows/
│   │   ├── ci.yml              # CI pipeline
│   │   └── release.yml         # Release automation
│   └── ISSUE_TEMPLATE/         # Issue templates
├── docker/                     # Docker configuration
│   ├── wordpress/              # PHP-FPM + WordPress
│   ├── mysql/                  # Database init
│   └── nginx/                  # Web server
├── scripts/                    # Automation scripts
│   ├── build.sh                # Production build
│   ├── release.sh              # Create release package
│   ├── deploy.sh               # Server deployment
│   ├── install.sh              # Setup development
│   └── test.sh                 # Run all tests
├── docs/                       # Documentation
│   ├── api/                    # API reference
│   ├── deployment/             # Deployment guides
│   └── development/            # Developer guides
├── wp-ict-platform/            # WordPress Plugin
│   ├── admin/                  # Admin functionality
│   ├── api/                    # REST API endpoints
│   ├── includes/               # Core PHP classes
│   │   ├── features/           # 40+ feature modules
│   │   ├── integrations/       # External services
│   │   └── sync/               # Sync engine
│   ├── public/                 # Frontend functionality
│   ├── src/                    # React/TypeScript
│   │   ├── components/         # React components
│   │   ├── store/              # Redux slices
│   │   ├── hooks/              # Custom hooks
│   │   └── services/           # API services
│   └── tests/                  # PHP & JS tests
├── ict-mobile-app/             # React Native Mobile App
├── docker-compose.yml          # Development environment
├── Makefile                    # Common commands
└── .env.example                # Environment template
```

## Features

### WordPress Plugin

| Module | Description | Status |
|--------|-------------|--------|
| Project Management | Complete project lifecycle tracking | ✅ |
| Time Tracking | Clock in/out with GPS, approvals | ✅ |
| Resource Management | Technician allocation, skills matrix | ✅ |
| Inventory Management | Stock tracking, reorder alerts | ✅ |
| Procurement | PO workflow with approval | ✅ |
| Reports & Analytics | Custom dashboards, charts | ✅ |
| Zoho Integration | CRM, FSM, Books, People, Desk | ✅ |
| QuoteWerks | Quote management sync | ✅ |

### Mobile App

- JWT Authentication with auto-refresh
- Live time tracking with GPS
- Background location tracking (5-minute intervals)
- Smart task change reminders
- Expense submission with receipts
- Project and task management
- Push notifications
- Offline capability

## Development

### Prerequisites

- Docker & Docker Compose
- Node.js 18+
- PHP 8.1+ with Composer
- Make

### Available Commands

```bash
make help           # Show all available commands

# Development
make install        # Install all dependencies
make dev            # Start Docker + watch mode
make build          # Production build
make clean          # Clean build artifacts

# Testing
make test           # Run all tests
make test-php       # PHP unit tests only
make test-js        # JavaScript tests only
make test-coverage  # Tests with coverage

# Code Quality
make lint           # Run all linters
make lint-fix       # Auto-fix linting issues
make format         # Format code
make type-check     # TypeScript checking

# Docker
make docker-up      # Start containers
make docker-down    # Stop containers
make docker-logs    # View logs
make docker-shell   # Shell into WordPress

# Release
make release V=1.0.0  # Create release package
make deploy           # Deploy to production
```

### Development Environment

The Docker environment includes:

| Service | Port | Description |
|---------|------|-------------|
| WordPress | 8080 | Main application |
| MySQL | 3306 | Database |
| phpMyAdmin | 8081 | Database management |
| MailHog | 8025 | Email testing |
| Redis | 6379 | Object caching |

### Environment Configuration

```bash
# Copy template and configure
cp .env.example .env

# Required variables:
DB_NAME=wordpress
DB_USER=wordpress
DB_PASSWORD=your_password

# Zoho Integration (optional for development)
ZOHO_CRM_CLIENT_ID=your_client_id
ZOHO_CRM_CLIENT_SECRET=your_client_secret
# ... other integrations
```

## CI/CD Pipeline

### Continuous Integration

Every push triggers:
1. PHP linting and PHPCS
2. JavaScript/TypeScript linting
3. PHPUnit tests
4. Jest tests
5. TypeScript type checking
6. Production build
7. Security audit

### Release Process

```bash
# Create a new release
git tag v1.2.0
git push origin v1.2.0

# This automatically:
# 1. Builds production assets
# 2. Creates optimized plugin package
# 3. Generates changelog
# 4. Creates GitHub release
```

## Testing

### Coverage Targets

| Type | Target |
|------|--------|
| PHP Unit Tests | 70% |
| JavaScript Tests | 70% |
| Integration Tests | Key workflows |

### Running Tests

```bash
# All tests
make test

# With coverage
make test-coverage

# Specific suites
cd wp-ict-platform
npm test                    # Jest
composer test               # PHPUnit
```

## API Reference

### REST Endpoints

```
/wp-json/ict/v1/
├── auth/                   # Authentication
├── projects/               # Project management
├── time/                   # Time tracking
├── inventory/              # Stock management
├── purchase-orders/        # PO workflow
├── resources/              # Resource allocation
├── reports/                # Analytics
├── schedule/               # Calendar events
├── expenses/               # Expense tracking
├── location/               # GPS tracking
└── health/                 # System health
```

### Health Check

```bash
curl -X GET "https://yoursite.com/wp-json/ict/v1/health" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Deployment

### Production Checklist

1. Configure environment variables
2. Run production build: `make build`
3. Create release: `make release`
4. Deploy: `make deploy`
5. Verify health endpoint
6. Test integrations

### Server Requirements

- PHP 8.1+
- WordPress 6.4+
- MySQL 5.7+
- SSL certificate (required for OAuth)
- WP-Cron or system cron

## Documentation

- [Development Guide](docs/development/)
- [API Reference](docs/api/)
- [Deployment Guide](docs/deployment/)
- [Zoho Integration](ZOHO_SYNC_ENHANCEMENTS.md)
- [Troubleshooting](TROUBLESHOOTING_GUIDE.md)
- [Deployment Checklist](DEPLOYMENT_CHECKLIST.md)

## Contributing

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

See [CONTRIBUTING.md](wp-ict-platform/CONTRIBUTING.md) for guidelines.

## License

GPL v2 or later - see [LICENSE](LICENSE) for details.

## Support

- **Issues**: [GitHub Issues](https://github.com/nexusct/ict-platform/issues)
- **Documentation**: [Project Wiki](https://github.com/nexusct/ict-platform/wiki)

---

**Built with precision for ICT/electrical contractors**
