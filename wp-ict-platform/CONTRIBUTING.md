# Contributing to ICT Platform

First off, thank you for considering contributing to ICT Platform! It's people like you that make this plugin a great tool for ICT/electrical contracting businesses.

## Code of Conduct

This project and everyone participating in it is governed by our commitment to creating a welcoming and inclusive environment. By participating, you are expected to uphold this standard.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When you create a bug report, include as many details as possible:

**Use the bug report template and include:**
- A clear and descriptive title
- Exact steps to reproduce the problem
- Expected behavior vs actual behavior
- Screenshots if applicable
- Your environment (WordPress version, PHP version, browser)
- Error messages from browser console or PHP logs

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion:

- Use a clear and descriptive title
- Provide a detailed description of the suggested enhancement
- Explain why this enhancement would be useful
- List any similar features in other plugins if applicable

### Pull Requests

1. **Fork the repository** and create your branch from `main`:
   ```bash
   git checkout -b feature/my-new-feature
   ```

2. **Install dependencies:**
   ```bash
   npm install
   composer install
   ```

3. **Make your changes** following our coding standards (see below)

4. **Test your changes:**
   ```bash
   npm run lint
   npm test
   composer test
   ```

5. **Commit your changes** using clear commit messages:
   ```bash
   git commit -m "Add feature: brief description"
   ```

6. **Push to your fork:**
   ```bash
   git push origin feature/my-new-feature
   ```

7. **Submit a Pull Request** with:
   - Clear description of changes
   - Reference to related issues
   - Screenshots for UI changes
   - Test results

## Development Setup

### Prerequisites

- WordPress 5.8+ local environment (Local, XAMPP, MAMP, etc.)
- PHP 7.4+
- Node.js 16+
- Composer 2.0+

### Local Development

```bash
# Clone your fork
git clone https://github.com/YOUR-USERNAME/wp-ict-platform.git
cd wp-ict-platform

# Install dependencies
npm install
composer install

# Start development mode (watches for changes)
npm run dev

# In another terminal, start PHP server if needed
php -S localhost:8000
```

## Coding Standards

### PHP

We follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/):

```bash
# Check PHP code standards
composer phpcs

# Auto-fix PHP code standards
composer phpcbf
```

**Key PHP guidelines:**
- Use tabs for indentation
- Class names: `Class_Name_With_Underscores`
- Function names: `function_names_with_underscores`
- Variable names: `$variable_names_with_underscores`
- Add docblocks to all functions and classes
- Sanitize all inputs, escape all outputs

### JavaScript/TypeScript

We use ESLint and Prettier:

```bash
# Check JavaScript/TypeScript
npm run lint

# Auto-fix
npm run lint:fix

# Format code
npm run format
```

**Key JS/TS guidelines:**
- Use camelCase for variables and functions
- Use PascalCase for components and classes
- Add JSDoc comments to functions
- Use TypeScript types everywhere
- Prefer functional components with hooks

### CSS/SASS

We use BEM methodology:

```scss
// Good
.block-name {
  &__element {
    // Element styles
  }

  &--modifier {
    // Modifier styles
  }
}

// Bad
.blockName .elementName {
  // Avoid camelCase and deep nesting
}
```

## Git Commit Messages

* Use the present tense ("Add feature" not "Added feature")
* Use the imperative mood ("Move cursor to..." not "Moves cursor to...")
* Limit the first line to 72 characters
* Reference issues and pull requests after the first line

**Good examples:**
```
Add purchase order approval workflow

- Implement approve/reject endpoints
- Add approval UI components
- Update PO status transitions
- Add email notifications

Fixes #123
```

```
Fix inventory stock calculation bug

The available quantity was not correctly calculated
when items had pending allocations.

Closes #456
```

## Testing

### PHP Tests (PHPUnit)

```bash
# Run all PHP tests
composer test

# Run specific test file
./vendor/bin/phpunit tests/test-projects.php

# Run with coverage
composer test -- --coverage-html coverage
```

### JavaScript Tests (Jest)

```bash
# Run all JS tests
npm test

# Run in watch mode
npm run test:watch

# Run with coverage
npm run test:coverage
```

### Manual Testing Checklist

Before submitting a PR, test:

- [ ] Feature works as expected in latest WordPress
- [ ] No console errors in browser
- [ ] No PHP errors in debug.log
- [ ] Responsive design works on mobile
- [ ] Works in Chrome, Firefox, Safari
- [ ] No accessibility issues (keyboard navigation, screen readers)

## Documentation

* Update README.md if adding features
* Add JSDoc comments to new JavaScript functions
* Add PHPDoc comments to new PHP functions
* Update CHANGELOG.md following [Keep a Changelog](https://keepachangelog.com/)
* Update inline code comments for complex logic

## Project Structure

```
wp-ict-platform/
├── api/rest/              # REST API controllers
├── includes/              # PHP core classes
├── src/                   # React/TypeScript source
│   ├── components/       # React components
│   ├── store/slices/     # Redux slices
│   ├── services/         # API services
│   ├── types/            # TypeScript types
│   └── styles/           # SASS styles
├── tests/                # PHPUnit tests
├── __tests__/            # Jest tests
└── webpack.config.js     # Build configuration
```

## Questions?

Feel free to open an issue with the `question` label or reach out to the maintainers.

## License

By contributing, you agree that your contributions will be licensed under the GPL-2.0 License.

---

**Thank you for your contributions!** 🙏
