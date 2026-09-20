# "Student ID" instead of "Admission number"

Done everywhere a person sees it. One decision worth understanding, because
it looks like I ignored half the request.

## The database column is still `admission_number`

Eight tables already have a `student_id` column, and in every one of them it
holds this record's numeric primary key:

```
payments.student_id          -> students.id   (47)
invoices.student_id          -> students.id   (47)
scores.student_id            -> students.id   (47)
attendance_records.student_id
enrollments.student_id
guardian_student.student_id
discounts.student_id
```

If `students.student_id` held `'HOA-2026-0001'` as well, then this query:

```sql
select * from payments p join students s on s.student_id = p.student_id
```

would be silently, catastrophically wrong — and it reads like the obvious
thing to write. Someone will write it eventually, probably at 11pm before a
term-end report.

So the rule is: **"Student ID" is a label for people, `admission_number` is
the column.** Every screen, form, table header, search box and receipt now
says Student ID. The schema doesn't.

There is a read-only accessor on the model if you want the friendlier name
in a response or a Blade view:

```php
$student->student_id;   // "HOA-2026-0001"
$student->id;           // 47
```

It is deliberately not fillable — the generator is the only way a student ID
gets set.

## If you do want the column renamed

The defensible version is `student_code` rather than `student_id`, which
keeps the distinction visible in every join:

```php
Schema::table('students', function (Blueprint $table) {
    $table->renameColumn('admission_number', 'student_code');
});
```

Then update the ~12 places that reference it. Say the word and I'll do the
sweep. But do it before real data exists, not after.

## Files

| File | Destination |
|---|---|
| `StudentsPage.tsx` | `frontend/src/pages/` — overwrite |
| `FeesPage.tsx` | `frontend/src/pages/` — overwrite |
| `Login.tsx` | `frontend/src/pages/` — overwrite |
| `domain.ts` | `frontend/src/lib/` — overwrite |
| `Student.php` | `backend/app/Models/` — overwrite |

No migration, no data change. Pure relabelling plus the accessor.

## One behaviour change

**The Student ID field is now read-only in the admission form.** It was
editable, which meant a clerk could type over a generated ID and create a
duplicate. The server generates it under a row lock so two people admitting
at once can't collide — but only if nobody overwrites it.

It stays visible rather than hidden, because the office reads it aloud and
writes it on paperwork.

## The format

Currently `HOA-2026-0001`: school initials, entry year, then a sequence that
continues across the year.

Your earlier notes used `STU-2026-000123` instead. Both work. If you want to
switch, it's the one `$prefix` default in `Student::nextStudentId()` and the
`padStart` width — but change it **before** admitting real students. Mixed
prefixes inside one sequence are genuinely unpleasant to reconcile later,
and the school's registers will already have the old ones written in them.
