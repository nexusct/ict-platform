# Security

This repository is maintained by **Nexus Communications Technology** (Nexuscomm LLC).

## Reporting a security issue

If you discover a security vulnerability, **do not** open a public issue.

Instead, email **office@nexusct.com** with:
- A description of the vulnerability
- Steps to reproduce (if applicable)
- The commit/branch where you observed it

We will acknowledge within 2 business days.

## What belongs in this repo

**Never commit:**
- Passwords, API keys, tokens, private keys, certificates
- Internal pricing data, dealer costs, margin percentages
- Customer PII or PHI
- Internal network addresses or credentials
- Database dumps or backups containing real data

Use environment variables, `config.js` (gitignored), or a runtime
config endpoint — never hardcode.

## Pre-commit hooks

This repo uses [pre-commit](https://pre-commit.com/) with:
- `gitleaks` — detects committed secrets
- `detect-secrets` — second-layer scanner
- Nexus-specific pattern blocks — rejects known-bad strings

**Setup (one-time per clone):**

```bash
pip install pre-commit
pre-commit install
```

Hooks will run automatically on every commit.

## Security best practices

### Code security

This repository follows secure coding practices:

- **SQL Injection Prevention**: All database queries that include user input use WordPress `$wpdb->prepare()` with proper parameterization
- **XSS Prevention**: All user-facing output is properly escaped using `esc_html()`, `esc_attr()`, `esc_url()`, etc.
- **No eval()**: Mathematical expressions are parsed safely without `eval()` to prevent code injection
- **Input Validation**: All base64-encoded data is validated before decoding and use
- **Authentication**: OAuth 2.0 for Zoho integrations, JWT tokens for mobile API, nonce verification for REST endpoints
- **Encryption**: Sensitive credentials encrypted using OpenSSL AES-256-CBC

### Recent security improvements (2026-08-14)

- Removed `eval()` from custom field formula calculator, replaced with safe mathematical expression parser
- Fixed SQL query preparation to always use `$wpdb->prepare()` even for queries without user input
- Added strict validation for base64-decoded image data (format check + image verification)
- Ensured all REST API endpoints validate and sanitize input parameters
- Verified no hardcoded credentials in codebase (all use environment variables or encrypted options)

## Incident history

- **2026-08-14** — Security audit and hardening (NCT-SEC-2026-08-14-001).
  Fixed: SQL injection prevention gaps, eval() vulnerability, base64 validation.
- **2026-04-19** — Credential exposure incident (NCT-SEC-2026-04-19-001).
  Rotated: Google Maps API key, admin password. Full report on file.
