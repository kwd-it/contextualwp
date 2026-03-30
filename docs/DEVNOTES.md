# ContextualWP – Development & Release Workflow

This document describes the intended workflow for developing, testing, and releasing ContextualWP.  
It focuses on **process**, not domain-specific behaviour.

---

### Note on v1.0

**v1.0.0** is the first stable release. Post-v1, **stability** matters as much as new capability. Prefer small, reviewable changes, clear intent on each branch, and explicit consideration of regressions before merge.

---

## Pack architecture (high-level)

- **ContextualWP** is the core engine: sector-agnostic behaviour, REST and admin surfaces, and extension points.
- **Sector packs** are optional companion plugins that register with ContextualWP and extend it for specific industries or workflows.
- Packs **must not** change core plugin files or rely on undocumented internals. They integrate through filters, hooks, and registration APIs exposed by core.
- Core stays usable and coherent when no packs are active.

For pack responsibilities and expectations in more detail, see [PACK-SPEC.md](PACK-SPEC.md). Versioning expectations for core releases are in [COMPATIBILITY.md](COMPATIBILITY.md).

---

## A. Core plugin development

Routine core work follows the steps below. Treat **README.md** as the **authoritative user-facing contract**: endpoints, behaviour, and security claims there should match the product.

### Branch strategy

Use **one primary intent per branch**. Name the branch after that intent.

Examples of prefixes:

- `docs/` – documentation only  
- `feat/` – new or improved user-facing behaviour  
- `fix/` – bug fix for behaviour already on `main`  
- `refactor/` – internal restructuring, no intended behaviour change  
- `test/` – tests only  
- `chore/` – maintenance or tooling changes  

Example: `feat/schema-intent-routing`

### 1. Decide what to build next

- Identify the single biggest pain point or improvement opportunity.
- Start a fresh ChatGPT conversation.
- Share (as relevant):
  - Current **README** (authoritative contract)
  - Current schema (or the smallest excerpt that is enough to reason about the change)
  - Current CHANGELOG
  - A short explanation of what feels weakest right now
- Prioritise:
  - Clear user-facing value
  - Portfolio quality over edge-case perfection

### 2. Create a branch

Ask ChatGPT for a branch name if needed. Keep **one intent per branch** (see above).

### 3. Implement the task

- Provide Cursor with a **focused prompt** that only covers the task at hand.
- Iterate freely: prompt, change, test, refine.
- Do **not** worry about commit types during iteration.

### 4. Test the result

After Cursor finishes:

- Ask ChatGPT how best to test the change.
- Run those tests manually (chat UI, Postman, admin UI, etc.).
- If something is wrong, refine and retest.

**Regression scope:** decide whether **smoke** testing (quick paths that prove nothing obvious broke) is enough, or whether **full** regression against the core baseline in [QA.md](QA.md) is needed. Larger or riskier changes should lean toward full checks.

Fixing issues found during testing **does not change** the eventual commit type.

### 5. Impact review before merge

Before merging to `main`:

- Summarise **user-facing and API impact** (endpoints, auth, caching, hooks).
- Confirm **README** and, when shipping, **CHANGELOG** still match reality.
- Confirm **no unintended breaking changes**, or that they are versioned and documented per [COMPATIBILITY.md](COMPATIBILITY.md).

### 6. Commit the feature or fix

Once behaviour is correct:

- Choose commit type based on the **final outcome**, not iteration history:
  - `feat:` if capability was added or meaningfully improved
  - `fix:` only if correcting behaviour that existed on `main`
- One commit is perfectly acceptable.

Example commit message:

`feat(schema): improve intent-aware ACF query handling`

### 7. Decide version bump (on the branch)

Choose version based on impact:

- **MAJOR** – breaking changes  
- **MINOR** – new or improved capability  
- **PATCH** – bug fixes to released behaviour  

See [COMPATIBILITY.md](COMPATIBILITY.md) for practical rules.

### 8. Bump version and docs

Use Cursor to update (if applicable):

- Main plugin file version header
- `composer.json` version
- `README.md` (only if version is referenced)
- `CHANGELOG.md`

Commit this separately with a release-style commit, for example:

`chore(release): v1.0.0`

### 9. Merge, tag, clean up

- Merge branch into `main`
- Tag the release **on `main`**
- Delete the feature branch

---

## B. Sector pack development

Sector packs are **separate WordPress plugins** that depend on ContextualWP and extend it. **Core remains sector-agnostic:** industry-specific wording, templates, and assumptions live in packs, not in the main plugin.

Typical pack responsibilities:

- **Prompt templates** tailored to a sector or editorial workflow  
- **Richer schema interpretation** (e.g. manifest relationships, guided summaries) via supported filters and registration patterns  
- **Optional behaviour** wired through hooks and filters, without replacing core flows  

### What to share when working on a pack (e.g. with ChatGPT)

- The pack **README** or internal **spec** (goals, non-goals, user stories)  
- A **relevant schema excerpt** (field group, post type, or manifest slice). You do **not** need the full site schema every time.  
- **Target user and problem** (who installs the pack, what mistake or friction it prevents)  
- **Pack-specific QA expectations** (prompt quality, grounding rules, safe behaviour when data is missing)  

**Do not** paste the full plugin or full schema dump unless debugging a specific integration issue. Prefer **sector-specific context** and the smallest artefact that still captures the behaviour you are changing.

For architecture boundaries between core and packs, see [PACK-SPEC.md](PACK-SPEC.md).

---

## ACF AskAI debug logging

To verify the ACF AskAI scripts are running and fields are being enhanced, add to `wp-config.php`:

```php
define( 'CONTEXTUALWP_ACF_ASKAI_DEBUG', true );
```

When enabled, the browser console will show `[ContextualWP ACF AskAI]` logs for field enhancements. Disabled by default.

---

## Notes

- DEVNOTES is intentionally domain-agnostic about any one sector; sector detail belongs in pack docs.
- CHANGELOG documents **what shipped**, not how it was built.
- Commit types describe the **difference to `main`**, not internal iteration.
