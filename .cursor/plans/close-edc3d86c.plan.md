<!-- edc3d86c-c61f-4ada-83b2-e9250ec6d511 329871b1-8e46-4839-ab2f-15d55b309a85 -->
# Gap-Closure Plan for Timetable Objectives (Admin-only scope)

## Scope

Implement missing pieces for this release with admin-only access: authentication (single admin), reporting/analytics, and academic progression. Reuse existing stream-aware model, GA generation, and validation.

## Key Files To Leverage

- `includes/stream_manager.php`, `includes/stream_validation.php`
- `ga/` (generation, conflict analysis, utilization stats)
- `generate_timetable.php`, `schedule_functions.php`

## Changes

### 1) Authentication (Admin-only)

- Single admin login only; no roles beyond admin.
- Middleware: `includes/auth.php` with `requireAdmin()` and `redirectIfAuthenticated()`.
- Login/logout pages: `auth/login.php`, `auth/logout.php` (CSRF-protected).
- DB migration: `migrations/2025_10_admin_user.sql` with `users(id, username, password_hash, is_admin, is_active, created_at)`.
- Protect all pages (`*.php`) behind `requireAdmin()` except `index.php`, `auth/*`, and static assets.

### 2) Reporting & Analytics

- Room utilization report: `reports/room_utilization.php` + `api/report_room_utilization.php` using `timetable` aggregation and GA stats per stream/semester.
- Lecturer workload report: `reports/lecturer_workload.php` + `api/report_lecturer_workload.php` (teaching loads, hours per week, clash counts).
- Scheduling performance: `reports/scheduling_performance.php` showing conflict counts, fill rates, GA runtime; CSV/PDF export.

### 3) Academic Progression

- Year advancement tool: `progression/advance_year.php` (promote classes, rollover `academic_year`, archive old timetable versions) with dry-run and summary.
- Graduation processing: `progression/graduation_process.php` (mark final-year classes completed, deactivate where needed).
- Intake planner: `progression/intake_planner.php` (wizard to create classes/courses for a new cohort per stream, integrating with `classes.php`/`courses.php`).

### 4) Security & Data Integrity

- Ensure CSRF on remaining forms/AJAX via `includes/csrf_helper.php`.
- Input validation helpers: `includes/validation.php` (ids, enums, strings) applied to new endpoints.
- Optional audit logging: `includes/audit.php` + `audit_logs` table for admin actions (create/update/delete), time-permitting.

### 5) Docs & UX

- Update admin guide sections in `FINAL_IMPLEMENTATION_SUMMARY.md` to reference new reports and progression tools.
- Add stream/semester badges to new report pages.

## Minimal Schema (new)

- `users(id, username, password_hash, is_admin, is_active, created_at)`
- Optional: `audit_logs(id, user_id, action, entity, entity_id, details, created_at)`

## Risks

- Backward compatibility: gate auth with `AUTH_ENABLED` in `.env` for easy disable during local debug.
- Data migrations: provide reversible scripts and dry-run for progression tools.

### To-dos

- [ ] Create users/roles schema and auth middleware; protect admin pages
- [ ] Build login/logout pages and wire sessions with CSRF
- [ ] Implement room utilization report (API+UI) per stream/semester
- [ ] Implement lecturer workload report (API+UI)
- [ ] Implement scheduling performance report using GA stats
- [ ] Add year advancement tool with dry-run and summary
- [ ] Add graduation processing tool for final-year classes
- [ ] Add intake planner to bootstrap new cohort data
- [ ] Ensure CSRF and validation across remaining forms/AJAX
- [ ] Introduce audit logs for admin actions