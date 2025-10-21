=== ICT Platform ===
Contributors: yourname
Tags: zoho, crm, project-management, time-tracking, inventory
Requires at least: 6.4
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete ICT/electrical contracting operations platform with Zoho integration for project management, time tracking, resources, and inventory.

== Description ==

ICT Platform is a comprehensive WordPress plugin designed for ICT and electrical contracting businesses. It seamlessly integrates with Zoho's suite of services (CRM, FSM, Books, People, Desk) to provide:

* **Project Management** - Sync projects with Zoho CRM deals, track budgets, timelines, and deliverables
* **Time Tracking** - Mobile-friendly time clock with offline support, sync to Zoho People
* **Resource Management** - Schedule technicians, equipment, and materials
* **Inventory Control** - Real-time stock tracking synced with Zoho Books
* **Procurement** - Purchase order workflow integrated with Zoho Books
* **Client Portal** - Customer-facing project dashboard
* **Mobile PWA** - Progressive web app for field technicians

== Features ==

= Zoho Integration =
* OAuth 2.0 authentication for all Zoho services
* Bidirectional sync with rate limiting and queue management
* Webhook support for real-time updates
* Conflict resolution and sync logs

= Project Management =
* Gantt chart timeline view
* Budget tracking and forecasting
* Document management
* Task assignment and tracking
* Client tier categorization

= Time & Task Management =
* Clock in/out with GPS location
* Timesheet approval workflow
* Overtime calculation
* Task board (Kanban view)
* Offline time tracking

= Resource Management =
* Technician scheduling
* Equipment allocation
* Skill matrix
* Availability tracking
* Conflict detection

= Inventory & Procurement =
* Stock level monitoring
* Low stock alerts
* Barcode scanning
* Purchase order workflow
* Supplier management

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ict-platform` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to ICT Platform > Settings to configure Zoho API credentials
4. Configure your Zoho OAuth apps and enter credentials
5. Run initial sync for each Zoho service
6. Configure user roles and permissions

== Frequently Asked Questions ==

= What Zoho services are supported? =

The plugin integrates with Zoho CRM, Zoho FSM (Field Service Management), Zoho Books, Zoho People, and Zoho Desk.

= Does this work offline? =

Yes, the time tracking and basic project viewing features work offline and sync when connection is restored.

= Can I customize the sync schedule? =

Yes, sync intervals and conflict resolution rules can be configured in the settings.

= What happens if there's a sync conflict? =

The plugin provides conflict resolution options and logs all sync activities for review.

== Screenshots ==

1. Project dashboard with Gantt chart
2. Mobile time clock interface
3. Resource scheduling calendar
4. Inventory management dashboard
5. Zoho integration settings

== Changelog ==

= 1.0.0 =
* Initial release
* Project management module
* Time tracking with Zoho People sync
* Resource management
* Inventory control with Zoho Books sync
* Purchase order workflow
* Mobile PWA support

== Upgrade Notice ==

= 1.0.0 =
Initial release of ICT Platform.

== Development ==

This plugin is actively developed on GitHub. Report issues and contribute at:
https://github.com/yourusername/ict-platform

== Credits ==

Built with React, TypeScript, and WordPress REST API.
Zoho integration using OAuth 2.0 and REST APIs.
