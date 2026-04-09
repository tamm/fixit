# Fixit

WordPress migration toolkit. Fix broken URLs when moving WordPress between domains or directories.

## The Problem

WordPress stores absolute URLs in the database — in options, post content, widget settings, menus, and plugin data. When you move a site to a new domain or directory, those URLs break everything. A simple SQL `REPLACE()` doesn't work because WordPress also stores **serialized PHP data** where string lengths are encoded — changing a URL inside serialized data corrupts it.

Fixit handles all of this: it walks every table and column, does plain replacements on normal data, and properly unserializes → replaces → reserializes structured data.

## Three Modes

### 1. Prepare Migration (plugin)

Install the plugin **before** you move. Go to **Tools → Fixit**, enter your target domain (or leave it blank for auto-detection), and click **Prepare Migration**. Fixit generates a one-click migration URL.

After copying your files and database to the new location, visit the migration URL. Fixit fires before WordPress tries to redirect, fixes every URL in the database, and your site works on the new domain.

### 2. Emergency Fix (standalone)

Already moved and everything is broken? Can't access wp-admin?

1. Connect via FTP or SSH.
2. Copy `fixit-emergency.php` from the plugin directory into your WordPress root (next to `wp-config.php`).
3. Visit `https://your-new-domain.com/fixit-emergency.php`.
4. Verify the auto-detected old and new URLs, click **Fix It**.
5. Click **Delete this file** to clean up.

The emergency script uses WordPress's `SHORTINIT` mode — it gets database access without booting the full stack, so it works even when the site is completely broken.

### 3. URL Audit (plugin)

Migrated a while ago and something is still off? Go to **Tools → Fixit → URL Audit**. Scan the database for stale URLs from old domains. The audit checks options, posts, postmeta, and comments, then shows every domain found with occurrence counts.

Use the **Search & Replace** form to fix specific URLs, or clean up remnants from previous migrations.

## Installation

### As a WordPress plugin (recommended)

1. Download or clone this repository into `wp-content/plugins/fixit/`.
2. Activate **Fixit** in the WordPress admin under **Plugins**.
3. Find it under **Tools → Fixit**.

### Emergency only

If you just need the emergency script, copy `fixit-emergency.php` to your WordPress root and open it in a browser. No plugin installation needed.

## Security

- The plugin admin page requires `manage_options` capability (administrator).
- All forms are protected with WordPress nonces (CSRF protection).
- All output is escaped (XSS protection).
- All queries use `$wpdb->prepare()` (SQL injection protection).
- The migration URL uses a cryptographically random 32-character token.
- The emergency script uses a file-based nonce and includes self-delete functionality.
- **Always delete `fixit-emergency.php` after use** — it provides direct database access.

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.5+ / MariaDB 10.0+

## History

| Version | Date | Notes |
|---------|------|-------|
| 2.0.0 | 2026 | Complete rewrite as WordPress plugin. Three operating modes. Modern PHP, security hardening, proper serialization handling. |
| 1.1 | October 2017 | Revised logging, visual updates. |
| 1.0 | February 2011 | Original standalone script. |

## License

MIT — see [LICENSE.md](LICENSE.md).

## Author

Tamm Sjödin
