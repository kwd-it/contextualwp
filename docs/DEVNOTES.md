# ContextualWP – Development & Release Workflow

This document describes the intended workflow for developing, testing, and releasing ContextualWP.  
It focuses on **process**, not domain-specific behaviour.

---

## 1. Decide What to Build Next

- Identify the single biggest pain point or improvement opportunity.
- Start a fresh ChatGPT conversation.
- Share (as relevant):
  - Current schema
  - Current CHANGELOG
  - A short explanation of what feels weakest right now
- Prioritise:
  - Clear user-facing value
  - Portfolio quality over edge-case perfection

---

## 2. Create a Branch

Ask ChatGPT for a branch name if needed.

Branch names should reflect the **primary intent**:

- `feat/` – new or improved user-facing behaviour  
- `fix/` – bug fix for behaviour already on `main`  
- `docs/` – documentation only  
- `refactor/` – internal restructuring, no behaviour change  
- `test/` – tests only  
- `chore/` – maintenance or tooling changes  

Example branch name:

`feat/schema-intent-routing`

---

## 3. Implement the Task

- Provide Cursor with a **focused prompt** that only covers the task at hand.
- Iterate freely:
  - prompt → change → test → refine
- Do **not** worry about commit types during iteration.

---

## 4. Test the Result

After Cursor finishes:

- Ask ChatGPT how best to test the change.
- Run those tests manually (chat UI, Postman, admin UI, etc.).
- If something is wrong, refine and retest.

Fixing issues found during testing **does not change** the eventual commit type.

---

## 5. Commit the Feature or Fix

Once behaviour is correct:

- Choose commit type based on the **final outcome**, not iteration history:
  - `feat:` if capability was added or meaningfully improved
  - `fix:` only if correcting behaviour that existed on `main`
- One commit is perfectly acceptable.

Example commit message:

`feat(schema): improve intent-aware ACF query handling`

---

## 6. Decide Version Bump (on the Branch)

Choose version based on impact:

- **MAJOR** – breaking changes  
- **MINOR** – new or improved capability  
- **PATCH** – bug fixes to released behaviour  

---

## 7. Bump Version & Docs

Use Cursor to update (if applicable):

- Main plugin file version header
- `composer.json` version
- `README.md` (only if version is referenced)
- `CHANGELOG.md`

Commit this separately with a release-style commit, for example:

`chore(release): v0.6.3`

---

## 8. Merge, Tag, Clean Up

- Merge branch into `main`
- Tag the release **on `main`**
- Delete the feature branch

---

## ACF AskAI Debug Logging

To verify the ACF AskAI scripts are running and fields are being enhanced, add to `wp-config.php`:

```php
define( 'CONTEXTUALWP_ACF_ASKAI_DEBUG', true );
```

When enabled, the browser console will show `[ContextualWP ACF AskAI]` logs for field enhancements. Disabled by default.

---

## Notes

- DEVNOTES is intentionally domain-agnostic.
- CHANGELOG documents **what shipped**, not how it was built.
- Commit types describe the **difference to `main`**, not internal iteration.
