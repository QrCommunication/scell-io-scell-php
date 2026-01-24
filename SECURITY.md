# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability within Scell.io PHP SDK, please send an email to security@scell.io.

All security vulnerabilities will be promptly addressed.

Please include the following information in your report:

- Type of vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

## Security Best Practices

When using this SDK, please follow these best practices:

1. **Never commit API keys** - Use environment variables
2. **Verify webhook signatures** - Always use `WebhookVerifier` for incoming webhooks
3. **Use HTTPS only** - Never disable SSL verification in production
4. **Keep the SDK updated** - Install security updates promptly
5. **Limit API key permissions** - Use scoped API keys when possible
