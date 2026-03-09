# CoreX OS — Execution Reference

How Johan writes implementation prompts for Andre via VS Code Claude. These standards exist because vague prompts produce vague code.

---

## The Investigation-First Rule

**Before writing any prompt, investigate:**

1. Open the relevant file(s) in VS Code
2. Find the exact method name(s) involved
3. Find the exact line numbers
4. Check the migration to confirm the column name
5. Check the model to confirm the relationship method name
6. Check the route to confirm the URL and controller method

**Only after investigation** write the prompt with exact references. Never guess.

---

## Prompt Structure

A good implementation prompt contains:

```
CONTEXT
What module/feature this belongs to.

WHAT TO BUILD / CHANGE
Precise description. What it does, not how it looks.

FILES INVOLVED
- app/Models/Contact.php — add X relationship
- app/Http/Controllers/ContactController.php — modify store() method, line ~42
- resources/views/contacts/show.blade.php — add X section after line ~85

EXACT BEHAVIOUR
Step by step what happens when the user does Y.

DATA
What gets stored where. Which table, which column, which foreign key.

STANDARDS REMINDERS (if relevant)
- Soft delete this, not hard delete
- Read from settings table, not hardcoded array
- Link to pillar: Property/Contact/Deal/Agent

DO NOT
Specific things to avoid if there's a risk of the wrong approach.
```

---

## Example — Good Prompt

```
CONTEXT: Contact record — FICA compliance flag

WHAT TO BUILD:
Add a FICA compliance status indicator to the Contact show page. This should display the 
current FICA status (compliant / pending / overdue) and link to the FICA document checklist 
for that contact.

FILES INVOLVED:
- app/Models/Contact.php — add ficaStatus() accessor (check existing methods first)
- resources/views/contacts/show.blade.php — add FICA status badge after the contact 
  header section (around line 45, after the contact type badge)
- No new migration needed — fica_status column already exists (confirmed in contacts table)

EXACT BEHAVIOUR:
- If fica_status = 'compliant' → green badge "FICA Complete"
- If fica_status = 'pending' → amber badge "FICA Pending"  
- If fica_status = 'overdue' → red badge "FICA Overdue"
- Badge is clickable → navigates to /contacts/{id}/compliance

STANDARDS:
- Status values come from the compliance settings table, not hardcoded
- Soft delete applies if the compliance record is removed
```

---

## Example — Bad Prompt

```
Add FICA stuff to the contact page please
```

This wastes time, produces guesswork code, and often requires a full rewrite.

---

## Spec-First Rule

No prompt gets written for a Phase 2 feature until the spec in `/.ai/specs/` is complete and both Johan and Andre have reviewed it.

For Phase 1 consolidation items, the spec is implicitly the ROADMAP.md checklist item + the relevant existing module code. Still investigate before prompting.

---

## After Andre Builds

1. Pull his branch: `git pull origin andre`
2. Run migrations if any: `php artisan migrate`
3. Run test suite: `scripts/dev-check.ps1`
4. Test the feature manually in browser
5. Check the UI checklist in `DIAG_CHECKLIST_UI.md`
6. If good → merge to `main` via agreed process
7. Update ROADMAP.md checklist item to `[x]`
