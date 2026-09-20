# Hands-On Academy branding

The name is **Hands-On Academy** — plural, hyphenated. I had it as "Hand On
Academy" in everything built before this, so several files need correcting,
not just restyling.

## Colours, taken from the crest

| Token | Hex | Where |
|---|---|---|
| Ink | `#111111` | all body text, headings, figures |
| White | `#ffffff` | sidebar, cards, login panel |
| Paper | `#fafafa` | page ground behind the cards |
| Line | `#e2e2e2` | card borders, table rules |
| Grey | `#5c5c5c` | labels, secondary text |
| Crest red | `#bf3c36` | active nav edge, primary buttons, errors |
| Crest red (hover) | `#a5322d` | button hover |
| Crest red (deep) | `#8f2a25` | error text on white |

Black on white, with red as the only accent. That is the whole palette.

Three things this forces, all deliberate:

**Red appears in three places and nowhere else** — the left edge of the
active nav item, the one primary button on a screen, and error states.
Spread wider it stops meaning anything; kept this narrow it tells you where
you are and what the main action is without a word of explanation.

**The page ground is `#fafafa`, not pure white.** The sidebar and cards are
white, so if the page behind them were also white the cards would vanish and
you would be looking at floating text. The near-white keeps a visible edge
without drawing a heavy border around everything.

**Primary buttons keep a white label on red.** "Text is black" governs body
copy; a button label sitting on a red ground has to be white to be legible,
and its focus ring is black rather than red for the same reason.

**The crest needs no white chip now.** On the old red sidebar it had to sit
on one, because the artwork's own white half would otherwise read as a
floating rectangle. On a white sidebar it drops straight in.

## Files in this folder

| File | Destination |
|---|---|
| `logo.png` | `frontend/src/assets/logo.png` |
| `Shell.tsx` | `frontend/src/components/Shell.tsx` — overwrite |
| `Login.tsx` | `frontend/src/pages/Login.tsx` — overwrite |

The logo is 600px wide, which is ample for both the 168px sidebar chip and
the 230px login panel on a retina screen. The original 1080px upload was
heavier than any screen needs, and page weight matters on a slow connection.

Vite handles the `import logo from '../assets/logo.png'` — no config needed.

## Still to correct by hand

These carry the old spelling and I can't edit your working copy:

**`backend/.env`**
```dotenv
APP_NAME="Hands-On Academy"
```
This one matters beyond cosmetics: it's the label staff see in their
authenticator app when they enrol in 2FA, and the sender name on
password-reset emails.

**`backend/database/seeders/SchoolSeeder.php`** — the closing
`$this->command->info()` lines say "Hand On Academy". Cosmetic.

**`backend/app/Models/Student.php`** — `nextAdmissionNumber()` defaults to
the prefix `HOA`, which still reads correctly for Hands-On Academy. Leave it
unless the school uses something else on its registers. If you change it,
change it *before* admitting real students — mixed prefixes in a sequence
are painful to reconcile later.

**The prototype portal** — already updated, name and crest both.

## The crest on dark backgrounds

The artwork has a white upper half as part of its design, so it can't sit
directly on the black sidebar without looking like a floating white box.
Both components put it on a deliberate white chip with a thin border, which
reads as a badge.

If you have a version of the crest with a transparent background, or a
single-colour version for small sizes, send it and I'll use that instead —
it would look better in the sidebar and would let us use the crest as the
favicon and on report cards, where a white rectangle would be intrusive.

## What the crest should appear on next

Report cards and fee receipts, once those are rendered as PDFs. That's the
main practical reason to get the asset right: a receipt with the school crest
on it is the document a parent keeps.
