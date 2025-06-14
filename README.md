# 📦 Listmonk Backup & Cleanup System

Automated backup and cleanup system for your [Listmonk](https://listmonk.app) instance.  
Built in PHP and designed to run securely **outside the web root**, triggered via `cron`.

---

## 🧰 Features

- Exports Listmonk data (subscribers, campaigns, templates, etc.) to timestamped folders
- Sends summary reports via email
- Retains backups for a configurable number of days
- Cleans up old backups automatically
- Configuration via a simple `config.ini` file
- Runs safely via cronjobs — **not web-accessible**

---

## 📁 Files Overview

| File                | Purpose                                                                 |
|---------------------|-------------------------------------------------------------------------|
| `ListmonkAPI.php`   | Core logic for exporting and backing up Listmonk data                   |
| `CleanupBackups.php`| Deletes old backups based on retention setting                          |
| `config.ini`        | Configuration for backup behavior and email reporting                   |

---

## ⚙️ Configuration

Create a `config.ini` file in the same directory:

```ini
# GENERAL SETTINGS
LISTMONK_URL=https://example.com
LISTMONK_USER=admin
LISTMONK_PASS=password
BACKUP_RETENTION_DAYS=30

# MAIL SETTINGS
MAIL_TO=log@example.com
MAIL_FROM=system@example.com
MAIL_SUBJECT=Listmonk Backup Report

# SMTP SETTINGS
SMTP_HOST=localhost
SMTP_USER=system@example.com
SMTP_PASS=password
SMTP_PORT=465
```

> 🔐 **Important:** Store `config.ini` in a secure location with correct permissions (`chmod 600` recommended). Do **not** place this project inside your `www/` or public web root.

---

## ⏱️ Cronjob Setup

Add these lines to your crontab (`crontab -e`) to automate backup and cleanup:

```bash
# Run backup every night at 2:00 AM
0 2 * * * /usr/bin/php /path/to/ListmonkBackup.php

# Clean up old backups every night at 3:00 AM
0 3 * * * /usr/bin/php /path/to/CleanupBackups.php
```

> ✅ Make sure `/usr/bin/php` points to your correct PHP binary and adjust the path to your script directory.

---

## ✅ Security Best Practices

- Store everything **outside** of public web directories.
- Protect your `config.ini` with strict permissions.
- Use encrypted SMTP and strong passwords for mail settings.
- Restrict cronjob access to authorized users only.

---

## 📄 License

MIT License — free for personal or commercial use. Contributions welcome!

---

## 🙋 Need Help?

Open an issue or contact the repository maintainer for support.
