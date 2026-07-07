# Moderation

The Moderation feature in Indieinabox allows site administrators to review, approve, or delete incoming interactions (such as webmentions, comments, likes, reposts) before they are displayed publicly on the site.

## How it Works

When an external interaction is received (via Webmention or ActivityPub), it is processed by the `BackgroundWorker`.
By default, all incoming interactions are marked with the `status: pending` property in their frontmatter. They are stored in the `data/microsub/inbox/notifications/` directory as markdown files.

Interactions with a `status: pending` are filtered out of public views by `Helper::getInteractions()`, meaning they are not rendered on your static site pages.

## Admin Moderation Panel

Site administrators can access the moderation panel by navigating to `/admin/moderation` while logged in via IndieAuth.

The moderation panel displays a unified layout with other admin pages (Config, Micropub, Microsub) and shows a list of all interactions currently pending moderation.

For each pending interaction, you will see:
- The type of interaction (like, reply, repost, etc.)
- The content and author details
- Options to **Approve** or **Delete** the interaction

### Approving an Interaction

Clicking **Approve** updates the file's frontmatter to `status: approved`. Once approved, the interaction will be included on the public site the next time the site is built (or automatically if dynamic features are active).

### Deleting an Interaction

Clicking **Delete** permanently removes the notification markdown file from the filesystem.

## Legacy Interactions

Any interactions received prior to the introduction of the moderation feature (which do not have a `status` field in their frontmatter) are treated as `status: approved` for backwards compatibility, ensuring existing comments continue to appear on your site.
