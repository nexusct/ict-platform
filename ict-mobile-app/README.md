# ICT Platform Mobile App

React Native mobile application for ICT Platform - Operations management for ICT/electrical contractors.

## Features

- **Time Tracking**: Clock in/out with GPS, breaks, multiple rates
- **Project Management**: View projects, details, documents, team
- **Inventory Management**: Barcode scanning, stock levels, alerts
- **Equipment Tracking**: Tools, assignments, maintenance
- **Fleet Management**: Vehicles, GPS tracking, mileage
- **Expense Tracking**: Receipts, approvals, reimbursements
- **Offline Support**: Works offline with automatic sync
- **Push Notifications**: Real-time updates
- **Dark Mode**: Light/dark/system theme support

## Tech Stack

- React Native (Expo)
- TypeScript
- Redux Toolkit
- React Navigation
- Expo modules (Camera, Location, Notifications)

## Getting Started

### Prerequisites

- Node.js 18+
- npm or yarn
- Expo CLI
- Xcode (for iOS development)
- Apple Developer account (for App Store distribution)

### Installation

```bash
# Install dependencies
npm install

# Start development server
npm start

# Run on iOS simulator
npm run ios

# Run on Android emulator
npm run android
```

### Configuration

1. Update `app.json` with your:
   - Bundle identifier
   - Expo project ID
   - Google Maps API key

2. Update `src/services/api.ts` with your API base URL

3. Configure push notifications in Expo dashboard

## Building for iOS App Store

### Using EAS Build (Recommended)

```bash
# Install EAS CLI
npm install -g eas-cli

# Login to Expo
eas login

# Configure build
eas build:configure

# Build for App Store
eas build --platform ios --profile production

# Submit to App Store
eas submit --platform ios
```

### Using Fastlane

```bash
# Install Fastlane
brew install fastlane

# Setup certificates (first time)
cd fastlane
fastlane certificates_appstore

# Build and submit to TestFlight
fastlane beta

# Build and submit to App Store
fastlane release
```

## Project Structure

```
ict-mobile-app/
├── App.tsx                 # Main entry point
├── app.json                # Expo configuration
├── eas.json                # EAS Build configuration
├── package.json            # Dependencies
├── tsconfig.json           # TypeScript configuration
├── src/
│   ├── components/         # Reusable components
│   ├── context/            # React contexts
│   ├── hooks/              # Custom hooks
│   ├── navigation/         # Navigation structure
│   ├── screens/            # Screen components
│   │   ├── auth/           # Authentication screens
│   │   ├── projects/       # Project screens
│   │   ├── inventory/      # Inventory screens
│   │   └── more/           # Settings & profile
│   ├── services/           # API services
│   ├── store/              # Redux store
│   │   └── slices/         # Redux slices
│   └── types/              # TypeScript types
├── ios/                    # iOS native code
└── fastlane/               # Fastlane configuration
    ├── Fastfile            # Build lanes
    ├── Appfile             # App identifiers
    ├── Matchfile           # Code signing
    └── metadata/           # App Store metadata
```

## App Store Submission Checklist

### Before Submission

- [ ] Update version in `app.json`
- [ ] Test on physical devices
- [ ] Verify all permissions have descriptions
- [ ] Check offline functionality
- [ ] Review App Store guidelines

### Assets Required

- [ ] App icon (1024x1024)
- [ ] Screenshots for all device sizes
- [ ] App preview video (optional)

### App Store Connect

- [ ] Create app record
- [ ] Fill in app information
- [ ] Set up in-app purchases (if applicable)
- [ ] Configure pricing
- [ ] Submit for review

## Environment Variables

For CI/CD, set these environment variables:

```bash
APPLE_ID=your-apple-id@email.com
TEAM_ID=YOUR_TEAM_ID
ITC_TEAM_ID=YOUR_ITC_TEAM_ID
MATCH_GIT_URL=git@github.com:your-org/certificates.git
MATCH_PASSWORD=your-match-password
SLACK_WEBHOOK_URL=https://hooks.slack.com/...
```

## Troubleshooting

### Build Issues

- Clear Expo cache: `expo start -c`
- Reset Metro bundler: `watchman watch-del-all`
- Reinstall pods: `cd ios && pod install --repo-update`

### Code Signing

- Ensure certificates are valid in Apple Developer Portal
- Run `fastlane match nuke` to reset (destructive)
- Check team ID matches in all configuration files

## License

Proprietary - All rights reserved
