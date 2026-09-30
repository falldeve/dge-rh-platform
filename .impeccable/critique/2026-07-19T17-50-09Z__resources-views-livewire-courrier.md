---
target: resources/views/livewire/courrier
total_score: 32
p0_count: 0
p1_count: 0
timestamp: 2026-07-19T17-50-09Z
slug: resources-views-livewire-courrier
---
# Critique — resources/views/livewire/courrier (register: product) — run 2

Method: single-context (DEGRADED — sub-agents not spawned per harness policy)

## Design Health Score: 32/40 (Good) — up from 30

Deltas vs run 1: H7 Flexibility 2->3 (column sorting), H10 Help 2->3 (contextual tooltips on entity codes). Transverse a11y: muted contrast now WCAG AA (6:1), focus-visible, prefers-reduced-motion.

Unchanged: H1 3 (loading added, no skeletons), H2 4, H3 3, H4 4, H5 3, H6 3 (tooltips help but codes still primary), H8 3 (contrast legible; card density remains), H9 3.

## Anti-Patterns
Detector: 3x overused-font (Fraunces) only. No new slop introduced by the refine/fix pass. LLM: soft tell = card density on recipient fiche.

## Resolved since run 1
- P1 contrast (--muted #6a7568 -> #545e4c, 6:1).
- P1 efficiency (partial): column sorting via SortableList trait on registre/archives/mes-courriers. Bulk actions / keyboard shortcuts not taken.
- P2 contextual help: title tooltips revealing full entity names on codes (historique, accuse, diligences).

## Remaining
- [P2] Card density on recipient fiche -> layout
- [P3] Fraunces overused font -> typeset
