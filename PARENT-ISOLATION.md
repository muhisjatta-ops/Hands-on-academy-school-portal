# Parent portal: how "only my own child" is actually enforced

A parent seeing only their own children is not a screen. It is a property of
every database query behind that screen. The page can hide things; only the
server can refuse them.

## The one rule

Every query in `ParentPortalController` starts from the signed-in guardian
and works outwards. Not one method fetches a student and then checks whether
the parent is allowed to see it.

```php
// WRONG — the record is already in memory, so a forgotten check leaks it
$student = Student::findOrFail($id);
if (! $parentOwns($student)) abort(403);

// RIGHT — the record cannot be fetched at all unless it is theirs
$student = $this->guardian()->students()->where('students.id', $id)->first();
if (! $student) throw new NotFoundHttpException('Student not found.');
```

The difference is what happens when someone adds a method in a hurry next
year. With the first shape, a missing `if` exposes every child in the school
to every guardian account. With the second, a missing check returns nothing,
because the query never reached outside the family.

That gate is `childOrFail()`. Every method uses it, and nothing else in the
controller may call `Student::find` in any form.

## Why 404 and not 403

Asking for another family's child returns **404 Student not found** — byte
for byte the same response as asking for a child who does not exist.

A 403 would confirm the record exists. A parent could then walk the ids —
1, 2, 3, 4 — and learn the size of the roll and which ids are real. That is
a disclosure about other people's children even without seeing a name, and
there is a test asserting the two responses are identical.

## Why the route is not model-bound

```php
Route::get('/children/{student}', [ParentPortalController::class, 'child']);
```

`{student}` is deliberately **not** route-model-bound. Binding would let
Laravel fetch the record before the controller could check whose child it is,
and the day someone type-hints `Student $student` on that method, Laravel
would hand over any pupil in the school. The controller resolves the id
itself.

The role restriction is on the route **group**, not on each route, so a route
added to that group later inherits it automatically. That is precisely the
mistake you want the framework to prevent rather than trusting whoever adds
it to remember.

## What the endpoints deliberately do not exist

- `/parent/students` — the school roll
- `/parent/class/{id}` — the class register, which names other children
- any write endpoint — a correction goes through the office, so there is an
  audit trail of who changed what

Two subtler ones:

**The class register is never returned.** A parent seeing who else was absent
is a disclosure about other people's children, and no parent needs it.

**Position in class is returned; other pupils' marks are not.** "4th of 22"
is a rank. It tells a parent where their child stands without revealing a
single other mark or name.

## The test is the deliverable

`ParentPortalIsolationTest` is what makes the guarantee real, and keeps
making it real when someone edits the controller a year from now.

```bash
php artisan test --filter=ParentPortalIsolationTest
```

Ten assertions, including the ones that are easy to get wrong in the other
direction:

- A parent sees only their own child in the list, by name **and** by student ID
- All four child endpoints refuse another family's child
- A missing id and another family's child return identical responses
- A parent account with no guardian record linked sees **nothing** — fails closed
- A signed-out visitor gets 401
- A teacher gets 403 on the parent routes, so they cannot read fee statements
- **Both parents of the same child can see that child** — isolation must not
  lock out the mother
- **Siblings both appear** for the same guardian

If one assertion fails, the parent portal is not fit to switch on. There is
no "mostly isolated".

## Files

| File | Destination |
|---|---|
| `ParentPortalController.php` | `backend/app/Http/Controllers/` |
| `ParentPortalIsolationTest.php` | `backend/tests/Feature/` |
| `api-parent.php` | paste into `routes/api.php` — see the file's header |

`StudentPolicy` from Phase 2 already carries the same rule for the admin
side, so a parent cannot reach a child through the staff endpoints either.

## Before any real parent account exists

1. Run the test. All ten assertions pass, or stop.
2. Link guardians to user accounts. A `Guardian` row needs its `user_id` set;
   without it the parent sees nothing, which is safe but useless.
3. Give parents 2FA or at minimum force a password change on first sign-in.
   A guardian account is a window onto a child's whereabouts and a family's
   finances, and parents reuse passwords more than staff do.
4. Try to break it yourself. Sign in as a test parent and edit the id in the
   address bar. You should get "Student not found" every time.

That fourth step is worth doing by hand even though the test covers it —
there is no substitute for seeing the refusal with your own eyes before you
hand the link to four hundred families.

## The preview page

`parent-portal.html` shows what a parent gets: a switcher across their own
children, then fees across all three terms, receipts, attendance with the
reasons recorded, and a printable report card.

It is demo data for one family, hard-coded. It enforces nothing and it must
never hold real children's records.
