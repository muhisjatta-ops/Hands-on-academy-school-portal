# Hands-On Academy fee schedule — loaded, with three queries

The 2026/2027 bill is now in the portal: nine levels, three terms each,
174 fee rows.

## Three totals that don't match their own line items

I added up every row on the bill. Six levels reconcile exactly. Three
don't — and since these are the numbers parents will be quoted, they need
the accounts office to settle them rather than me picking one.

| Level | Figure | Line items add up to | Bill states | Difference |
|---|---|---|---|---|
| Grade 7 | Year total | 35,000 | 33,500 | +1,500 |
| Grade 8 | Term 1 (new) | 17,000 | 16,500 | +500 |
| Grade 8 | Year total | 38,000 | 37,500 | +500 |
| Grade 9 | Term 2 | 10,500 | 11,000 | −500 |
| Grade 9 | Term 3 | 10,500 | 11,000 | −500 |
| Grade 9 | Year total | 33,500 | 34,500 | −1,000 |

Reading each one:

**Grade 7** — the three term figures are internally consistent
(16,000 + 9,500 + 9,500), and they sum to 35,000, but the year total says
33,500. So one of the four numbers is wrong. Note that the Grade 7 "Old"
figure of 9,500 does check out: 16,000 less registration (500), uniform
(2,500) and book bill (3,500). The term rows look right; the year total
looks like the slip.

**Grade 8** — the line items give 17,000 for a new student, but the bill
says 16,500. Here the bill contradicts *itself*: its own "Old 10,500"
equals 17,000 less registration, uniform and book bill. So 17,000 is what
the items and the Old figure both imply, and 16,500 appears to have dropped
the 500 exam fee. The year total carries the same 500 through.

**Grade 9** — Terms 2 and 3 list seven items summing to 10,500, but state
11,000. The year total of 34,500 is built on 11,000, so the same 500 is
missing from the rows twice. Either an item is absent from the table or the
total is 500 high. Also worth confirming: Grade 9 Term 1 shows the portal
fee and lab fee at 250 each rather than the 500 used everywhere else. That
may be deliberate — half-term charges alongside the external exam fee — but
it stands out.

**What the portal is doing meanwhile.** It charges the **line items**, not
the stated totals, because an invoice has to list what it charges row by
row — "Total 33,500" isn't something you can put on a receipt. So Grade 7
currently bills 35,000 for the year. Once the office confirms the right
figures, the fix is one amount in one place and a re-run.

## The thing the bill taught me my schema couldn't do

Every level has two Term 1 prices — "New 10,500 / Old 8,400". My fee tables
had no way to express that. A fee was priced per grade level per term, full
stop, which would have charged every returning pupil registration and a new
uniform every September.

So fee items now carry `applies_to`: `ALL` or `NEW_ONLY`. Registration,
uniform and the upper-basic book bill are `NEW_ONLY`.

**This is not a discount, and the distinction matters.** A returning Grade
1-3 pupil is not receiving 2,100 off a 10,500 bill — they were never charged
those items at all. If I had modelled it as a discount, every billing report
the bursar runs would overstate both the amount billed and the collection
rate, and the year-end figures would not match the bank.

There's a second rule inside the bill that is easy to miss: **Level 1,
Grade 1 and Grade 7 are always billed as new**, because they're the entry
year of each stage. A child moving up from ECD Level 3 into Grade 1 has been
at the school for years and still re-registers. That's the
`is_stage_entry` flag on the grade level, and it overrides the student's own
status.

## Files

| File | Destination |
|---|---|
| `2026_09_17_000100_add_new_student_billing.php` | `backend/database/migrations/` |
| `HandsOnFeeSeeder.php` | `backend/database/seeders/` |
| `InvoiceGenerator.php` | `backend/app/Services/` — **overwrite** |

```bash
php artisan migrate
php artisan db:seed --class=HandsOnFeeSeeder
```

The seeder uses `updateOrCreate`, so correcting an amount and re-running
updates in place rather than duplicating. It won't touch invoices already
issued — by design.

## Also in the Laravel code

`InvoiceGenerator::quote()` is new. It returns what a student would be
billed without creating anything, and includes **both** rates side by side.
That's for the office: when a parent asks "what would it be if my child were
returning," someone needs to answer without issuing an invoice to find out.

## In the portal

**Setup → Load the 2026/2027 fees** creates all nine levels and all 174 fee
items. **Setup → Load sample data** does that plus twelve demo students,
two-thirds new and one-third returning, so both rates show up in the ledger.

The student form now has a **Student type** field — new/re-registering or
returning — and the Students table shows it. A fee item can be marked
"charged to new students only" when you add it by hand.

## Two things not yet handled

**The bank account numbers and the accounts-office phone numbers** on the
bill aren't in the system anywhere. They belong on printed invoices and
receipts, which means they should be school settings rather than hard-coded.
Worth adding when we build the PDFs.

**"Fees are non-refundable"** is a policy the system doesn't encode. It
matters for the reversal flow: if a payment is reversed, is that a
correction of a recording error, or a refund the policy forbids? Those are
different things and the reversal reason field should probably distinguish
them.
