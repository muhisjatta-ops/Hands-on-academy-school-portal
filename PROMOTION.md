# Promotion — moving pupils between levels

The school administrator can move a whole class up at the end of the year,
with exceptions for repeaters, leavers and the top year, and can move a
single pupil mid-year. Every move is dated, attributed and given a reason.

## Install

```bash
cp -r backend/app/*      app/
cp -r backend/database/* database/
cp -r backend/tests/*    tests/

php artisan migrate
php artisan db:seed --class=RolePermissionSeeder   # adds students.promote
```

Paste `backend/routes/api-promotion.php` into `routes/api.php`, and **delete
the old transfer route** from `api-phase2.php`:

```php
Route::post('/students/{student}/transfer', [StudentController::class, 'transfer'])
```

That one edits the enrollment in place with no record of the previous class
and no reason. `POST /students/{student}/move` is the same operation with a
trail.

## Who can do it

`students.promote` is a permission of its own, held by the administrator and
nobody else. It is deliberately **not** part of `students.update`: a clerk
who may fix a spelling should not be able to move a year group, and the two
are asked for by different people at different times of the year. The
registrar admits and edits pupils but cannot promote them.

## How a promotion is stored

**A promotion creates a new enrollment. It does not edit the old one.**

`enrollments` already holds one row per pupil per academic year, with the
class they were in and how the year ended (`PROMOTED`, `REPEAT`, `LEFT`).
Promotion sets that outcome on the closing row and writes a fresh row for
the new year. Last year keeps saying what last year was — which is what a
transcript, a report card reprint and a fee query all depend on.

`class_moves` is the narrative alongside it: who moved whom, when, from
where to where, and why. It answers the question the enrollment cannot —
*why* is this child in Grade 5 rather than repeating Grade 4 — and it covers
within-year transfers too.

Class names are **copied** into each move, not referenced. Rename or retire
a class next year and last year's record still says what actually happened.

## Four things that must not happen, and don't

**A half-promoted class.** The whole batch runs in one transaction. Eleven
pupils moved and nine not is worse than none moving: it reads as finished
and is wrong about the rest. There's a test that deletes the target class
mid-batch and asserts the pupil ahead of the failure didn't move either.

**A duplicated enrollment.** Running the promotion twice — because someone
was unsure it worked the first time — skips the pupils already enrolled for
the new year and says so, rather than aborting the whole batch on the unique
index.

**A pupil promoted into the wrong year.** A target class belonging to a
different academic year is refused, naming the year it actually belongs to.
A within-year move that would cross years is refused too, and points at
promotion instead.

**A silent class change.** Every path that changes a class writes a
`class_moves` row. There is no endpoint that changes one without.

## Fees follow the class — and that is the trap

Moving a pupil changes what they are billed from that moment on. At the end
of the year that's correct: the new year is billed at the new level. Mid-year
it means payments already taken were against the old figure, so the balance
needs checking with the accounts office.

**The preview shows each pupil's outstanding balance but never enforces it.**
Whether a debt blocks promotion is the school's decision, not the software's
— a system that silently held a child back over unpaid fees would be making
that decision on the school's behalf, in the worst possible way.

Marks and registers do not move. They stay against the class and term they
were entered for, which is what a report card has to show.

## Endpoints

| Method | Path | Permission |
|---|---|---|
| GET | `/api/classes/{classRoom}/promotion` | `students.promote` — the roll, defaults, balances, warnings |
| POST | `/api/classes/{classRoom}/promotion` | `students.promote` — apply the batch |
| POST | `/api/students/{student}/move` | `students.promote` — one pupil, within the year |
| GET | `/api/students/{student}/class-history` | `students.view` |
| GET | `/api/class-moves?from=&to=` | `students.view` |

The preview returns `warnings` — no following academic year, no class at the
next level in it, or a top level whose pupils graduate rather than move up.
These are the three things that stop a promotion at the worst moment, so the
screen says them before anyone clicks.

## In the portal

The prototype has the same feature under **Promotion**, available to the
school administrator only. It opens on a class that actually has pupils,
defaults everyone to the sensible outcome, shows what each one owes, and
records every change under "Recent moves".

The class field on the student form is **read-only** there. Changing a class
goes through **Move class** on the pupil's record, which states what the move
does to their bill before you confirm it. A class quietly retyped in an edit
form leaves no trace, and the operation most likely to be done wrong is the
one that most needs one.

The portal also has a **Close the year** step, which the Laravel system does
not need: it stamps every payment with the outgoing year before the clock
moves on. Without that, a family who had paid in full for Grade 4 would
appear the next morning to have paid their Grade 5 fees too. Laravel has
this for free — invoices belong to a term, which belongs to a year.

## Tests

```bash
php artisan test --filter=PromotionTest
```

Eighteen cases. Most are about what must *not* happen: no duplicate
enrollment on a re-run, no half-finished batch, no cross-year move, no
rewritten history when a class is renamed, and no promotion by anyone
without the permission.
