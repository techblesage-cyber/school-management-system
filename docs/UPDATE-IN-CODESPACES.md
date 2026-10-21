# Adding an update zip in Codespaces

Use this whenever you receive an update zip (for example
`school-system-update-2026-10-21.zip`). Each step says where it goes:
**Terminal** means type it in the Codespaces terminal and press Enter.

## 1. Save where you are first

So you can always go back.

**Terminal:**

```bash
git add -A && git commit -m "Before update" ; git status
```

(If it says "nothing to commit", that's fine.)

## 2. Upload the zip

Drag the zip from your computer onto the **file list on the left** of
Codespaces, onto the top folder (`school-management-system`), not inside
`app` or any other folder.

## 3. Unpack it

**Terminal** (use your zip's real name):

```bash
unzip -o school-system-update-2026-10-21.zip && rm school-system-update-2026-10-21.zip
```

`-o` replaces the old copies of the files. Nothing else in your project is
touched: no `.env`, no `vendor`, no `composer.json`.

## 4. Update the database

**Terminal:**

```bash
php artisan migrate
```

## 5. Settings for the codespace (once)

These make background jobs (like SMS) run straight away in the codespace, and
keep SMS in "log only" mode so nothing is really sent:

**Terminal:**

```bash
sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/' .env
grep -q '^SMS_DRIVER=' .env || echo 'SMS_DRIVER=log' >> .env
php artisan optimize:clear && php artisan filament:assets
```

## 6. Run the tests

**Terminal:**

```bash
php artisan test
```

All should pass (the update notes say how many to expect).
If any fail, copy the red part and send it to Claude.

## 7. Try it

**Terminal:**

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Open the forwarded port 8000 (the **Ports** tab, globe icon).

To fill in a demo school with students, marks, report cards, fees, attendance
and circulars, open a **second terminal** (the **+** in the terminal panel) and run:

**Terminal:**

```bash
php artisan db:seed --class=DemoSeeder
```

Then sign in:

| Where | Login | Password |
|---|---|---|
| `/school/login` (head teacher) | `head@demo.test` | `password123` |
| `/school/login` (teacher) | `teacher@demo.test` | `password123` |
| `/school/login` (bursar) | `bursar@demo.test` | `password123` |
| `/school/login` (secretary) | `secretary@demo.test` | `password123` |
| `/parent` (parent of 2 children) | phone `0700 000 001` | `parent123` |
| `/admin` | your own super admin email | your password |

The demo school only goes in a codespace, never on the live server. To remove
it, delete "Demo Secondary School" in `/admin` → Schools.

## 8. Save to GitHub

If an earlier push failed with a **Git LFS** message, run this once first
(it removes the LFS hooks the repository doesn't use):

**Terminal:**

```bash
rm -f .git/hooks/pre-push .git/hooks/post-checkout .git/hooks/post-commit .git/hooks/post-merge
```

Then:

**Terminal:**

```bash
git add -A && git commit -m "Update: exams, report cards, attendance, circulars, parents' portal, SMS" && git push
```

## If something goes wrong

To undo the update completely and go back to step 1:

**Terminal:**

```bash
git reset --hard && git clean -fd
```

(`migrate` changes can be undone with `php artisan migrate:rollback` before that.)
