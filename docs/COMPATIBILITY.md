# Versioning and compatibility

This document summarises how **semantic versioning** applies to ContextualWP **core** releases. Sector packs should declare compatible core versions in their own readme; they follow the same spirit (avoid breaking adopters without a major bump).

---

## Patch

- Bug fixes and reliability improvements  
- No change to the public contract (endpoints, response shapes, documented hook semantics)  
- Safe to upgrade for integrators who rely only on documented behaviour  

---

## Minor

- New features, new optional parameters, or new **backwards-compatible** extension points  
- New endpoints or new filters that **do not** change existing defaults in a breaking way  
- Existing consumers should keep working without code changes  

---

## Major

- **Breaking changes** for integrators or site owners  
- Changes to endpoint paths, methods, or **documented** request or response contracts  
- **Schema contract** changes where consumers could reasonably depend on previous shape or fields  
- **Documented** hook or filter behaviour changes that alter prior guarantees  

When in doubt, treat a change as **major** if an existing integration could fail or misbehave without updates.
