# Rental Applications — What To Test This Morning

For Johan. Everything below is on the test site (QA1) only — nothing has gone
to the live site.

---

## 1. What you can test

### The new Rentals section in the menu
Log in. On the left-hand menu you'll now see **Rentals** as its own
section. Click it — it opens to **Rental Applications** and **Returned
Applications**.

### The list screen — search, sort, own/branch/agency, filter, paging, archive
- Click **Rental Applications**.
- **Search** — type into the search box (an applicant's name, email, ID
  number, or part of an address) and click Filter.
- **Sort** — click any column heading (Applicant, Status, Agent, Created,
  Last updated). Click it again to flip the order.
- **Own / Branch / Agency** — near the top there are three buttons letting
  you switch between your own applications, your branch's, or the whole
  agency's. You'll only see the buttons for the levels you're actually
  allowed to see.
- **Status** — use the Status box to show only one status at a time.
- **Rows per page** — use the "Per page" box to show more or fewer rows.
- **Archive / restore** — click **Archive** on any row to take it off the
  list. Nothing is ever deleted for good — click **Show Archived** to see
  it again, and **Restore** to bring it back.

### The applicant's own form
- Open **Rental Applications**, click **New Rental Application**, fill in
  a contact, and save it — **don't** click Send yet.
- Open the "Online link" shown on that application in a new tab. It should
  say the link isn't ready yet and show no form — this is deliberate, so
  a link can't be filled in before you've actually sent it.
- Go back and click **Send**. Open the same link again — it should now be
  the real, fillable form.
- Type a rand amount into a money field (e.g. current rent) using a comma,
  like `8,500` — it should be accepted.
- Fill in a few fields, attach a document, and confirm everything you
  typed is still there afterwards (nothing should clear).
- **One thing worth checking with us rather than assuming:** the Submit
  button currently sits below the document-upload area, not above it —
  that was a deliberate change (so nobody submits before they've had the
  chance to attach anything), but if that's not what you had in mind, say
  so and we'll move it back.

### The agent review screen
- From **Returned Applications**, click **Review** on any row.
- **Highlighting** — open a document and mark it up directly on the page
  with a highlighter (drag like a real highlighter pen, not a box).
  Several colours are available; yellow is the default.
- **Notes** — pin a written note to a spot on the document.
- **Two panels scroll separately** — the document on the left and the
  income/assessment panel on the right each scroll on their own, so you
  can work on one without losing your place on the other.
- **Saving** — the assessment panel saves itself as you go, and shows
  "Saved at [time]" so you can see it actually happened. Your highlights
  and notes have their own Save button, which now sits in a fixed strip
  at the top of the screen so it's always visible, however far down you've
  scrolled.
- **New:** if you try to close a document while a highlight or note hasn't
  been saved yet, it now stops you and asks first, instead of quietly
  losing it.
- **New:** opening a large document used to take about nine seconds before
  anything appeared. It now shows the first page in well under a second,
  at full quality, and loads the rest behind it.

### Submitting to the authoriser
- On the review screen, click **Submit to authoriser** (it says
  "Re-submit to authoriser" if it's already been sent once before).

### The authoriser screens
**Note:** there's no menu button for this yet, so the first time, whoever
set you up as an authoriser will need to send you the direct link.
- **Approve** — enter the rand amount the tenant is approved for and
  confirm. (See the comma note below — a comma in this box currently
  causes an error; type the amount without commas for now.)
- **Decline** — decline the application, with an optional reason.
- **Request more information** — sends it back to the agent, not the
  applicant, with your reason attached in a real email to the agent.
- **If a decision has already been made** on an application, only a
  designated override-level person can change it, and they must give a
  reason.
- **What an ordinary agent sees if they try to reach these screens or
  actions directly:** they're blocked outright with a plain "not allowed"
  message — it isn't just a hidden button, the system itself refuses it.

### Setting a status from Returned Applications
On an application that's already come back, an agent can set it to
**Under Assessment** or **Withdrawn** by hand from a dropdown. (Approve
and Decline are authoriser-only now, done from the authoriser screens
above, not from this dropdown.)

### The decline email wording
Go to **Settings → Rental Applications**. The subject and body of the
email sent when an application is declined can be rewritten there, per
agency. The same screen is also where you choose who your authorisers
are.

---

## 2. What is not done yet

- **A document added after submission doesn't yet show a warning on the
  review screen** — an agent reviewing an application there currently
  can't tell that a document arrived late. (It does already show
  correctly on the application's own page — just not yet on this
  particular screen. This is being finished now and may be ready before
  you test.)
- **The authoriser's approve screen rejects a comma in the rand amount.**
  Type the number without commas for now (e.g. `8500` not `8,500`) — a
  fix is queued.

---

## 3. Found, but not fixed — reporting only, as instructed

- The same raw technical error message that was showing on rental
  application screens earlier was also found on the viewing pack redaction
  screen.
- Mailbox failure alerts are under-reporting because several failures
  share one cooldown timer and only the first one notifies.
- The sales document sending screens have the same false "Sent" status
  problem that rental applications had — it can say "Sent" when nothing
  actually went out.
