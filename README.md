# School Management System

A multi-school management system by Sharebility. Each school has its own
portal (optionally on its own subdomain); parents have a portal of their own.

Built with Laravel 13, Filament 5 and MySQL. Runs on PHP 8.3+.

## Portals

| Address | For |
|---|---|
| `/admin` (a private address on the live server, `ADMIN_PATH`) | Sharebility only: add and manage schools, switch SMS on per school, see SMS usage. Never linked from school or parent screens. |
| `/school/{school}` (or the school's subdomain) | School staff, each seeing what their role allows |
| `/parent` | Parents, signing in with their phone number |

## Easy to use

- An iPhone-style "glass" look, the same on phones, tablets, laptops and desktops
- A home screen of big app tiles for each person's daily jobs (only the ones their role can open), and a tab bar along the bottom on phones
- First logins made automatically and emailed (and for parents sent by SMS or printed on a login slip); everyone chooses their own password at first sign-in; staff can reset a forgotten password by email

## What schools can do

- **Students and admissions**: applications, admission, classes and streams, passport photos, parents and guardians
- **Staff and roles**: school admin, head teacher, bursar, secretary and teachers, each with their own permissions
- **Teaching**: each teacher's subjects and classes; teachers only see and mark their own classes
- **Fees**: fee structure per term (one fee for all classes or different fees per class), invoices, payments with receipts, balances, statements, fee reminders
- **Fees by class**: S.1 East, S.1 West, S.2... each with its students' fees, payments and balances, and a Pay button on every row
- **Exams and report cards**: exams with weights, marks entry, grading scales (Primary D1–F9 with divisions, O-level A–E, A-level A–F with points), positions, comments, publishing, printable report cards
- **Attendance**: daily register per class or stream with one-tap Present / Absent / Sick / Late / Permission buttons, attendance on report cards
- **Circulars**: to staff, to parents (all or chosen classes), with attachments, scheduled publishing, by SMS
- **SMS** (through Africa's Talking): payment receipts, fee reminders, circulars, report cards ready, parents' portal logins

## What parents see

Their children, published report cards, latest marks and performance charts,
attendance this term, fee balance and statement, and the school's circulars.
Each school gives a parent its own separate login, even with the same phone
number, and its portal shows only that school.

## Guides

- [Adding an update zip in Codespaces](docs/UPDATE-IN-CODESPACES.md)
- [Putting the system on the cPanel server](docs/DEPLOY-CPANEL.md)

## Useful commands

| Command | What it does |
|---|---|
| `php artisan test` | Runs all the tests |
| `php artisan db:seed --class=DemoSeeder` | Adds a demo school to try everything (not on the live server) |
| `php artisan app:super-admin you@example.com` | Gives someone access to `/admin` (creates them if needed) |
| `php artisan app:package` | Builds the zip to upload to the server |
| `php artisan app:install` | On the server, after each upload: database, storage, caches |
