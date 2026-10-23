# TR Dashboard — PHP / Laravel Panel

A dark, purple-accented hosting dashboard inspired by the supplied screenshots, branded **TR Dashboard** with **“Officially made by TheRynzo”** in the footer. This package installs as a Laravel PHP application and integrates with the **Pterodactyl Application API**.

## Quick install (GitHub or local ZIP)

### Install directly from GitHub

After the updated `install.sh` is committed to the repository's `main` branch, run this on a Linux VPS:

```bash
curl -fsSL https://raw.githubusercontent.com/therynzo/TR-Dashboard/main/install.sh -o /tmp/tr-dashboard-install.sh
sudo bash /tmp/tr-dashboard-install.sh
```

The installer detects that it was launched without `payload/`, installs Git if needed, fetches `https://github.com/therynzo/TR-Dashboard.git` at `/opt/TR-Dashboard`, safely updates the verified clean checkout to the fetched `main` commit (including after a GitHub force-update), then runs the repository copy with the full payload. It will not overwrite a non-Git directory or a checkout with local changes; choose a new repository path by setting `TR_DASHBOARD_REPO_DIR` if necessary.

### Install from the ZIP

1. Upload and extract `TR-Dashboard-Package.zip` on the VPS.
2. Run:

```bash
chmod +x install.sh
bash -n install.sh
./install.sh --check
sudo ./install.sh
```

`--check` validates the Bash syntax and confirms that the package contains its essential payload files without starting an installation. The full installer also checks the copied PHP files, validates Laravel routes after migration, and checks the local HTTP response after starting the service.

3. The purple installer menu supports `1` Install, `2` Update existing dashboard (backs up source, preserves `.env`/database and runs migrations), `3` Create an additional admin, `4` Delete the dashboard (requires backup plus the exact confirmation phrase), and `5` Exit. For install, enter the dashboard URL (default `http://localhost:1000`), install path, database choice, admin email, admin username, and admin password twice. The CLI installer does not ask for Pterodactyl credentials; configure the panel URL and Application API key after signing in at **Admin → Pterodactyl API**.
4. Review the installation summary and enter `Y` to continue.

The installer creates a clean Laravel 12 project, copies the TR Dashboard PHP files into it, sets `.env`, generates the Laravel application key, runs migrations, initializes the default settings/admin, clears caches, creates the public storage link, configures the scheduled task, and starts the web service on **port 1000**. On a root-managed system it creates a `tr-dashboard` systemd service and `/etc/cron.d/tr-dashboard` scheduler entry.

The primary local address is `http://localhost:1000`. On a remote VPS, use `http://YOUR_SERVER_IP:1000` if the firewall permits the port. For a public domain with HTTPS, use the included `deploy/nginx-proxy.conf.example` to reverse proxy your domain to `127.0.0.1:1000`, then enable TLS with Certbot/ACME. The bundled Artisan server is a quick bootstrap/small deployment option; for higher-traffic production, use Nginx/Apache + PHP-FPM with the Laravel `public/` directory as the document root.

## Update branding on an existing installation

If TR Dashboard is already installed, extract this package and run the included branding updater with the actual Laravel application directory (the default is `/var/www/tr-dashboard`):

```bash
chmod +x update-branding.sh
sudo ./update-branding.sh /var/www/tr-dashboard
```

The updater backs up each replaced branding file, installs the provided logo in `public/tr-logo.png`, updates shared dashboard/auth layouts and the branding settings preview, then runs `php artisan optimize:clear`. It does not migrate, reset, or delete your database. If your panel is installed elsewhere, pass that directory instead.

## Included features

- Registration and login with hashed dashboard passwords, session regeneration, CSRF protection, and admin-only routes.
- Dashboard overview with credit balance, server count/slots, server status, renewal timing, quick actions, and recent activity.
- User accounts automatically created in Pterodactyl at registration **when the Application API URL and key are already configured**. The same email, username, and password are sent to Pterodactyl during registration. The password is never stored in plaintext by TR Dashboard.
- Automatic Pterodactyl server provisioning through the Application API, with configurable default Egg, allocation, Docker image, startup command, environment JSON, memory, disk, CPU, swap, databases, allocations, and backups.
- Credit wallet and transaction history; configurable default credits, default server slots, server creation cost, and renewal cost.
- Server renewal: default lifetime is 48 hours. Once expired, the scheduler suspends the Pterodactyl server; the user can renew it during the grace period. The default grace period is seven days **after suspension**, then the scheduler permanently deletes the Pterodactyl server.
- Admin user management (username, email, credits, and slots), user/Pterodactyl profile sync for linked users, server inventory and suspend/delete actions.
- Admin-generated single or batch redeem codes awarding credits, extra server slots, or both, with use limits and optional expiry.
- Support tickets with categories, priorities, client/staff replies, status management, and an admin ticket queue.
- Admin settings separated into distinct sidebar categories: Brand & appearance, Pterodactyl API (separate Save and Test actions), Credits & Defaults, Authentication, Linkvertise Rewards, plus Users, All Servers, Ticket List, Redeem Codes and Activity Logs. The Store navigation has been removed and replaced with Credits.
- Profile page to view/update email and username and manage passwords. OAuth-created accounts may set a first local password; subsequent changes require the current password.
- Optional Discord and Google OAuth registration/login, **disabled by default**. OAuth secrets are encrypted at rest and identity providers must return verified emails.
- Optional Linkvertise reward integration, **disabled by default**, with configurable reward credits and daily limits. Credits are granted only after server-side anti-bypass verification succeeds, and each provider hash is recorded by a unique fingerprint to prevent replay claims.
- Admin activity logs, configurable brand name, the supplied white flower logo across dashboard/auth/footer/favicon, optional logo upload, accent color, announcement, support Discord URL, and Pterodactyl API settings.

## Connect to Pterodactyl

1. In Pterodactyl, create an **Application API key** with permissions sufficient to create/manage users and servers. Use the Application API key (usually prefixed `ptla_`), not a Client API key.
2. In TR Dashboard, open **Admin → Settings** and set the panel URL, key, default Egg ID, and default Allocation ID. Set the environment JSON to match that Egg. Save, then click **Test Pterodactyl API**.
3. Make sure the selected Allocation is available for new servers and the configured resource limits fit your node. The default environment values are an example for a Java server and may need changing to match your selected Egg.
4. Configure Pterodactyl first, **before clients register**, so their Pterodactyl user can be created with their selected dashboard password. For privacy and security, TR Dashboard does not retain the user's plaintext password to sync accounts later.

The application stores newly entered API keys and OAuth/Linkvertise secrets encrypted using Laravel's `APP_KEY`. Older plaintext Pterodactyl API keys remain readable for update compatibility and can be re-saved from Admin → Pterodactyl API to encrypt them. Keep `.env` private and keep the same `APP_KEY` when moving the application; changing it can make encrypted settings unreadable.

## Optional Discord / Google login and Linkvertise rewards

Open **Admin → Authentication** and configure each provider's OAuth Client ID and Client Secret. Copy the exact callback URL shown on that page into the provider console, save the credentials, then turn on its separate switch. Both providers default to off and their buttons are not shown on login/register until enabled and configured. Google and Discord must return a verified email. Keep your Laravel `APP_URL` set to the public HTTPS dashboard URL so OAuth callback URLs match exactly.

Open **Admin → Linkvertise Rewards** to enable the optional reward button, configure the HTTPS Linkvertise target, anti-bypass token, credits per completion, and daily reward limit. This defaults to off. Set Linkvertise's Target Link destination to the callback displayed in the admin page and enable its anti-bypass check; the application verifies returned hashes server-side before adding any credits. Refer to [Linkvertise's official anti-bypass documentation](https://publisher.linkvertise.com/documentations/Anti_Bypass_Documentation.pdf) for provider setup.

## Renewal scheduler

The installer sets a scheduler entry to run Laravel's task scheduler every minute. To run the lifecycle task manually:

```bash
cd /var/www/tr-dashboard
php artisan tr:server-lifecycle
```

To manually run Laravel setup/cache commands:

```bash
php artisan migrate --force
php artisan optimize:clear
```

Do not remove the scheduler entry if automatic suspension/deletion is required.

## Defaults

| Setting | Default |
| --- | --- |
| Default new-user credits | 100 |
| Default server slots | 1 |
| Server create cost | 0 credits |
| Renewal cost | 0 credits |
| Initial server lifetime | 48 hours (2 days) |
| Grace after suspension | 7 days |
| Web service port | 1000 |
| Database | SQLite for quick setup (MySQL/MariaDB optional) |

All core defaults are editable in **Admin → Credits & Defaults**. Existing users are not retroactively changed when the new-user defaults are changed.

## Important deployment notes

- Install into a clean directory; the script deliberately refuses to overwrite a non-empty install target.
- The install process requires PHP 8.2+ and Composer. It uses Composer to fetch Laravel and its dependencies, so the VPS needs outbound internet access during installation.
- SQLite is convenient for a first install. For a larger multi-user deployment, use MySQL/MariaDB and a production Nginx/Apache + PHP-FPM setup.
- Back up your database and `.env`. Treat the Pterodactyl API key, Laravel app key, redeem codes, and admin password as secrets.
- Server create/renewal/delete calls change real Pterodactyl resources. Test with a non-critical node and allocation before offering the dashboard to customers.
- This package provides account credits and redeem codes, not a payment gateway. Credits must be granted by an administrator or redeem code until a payment integration is added.
- No Pterodactyl credentials were included in this source package. Configure your own panel URL and API key.

## Fixing a service startup error (`status=200/CHDIR`)

If installation completes but systemd reports `status=200/CHDIR`, Laravel and the database may already be installed. Do not delete the application folder or database. The service user may lack permission to traverse a parent directory such as `/var/www`. Run the repair script from this package:

```bash
chmod +x TR-Dashboard-repair-service.sh
sudo ./TR-Dashboard-repair-service.sh /var/www/tr-dashboard
```

`TR-Dashboard-complete-repair.sh` is provided as an alias for older install instructions that already reference that filename.

The repair script checks for `artisan` and `.env` before making changes, grants the `trdashboard` service user read/traverse ACLs throughout the Laravel tree (including root-owned `bootstrap/` directories), fixes Laravel runtime directory ownership and write permissions, restarts the existing service, and prints recent logs if the health check still fails. It does not rerun migrations or replace the database.

#   T R - D a s h b o a r d  
 