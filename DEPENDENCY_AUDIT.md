# Dependency Audit Report

**Date:** 2025-12-31
**Projects Analyzed:** wp-ict-platform (WordPress Plugin), ict-mobile-app (React Native/Expo)

---

## Executive Summary

| Metric | wp-ict-platform | ict-mobile-app |
|--------|-----------------|----------------|
| Total Packages | 1,748 | 1,438 |
| Security Vulnerabilities | 11 (2 low, 1 moderate, 8 high) | 13 (2 low, 11 high) |
| Outdated Dependencies | 18 | 28 |
| Major Version Behind | 8 | 15+ |

---

## Security Vulnerabilities

### wp-ict-platform (WordPress Plugin)

#### HIGH Severity

1. **cross-spawn** (< 6.0.6)
   - **Issue:** Regular Expression Denial of Service (ReDoS)
   - **Advisory:** GHSA-3xgq-45jj-v275
   - **Fix:** Update `@wordpress/scripts` to ^31.2.0

2. **tar-fs** (2.0.0 - 2.1.3, 3.0.0 - 3.1.0)
   - **Issue:** Symlink validation bypass, path traversal vulnerabilities
   - **Advisories:** GHSA-vj76-c3g6-qr5v, GHSA-8cj5-5rvv-wf4v, GHSA-pq67-2wwv-3xjx
   - **Fix:** Update `@wordpress/scripts` to ^31.2.0

3. **ws** (8.0.0 - 8.17.0)
   - **Issue:** DoS when handling requests with many HTTP headers
   - **Advisory:** GHSA-3h5v-q93c-6h6q
   - **Fix:** Update `@wordpress/scripts` to ^31.2.0

#### MODERATE Severity

4. **webpack-dev-server** (<= 5.2.0)
   - **Issue:** Source code theft via malicious website in non-Chromium browsers
   - **Advisories:** GHSA-9jgg-88mc-972h, GHSA-4v9v-hfq4-rm2v
   - **Fix:** Update `@wordpress/scripts` to ^31.2.0

#### LOW Severity

5. **cookie** (< 0.7.0)
   - **Issue:** Accepts cookie name/path/domain with out-of-bounds characters
   - **Advisory:** GHSA-pxg6-pf52-xh8x

### ict-mobile-app (React Native/Expo)

#### HIGH Severity

1. **ip** (all versions via react-native CLI)
   - **Issue:** SSRF improper categorization in isPublic
   - **Advisory:** GHSA-2p57-rm9w-gvfp
   - **Fix:** Update react-native to 0.73.11+

2. **semver** (7.0.0 - 7.5.1)
   - **Issue:** Regular Expression Denial of Service
   - **Advisory:** GHSA-c2qf-rxjj-qqgw
   - **Fix:** Update expo and expo-notifications

3. **send** (< 0.19.0)
   - **Issue:** Template injection leading to XSS
   - **Advisory:** GHSA-m6fv-jmcg-4jfg
   - **Fix:** Update expo to ~54.0.0

---

## Outdated Dependencies

### wp-ict-platform - Critical Updates Needed

| Package | Current | Latest | Priority | Notes |
|---------|---------|--------|----------|-------|
| `@wordpress/scripts` | ^26.17.0 | ^31.2.0 | **CRITICAL** | Fixes all security vulnerabilities |
| `@reduxjs/toolkit` | ^1.9.7 | ^2.11.2 | HIGH | Major version update, API changes |
| `react-redux` | ^8.1.3 | ^9.2.0 | HIGH | Requires RTK v2 |
| `date-fns` | ^2.30.0 | ^4.1.0 | MEDIUM | Breaking changes in v3+ |
| `framer-motion` | ^10.16.4 | ^12.23.26 | MEDIUM | Performance improvements |
| `react-router-dom` | ^6.17.0 | ^7.11.0 | LOW | Major version, new features |
| `react-toastify` | ^9.1.3 | ^11.0.5 | LOW | Minor breaking changes |
| `eslint` | ^8.52.0 | ^9.x | LOW | Major version with flat config |
| `typescript` | ^5.2.2 | ^5.7.x | LOW | Minor update |

### ict-mobile-app - Critical Updates Needed

| Package | Current | Latest | Priority | Notes |
|---------|---------|--------|----------|-------|
| `expo` | ~50.0.0 | ~54.0.0 | **CRITICAL** | Security fixes, SDK 54 |
| `react-native` | 0.73.0 | 0.76.x | **CRITICAL** | Security fixes |
| `expo-notifications` | ~0.27.0 | ~0.32.0 | HIGH | Security fixes |
| `expo-splash-screen` | ~0.26.0 | ~31.0.0 | HIGH | Major version bump |
| `@react-navigation/*` | ^6.x | ^7.x | MEDIUM | Major version |
| `react-native-reanimated` | ~3.6.0 | ~4.2.0 | MEDIUM | Performance |
| `react-native-screens` | ~3.29.0 | ~4.19.0 | MEDIUM | Major version |

---

## Unnecessary Bloat & Optimization Opportunities

### wp-ict-platform

1. **`@types/chart.js` (v2.9.41)** - REMOVE
   - Chart.js v4 includes TypeScript definitions
   - This is for Chart.js v2 and conflicts with v4

2. **`react-beautiful-dnd` (v13.1.1)** - CONSIDER REPLACING
   - Unmaintained since 2021
   - **Recommendation:** Replace with `@dnd-kit/core` or `@hello-pangea/dnd` (fork)

3. **`@wordpress/scripts`** includes many unused sub-packages
   - If not using WordPress blocks/editor features, consider:
   - Using standalone webpack config (already present)
   - Removing `@types/wordpress__*` if not needed

4. **Duplicate functionality:**
   - `axios` + WordPress has built-in `apiFetch`
   - Consider using `@wordpress/api-fetch` for WordPress REST calls

### ict-mobile-app

1. **`expo-barcode-scanner`** - DEPRECATED
   - Expo recommends using `expo-camera` barcode scanning instead
   - Can remove this dependency

2. **Heavy dependencies without tree-shaking:**
   - `date-fns` - Only import needed functions
   - `react-native-maps` - Consider if maps are essential (large bundle impact)

---

## Recommended package.json Updates

### wp-ict-platform/package.json

```json
{
  "devDependencies": {
    "@wordpress/scripts": "^31.2.0",
    "@typescript-eslint/eslint-plugin": "^8.20.0",
    "@typescript-eslint/parser": "^8.20.0",
    "eslint": "^8.57.0",
    "typescript": "^5.7.2",
    "ts-jest": "^29.2.5"
  },
  "dependencies": {
    "@reduxjs/toolkit": "^2.5.0",
    "react-redux": "^9.2.0",
    "date-fns": "^4.1.0",
    "framer-motion": "^11.15.0",
    "react-router-dom": "^6.30.0",
    "react-toastify": "^10.0.6",
    "axios": "^1.7.9"
  },
  "remove": [
    "@types/chart.js"
  ],
  "consider-replacing": {
    "react-beautiful-dnd": "@hello-pangea/dnd"
  }
}
```

### ict-mobile-app/package.json

```json
{
  "dependencies": {
    "expo": "~54.0.0",
    "react-native": "0.76.7",
    "expo-camera": "~17.0.0",
    "expo-notifications": "~0.32.0",
    "expo-splash-screen": "~31.0.0",
    "@react-navigation/native": "^7.1.0",
    "@react-navigation/native-stack": "^7.3.0",
    "@react-navigation/bottom-tabs": "^7.3.0",
    "react-native-reanimated": "~3.16.0",
    "react-native-screens": "~4.6.0"
  },
  "remove": [
    "expo-barcode-scanner"
  ]
}
```

---

## Migration Steps

### Phase 1: Security Fixes (Immediate)

1. **wp-ict-platform:**
   ```bash
   npm install @wordpress/scripts@^31.2.0 --save-dev
   npm audit fix
   ```

2. **ict-mobile-app:**
   ```bash
   # Update to Expo SDK 54
   npx expo install expo@~54.0.0
   npx expo install --fix
   ```

### Phase 2: Redux Toolkit v2 Migration

1. Update imports to use new slice selectors
2. Replace `configureStore` middleware syntax if using custom middleware
3. Test all Redux-connected components

### Phase 3: React Navigation v7 Migration (Mobile)

1. Update navigation type definitions
2. Check for breaking changes in screen options
3. Test all navigation flows

### Phase 4: date-fns v4 Migration

1. Update import paths (tree-shakeable by default)
2. Check for removed/renamed functions
3. Update date formatting patterns if needed

---

## Composer Dependencies (PHP)

### Current State
```json
{
  "require": {
    "php": ">=8.1",
    "composer/installers": "^2.0",
    "psr/container": "^2.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.0",
    "wp-coding-standards/wpcs": "^3.0",
    "phpcompatibility/php-compatibility": "^9.3",
    "dealerdirect/phpcodesniffer-composer-installer": "^1.0"
  }
}
```

### Recommendations

| Package | Current | Latest | Action |
|---------|---------|--------|--------|
| `phpunit/phpunit` | ^10.0 | ^11.5 | Update for PHP 8.1+ |
| `wp-coding-standards/wpcs` | ^3.0 | ^3.1 | Minor update |

The PHP dependencies are relatively minimal and well-maintained.

---

## Bundle Size Optimization

### wp-ict-platform Recommendations

1. **Enable tree-shaking** for date-fns:
   ```js
   // Instead of
   import { format } from 'date-fns';
   // Use
   import format from 'date-fns/format';
   ```

2. **Lazy load FullCalendar** (heavy library):
   ```js
   const Calendar = lazy(() => import('@fullcalendar/react'));
   ```

3. **Split chunks** by route in webpack config

### ict-mobile-app Recommendations

1. Remove unused Expo modules
2. Use Hermes engine for better performance
3. Enable Proguard for Android release builds

---

## Priority Action Items

| Priority | Action | Impact |
|----------|--------|--------|
| 1 | Update `@wordpress/scripts` to ^31.2.0 | Fixes 8 security vulnerabilities |
| 2 | Update Expo to SDK 54 | Fixes 11 security vulnerabilities |
| 3 | Remove `@types/chart.js` | Eliminates type conflicts |
| 4 | Remove `expo-barcode-scanner` | Removes deprecated package |
| 5 | Update `@reduxjs/toolkit` to v2 | Modern Redux patterns |
| 6 | Replace `react-beautiful-dnd` | Maintenance concerns |

---

## Summary

The codebase has **24 security vulnerabilities** across both projects, with the majority stemming from outdated build tools (`@wordpress/scripts`) and mobile framework versions (`expo`, `react-native`).

**Immediate actions required:**
1. Update `@wordpress/scripts` to v31.2.0 (WordPress plugin)
2. Update to Expo SDK 54 (Mobile app)
3. Remove deprecated `expo-barcode-scanner`
4. Remove conflicting `@types/chart.js`

**Medium-term improvements:**
1. Migrate to Redux Toolkit v2
2. Migrate to React Navigation v7
3. Replace unmaintained `react-beautiful-dnd`
4. Optimize bundle sizes with lazy loading
