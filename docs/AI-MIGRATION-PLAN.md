# AI migration planning (future)

This document describes a **future** direction for ContextualWP’s AI-assisted features when WordPress platform AI APIs are mature enough. It is **planning only**: nothing here removes, disables, or changes runtime behaviour by itself.

---

## 1. Current position

- **Primary product focus** is the **structured WordPress context layer**: MCP-compatible REST endpoints, schema and ACF metadata, interpretation, and sector pack extension points.
- **AI-assisted features remain available** for sites that use them. They are **secondary** to the structured context APIs.
- The following are **still present** and unchanged by this document:
  - Provider settings (OpenAI, Claude, Mistral, and related admin configuration)
  - **`/wp-json/contextualwp/v1/generate_context`**
  - **Global admin chat** (ContextualWP Chat)
  - **ACF AskAI** field helpers
- **No AI functionality has been removed** as part of publishing this plan.

---

## 2. Why migration is being considered

WordPress **7** introduces platform-level building blocks for AI integration, including:

- **AI Client** — unified client surface for provider calls from core and extensions
- **Connectors** — credential and provider connection management at the platform level
- **Abilities / MCP-related APIs** — structured capabilities and adapter patterns for agent workflows

Long term, ContextualWP should **not duplicate** WordPress-managed provider configuration, credential storage, and permission models if the platform offers stable, site-owner-friendly equivalents.

Before any removal of existing provider settings or direct HTTP integrations:

- **ACF AskAI** needs a clear, reliable path to call through WordPress AI Client (or an equivalent supported bridge).
- **`/generate_context`** needs a documented provider strategy and deprecation path for existing callers.

Migration is **deferred** until those APIs and practices are stable enough for production sites.

---

## 3. What should stay

Regardless of how AI transport is implemented later, these remain **core product** surfaces:

| Area | Rationale |
|------|-----------|
| **MCP / context endpoints** (`/wp-json/mcp/v1/*`, context listing and retrieval) | Primary integration contract for tools and agents |
| **`/wp-json/contextualwp/v1/schema`** and **`/acf_schema`** | Structured site and field metadata |
| **Schema interpretation layer** | AI-friendly summaries and relationship guidance without Schema.org JSON-LD from ContextualWP |
| **Sector pack registry and extension hooks** | Optional industry extensions without forking core |
| **Contextual Console integration role** | Documented consumer of structured context, not a replacement for core endpoints |

---

## 4. What may be refactored later

These are **candidates** for internal refactor or platform delegation—not commitments to remove:

- **ContextualWP-owned provider settings** (admin screens and stored credentials)
- **Direct OpenAI / Claude / Mistral HTTP calls** in core
- **Smart model selection** logic tied to plugin-owned settings
- **`/generate_context` transport layer** (request/response wiring to a provider)
- **Global admin chat** implementation and how it obtains completions
- **ACF AskAI transport layer** (how field helpers reach a model)

Refactors should preserve **documented behaviour** for integrators until an explicit deprecation window ends.

---

## 5. Migration conditions

Do **not** remove existing provider settings or direct AI functionality until **all** of the following are satisfied (or explicitly waived in a versioned release note with a migration guide):

1. **ACF AskAI** can call **WordPress AI Client** (or a supported core bridge) reliably across supported WordPress versions.
2. **Site owners** can manage AI credentials through **WordPress-managed connectors** with clear admin UX.
3. **Permissions and privacy** behaviour (who can trigger AI, what data is sent, logging) is documented and predictable for site owners and integrators.
4. There is a **fallback** or stated **minimum WordPress version** strategy so sites on older core are not left without a path (legacy settings, feature detection, or documented opt-out).
5. Existing **`/generate_context` callers** have a **deprecation path** (timeline, alternative endpoint or parameters, and changelog notice).

Until then: keep current AI-assisted features, settings, and endpoints as documented in README.

---

## 6. Possible phased plan

### Phase 1 — Stabilise and document (now)

- Keep all current AI-assisted features.
- Keep user-facing docs clear that **structured context** is the main product.
- Remove only **genuinely unused dead code** (no behaviour change for active features).

### Phase 2 — Bridge abstraction

- Introduce an **internal AI service / bridge** abstraction in core (implementation detail).
- **Experiment** with WordPress AI Client where the API exists and is stable on test sites.
- **Retain legacy provider settings** as fallback when platform AI is unavailable.

### Phase 3 — Prefer platform credentials

- Move **ACF AskAI** to WordPress-managed AI credentials where possible.
- **Decide** global chat policy: keep as-is, make opt-in, or retire—with product and support input.
- **Deprecate** old direct provider settings only after a safe transition period and documented migration.

### Phase 4 — Cleanup (optional)

- After a **deprecation window**, remove obsolete direct provider HTTP code **if** adoption and support burden justify it.
- Core structured context endpoints and interpretation remain regardless.

Phases may overlap; dates depend on WordPress core maturity, not a fixed ContextualWP release calendar.

---

## 7. Watch list

Track these before committing to Phase 3+:

| Topic | Why it matters |
|-------|----------------|
| **WordPress AI Client maturity** | Stability, error handling, and extension APIs |
| **Connectors API** | Permissions, credential storage, rotation, multisite |
| **Official examples** | Text generation, editor workflows, and admin patterns |
| **Abilities API and MCP Adapter adoption** | Alignment with agent/MCP tooling without duplicating manifest work |
| **ACF AskAI** | Field-level UX must stay editor-safe with any new transport |
| **Sector packs** | Packs must not assume plugin-owned provider keys; hooks should remain transport-agnostic where possible |

---

## Related docs

- [README.md](../README.md) — Authoritative endpoint and settings contract
- [DEVNOTES.md](DEVNOTES.md) — Development workflow
- [PACK-SPEC.md](PACK-SPEC.md) — Sector pack boundaries (structured context vs optional AI)
- [COMPATIBILITY.md](COMPATIBILITY.md) — Versioning when migration ships behaviour changes
