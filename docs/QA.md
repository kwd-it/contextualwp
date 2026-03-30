# QA Checklist (v1.0)

**This document now serves as a Core Regression Baseline for v1.0.**

It is **not** the only QA process for the project post-v1. Use it when you need a **repeatable regression check** against behaviour that shipped in the v1.0 line: field helper flows, admin chat contexts, and the global pass criteria below. Feature work and sector packs may need **additional** checks (see **QA strategy post-v1**).

Purpose: lightweight, repeatable QA.  
No need to paste model outputs. Run the prompt, judge the response, rate it, and move on.

Ratings:
- ✅ Good – correct, concise, editor-friendly
- ⚠️ Average – mostly OK but could be clearer/shorter/more practical
- ❌ Bad – incorrect, confusing, invents behaviour, or ignores intent

Date format: YYYY-MM-DD

---

## QA strategy post-v1

After v1.0, quality work splits by **surface**:

**Core QA** (use this document’s baseline, plus targeted checks for each change):

- **Endpoint stability:** documented routes, methods, and error behaviour stay consistent unless versioned as breaking (see [COMPATIBILITY.md](COMPATIBILITY.md)).  
- **Auth and permissions:** only the intended roles and capabilities reach protected routes and admin features.  
- **Caching behaviour:** TTL and invalidation assumptions still match README and real responses.  
- **Chat and AskAI reliability:** no new systematic failures; responses remain grounded and editor-focused where applicable.  
- **No schema leakage:** internal keys, paths, and unsafe metadata do not appear in user-facing or integrator-facing output.
- **Sector pack registry (core):** the ContextualWP Packs submenu lists only packs that registered in the current request; with none registered, the empty state matches documentation and core behaviour elsewhere is unchanged.

**Sector pack QA** (per pack; not fully covered by the tables below):

- **Prompt quality** and tone for the intended sector.  
- **Sector-specific grounding** (correct use of pack-supplied context, no invented site structure).  
- **Safe fallback** when the pack is inactive or partial data is present.  
- **No interference with core:** with the pack off, core behaves as documented; with the pack on, core contracts remain intact.

Pack boundaries and expectations: [PACK-SPEC.md](PACK-SPEC.md).

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
| Textarea A | A1, A2 | ✅ Good | No | 2026-03-29 | Responses are now schema-grounded. No layout, template, or content-structure assumptions. A1 is concise and accurate. A2 provides safe, editor-focused guidance without inventing rules or tone. |
| Textarea B (optional) | A1, A2 | ⚠️ Average | No | 2026-03-29 | Behaviour is now grounded and safe. Minor verbosity and slightly generic guidance remain, but no incorrect assumptions or invented structure. Previous overreach issue resolved. |

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
|----------|---------|--------|---------------|------------|-------|
| Checkbox A | A1, A3 | ✅ Good | No | 2026-03-16 | AskAI icon now appears for checkbox fields. A1 and A3 responses are schema-grounded, correctly limited to stored values and explicit logic, and contain no frontend/template assumptions. Coverage gap resolved. |

---

### 8. URL (url)

| Instance | Prompts | Rating     | Issue logged? | Last tested  | Notes |
|----------|---------|------------|---------------|-------------|-------|
| URL A    | A1, A2, A4 | ⚠️ Average | No            | 2026-02-19  | Correct purpose and formatting guidance. Minor frontend/display assumptions (e.g., “visitors can open the tour”), and inferred best practices (public link, avoid shortened URLs). No invented validation rules or conditional logic. |

---

### 9. Email (email)

| Instance | Prompts     | Rating     | Issue logged? | Last tested  | Notes |
|----------|------------|------------|---------------|-------------|-------|
| Email A  | A1, A2, A4 | ⚠️ Average | Yes           | 2026-02-19  | A1 and A2 appropriate and practical. A4 incorrectly states “This field is required” (not defined in schema). Also lacks explicit email format constraint guidance (e.g., standard email structure). Minor usage inference (“site communications”). |

---

### 10. Number (number)

| Instance | Prompts     | Rating | Issue logged? | Last tested  | Notes |
|----------|------------|--------|---------------|-------------|-------|
| Number A | A1, A2, A4 | ✅ Good | No            | 2026-02-19  | Correctly identifies bedroom count purpose and accurately reflects configured validation (Range: 1–9). No invented constraints or behaviour. Clear whole-number guidance. |

---

### 11. Image (image)

| Instance | Prompts     | Rating     | Issue logged? | Last tested  | Notes |
|----------|------------|------------|---------------|-------------|-------|
| Image A  | A1, A2, A4 | ⚠️ Average | Yes           | 2026-02-19  | Correctly reflects field constraints from ACF (required; PNG-only). Transparency/branding guidance is reasonable. Minor overreach: assumes site-wide placement/usage (e.g., header/branded outputs) not defined in schema/instructions. |

---

### 12. File (file)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| File A (Developments → Brochure) | A1, A2, A4 | ✅ Good | No | 2026-02-20 | Correctly reflects schema: PDF-only (mime_types: pdf). No invented required rules or behaviour. Practical guidance (filename, compression, accessibility) clearly framed as best practice, not constraints. Minor frontend usage inference acceptable and consistent with field purpose. |

---

### 13. Date Picker (date_picker)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Date A (CPT: Careers Job Role → Closing Date) | A1, A2, A4 | ✅ Good | No | 2026-03-16 | AskAI icon now appears for date_picker fields. Responses are grounded and compliant, with no invented formatting or behavioural assumptions. Coverage gap resolved. |

---

### 14. Taxonomy (taxonomy)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Taxonomy A (Block: Helping You Move → Select Scheme Type) | A1, A3 | ✅ Pass | Yes | 2026-03-14 | A1 and A3 now fully grounded in schema. Responses correctly describe taxonomy assignment and stored value changes without frontend, template, or visitor-facing assumptions. No overreach observed. |

---

### 15. Post Object (post_object)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Post Object A (CPT: Plots → Development) | A1, A3 | ⚠️ Average | Yes | 2026-02-20 | Correctly explains linking to a Development post. However, response assumes frontend population of title, permalink, featured image, and development fields — behaviour not guaranteed by schema or conditional logic. Mild implementation overreach. |

---

### 16. Relationship (relationship)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Relationship A (Block: Development Helping You Move → Moving Schemes) | A1, A3 | ⚠️ Average | Yes | 2026-02-20 | Correctly identifies that the field stores links to one or more `helping_you_move` posts. However, it asserts template/front-end usage (“used by the template/front-end”, “for lists/front-end displays/modules”) which is not guaranteed by schema/instructions/conditional logic. Implementation overreach. |

---

### 17. Group (group)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Group A (Developments → Address) | A1, A3 | ⚠️ Average | No | 2026-03-17 | AskAI icon now appears on group container fields. A1 response is correct and schema-grounded. A3 remains structural and safe but is slightly descriptive rather than clearly explaining what changes when values are edited. No frontend/template assumptions. Coverage gap resolved. |

---

### 18. Repeater (repeater)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Repeater A (Developments → Additional Featured Images) | A1, A3 | ⚠️ Average | No | 2026-03-17 | AskAI icon now appears on repeater container fields. A1 response correctly explains repeating structure and respects schema constraints (max 2). A3 response is safe and structural but could more clearly describe add/remove/edit row behaviour. No frontend/template assumptions. Coverage gap resolved. |

---

### 19. Google Map (google_map)

| Instance | Prompts | Rating | Issue logged? | Last tested | Notes |
|----------|---------|--------|---------------|-------------|-------|
| Map A (Careers Job Role → Job Role Location Address) | A1, A2, A4 | ✅ Good | No | 2026-02-22 | No invented constraints. Correctly explains map/address purpose. Instructions are empty in JSON and response reflects that. Minor frontend inference (“shown for the role”) but acceptable and not misleading. |

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
| B1 | ✅ Good | No | 2026-02-25 | Accurate 5-bullet summary of Land & Planning page. Correctly references planning lifecycle, place-led design, collaboration with LPAs, community engagement (Lancaster Gate), sustainability, craftsmanship and community infrastructure. No invented behaviour or schema/media leakage. |
| B2 | ✅ Good | No | 2026-03-29 | Now returns practical clarity and tone improvements for page content without runtime failure. Suggestions are grounded in the actual page copy, including repetition, wording, grammar, heading clarity, formatting, and tone consistency. No invented claims or schema/meta leakage. |
| B3 | ✅ Good | No | 2026-02-25 | SEO title and meta description are concise, within requested length, and grounded in actual page themes (land promotion, consent, place-led design, community engagement). No invented claims or drift. |
| B4 | ⚠️ Average | No | 2026-02-25 | Accurate and grounded in page content with no invented facts. However, output is verbose and more analytical than a “simple table”; structure is heavy rather than concise/editor-friendly. |
| B5 (optional) | ✅ Good | No | 2026-02-25 | Clear, concise rewrite of the intro. Maintains original meaning and positioning without adding new claims or changing tone. |

---

### C2 – Multi context

| Prompt | Rating | Issue logged? | Last tested | Notes |
|--------|--------|---------------|-------------|-------|
| B1 | ⚠️ Average | No | 2026-02-26 | Now returns a thematic summary grounded in real rendered site content (no schema/ACF/media inventory dump). Specific figures and pricing included; confirm they exist explicitly in source content to rule out inference. |
| B2 | ✅ Good | No | 2026-02-26 | Practical, structured suggestions across multiple recent pages/posts. No schema/meta dump. Clear, actionable tone improvements. Confirmed stable after OpenAI provider fix. |
| B3 | ✅ Good | No | 2026-02-26 | SEO title and meta description within requested limits. Grounded in site themes and geography. No invented claims or runtime issues. |
| B4 | ⚠️ Average | No | 2026-02-26 | Structured output returned correctly in table format. However, table is overly dense and not “simple”; rows contain excessive detail (names, phone numbers, URLs, internal page IDs). Needs prompt tuning for brevity and simplicity. |
| B5 (optional) | ✅ Good | No | 2026-02-26 | Clearer, more engaging rewrite while preserving all original meaning, figures, and named developments. No invented claims or tone drift. |

---

### C3 – CPT context

| Prompt | Rating | Issue logged? | Last tested | Notes |
|--------|--------|---------------|-------------|-------|
| B1 | ✅ Good | No | 2026-03-01 | 5 bullet summary strictly grounded in CPT content (3–5 bedroom homes; prices from £500,000; 5% deposit paid up to £33,750 available until November on selected homes; “FINAL HOMES REMAINING” / over 80% sold; 93 homes forming part of the wider 300-home Ashfield Estate consortium; located three miles from Romsey and ten miles from Southampton; country park and open pasture grassland nearby). |
| B2 | ✅ Good | No | 2026-03-29 | Now returns practical, grounded clarity and tone improvements based on real CPT content. Suggestions correctly focus on tightening wording, reducing repetition, improving consistency, readability, and tone without inventing facts or claiming content is missing. |
| B3 | ✅ Good | No | 2026-03-01 | SEO output scoped only to information present in CPT (Mountbatten Park, North Baddesley, 3–5 beds, from £500,000, final homes remaining, 5% deposit incentive). No invented claims or cross-development bleed. |
| B4 | ✅ Good | No | 2026-03-01 | Table output reflects information explicitly present in CPT content, including Romsey/Southampton distances and Ashfield Estate context. No evidence of invented amenities, incentives, or cross-site contamination. |
| B5 (optional) | ✅ Good | No | 2026-03-01 | Intro rewrite remained grounded in CPT content (location, home range, final homes remaining, countryside/park setting). No invented claims or scope bleed. |

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
