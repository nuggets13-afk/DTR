# OJT Tracking System TODO

- [x] Create `config.php` with PDO + getenv DB config and session bootstrap
- [x] Create `schema.sql` with `users` and `time_logs` tables
- [x] Create `register.php` (registration + password_hash)
- [x] Create `login.php` (login + password_verify + session set)
- [x] Create `auth.php` (route protection helper)
- [x] Create `logout.php` (destroy session)
- [x] Create `dashboard.php` (clock in/out + analytics + bootstrap progress)
- [x] Update `dtr.php` as entry redirect
- [x] Create `vercel.json` for PHP runtime/routes

## Redesign + persistence adjustments
- [x] Keep login page light/white
- [x] Make Add Shift button red like Logout
- [x] Keep hours bold
- [x] Add pink theme option (previous phase)
- [x] Remove progress bar color setting from dashboard settings
- [ ] Remove theme mode entirely and make fixed Netflix-like theme
- [ ] Update dashboard fonts/text style to Netflix-like look
- [ ] Align login/register typography with new fixed style
- [ ] Run php lint for updated files
