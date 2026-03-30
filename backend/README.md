# ICT Platform Backend

A Spring Boot backend for the ICT Platform - a complete operations management system for ICT/electrical contracting businesses.

## Overview

This backend provides a RESTful API and WebSocket interface that integrates with Zoho applications (CRM, FSM, Books, People, Desk) and supports the React frontend.

## Technology Stack

- **Framework**: Spring Boot 3.2 (Java 17)
- **Database**: PostgreSQL (MySQL and H2 also supported)
- **ORM**: Spring Data JPA / Hibernate
- **Security**: JWT authentication with Spring Security
- **Real-Time**: WebSocket (STOMP over SockJS)
- **API Docs**: OpenAPI 3 / Swagger UI
- **Monitoring**: Spring Boot Actuator
- **Containerization**: Docker + Docker Compose

## Features

- **Project Management** - Full CRUD for projects with Zoho CRM sync
- **Time Tracking** - Clock in/out with GPS, overtime calculation, approval workflow
- **Inventory Management** - Stock tracking, low-stock alerts, reorder management with Zoho Books sync
- **Purchase Orders** - PO workflow with approval process and Zoho Books sync
- **Zoho Integration** - Bidirectional sync with CRM, FSM, Books, People, Desk
- **JWT Authentication** - Secure role-based access (Admin, Project Manager, Technician, Inventory Manager)
- **WebSocket Notifications** - Real-time updates for projects, inventory, and sync status
- **Dashboard** - Aggregated statistics and KPIs
- **Sync Engine** - Scheduled bidirectional sync with Zoho services (every 15 minutes)

## Quick Start

### Prerequisites

- Java 17+
- Maven 3.9+
- PostgreSQL 14+ (or Docker)

### Run with Docker Compose

```bash
cd backend
cp .env.example .env   # configure your environment variables
docker compose up -d
```

The API will be available at `http://localhost:8080/api/v1`

### Run Locally

```bash
cd backend
mvn spring-boot:run
```

### Run Tests

```bash
cd backend
mvn test
```

## Configuration

Key environment variables (see `application.yml` for full list):

| Variable | Description | Default |
|---|---|---|
| `DB_URL` | Database JDBC URL | `jdbc:postgresql://localhost:5432/ict_platform` |
| `DB_USERNAME` | Database username | `ict_user` |
| `DB_PASSWORD` | Database password | `ict_password` |
| `JWT_SECRET` | JWT signing secret (base64) | (dev default) |
| `JWT_EXPIRATION` | JWT expiry in ms | `86400000` (24h) |
| `CORS_ALLOWED_ORIGINS` | Allowed frontend origins | `http://localhost:3000` |
| `ZOHO_CRM_CLIENT_ID` | Zoho CRM OAuth2 client ID | |
| `ZOHO_CRM_CLIENT_SECRET` | Zoho CRM OAuth2 client secret | |
| `ZOHO_CRM_REFRESH_TOKEN` | Zoho CRM refresh token | |
| `ZOHO_BOOKS_ORG_ID` | Zoho Books organization ID | |

## API Endpoints

Interactive API documentation is available at: `http://localhost:8080/api/v1/swagger-ui.html`

### Authentication
| Method | Endpoint | Description |
|---|---|---|
| POST | `/auth/login` | Login with credentials |
| POST | `/auth/register` | Register new user |
| POST | `/auth/refresh` | Refresh access token |
| GET | `/auth/me` | Get current user |

### Projects
| Method | Endpoint | Description |
|---|---|---|
| GET | `/projects` | List projects (filtered, paginated) |
| GET | `/projects/{id}` | Get project |
| POST | `/projects` | Create project |
| PUT | `/projects/{id}` | Update project |
| PATCH | `/projects/{id}/status` | Update project status |
| DELETE | `/projects/{id}` | Delete project |
| GET | `/projects/due-soon` | Projects due in N days |
| POST | `/projects/{id}/sync` | Queue Zoho sync |

### Time Tracking
| Method | Endpoint | Description |
|---|---|---|
| POST | `/time-entries/clock-in` | Clock in to project |
| POST | `/time-entries/clock-out` | Clock out |
| POST | `/time-entries` | Create manual entry |
| GET | `/time-entries/project/{id}` | Entries by project |
| GET | `/time-entries/user/{id}` | Entries by user |
| GET | `/time-entries/range` | Entries in date range |
| PATCH | `/time-entries/{id}/approve` | Approve entry |

### Inventory
| Method | Endpoint | Description |
|---|---|---|
| GET | `/inventory` | List items (filtered, paginated) |
| GET | `/inventory/{id}` | Get item |
| POST | `/inventory` | Create item |
| PUT | `/inventory/{id}` | Update item |
| PATCH | `/inventory/{id}/stock` | Adjust stock |
| GET | `/inventory/low-stock` | Items below reorder point |
| GET | `/inventory/out-of-stock` | Out of stock items |

### Purchase Orders
| Method | Endpoint | Description |
|---|---|---|
| GET | `/purchase-orders` | List POs |
| GET | `/purchase-orders/{id}` | Get PO |
| POST | `/purchase-orders` | Create PO |
| PUT | `/purchase-orders/{id}` | Update PO |
| PATCH | `/purchase-orders/{id}/status` | Update PO status |
| GET | `/purchase-orders/project/{id}` | POs for project |

### Dashboard
| Method | Endpoint | Description |
|---|---|---|
| GET | `/dashboard/summary` | Dashboard statistics |

### Monitoring
| Method | Endpoint | Description |
|---|---|---|
| GET | `/actuator/health` | Health check |
| GET | `/actuator/info` | Application info |
| GET | `/actuator/metrics` | Metrics |

## WebSocket

Connect to: `ws://localhost:8080/api/v1/ws`

### Topics
- `/topic/projects/{id}` - Project updates
- `/topic/inventory/alerts` - Inventory alerts
- `/topic/sync` - Sync status updates
- `/topic/dashboard` - Dashboard updates
- `/user/queue/notifications` - User-specific notifications

## Security

- **JWT** tokens required for all endpoints except `/auth/**` and `/actuator/health`
- **Roles**: `ADMIN` > `PROJECT_MANAGER` > `INVENTORY_MANAGER` > `TECHNICIAN` > `VIEWER`
- Tokens expire in 24 hours; use refresh token to renew

## Zoho Integration

The sync engine processes the queue every 15 minutes (configurable). Each entity supports CREATE, UPDATE, and DELETE operations synced to the appropriate Zoho service:

- **Projects** → Zoho CRM (Deals) + Zoho FSM (Jobs)
- **Time Entries** → Zoho People
- **Inventory** → Zoho Books (Items)
- **Purchase Orders** → Zoho Books (Purchase Orders)

Failed syncs are retried up to 3 times with exponential backoff. Sync logs older than 30 days are automatically cleaned up.

## Deployment

### Docker

```bash
docker build -t ict-platform-backend .
docker run -p 8080:8080 -e DB_URL=... -e JWT_SECRET=... ict-platform-backend
```

### Azure App Service

```bash
az webapp create --name ict-platform-api --resource-group myRG --plan myPlan --runtime "JAVA:17-java17"
az webapp config appsettings set --name ict-platform-api --resource-group myRG --settings DB_URL="..." JWT_SECRET="..."
az webapp deploy --name ict-platform-api --resource-group myRG --src-path target/ict-platform-1.0.0.jar
```

### GitHub Actions

See `.github/workflows/` for CI/CD pipeline configuration.

## Development

```bash
# Build
mvn clean package

# Run with test profile (H2 in-memory DB)
mvn spring-boot:run -Dspring-boot.run.profiles=test

# Run tests
mvn test

# Generate test coverage report
mvn test jacoco:report
```
