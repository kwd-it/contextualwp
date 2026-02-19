# QA Checklist (v1.0 target)

Purpose: lightweight, repeatable QA.  
No need to paste model outputs. Run the prompt, judge the response, rate it, and move on.

Ratings:
- ✅ Good – correct, concise, editor-friendly
- ⚠️ Average – mostly OK but could be clearer/shorter/more practical
- ❌ Bad – incorrect, confusing, invents behaviour, or ignores intent

Date format: YYYY-MM-DD

---

## Global pass criteria (all tests)

- No UI issues (popover within viewport, no horizontal scroll, no layout breakage).
- Intent routing works (explain vs advise).
- Does not invent field behaviour or settings.
- Does not leak unsafe/internal metadata (keys, IDs, file paths).
- Output stays editor-focused and concise.
- Advise responses are practical and actionable (ideally a small number of clear bullets or short points).

---

# 1) ACF AskAI – Field Helper

## Standard prompts

**A1 – Explain**  
> What is this field for?

**A2 – Advise (content-entry fields only)**  
Use for: text, textarea, wysiwyg, number, email, url, image, file, date_picker, google_map  
> How should I fill this in well?

**A3 – Behaviour (state/relationship/nesting fields + conditional logic)**  
Use for: true_false, select, radio, checkbox, taxonomy, post_object, relationship, group, repeater  
> What changes when I change this field?

**A4 – Format / constraints (optional)**  
> Are there any formatting rules or constraints I should follow?

---

## Field types (priority order)

### 1. Text (text)

| Instance | Required | Instructions | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|----------|--------------|---------|--------|---------------|-------------|-------|
| Text 1 | Yes | Yes | A1, A2 | ✅ Good | No | 2026-02-11 | Correctly identified 50 character limit from instructions. Clear, concise guidance with no invented requirements. |
| Text 2 | Yes | No  | A1, A2 | ⚠️ Average | Yes | 2026-02-11 | A1 accurate and concise. A2 assumed display context (header/footer/contact page) and added formatting guidance not derived from schema or instructions. |
| Text 3 | No | Yes | A1, A2 | ✅ Good | No | 2026-02-10 | Clear, concise explanation. Guidance correctly follows instructions (comma-separated list). No invented requirements. |
| Text 4 | No  | No  | A1, A2 | ✅ Good | No | 2026-02-11 | Accurate explanation and correct formatting guidance. No invented behaviour or display assumptions. |

---

### 2. Textarea (textarea)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Textarea A | A1, A2 | ❌ Bad | Yes | 2026-02-11 | A1 assumed page-level body content and layout structure. A2 invented content structure (headline, benefits, CTA), tone guidance, word count, and specific services not derived from schema or instructions. |
| Textarea B (optional) | A1, A2 | ⚠️ Average | Yes | 2026-02-11 | A1 and A2 assumed layout position (beneath main heading). A2 added tone and CTA guidance not derived from schema or instructions. |

---

### 3. WYSIWYG (wysiwyg)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| WYSIWYG A | A1, A2 | ⚠️ Average | Yes | 2026-02-19 | A1 assumed placement under page heading. A2 introduced invented structure (2–3 paragraphs, word counts, CTA guidance). Practical and editor-friendly but adds layout/context assumptions not derived from schema. UI issue: response scrollbar overlaps close (X) button on longer outputs. |

---

### 4. True / False (true_false)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Toggle A (no conditional logic) | A1, A3 | ✅ Good | No | 2026-02-19 | Correct explanation of toggle behaviour. Clear ON/OFF logic. No layout or frontend assumptions. Behaviour tied to field instructions regarding required fields. |
| Toggle B (with conditional logic) | A1, A3 | ✅ Good | No | 2026-02-19 | Correctly identifies conditional relationship: Monday toggle shows/hides the “Monday Schedule” fields. Minor noise (“current value is empty”) and repeated “displayed” wording, but no invented behaviour. |

---

### 5. Select (select)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Select A | A1, A3 | ✅ Good | No | 2026-02-19 | Correctly identifies status as selectable availability state. Appropriately notes no defined conditional logic. Does not invent frontend behaviour. Sensible preview guidance. Minor unnecessary mention of current value. |

---

### 6. Radio (radio)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Radio A | A1, A3 | ✅ Good | No | 2026-02-19 | Correctly identifies ordering behaviour from selected value. No invented query logic or layout assumptions. Minor inference about listing/archive context but acceptable. |

---

### 7. Checkbox (checkbox)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Checkbox A | A1, A3 |  |  |  |  |

---

### 8. URL (url)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| URL A | A1, A2, A4 |  |  |  |  |

---

### 9. Email (email)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Email A | A1, A2, A4 |  |  |  |  |

---

### 10. Number (number)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Number A | A1, A2, A4 |  |  |  |  |

---

### 11. Image (image)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Image A | A1, A2, A4 |  |  |  |  |

---

### 12. File (file)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| File A | A1, A2, A4 |  |  |  |  |

---

### 13. Date Picker (date_picker)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Date A | A1, A2, A4 |  |  |  |  |

---

### 14. Taxonomy (taxonomy)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Taxonomy A | A1, A3 |  |  |  |  |

---

### 15. Post Object (post_object)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Post Object A | A1, A3 |  |  |  |  |

---

### 16. Relationship (relationship)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Relationship A | A1, A3 |  |  |  |  |

---

### 17. Group (group)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Group A | A1, A3 |  |  |  |  |

---

### 18. Repeater (repeater)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Repeater A | A1, A3 |  |  |  |  |

---

### 19. Google Map (google_map)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Map A | A1, A2, A4 |  |  |  |  |

---

### 20. Other / site-specific fields

| Field type | Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|------------|----------|---------|--------|---------------|-------------|-------|
|            |          |         |        |               |             |       |

---

# 2) Admin Chat Widget QA

Goal: ensure the floating chat is predictable, useful, and stable.

## Contexts to test
- C1: Current post/page
- C2: Multi context
- C3: CPT context (e.g. developments or plots)

## Standard prompts

**B1 – Summarise**  
> Summarise this content in 5 bullet points.

**B2 – Improve**  
> Suggest improvements for clarity and tone. Keep it practical.

**B3 – SEO**  
> Suggest an SEO title (max 60 chars) and meta description (max 155 chars).

**B4 – Structured extraction**  
> Extract key facts into a simple table.

**B5 – Rewrite (optional)**  
> Rewrite the intro to be clearer and more engaging, without changing meaning.

---

## Test matrix

### C1 – Current post/page

| Prompt | Rating | Issue logged? | Last tested | Notes |
|--------|--------|---------------|-------------|-------|
| B1 |  |  |  |  |
| B2 |  |  |  |  |
| B3 |  |  |  |  |
| B4 |  |  |  |  |
| B5 (optional) |  |  |  |  |

---

### C2 – Multi context

| Prompt | Rating | Issue logged? | Last tested | Notes |
|--------|--------|---------------|-------------|-------|
| B1 |  |  |  |  |
| B2 |  |  |  |  |
| B3 |  |  |  |  |
| B4 |  |  |  |  |
| B5 (optional) |  |  |  |  |

---

### C3 – CPT context

| Prompt | Rating | Issue logged? | Last tested | Notes |
|--------|--------|---------------|-------------|-------|
| B1 |  |  |  |  |
| B2 |  |  |  |  |
| B3 |  |  |  |  |
| B4 |  |  |  |  |
| B5 (optional) |  |  |  |  |

---

## Known limitations (tracked, not blocking v1.0)

- Flexible content fields are handled generically; per-layout guidance may be improved in a future release.
- AskAI response quality depends on ACF field instructions; fields without instructions may receive more general guidance.
- Chat widget does not currently persist conversation history across page reloads.
- AI output is non-deterministic; QA validates structure, intent, and safety rather than exact wording.

---

## v1.0 release gate

Consider v1.0 ready when:
- All commonly used field types are ✅ or ⚠️ with no major correctness issues.
- No ❌ remain on core field types (text, textarea, wysiwyg, true_false, select/radio/checkbox, relationship/post_object/taxonomy, group, repeater).
- Chat widget passes all C1/C2/C3 prompts with no repeatable failures.
- No UI regressions observed in wp-admin.
