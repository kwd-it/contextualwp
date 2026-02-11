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

**A2 – Advise**  
> How should I fill this in well? 

**A3 – Behaviour (only when relevant)**  
Use only for: true_false, select, radio, checkbox, button_group, conditional logic, or nested fields.
> What changes when I change this field?

**A4 – Format / constraints (optional)**  
> Are there any formatting rules or constraints I should follow?

---

## How many instances to test per field type?

- Default: **1** instance per type.
- Prefer **2** where useful:
  - required vs optional
  - has instructions vs none
  - has conditional logic vs none

No need for more unless something looks unstable.

---

## Field types (priority order)

### 1. Text (text)

| Instance | Required | Instructions | Prompts | Rating | Issue logged? | Last tested | Notes |
|---------|----------|--------------|---------|--------|---------------|-------------|-------|
| Text 1 | Yes | Yes | A1, A2 |  |  |  |  |
| Text 2 | Yes | No  | A1, A2 |  |  |  |  |
| Text 3 | No | Yes | A1, A2 | ✅ Good | No | 2026-02-10 | Clear, concise explanation. Guidance correctly follows instructions (comma-separated list). No invented requirements. |
| Text 4 | No  | No  | A1, A2 |  |  |  |  |

---

### 2. Textarea (textarea)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Textarea A | A1, A2 |  |  |  |  |
| Textarea B (optional) | A1, A2 |  |  |  |  |

---

### 3. WYSIWYG (wysiwyg)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| WYSIWYG A | A1, A2 |  |  |  |  |

---

### 4. True / False (true_false)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Toggle A (no conditional logic) | A1, A2, A3 |  |  |  |  |
| Toggle B (with conditional logic) | A1, A2, A3 |  |  |  |  |

---

### 5. Select (select)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Select A | A1, A2, A3 |  |  |  |  |

---

### 6. Radio (radio)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Radio A | A1, A2, A3 |  |  |  |  |

---

### 7. Checkbox (checkbox)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Checkbox A | A1, A2, A3 |  |  |  |  |

---

### 8. URL (url)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| URL A | A1, A2, A4 |  |  |  |  |

---

### 9. Email (email)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Email A | A1, A2, A4 |  |  |  |  |

---

### 10. Number (number)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Number A | A1, A2, A4 |  |  |  |  |

---

### 11. Image (image)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Image A | A1, A2, A4 |  |  |  |  |

---

### 12. File (file)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| File A | A1, A2, A4 |  |  |  |  |

---

### 13. Date Picker (date_picker)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Date A | A1, A2, A4 |  |  |  |  |

---

### 14. Taxonomy (taxonomy)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Taxonomy A | A1, A2, A3 |  |  |  |  |

---

### 15. Post Object (post_object)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Post Object A | A1, A2, A3 |  |  |  |  |

---

### 16. Relationship (relationship)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Relationship A | A1, A2, A3 |  |  |  |  |

---

### 17. Group (group)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Group A | A1, A2, A3 |  |  |  |  |

---

### 18. Repeater (repeater)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Repeater A | A1, A2, A3 |  |  |  |  |

---

### 19. Google Map (google_map)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|--------|---------|--------|---------------|-------------|-------|
| Map A | A1, A2, A4 |  |  |  |  |

---

### 20. Other / site-specific fields

| Field type | Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|-----------|----------|---------|--------|---------------|-------------|-------|
|           |          |         |        |               |             |       |

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
|-------|--------|---------------|-------------|-------|
| B1 |  |  |  |  |
| B2 |  |  |  |  |
| B3 |  |  |  |  |
| B4 |  |  |  |  |
| B5 (optional) |  |  |  |  |

---

### C2 – Multi context

| Prompt | Rating | Issue logged? | Last tested | Notes |
|-------|--------|---------------|-------------|-------|
| B1 |  |  |  |  |
| B2 |  |  |  |  |
| B3 |  |  |  |  |
| B4 |  |  |  |  |
| B5 (optional) |  |  |  |  |

---

### C3 – CPT context

| Prompt | Rating | Issue logged? | Last tested | Notes |
|-------|--------|---------------|-------------|-------|
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
