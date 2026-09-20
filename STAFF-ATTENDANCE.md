# Staff attendance — Laravel backend

The principal keeps the register; the Proprietress reads it. That split is
the whole design, and it is enforced by permissions on the routes, not by
hiding buttons.

## Install, in this order

```bash
# 1. copy the files into your Laravel project, keeping the paths
cp -r backend/app/*            app/
cp -r backend/database/*       database/
cp -r backend/tests/*          tests/

# 2. the position/unit columns must exist before the register uses them
php artisan migrate

# 3. grant the new permissions and create the `principal` role
php artisan db:seed --class=RolePermissionSeeder

# 4. give the staff their accounts (or re-run to refresh titles)
php artisan db:seed --class=HandsOnStaffSeeder
```

Then paste the contents of `backend/routes/api-staff-attendance.php` inside
the `Route::middleware('two-factor')->group(...)` block in `routes/api.php`,
next to the Phase 2 routes.

`RolePermissionSeeder.php` here **replaces** the existing one. It is the
previous file plus two permissions and the `principal` role; nothing was
removed. Re-running it resets every role to the list in that file, which is
the point — it is the record of who may do what.

## Who may do what

| Role | Read the register | Mark it |
|---|---|---|
| `super-admin` | yes | yes |
| `admin` (Proprietress) | yes | **no** |
| `principal` (Fatou Touray) | yes | yes |
| `bursar`, `teacher`, `registrar`, `parent`, `student` | no | no |

Two notes on that table.

**The Proprietress cannot mark the register.** You asked for the principal to
manage it and the Proprietress to view it, so there is one keeper and two
readers. If she should also be able to correct a mark, delete
`'staff-attendance.record'` from the `array_diff` on the `admin` role in
`RolePermissionSeeder.php` and re-seed.

**Teachers cannot read it at all.** Who was absent is a matter between a
person and their head, not something the whole common room reads off a
screen.

## Endpoints

| Method | Path | Permission | What it returns |
|---|---|---|---|
| GET | `/api/staff-attendance?date=` | `staff-attendance.view` | The day's register, every staff member, plus each one's running totals for that month |
| GET | `/api/staff-attendance/today` | `staff-attendance.view` | The dashboard card: how many present, and who is away, by name and position |
| GET | `/api/staff-attendance/summary?from=&to=` | `staff-attendance.view` | Totals per person over a range; defaults to this month |
| POST | `/api/staff-attendance` | `staff-attendance.record` | Saves the whole day in one transaction |
| DELETE | `/api/staff-attendance` | `staff-attendance.record` | Clears one person's mark for one day |

## Decisions worth knowing about

**A separate table, not a column on `attendance_records`.** The two registers
answer to different people and carry different states. Sharing a table would
mean every student query filters on a discriminator for ever, and the first
person to forget that filter puts a teacher's absence on a child's report
card.

**No `term_id`.** Staff work through the holidays and across the year
boundary, so a term would be null on a large share of the rows. This table is
asked about date ranges.

**States are P, A, L, V — present, absent, late, on leave.** Not "excused".
Excused is what a school says about a child; leave is what an employer says
about a member of staff, and this register can end up quoted on a payroll
query.

**Leave is counted but kept out of the attendance rate.** Approved leave is
not a failure to turn up, and folding it in would count a holiday the school
granted against the person who took it.

**One mark per person per day**, enforced by a unique index. A correction
replaces the mark rather than adding a second, contradictory row. Every mark
records who made it.

**A register cannot be taken for a future date.** That is always a typo in
the date box.

**`position` and `unit` are not roles.** Position is what someone is called
on a letter; role is what they may do in the portal. They diverge the moment
a department head is given no extra portal access — which is the case for
seven of the twelve people here — so they cannot share a column.

## Tests

```bash
php artisan test --filter=StaffAttendanceTest
```

Thirteen cases, each with its mirror: the principal can mark the register
**and** the Proprietress cannot; the Proprietress sees the card **and** the
teacher gets a 403. A test that only proves the permitted case would still
pass if everybody were permitted.

## Still outstanding

The Head Master (Lower Basic) and the two ECD Heads are seeded as `teacher`,
so they can neither see nor mark the staff register. If each section should
keep its own, they need the `principal` role — one word each in
`HandsOnStaffSeeder.php`. There is no per-section scoping yet: anyone with
`staff-attendance.view` sees every member of staff. If the school wants the
Lower Basic head to see only Lower Basic staff, that is a row-level rule and
belongs in a policy, the same way `ScorePolicy` scopes marks to a teacher's
own classes.
