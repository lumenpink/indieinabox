# Interactive Public Demo Sandbox & Automated Reset

IndieInABox includes native support for running an isolated, interactive public demo sandbox instance (e.g. `demo.indieinabox.2lp.in`). This environment allows prospective users, developers, and evaluators to explore the full capabilities of IndieInABox—including the Web Admin deck (`/admin/config`), the Micropub posting client, and the Microsub feed reader—with automatic, periodic rollback to a pristine "golden seed" state.

---

## 🎯 Architecture & Sandbox Lifecycle

```text
               +---------------------------------------------+
               |        Forgejo CI/CD (deploy-demo.yml)       |
               +---------------------------------------------+
                                      │
                                      ▼
               +---------------------------------------------+
               |  Docker Host (obitwo / stacks/demo)         |
               |                                             |
               |   [Caddy Edge Reverse Proxy: 80/443]        |
               |                 │                           |
               |                 ▼                           |
               |   [indieinabox_demo Container]              |
               |    ├── /app/data/.demo_seed/ (Golden Seed)  |
               |    ├── /app/data/indieinabox.sqlite         |
               |    └── /app/content/ (Sample posts)         |
               +---------------------------------------------+
                                      │
                         Periodic Reset Cron (60m)
                                      ▼
               +---------------------------------------------+
               |           php indieinabox.php demo reset    |
               |   1. Closes active DB connections           |
               |   2. Restores SQLite from golden seed       |
               |   3. Restores content/ from golden content  |
               |   4. Runs full static SiteBuilder build     |
               +---------------------------------------------+
```

---

## 🔑 Default Credentials & Access

The public sandbox is initialized with standard demonstration credentials:

- **Site URL**: `https://demo.indieinabox.2lp.in`
- **Admin Panel**: `https://demo.indieinabox.2lp.in/admin/config`
- **Micropub Client**: `https://demo.indieinabox.2lp.in/micropub/client`
- **Microsub Reader**: `https://demo.indieinabox.2lp.in/microsub/reader`
- **Admin Account**: `admin`
- **Admin Password**: `demo123`
- **Reset Interval**: Every 60 minutes (3600 seconds)

A visual banner is displayed at the top of the interface informing visitors that changes are temporary and will be rolled back on the next reset cycle.

---

## 🛠️ CLI Demo Management

IndieInABox provides a dedicated `demo` command suite for initializing and managing the sandbox:

### 1. Seeding the Demo (`demo seed`)

Initializes the golden seed state with rich sample data:
- Sets site name, author, and public domain (`demo.indieinabox.2lp.in`).
- Sets admin password to `demo123`.
- Generates curated sample posts:
  - Rich articles with embedded photos and markdown formatting.
  - Short notes with hashtags (`#indieweb`, `#fediverse`).
  - Digital garden entries with different maturity stages (`sprout`, `seedling`, `tree`).
- Subscribes default feeds in the Microsub reader (e.g. IndieWeb blog, W3C feeds).
- Backs up the fresh database and content directories into `data/.demo_seed/`.

```bash
php indieinabox.php demo seed --fqdn https://demo.indieinabox.2lp.in --password demo123
```

### 2. Resetting the Demo (`demo reset`)

Restores the database and content directories from `data/.demo_seed/` and triggers a full static rebuild:

```bash
php indieinabox.php demo reset
```

### 3. Checking Demo Status (`demo status`)

Reports whether demo mode is active, the time of the last reset, and the next scheduled reset:

```bash
php indieinabox.php demo status
```

---

## ⏱️ Automated Reset Mechanisms

The periodic reset can be triggered via two complementary mechanisms:

1. **Internal Container Cron**:
   When `DEMO_MODE=true` is set in the environment, the entrypoint background process checks elapsed time and invokes `demo reset` every `DEMO_RESET_INTERVAL` (default: 3600 seconds).

2. **System Cron / External Trigger**:
   Host systems can schedule an hourly cron execution:
   ```cron
   0 * * * * docker exec indieinabox_demo php /app/indieinabox.php demo reset > /dev/null 2>&1
   ```

---

## 🔒 Security & Sandbox Isolation

To protect the host system while allowing uninhibited user testing:
- **Ephemeral Sandbox**: All uploads, newly created users, and configuration changes are wiped during reset.
- **Docker Isolation**: The container runs under a non-root user (`1000:1000`) on an isolated Docker bridge network.
- **Strict Media Limits**: Maximum media upload sizes are enforced to prevent disk exhaustion.
