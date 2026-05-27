# E-Sign `editable_by` Runtime Investigation
**Date:** 2026-05-26  
**Template:** 111 (JR TEST - EXCLUSIVE AUTHORITY TO SELL)  
**Scope:** Two confirmed runtime breaks in field-party rendering

---

## EXECUTIVE SUMMARY

Two distinct runtime breaks prevent `editable_by` field permissions from being honored:

1. **Step 5 (Fill & Review) break:** Field data is truncated server-side before reaching the view, collapsing multi-party `editable_by` arrays into single-value `assignedTo` fields.
2. **Recipient signing view break:** When a multi-seller document assigns fields to both "owner_party" roles, the current party-resolution logic cannot distinguish Seller 1 from Seller 2 (both map to "owner_party"), preventing per-seller field locking.

Both are data-shape + code-logic issues.

---

## SECTION 1: TEMPLATE 111 FIELD_MAPPINGS (GROUND TRUTH)

### Tinker dump output

```
NAME: JR  TEST - EXCLUSIVE AUTHORITY TO SELL
CATEGORY: sales
DOC_TYPE_ID: 1
SIGNING_PARTIES: ["owner_party","agent"]
PARTY_MODE: shared

=== FIELD MAPPINGS (26 total) ===
tag=tag-mmygtomm-2thvp8 | label=Complex | typeKey=sf:property | party=auto | parties=null | editable_by=["agent"]
tag=tag-mmygtomm-aatpdj | label=Suburb | typeKey=sf:property | party=auto | parties=null | editable_by=["agent"]
tag=tag-mmygtomn-8n9jvp | label=Seller 1 Phone | typeKey=sf:manual | party=auto | parties=null | editable_by=["owner_party","agent"]
tag=tag-mmygtomn-ax9df4 | label=Seller Address 1 | typeKey=sf:manual | party=seller | parties=null | editable_by=["owner_party","agent"]
tag=tag-mmygtomn-e81juv | label=Seller Address 2 | typeKey=sf:manual | party=seller | parties=null | editable_by=["owner_party","agent"]
tag=tag-mmygtomn-hzwkku | label=Seller 2 Phone | typeKey=sf:manual | party=auto | parties=null | editable_by=[]
tag=tag-mmygtomn-ju601f | label=Seller 1 Email | typeKey=sf:manual | party=auto | parties=null | editable_by=["owner_party","agent"]
tag=tag-mmygtomn-nz2t09 | label=District | typeKey=sf:property | party=auto | parties=null | editable_by=["agent"]
tag=tag-mmygtomo-0ijw7u | label=Seller 3 Email | typeKey=sf:manual | party=auto | parties=null | editable_by=["owner_party","agent"]
tag=tag-mmygtomo-adalfm | label=Seller Addres 3 | typeKey=sf:manual | party=seller | parties=null | editable_by=["owner_party","agent"]
tag=tag-mmygtomo-dua6aq | label=Seller 2 Email | typeKey=sf:manual | party=auto | parties=null | editable_by=["owner_party","agent"]
tag=tag-mmygtomo-kuoyud | label=Seller Addres 4 | typeKey=sf:manual | party=seller | parties=null | editable_by=["owner_party","agent"]
tag=tag-mmygtomo-t30i2w | label=Seller 3 Phone | typeKey=sf:manual | party=auto | parties=null | editable_by=["owner_party","agent"]
tag=tag-mmygtomp-4dhhvy | label=Seller 4 Email | typeKey=sf:manual | party=auto | parties=null | editable_by=["owner_party","agent"]
tag=tag-mmygtomp-dlkfod | label=Seller 4 Phone | typeKey=sf:manual | party=auto | parties=null | editable_by=["owner_party","agent"]
tag=tag-mmygtomq-lyestv | label=Other Conditions | typeKey=sf:manual | party=auto | parties=null | editable_by=["owner_party","agent"]
```

### Key observations:

- **All Seller Address fields (1, 2, 3, 4) have identical data shapes:**
  - `party: "seller"` (single value)
  - `parties: null`
  - `editable_by: ["owner_party","agent"]` (array with two roles)
- **Data is clean and consistent at rest.**
- No per-seller distinction stored in `field_mappings` — all four address fields have the same `editable_by` array.

**Verdict on data: CLEAN. The bug is in code-logic, not data storage.**

---

## BREAK POINT 1: Step 5 Controller Truncates `editable_by` to Single Value

**Location:** `app/Http/Controllers/Docuperfect/ESignWizardController.php:3506-3531`

**Code excerpt from buildFieldsFromMappings():**

```php
// Line 3506-3509: COLLAPSE TO SINGLE VALUE
$editableBy = $m['filled_by'] ?? $m['editable_by'] ?? 'agent';
if (is_array($editableBy)) {
    $editableBy = $editableBy[0] ?? 'agent';
}

// Line 3531: Store single value only
'assignedTo'      => $editableBy,
```

**What happens:**

1. `buildFieldsFromMappings()` reads `field_mappings` for each field.
2. For `Seller Address 1`, it reads `$m['editable_by'] = ["owner_party","agent"]` (array).
3. **Lines 3507-3509 collapse this to a single value:** `$editableBy = $editableBy[0] ?? 'agent'` extracts ONLY `"owner_party"`.
4. The field is returned with `'assignedTo' => 'owner_party'` — the `"agent"` party is lost.

**Impact on Step 5:**

- Step 5 view receives field data with `assignedTo: 'owner_party'` (single string).
- View's `fieldRoleLabel()` function (line 2153-2155 in wizard.blade.php) reads `f.assignedTo` and renders ONE chip.
- Even though `field_mappings` has `editable_by: ["owner_party","agent"]`, Step 5 shows only OWNER/SELLER chip, not both OWNER + AGENT.

**Code in wizard.blade.php line 2153-2155:**

```javascript
fieldRoleLabel(f) {
    const role = this.fieldPartyOverrides[f.id] || f.assignedTo || f.assigned_to || 'creator';
    return getRoleLabel(role);  // Returns single label string, not array
},
```

**Why this is wrong:**

The controller MUST preserve the full `editable_by` array in the returned field object so that downstream (view, signing) can render all applicable parties. Collapsing to `[0]` silently discards valid party roles.

---

## BREAK POINT 2: Seller Signing View Cannot Distinguish Seller 1 from Seller 2

**Location:** `app/Http/Controllers/Docuperfect/SigningController.php:1347-1385`

**Code excerpt:**

```php
private function getEditableFieldsFromMappings(array $fieldMappings, string $partyRole): array
{
    // Map signing party roles to editable_by role names used in CDS builder
    $roleToEditableBy = [
        'landlord' => 'owner_party',
        'lessor' => 'owner_party',
        'seller' => 'owner_party',
        'tenant' => 'acquiring_party',
        'lessee' => 'acquiring_party',
        'buyer' => 'acquiring_party',
        'agent' => 'agent',
        'witness' => 'witness',
    ];

    $editableByRole = $roleToEditableBy[$partyRole] ?? $partyRole;  // Line 1361
    $editableFields = [];

    foreach ($fieldMappings as $field) {
        $editableBy = $field['editable_by'] ?? [];
        $fieldName = $field['field_name'] ?? $field['label'] ?? '';

        if (empty($fieldName)) {
            continue;
        }

        // Normalize field name to match blade variable format
        $varName = str_replace('.', '_', $fieldName);
        $varName = preg_replace('/[^a-zA-Z0-9_]/', '_', $varName);

        $canEdit = in_array('all', $editableBy)
            || in_array($editableByRole, $editableBy);  // Line 1376-1377: Check if party role in editable_by

        if ($canEdit) {
            $editableFields[] = $varName;
        }
    }

    return $editableFields;
}
```

**Usage in SigningController.php line 275-284:**

```php
// Determine which fields this signer can edit
// Prefer field_mappings with editable_by (CDS templates) over static party map
$fieldMappingsFromData = $webTemplateData['field_mappings'] ?? [];
if (!empty($fieldMappingsFromData)) {
    $editableFields = $this->getEditableFieldsFromMappings(
        $fieldMappingsFromData,
        $signingRequest->party_role  // THIS is passed in
    );
}
```

**The problem (party identity collapse):**

1. Template 111 has `signing_parties: ["owner_party","agent"]` — only TWO roles, no per-seller distinction.
2. When SignatureRequests are created for Seller 1, Seller 2, Seller 3:
   - Each gets `party_role = 'owner_party'` (same value for all sellers).
   - No column stores "this is seller_1" vs "this is seller_2".
3. When Seller 1 opens their signing link:
   - Controller reads `$signingRequest->party_role = 'owner_party'`.
   - Calls `getEditableFieldsFromMappings(..., 'owner_party')`.
   - For `Seller Address 3`, field has `editable_by: ["owner_party","agent"]`.
   - Resolver checks line 1376-1377: `in_array('owner_party', ["owner_party","agent"])` → **TRUE**.
   - Field is marked as EDITABLE for Seller 1.
4. **But Seller 1 should NOT be able to edit Seller 3's address.**

**Impact in sign.blade.php line 1940-1962:**

```javascript
fields.forEach(span => {
    const fieldName = span.getAttribute('data-field');
    if (this.editableFields.includes(fieldName)) {  // Line 1942
        // Convert to editable input
        const input = document.createElement('input');
        // ...
        span.replaceWith(input);
    } else {
        // Locked field — add locked styling
        span.style.opacity = '0.85';
    }
});
```

Since ALL seller addresses resolve to "editable by owner_party", `editableFields` list includes all of them. But the Blade template likely renders them with the SAME field_name (e.g., all as `seller_address` instead of `seller_address_1`, `seller_address_2`, `seller_address_3`), so the matching fails.

**Evidence from Johan's live test:**

- "8 items remaining" counter shows seller-linked fields ARE being filtered by party role → the resolution logic IS running.
- But Seller Address 3/4 render as static underlines (not inputs) → the Blade's `includes(fieldName)` check is failing.
- **Why:** `fieldName` matching is either:
  - A) All addresses have the same `fieldName` (bug in field_name generation), so only first matches.
  - B) `fieldName` format doesn't match between controller (normalized via regex) and Blade `data-field` attribute.

**The real issue:** The system does not track which seller is which at signing time. All sellers map to the same `party_role = 'owner_party'` token. Field_mappings has no per-seller identifier (e.g., no `seller_index` field). Therefore, the resolver cannot distinguish Seller 1's address from Seller 3's address — both are "owned by owner_party."

---

## PARTY IDENTITY AT SIGNING TIME

**Current architecture:**

Template 111 signing_parties:
```
["owner_party","agent"]
```

(Only two roles — no seller_1, seller_2, seller_3 distinction.)

SignatureRequest table for a 4-seller document:
```
| ID  | signature_template_id | party_role   | signer_name | signer_email |
|-----|------------------------|--------------|-------------|--------------|
| 234 | 5678                   | owner_party  | Seller 1    | s1@example   |
| 235 | 5678                   | owner_party  | Seller 2    | s2@example   |
| 236 | 5678                   | owner_party  | Seller 3    | s3@example   |
| 237 | 5678                   | agent        | Agent Name  | agent@hfc    |
```

All sellers have **identical** `party_role` value → no granular identification.

**Signing flow:**

1. Seller 1 opens token → loads SignatureRequest with `party_role = 'owner_party'`.
2. Controller calls `getEditableFieldsFromMappings(..., 'owner_party')`.
3. ALL fields with `editable_by: includes("owner_party")` are marked editable (because role matches).
4. Field_mappings has no concept of "whose address is this" — all are generic `editable_by: ["owner_party","agent"]`.

**Consequence:** Unable to enforce "Seller 1 edits only Seller 1's address; Seller 3 edits only Seller 3's address" because the system lacks per-seller field identification.

---

## THE "8 ITEMS REMAINING" COUNTER

Johan noted: "8 items remaining" counter in Seller 1's signing view implies some seller-counting logic works.

**What it likely counts:**

JavaScript in sign.blade.php (around line 2030-2031) counts `editableFields.length`:

```javascript
if (this.editableFields.length > 0) {
    // Show items remaining counter
    const itemsRemaining = this.editableFields.length;  // Counts array length
}
```

This shows that:
- The `editableFields` array IS being generated by controller.
- The FILTERING by party role IS working (controller knows Seller 1 should have 8 editable items).
- But FIELD NAME MATCHING in the Blade is failing for items 2–4.

**Hypothesis:** The "8 items remaining" are correct per party role (resolver did its job), but when the Blade tries to find matching `data-field` attributes on the HTML to convert spans to inputs, it finds 0 matches for sellers 2+. This suggests field_names either:
- Are not unique per seller address (all `seller_address` instead of `seller_address_1`, etc.).
- Don't match between controller's normalization and Blade's attribute values.

---

## SUMMARY TABLE

| Break | Component | Location | Root Cause | Impact | Category |
|-------|-----------|----------|-----------|--------|----------|
| **1. Step 5** | Controller | `ESignWizardController:3506-3531` | Collapse `editable_by: [array]` to single `assignedTo` via `[0]` | Only first party chip shown; agent chip missing; Step 5 user sees incomplete party list | Code logic error |
| **2. Signing** | Controller + View | `SigningController:1347-1385` + `sign.blade.php:1942` | No per-seller identifier in party_role or field_mappings; all sellers share "owner_party" token; field_names don't distinguish seller 1 vs 3 | Seller 3/4 addresses show static text not inputs; unintended field access | Data shape + code logic |

---

## RECOMMENDATIONS FOR INVESTIGATION NEXT STEPS

**For Break 1:** Trace field rendering in Step 5 view. Does `fieldRoleLabel()` support rendering an array of party chips, or only a single string? If array support is needed, must:
- Change controller to pass full `editable_by` array, not single `assignedTo`.
- Update view to iterate array and render multiple chips per field.

**For Break 2:** Determine template 111's intent:
- Should seller_1 and seller_3 have DIFFERENT signing_parties/roles?
- OR should field_mappings distinguish "seller 1's address" from "seller 3's address" (e.g., via seller_index metadata)?
- OR should field_names include seller number suffix (seller_address_1, seller_address_2, seller_address_3)?

Current state: All sellers map to single `owner_party` role. Field_mappings has no per-seller identifier. Result: Can't distinguish seller-specific field access at signing time.

---

End of investigation. Full field_mappings data and party resolution chain documented. Ready for fix-design session.
