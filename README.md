# School Management System

A multi-school management system by Sharebility. Each school has its own
portal (optionally on its own subdomain); parents have a portal of their own.

Built with Laravel 13, Filament 5 and MySQL. Runs on PHP 8.3+.

## Portals

| Address | For |
|---|---|
| `/admin` | Sharebility: add and manage schools, switch SMS on per school, see SMS usage |
| `/school/{school}` (or the school's subdomain) | School staff, each seeing what their role allows |
| `/parent` | Parents, signing in with their phone number |

## What schools can do

- **Students and admissions**: applications, admission, classes and streams, passport photos, parents and guardians
- **Staff and roles**: school admin, head teacher, bursar, secretary and teachers, each with their own permissions
- **Teaching**: each teacher's subjects and classes; teachers only see and mark their own classes
- **Fees**: fee structure per term, invoices, payments with receipts, balances, statements, fee reminders
- **Exams and report cards**: exams with weights, marks entry, grading scales (Primary D1–F9 with divisions, O-level A–E, A-level A–F with points), positions, comments, publishing, printable report cards
- **Attendance**: daily register per class or stream, attendance on report cards
- **Circulars**: to staff, to parents (all or chosen classes), with attachments, scheduled publishing, by SMS
- **SMS** (through Africa's Talking): payment receipts, fee reminders, circulars, report cards ready, parents' portal logins

## What parents see

Their children (across schools), published report cards, latest marks and
performance charts, attendance this term, fee balance and statement, and the
school's circulars.

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
