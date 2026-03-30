# Sector pack specification (lightweight)

This is a **high-level** specification for **sector packs**: optional plugins that extend ContextualWP. It is intentionally simple so new packs can align without locking in low-level implementation detail.

---

## What a sector pack is

- A **separate WordPress plugin** (its own slug, headers, and lifecycle).  
- It **registers with ContextualWP** using the extension mechanisms core provides (filters, hooks, registration APIs as documented).  
- It **must be optional**: deactivating or removing the pack must leave core working for generic use cases.  
- It **must not assume** that core will be forked or patched; packs integrate through supported surfaces only.

---

## Responsibilities of core

- Provide a **stable, sector-agnostic** engine: context APIs, admin chat and AskAI flows where applicable, auth, caching behaviour, and documented extension points.  
- Keep **default behaviour** sensible when **no** sector pack is active.  
- Document **contracts** (README, compatibility notes) so packs and sites can plan upgrades.

---

## Responsibilities of packs

- Encode **sector-specific** prompts, guidance, and schema interpretation aids.  
- Respect **core contracts**: do not rely on undocumented internals or core file edits.  
- Fail **safely** when the pack is inactive or when data is missing (no hard dependency on pack-only globals in core).  
- Version and document **their own** compatibility range with ContextualWP core.

---

## Extension mechanisms (conceptual)

Packs extend core through **high-level** patterns only:

- **Filters and actions** published by ContextualWP for context, prompts, manifest schema, AI payload or response, and related hooks (see core README).  
- **Registration-style** integration where core defines an API for registering templates or metadata, rather than packs replacing core classes.  

Exact hook names and signatures live in core code and README; this spec does not duplicate them.

---

## Future considerations

- Additional **registration APIs** or **namespaced pack metadata** may appear in minor releases if they remain backwards compatible.  
- **Multiple packs** on one site may need explicit priority or capability rules; document pack interaction in each pack’s readme when relevant.  
- Core will continue to prioritise **optional** extensions and **no required sector** in the base plugin.
