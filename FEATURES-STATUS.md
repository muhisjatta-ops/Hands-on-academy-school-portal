# Feature status, and what needs a decision from you

Built in this pass, visible in the portal now:

## Students — complete
- **Student profiles** — click any student name, anywhere in the portal
  (Students list, Fees ledger, Reports). One panel: profile, guardians, fee
  position across all three terms, receipts, marks by subject, attendance.
- **Guardians** — add and remove, with primary contact and fee-payer flags.
  Multiple guardians per student.
- **Student history** — class, fees and marks per term in one place.
- Admissions, enrollment and search were already there.

## Fees — complete
- **Receipts now print.** A proper document with the crest, the school's bank
  details, the term balance and signature lines. Opens in its own window.
- **Financial reports** — see Reports below.
- Structures, invoices, payments and balances were already there.

## Academics — complete
- **Teachers** — new page. Add staff, set a class teacher, and assign
  class/subject pairs.
- **Class assignments** — the page shows how many class/subject pairs nobody
  is assigned to teach, which is the gap that bites at marks time.
- **Report cards now print.** Per subject: mark, grade, remark and position
  in class, plus overall average and attendance. Signature lines for class
  teacher, head teacher and parent.
- Attendance, assessments, marks and grades were already there.

## Advanced reporting — complete, five tabs
- **Fee collection** — billed, collected and outstanding per class, with a
  collection rate, plus how money arrived (cash / bank / mobile money) so
  the bursar can reconcile against statements.
- **Arrears** — outstanding by term. A balance still sitting against Term 1
  in Term 3 has survived two reminder cycles; that is the list to act on.
- **Attendance** — rate per class, the registers taken in the last 14 days
  (a missing day cannot be reconstructed later), and the students with most
  days absent.
- **Class performance** — average and pass rate per class and subject,
  sorted worst first, with who teaches it.
- **Teacher activity** — marking coverage per teacher against their
  assignments, and registers taken.

Every tab prints. The navigation is hidden when printing.

## Branding
White background, black structural borders, red accents: a red rule along
the top of each card, red dividers between the statistic tiles, red left
edge on the active nav item. Table heads keep a heavier black rule; row
dividers stay light, because black rules between 400 student rows turn the
list into a barcode.

---

# The two groups I have not built, and why

## Portals — needs the Laravel system running

A teacher, student or parent portal is not a screen, it is a **login**. The
role switcher in the prototype is a demonstration, not a security boundary —
anyone can change it. A real parent portal means a parent account that can
see its own children and provably nothing else.

That is `StudentPolicy::view()` in the Laravel code, which is already
written:

```php
if ($user->hasRole('parent')) {
    return $student->guardians()
        ->where('guardians.user_id', $user->id)
        ->exists();
}
```

Getting this wrong exposes every child's records to every guardian account.
It is the single worst bug this system could have, and it cannot be tested
in a prototype with no real authentication. So the portals wait until the
Laravel install runs.

What I can do meanwhile: build the three portal screens against the Laravel
API, ready for when it is up. Say the word.

## Communication and payments — needs accounts and decisions

I can write all of this, but not until you have chosen providers, because
the integration is specific to each one. Four questions:

**1. SMS — which provider?** In The Gambia the practical options are an
aggregator with local routes (Africa's Talking covers Gambian networks) or a
direct arrangement with Africell or QCell. Africa's Talking is faster to
start and gives delivery reports; a direct carrier deal is usually cheaper
at volume. You need an account and a sender ID either way, and sender IDs
take days to approve.

**2. WhatsApp — Business API or nothing.** Sending fee reminders over
WhatsApp means the WhatsApp Business Platform: a Meta Business account, a
verified business, a dedicated number, and **pre-approved message
templates**. You cannot send free-form text to a parent who has not messaged
you first. Approval takes days to weeks. Worth knowing before promising it
to anyone.

**3. Mobile money — which wallet?** Afrimoney (Africell), QMoney (QCell) and
Wave all operate in The Gambia and have different APIs, settlement terms and
merchant onboarding. This is the one that needs a real conversation with the
provider before any code, because the money lands in a specific account
under specific terms.

**4. Email — who sends it?** Laravel can send through any SMTP service. A
transactional provider such as Postmark or Amazon SES is far more reliable
than sending from the school's own mailbox, which will hit spam folders once
you send 200 fee reminders at once.

**My suggestion on sequencing:** email first, since it is nearly free and
needs no approval. Then SMS, because in The Gambia it reaches parents who do
not read email. WhatsApp and online payment last, since both depend on
approvals outside your control and neither blocks the school from operating.

One design note for whenever we do build it: notifications need a
**delivery log** — who was sent what, when, and whether it arrived. Without
it, "we sent three reminders" is unprovable, and that is exactly the claim
that gets disputed when a child is sent home over fees.

---

# What I would do next, in order

1. **Get Laravel running.** Everything else waits on it. The portals cannot
   be built without real logins, and the prototype cannot hold real student
   data safely.
2. **Enter one real class by hand** — twenty students, their real guardians,
   the real fees. Small enough to fix if the model is wrong, big enough to
   expose what is missing.
3. **Print a real report card and a real receipt** and show them to the head
   teacher. Documents are what people judge; a screen they will forgive, a
   report card going home to a parent they will not.
4. **Then portals**, then email, then SMS.

A caution on step 2: the prototype stores data in one browser with no login.
It is the right place to work out what the school needs and the wrong place
to keep real children's names, guardian phone numbers or payment records.
Export regularly, and move to the Laravel system before it becomes the
school's actual record.
