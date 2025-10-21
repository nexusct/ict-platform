# ICT Platform - Complete Operations Management System

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue.svg)
![React Native](https://img.shields.io/badge/React%20Native-0.72.6-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL%20v2-green.svg)

A comprehensive operations management platform for ICT/electrical contracting businesses with WordPress plugin and React Native mobile app. Integrates with Zoho's suite of services (CRM, FSM, Books, People, Desk) for complete business management.

## 📦 Repository Structure

```
ZOHOQW/
├── wp-ict-platform/          # WordPress Plugin
│   ├── admin/                # Admin area functionality
│   ├── api/                  # REST API controllers
│   ├── includes/             # Core classes & integrations
│   ├── public/               # Public-facing functionality
│   └── src/                  # React/TypeScript frontend
│
├── ict-mobile-app/           # React Native Mobile App
│   ├── src/
│   │   ├── screens/          # 18 screen components
│   │   ├── navigation/       # React Navigation setup
│   │   ├── services/         # API & location services
│   │   ├── store/            # Redux Toolkit state
│   │   └── types/            # TypeScript definitions
│   └── App.tsx               # Root component
│
└── docs/                     # Documentation
```

## 🚀 Features

### WordPress Plugin

#### Core Modules (Phases 1-7 Complete)
- ✅ **Project Management** - Complete project lifecycle tracking
- ✅ **Time Tracking** - Clock in/out with GPS, approval workflows
- ✅ **Resource Management** - Technician allocation, skills matrix, calendar
- ✅ **Inventory Management** - Stock tracking, reorder alerts
- ✅ **Procurement** - Purchase order workflow with approval
- ✅ **Reports & Analytics** - Custom dashboards with charts
- ✅ **Zoho Integration** - Bidirectional sync with CRM, FSM, Books, People, Desk

#### REST API Endpoints
```
/ict/v1/auth/*              - JWT authentication
/ict/v1/projects/*          - Project & task management
/ict/v1/time/*              - Time tracking & clock operations
/ict/v1/location/*          - GPS tracking & history
/ict/v1/expenses/*          - Expense submission & receipts
/ict/v1/schedule/*          - Events & calendar
/ict/v1/inventory/*         - Stock management
/ict/v1/purchase-orders/*   - PO workflow
/ict/v1/resources/*         - Resource allocation
/ict/v1/reports/*           - Analytics & reports
```

### Mobile App (React Native)

#### Key Features
- 🔐 **Authentication** - JWT with auto-refresh
- ⏱️ **Time Tracking** - Live timer, clock in/out with GPS
- 📍 **Background GPS** - Tracks location every 5 minutes while clocked in
- 🔔 **Smart Reminders** - Auto-prompts task change when >0.5 miles away for >30 min
- 📊 **Projects & Tasks** - View and update project information
- 💰 **Expenses** - Submit expenses with receipt photos
- 📁 **File Management** - Upload to Zoho WorkDrive
- 📅 **Schedule** - View events and assignments
- 🔔 **Push Notifications** - Task reminders and alerts

#### Screens (18 Total)
- Auth: Login, Forgot Password
- Dashboard: Home with quick stats
- Time: Clock, Entries List, Entry Detail
- Projects: List, Project Detail, Task Detail
- Schedule: Calendar view
- More: Expenses, Files, Notifications, Settings, Profile

## 🛠️ Technology Stack

### WordPress Plugin
- **Backend**: PHP 8.1+, WordPress 6.4+
- **Frontend**: React 18.2, TypeScript 5.2, Redux Toolkit
- **Build**: Webpack 5, Babel
- **Database**: MySQL 5.7+ with 10 custom tables
- **Testing**: PHPUnit, Jest, React Testing Library
- **APIs**: WordPress REST API + Custom endpoints
- **Background Jobs**: WordPress Action Scheduler

### Mobile App
- **Framework**: React Native 0.72.6
- **Language**: TypeScript 5.2
- **State**: Redux Toolkit
- **Navigation**: React Navigation v6
- **Location**: react-native-background-geolocation
- **Camera**: react-native-vision-camera
- **Notifications**: @notifee/react-native
- **API**: Axios with interceptors

### Integrations
- Zoho CRM - Customer & deal management
- Zoho FSM - Field service management
- Zoho Books - Accounting & invoicing
- Zoho People - HR & time tracking
- Zoho Desk - Support tickets
- Zoho WorkDrive - File storage
- Microsoft Teams - Group messaging (ready)

## 📥 Installation

### Prerequisites
- PHP 8.1+ and Composer
- Node.js 18+ and npm
- WordPress 6.4+
- MySQL 5.7+
- React Native development environment (for mobile app)

### WordPress Plugin

```bash
cd wp-ict-platform

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Build production assets
npm run build
```

**Activate the plugin in WordPress:**
1. Copy `wp-ict-platform` to `wp-content/plugins/`
2. Activate in WordPress admin
3. Navigate to ICT Platform → Settings
4. Configure Zoho credentials

### Mobile App

```bash
cd ict-mobile-app

# Install dependencies
npm install

# iOS only - install pods
cd ios && pod install && cd ..

# Run on iOS
npm run ios

# Run on Android
npm run android
```

**Configure environment:**
Create `.env` file:
```env
API_BASE_URL=https://yoursite.com/wp-json/ict/v1
WP_BASE_URL=https://yoursite.com/wp-json/wp/v2
```

## 📊 Database Schema

### Custom Tables (10 total)
- `wp_ict_projects` - Project data synced with Zoho CRM
- `wp_ict_time_entries` - Time tracking with GPS
- `wp_ict_inventory_items` - Stock management
- `wp_ict_purchase_orders` - PO workflow
- `wp_ict_project_resources` - Resource allocation
- `wp_ict_sync_queue` - Pending Zoho sync operations
- `wp_ict_sync_log` - Sync history & debugging
- `wp_ict_location_tracking` - GPS coordinate history
- `wp_ict_expenses` - Expense submissions
- `wp_ict_tasks` - Task management

## 🔧 Configuration

### Zoho OAuth Setup
1. Create app at https://api-console.zoho.com/
2. Set redirect URI: `https://yoursite.com/wp-admin/`
3. Add scopes for each service (CRM, FSM, Books, People, Desk)
4. Enter credentials in plugin settings

### Mobile App Permissions
**iOS (Info.plist):**
- NSLocationAlwaysAndWhenInUseUsageDescription
- NSCameraUsageDescription
- NSPhotoLibraryUsageDescription

**Android (AndroidManifest.xml):**
- ACCESS_FINE_LOCATION
- ACCESS_BACKGROUND_LOCATION
- CAMERA
- READ_EXTERNAL_STORAGE

## 🚀 Deployment

### WordPress Plugin
```bash
# Create production build
npm run build

# Create plugin zip
zip -r ict-platform.zip wp-ict-platform/ \
  -x "*/node_modules/*" "*/src/*" "*/tests/*" "*/.git/*"
```

### Mobile App
```bash
# iOS
cd ios
xcodebuild -workspace ICTMobileApp.xcworkspace \
  -scheme ICTMobileApp -configuration Release

# Android
cd android
./gradlew assembleRelease
```

## 📖 Documentation

- [WordPress Plugin Documentation](wp-ict-platform/README.md)
- [Mobile App Documentation](ict-mobile-app/README.md)
- [API Reference](wp-ict-platform/docs/api.md)
- [Zoho Integration Guide](ZOHO_SYNC_ENHANCEMENTS.md)
- [Installation Guide](MASTER_INSTALLATION_GUIDE.md)

## 🧪 Testing

### WordPress Plugin
```bash
# PHP tests
composer test

# JavaScript tests
npm test
npm run test:coverage

# Code quality
composer phpcs
npm run lint
```

### Mobile App
```bash
# Run tests
npm test

# Type checking
npm run type-check

# Linting
npm run lint
```

## 📈 Project Status

### WordPress Plugin
- **Phase 1-3**: ✅ 100% Complete (Foundation, Zoho, Projects)
- **Phase 4**: ✅ 100% Complete (Time Tracking)
- **Phase 5**: ✅ 100% Complete (Resource Management)
- **Phase 6**: ✅ 100% Complete (Inventory & Procurement)
- **Phase 7**: ✅ 100% Complete (Reports & Analytics)

### Mobile App
- **Core Features**: ✅ 100% Complete
- **Screens**: ✅ 18/18 Implemented
- **REST API Integration**: ✅ Complete
- **Background Services**: ✅ Complete
- **Microsoft Teams**: ⏳ Ready for integration

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

See [CONTRIBUTING.md](wp-ict-platform/CONTRIBUTING.md) for detailed guidelines.

## 📝 License

This project is licensed under the GPL v2 or later - see the LICENSE file for details.

## 🙏 Acknowledgments

- WordPress Community
- React Native Community
- Zoho Developer Platform
- All contributors and testers

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/yourusername/ict-platform/issues)
- **Documentation**: [Wiki](https://github.com/yourusername/ict-platform/wiki)
- **Email**: support@example.com

---

## 🔮 Roadmap

### Future Enhancements
- [ ] Microsoft Teams full integration
- [ ] Offline mode with data sync
- [ ] Advanced reporting with exports
- [ ] Mobile biometric authentication
- [ ] Multi-language support
- [ ] Advanced role management
- [ ] Custom field builder
- [ ] Email notifications
- [ ] SMS notifications
- [ ] WhatsApp integration

---

**Built with ❤️ for ICT/electrical contractors**

🤖 Generated with [Claude Code](https://claude.com/claude-code)
