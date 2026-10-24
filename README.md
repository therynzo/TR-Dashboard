# TR Dashboard

**A modern, customizable PHP dashboard for Pterodactyl server hosting.**

TR Dashboard is a Laravel-based hosting panel with user accounts, server management, credits, redeem codes, administration tools, and optional integrations.

## ✨ Features

- Modern dark dashboard with customizable branding.
- User registration, login, and profile management.
- Pterodactyl Application API integration.
- Server creation, renewal, and lifecycle management.
- Credit system and redeem codes.
- Admin management for users, servers, and settings, including confirmed user deletion with safeguards.

- Optional Discord and Google authentication.
- Optional Linkvertise rewards.
- VPS installation and update script.

Optional integrations can be configured by the administrator and are disabled by default where applicable.

## 🚀 Installation

Run this command in your VPS terminal:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/therynzo/TR-Dashboard/main/install.sh)
```

### Installation steps

1. Connect to your VPS through SSH as root or a user with `sudo` privileges.
2. Copy and paste the command above into the terminal.
3. Select an option from the installer menu.
4. For a new installation, choose **1 — Install TR Dashboard**.
5. Enter your dashboard URL, database settings, and administrator account details.
6. Follow the prompts to complete installation.

After installation, open your configured dashboard domain and sign in with your administrator account.

**Security note:** This command downloads and executes the installer from GitHub. Review `install.sh` before running it on a production VPS.

## 🎬 Cinematic login intro

The login and registration screens show a full-viewport video intro before revealing the account form. It plays once per browser-tab session, with a skip control and a sound button for browsers that block audible autoplay. The logo-free blue wallpaper (`payload/public/tr-auth-wallpaper.svg`) is blurred behind the glass-style authentication card. Place the supplied intro video at `payload/public/tr-login-intro.mp4` before deploying the intro.

## ⚙️ Configuration

After installation, sign in as an administrator and configure your integrations from the dashboard.

You can manage Pterodactyl API settings, branding, default credits and server slots, authentication providers, Linkvertise rewards, users, and other administrative options.

Discord, Google, and Linkvertise integrations require their respective provider credentials and configuration before they can be enabled.

## 🛠️ Customization

You can customize the dashboard's appearance, branding, layout, and functionality to suit your hosting business.

When publishing or sharing a modified version, retain the original author attribution and clearly identify your modifications.

## 📜 Copyright & Attribution

**Officially made by TheRynzo**

Copyright © 2026 TheRynzo. All rights reserved.

TR Dashboard is created and maintained by **TheRynzo**.

You are encouraged to customize the project for your own use. When redistributing the source code or publishing a modified version, preserve the original copyright notice and creator credit. Do not remove the original attribution, misrepresent the project's authorship, or claim the original work as your own.

Please respect the developer's work and give credit where it is due.

**Official creator:** TheRynzo  
**Repository:** https://github.com/therynzo/TR-Dashboard


### Pterodactyl deployment defaults

In **Admin → Settings → Pterodactyl API**, select and save the default location, node, software category, Egg, and resource limits. Docker image, startup command, and Egg environment variables are taken from the selected Egg automatically; these are not manual admin fields. Customer server creation uses the administrator-selected default node/location, displays software choices, and selects a free allocation automatically. Required Egg variables are loaded from the Application API relationships, with safe defaults for common Minecraft Java values such as `SERVER_JARFILE` and `BUILD_NUMBER` when an Egg leaves them blank.
