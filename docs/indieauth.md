# IndieAuth Identity Provider Setup

Indieinabox provides a built-in, lightweight, secure, and spec-compliant IndieAuth identity provider (RFC 9245 / OAuth 2.1 compatible). This enables you to use your own domain name as your digital identity (Single Sign-On) to log in to external IndieWeb applications (like micro.blog, Quill, Aperture, and others) and issue secure API tokens.

---

## ⚙️ Configuration

To enable IndieAuth, you must configure an administration password in your settings.

### Production (Hidden Config file)
To keep secrets protected on real web servers, set the password in the hidden config file: `.config.yml` located at the root of your project:

```yaml
# .config.yml
indieauth_password: "YOUR_SECURE_PASSWORD_OR_BCRYPT_HASH"
```

> [!TIP]
> Both plain-text passwords and bcrypt-hashed passwords (generated via `password_hash('mypass', PASSWORD_BCRYPT)`) are supported. Using a bcrypt hash is highly recommended for production security.

---

## 🔍 Discovery Setup

To allow client applications to discover your IndieAuth endpoints, you have two options:

### Option A: Metadata Discovery (Zero-config)
Indieinabox automatically serves OAuth 2.0 Authorization Server Metadata at:
`/.well-known/oauth-authorization-server`

Many modern clients will detect this endpoint automatically using only your FQDN domain.

### Option B: HTML Head Links (Fallback)
If a client does not support metadata discovery, you must add the following `<link>` tags inside the `<head>` block of your main page template (`resources/views/page.php`):

```html
<link rel="authorization_endpoint" href="https://yourdomain.com/auth">
<link rel="token_endpoint" href="https://yourdomain.com/token">
```

---

## 🔒 Verification & Security Features

*   **PKCE (Proof Key for Code Exchange)**: Mandated for secure authorization code verification. Supports both `S256` and `plain` code challenge methods (RFC 7636).
*   **One-time Authorization Codes**: Generated authorization codes are saved under `data/indieauth/codes/<md5_hash>.json` with a 10-minute validity window and are deleted immediately after verification.
*   **Persistent Tokens**: Exchanged tokens are stored securely under `data/indieauth/tokens/<md5_hash>.json` containing scopes and client information.
*   **Token Verification**: External services (like Micropub endpoints) can verify bearer tokens by making a `GET` request to `/token` with the header:
    `Authorization: Bearer <token>`
