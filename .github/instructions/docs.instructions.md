---
applyTo: "docs/**"
---

# Documentation instructions

- The files in `docs/` are the specification. Do not edit `prd.md`, `technical-spec.md`, `database-design.md`, `erd.mmd`, `openapi.yaml`, or `data-flow.mmd` unless the developer explicitly asks for a specification change.
- If implementation shows that a document is wrong or incomplete, stop and describe the problem and a proposed change. The developer decides and records it in the spec change log in `docs/ai-workflow.md`.
- When a change is approved, update every affected document so they stay consistent: PRD, technical specification, database design, ERD, and OpenAPI.
- `docs/testing.md` and `docs/ai-workflow.md` record evidence and process. Never invent test results, dates, or outcomes: only write what was actually run or done.
- Keep Mermaid files valid: `erd.mmd` and `data-flow.mmd` contain exactly one diagram each and no code fences.