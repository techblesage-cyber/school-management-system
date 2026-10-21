# Putting the system on the cPanel server

This guide puts the School Management System on a cPanel server **without SSH**.
Everything is done in the cPanel website (File Manager, MySQL Databases, Cron
Jobs) plus one command in GitHub Codespaces to build the upload.

The examples use `sms.sharebility.net` as the address and `cpuser` as the cPanel
username. Replace both with your own.

---

## What you end up with

| Address | Who uses it |
|---|---|
| `https://sms.sharebility.net` | Home page with the buttons below |
| `https://sms.sharebility.net/admin` | Sharebility: add schools, switch SMS on, see SMS usage |
| `https://sms.sharebility.net/school/login` | School staff (head teacher, bursar, teachers, ...) |
| `https://sms.sharebility.net/parent` | Parents (sign in with phone number) |
| `https://stmarys.sms.sharebility.net` | A school's own address (optional, step 10) |

---

## 1. Set PHP to 8.3

1. cPanel → **MultiPHP Manager** → tick the domain → choose **PHP 8.3** → Apply.
2. cPanel → **Select PHP Version** (or **MultiPHP INI Editor** / **PHP Extensions**,
   depending on the host) and make sure these are ticked:
   `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `zip`, `bcmath`, `ctype`,
   `openssl`, `tokenizer`, `xml`, `curl`, `gd`.
3. Optional but helpful in **MultiPHP INI Editor**:
   `upload_max_filesize = 8M`, `post_max_size = 10M`, `memory_limit = 256M`.

> If you can't find PHP 8.3, ask the hosting support to enable it. The system
> needs PHP 8.3 or newer.

## 2. Create the database

cPanel → **MySQL Database Wizard**:

1. Database name: `sms` (cPanel makes it `cpuser_sms`).
2. User: `sms` (cPanel makes it `cpuser_sms`) with a strong password. Write it down.
3. Give the user **ALL PRIVILEGES** on the database.

## 3. Build the upload in Codespaces

**Terminal** (in Codespaces):

```bash
php artisan app:package
```

It takes a minute or two and prints something like
`Built /workspaces/school-management-system/deploy/school-system-2026-10-22-0930.zip`.
In the Codespaces file list, open the `deploy` folder, right-click the zip →
**Download**.

The zip has everything the server needs (including the `vendor` folder) and
nothing from your codespace's `storage` or `.env`, so it never overwrites
uploaded photos or settings on the server.

## 4. Upload and extract

cPanel → **File Manager**:

1. In your home folder (`/home/cpuser`), create a folder called `sms`.
   **Not inside `public_html`.** That keeps the code and settings private.
2. Open `sms`, click **Upload**, choose the zip.
3. Back in File Manager, right-click the zip → **Extract** → into `/home/cpuser/sms`.
4. Delete the zip afterwards.

You should now see folders like `app`, `public`, `vendor` inside `/home/cpuser/sms`.

## 5. Point the address at the `public` folder

cPanel → **Domains** → **Create A New Domain** (or edit the existing one):

- Domain: `sms.sharebility.net`
- **Untick** "Share document root"
- Document root: `sms/public`

If the domain already exists with another document root, edit it with
**Manage** → change the document root to `/home/cpuser/sms/public`.

Then cPanel → **SSL/TLS Status** → **Run AutoSSL** so the address works with `https`.

## 6. Create the settings file (.env)

1. In File Manager, open `/home/cpuser/sms`. Click **Settings** (top right) and
   tick **Show Hidden Files**.
2. Right-click `.env.production.example` → **Copy** → name it `.env`.
3. Right-click `.env` → **Edit** and fill in:

| Setting | Value |
|---|---|
| `APP_KEY` | Run `php artisan key:generate --show` in Codespaces and paste the whole result (starts with `base64:`) |
| `APP_URL` | `https://sms.sharebility.net` |
| `DB_DATABASE` | `cpuser_sms` |
| `DB_USERNAME` | `cpuser_sms` |
| `DB_PASSWORD` | the password from step 2 |
| `SCHOOL_TENANT_DOMAIN` | leave **empty** for now (see step 10) |

Save. **Keep the APP_KEY the same forever.** Changing it signs everyone out and
makes some saved data unreadable.

## 7. Run the installer once (from Cron Jobs)

Without SSH, commands run through cPanel → **Cron Jobs**. First find the right
PHP. Add this cron job with the time set to **Once Per Minute**:

**Cron command:**

```bash
cd /home/cpuser/sms && /usr/local/bin/php -v > php-version.txt 2>&1
```

Wait a minute, then open `php-version.txt` in File Manager. If it says
**PHP 8.3** (or newer), `/usr/local/bin/php` is the one to use. If it says an
older version, try one of these instead and check again:

- `/opt/cpanel/ea-php83/root/usr/bin/php`
- `/opt/alt/php83/usr/bin/php`

Once you know the PHP path, **edit that cron job** to:

**Cron command:**

```bash
cd /home/cpuser/sms && /usr/local/bin/php artisan app:install >> storage/logs/install.log 2>&1
```

Wait a minute, open `storage/logs/install.log`. It should end with
`Done. The system is up to date.` Then **delete this cron job** (it is safe if it
runs a few times, but it shouldn't keep running).

`app:install` updates the database, links the photos folder, publishes the
Filament files and caches the settings.

## 8. Create your admin login (once)

Add a cron job, **Once Per Minute**:

**Cron command:**

```bash
cd /home/cpuser/sms && /usr/local/bin/php artisan app:super-admin you@sharebility.net --name="Your Name" --password="a-strong-password" >> storage/logs/install.log 2>&1
```

After a minute, check `install.log` says `... can now use the admin panel.`
Then **delete this cron job straight away** (it contains your password), sign in
at `https://sms.sharebility.net/admin` and change the password from your profile.

## 9. The permanent cron job (keep this one)

The system sends SMS and other background work through a queue, which this
cron job processes every minute. Add it and **leave it there**:

**Cron command** (Once Per Minute):

```bash
cd /home/cpuser/sms && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

## 10. Schools on their own subdomains (optional)

Without this, each school uses `https://sms.sharebility.net/school/stmarys`.
With it, they get `https://stmarys.sms.sharebility.net`.

1. cPanel → **Domains** → **Create A New Domain**: `*.sms.sharebility.net`,
   document root `sms/public` (the same folder).
2. Make sure there is a DNS record for `*.sms.sharebility.net` pointing to the
   server (cPanel → **Zone Editor**; it is usually added automatically).
3. cPanel → **SSL/TLS Status**: check `*.sms.sharebility.net` is covered.
   AutoSSL can only issue wildcard certificates on some hosts. If it is not
   covered, ask the hosting support for a wildcard SSL certificate.
4. Only once `https://anything.sms.sharebility.net` opens without a certificate
   warning: edit `.env`, set `SCHOOL_TENANT_DOMAIN=sms.sharebility.net`, and run
   `app:install` once more (step 7).

## 11. SMS with Africa's Talking (when ready)

1. Create an account at africastalking.com, then an app for Uganda. Note the
   **username** and generate an **API key**. Ask them for a **sender ID**
   (e.g. `SHAREBILITY`); until it's approved, leave it empty.
2. Top up the account (SMS are charged per message).
3. Edit `.env`:

   ```
   SMS_DRIVER=africastalking
   AFRICASTALKING_USERNAME=your-username
   AFRICASTALKING_API_KEY=your-api-key
   AFRICASTALKING_SENDER_ID=SHAREBILITY
   ```

4. Run `app:install` once (step 7) so the new settings are picked up.
5. In `/admin` → **Schools** → edit a school → switch **SMS to parents** on.
   Each school then chooses in its **School profile** whether payments send
   receipts automatically.

Every SMS is listed in `/admin` → **SMS** (all schools) and in each school's
**SMS log**, with its status and cost.

With `SMS_DRIVER=log` (the default) nothing is really sent: messages are only
recorded, which is useful for trying things out.

---

## Updating to a new version

1. In Codespaces: `php artisan app:package`, download the new zip.
2. File Manager: upload it to `/home/cpuser/sms`, **Extract** (say yes to
   overwriting), delete the zip.
3. Run `app:install` once from Cron Jobs (step 7), then delete that cron job.

Your `.env`, uploaded photos, logos and circular attachments are kept: they
live in `.env` and `storage`, which the zip never contains.

## Backups

- cPanel → **Backup** → download a **MySQL database** backup regularly
  (weekly at least, and always before an update).
- Also download `/home/cpuser/sms/storage/app` (photos, logos, attachments)
  and keep a copy of `.env`.

## When something goes wrong

| Problem | What to do |
|---|---|
| "500 Server Error" | Open `storage/logs/laravel-YYYY-MM-DD.log` in File Manager; the last lines say why. |
| Changed `.env` but nothing changed | Settings are cached. Run `app:install` again (step 7). |
| Page has no styling | Check `APP_URL` starts with `https://` and run `app:install` again. |
| Photos or logos don't show | Look in `install.log`. If it says storage links aren't allowed, add the `PUBLIC_DISK_ROOT=...` line it gives you to `.env` and run `app:install` again. |
| SMS stay "queued" | The permanent cron job (step 9) is missing or uses the wrong PHP path. |
| "Database connection refused" | Check `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`; the names include the `cpuser_` prefix. |
| Forgot the /admin password | Repeat step 8 with your email and a new password: it sets the new password. Delete the cron job straight after. |
