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

## Incident history

- **2026-04-19** — Credential exposure incident (NCT-SEC-2026-04-19-001).
  Rotated: Google Maps API key, admin password. Full report on file.
