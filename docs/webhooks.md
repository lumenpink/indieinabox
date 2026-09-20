# Webhooks & Integration Guide

Indieinabox implements standard IndieWeb, Fediverse, and operational HTTP webhooks to receive real-time notifications, automate static site rebuilds, and process background tasks.

---

## 1. Webhook Endpoints Overview

| Endpoint | Method(s) | Authentication | Description |
| :--- | :--- | :--- | :--- |
| **`/build`** | `POST`, `GET` | `BUILD_TOKEN` / `WEBHOOK_TOKEN` | Triggers an immediate or queued rebuild of the static site. |
| **`/cron`** | `GET`, `POST` | `CRON_TOKEN` / `WEBHOOK_TOKEN` | Triggers the complete background processing pipeline. |
| **`/webmention`** | `POST` (or `GET` for help form) | Source Verification / Anti-Spoofing | W3C Webmention receiver for comments, likes, replies, and reposts. |
| **`/inbox`** | `POST` | HTTP Signatures (RSA-SHA256) | W3C ActivityPub federation inbox receiving remote activities. |
| **`/archive/force`** | `POST` | Public / Web UI | Enqueues a URL for snapshot capture into the local web archive. |

---

## 2. Build Webhook (`/build`)

The `/build` webhook triggers site regeneration on demand. It is engineered for instant synchronization whenever content is added or modified via Git, Syncthing, Obsidian, or external file watchers.

### How to Access

* **URL:** `https://your-domain.com/build`
* **Supported Methods:** `POST` (recommended for CI/CD and Git webhooks) or `GET`.

### Parameters & Modes

1. **Synchronous Mode (Default):**
   * Executes the site build immediately and blocks until completed.
   * Returns a JSON response containing execution duration and build timestamp.
   * **Request:**
     ```bash
     curl -X POST "https://your-domain.com/build" \
          -H "Authorization: Bearer YOUR_BUILD_TOKEN"
     ```
   * **Response (`200 OK`):**
     ```json
     {
         "status": 200,
         "message": "Site rebuilt successfully.",
         "duration_ms": 240.5,
         "timestamp": 1726868000
     }
     ```

2. **Asynchronous Mode (`?async=1`):**
   * Enqueues a `build_site` task in the background worker queue (`inbox_queue`) and returns immediately without blocking.
   * Ideal for webhooks with strict connection timeouts (e.g. GitHub/Forgejo webhook limits).
   * **Request:**
     ```bash
     curl -X POST "https://your-domain.com/build?async=1" \
          -H "Authorization: Bearer YOUR_BUILD_TOKEN"
     ```
   * **Response (`202 Accepted`):**
     ```json
     {
         "status": 202,
         "message": "Site build queued successfully."
     }
     ```

---

## 3. Cron Webhook (`/cron`)

The `/cron` endpoint allows external cron services or HTTP schedulers to trigger the background processing pipeline, which:
- Processes the incoming inbox queue (`webmention`, `activitypub`, `build_site`).
- Dispatches pending outgoing webmentions.
- Fetches followed Twtxt feeds and hubs.
- Processes background archive snapshots.

### How to Access

* **URL:** `https://your-domain.com/cron`
* **Supported Methods:** `GET`, `POST`.
* **Request:**
  ```bash
  curl -fsS "https://your-domain.com/cron?token=YOUR_CRON_TOKEN"
  ```
* **Response (`200 OK`):**
  ```text
  OK
  ```

> [!NOTE]
> When running Indieinabox via Docker Compose, the background worker is automatically executed locally inside the `indieinabox_cron` container via CLI (`php /app/indieinabox.php cron`). The `/cron` HTTP webhook is only needed if you are using an external scheduler or serverless cron ping.

---

## 4. Authentication & Security

### Configuring Tokens

Tokens can be configured through three methods (in order of resolution):

1. **Environment Variables (Docker Compose / Systemd):**
   ```yaml
   environment:
     - CRON_TOKEN=my_super_secret_cron_token
     - BUILD_TOKEN=my_super_secret_build_token
     # Alternatively, set a single master token for both:
     # - WEBHOOK_TOKEN=my_unified_webhook_token
   ```

2. **Environment File (`.env`):**
   ```dotenv
   CRON_TOKEN=my_super_secret_cron_token
   BUILD_TOKEN=my_super_secret_build_token
   ```

3. **Web Admin Dashboard (`/admin/config`):**
   Navigate to `/admin/config` &rarr; **Webhooks & Automation** and enter your desired tokens.

### Providing the Token in Requests

Indieinabox supports multiple standard authentication mechanisms:

1. **HTTP Authorization Header (Recommended):**
   ```bash
   curl -H "Authorization: Bearer YOUR_SECRET_TOKEN" https://your-domain.com/build
   ```

2. **Custom Webhook Headers:**
   ```bash
   curl -H "X-Webhook-Token: YOUR_SECRET_TOKEN" https://your-domain.com/build
   # Or endpoint-specific headers:
   curl -H "X-Build-Token: YOUR_SECRET_TOKEN" https://your-domain.com/build
   curl -H "X-Cron-Token: YOUR_SECRET_TOKEN" https://your-domain.com/cron
   ```

3. **Query Parameter:**
   ```bash
   curl "https://your-domain.com/build?token=YOUR_SECRET_TOKEN"
   ```

### Local vs. Remote Policy

* **Local Requests (`127.0.0.1`, `::1`, `localhost`, or CLI):** If no token is configured, local calls are permitted by default for development and local testing.
* **External Requests:** If no token is configured, external calls are rejected with `403 Forbidden` to prevent open public invocation.
* **Configured Tokens:** Once a token is set, all requests (local and remote) must supply a valid matching token or receive `401 Unauthorized`. Comparisons use timing-safe `hash_equals()`.

---

## 5. Integration Recipes

### A. Watcher / Auto-Sync Script (Syncthing / Local Content)

Add this curl call at the end of your content synchronization loop (e.g. in `watcher/entrypoint.sh`):

```bash
# Trigger immediate rebuild after content is updated
curl -s -X POST "http://localhost:80/build?token=${BUILD_TOKEN}" > /dev/null || true
```

### B. Git Post-Receive Hook (Bare Git / Self-Hosted Git)

Inside your remote Git server repository (`hooks/post-receive`):

```bash
#!/bin/sh
echo ">> Content pushed. Triggering Indieinabox rebuild..."
curl -s -X POST "https://your-domain.com/build?async=1" \
     -H "Authorization: Bearer YOUR_BUILD_TOKEN" \
     --max-time 10 > /dev/null &
```

### C. Forgejo / Gitea / GitHub Webhook

1. Go to repository **Settings** &rarr; **Webhooks** &rarr; **Add Webhook**.
2. **Target URL:** `https://your-domain.com/build?token=YOUR_BUILD_TOKEN&async=1`
3. **HTTP Method:** `POST`
4. **Trigger On:** Push Events.

### D. Systemd Timer or Cronjob

```cron
# Trigger background worker every 15 minutes
*/15 * * * * curl -fsS "https://your-domain.com/cron?token=YOUR_CRON_TOKEN" > /dev/null
```
