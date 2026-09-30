---
target: resources/views/livewire/courrier
total_score: 33
p0_count: 0
p1_count: 0
timestamp: 2026-07-19T17-59-09Z
slug: resources-views-livewire-courrier
---
# Critique — resources/views/livewire/courrier (product) — run 3

Method: single-context (DEGRADED per harness policy). Detector: 0 anti-patterns (was 3x Fraunces; font replaced with Spectral).

## Design Health Score: 33/40 (Good) — 30 -> 32 -> 33

H8 Aesthetic 3->4 (card density reduced on recipient fiche, contrast AA, distinctive Spectral serif). Others: H1 3, H2 4, H3 3, H4 4, H5 3, H6 3, H7 3, H9 3, H10 3.

## Resolved (all 5 critique issues)
- P1 contrast (muted 6:1 AA)
- P1 efficiency (column sorting)
- P2 contextual help (entity tooltips)
- P2 card density (header de-carded, replies+diligences merged)
- P3 typeset (Fraunces -> Spectral)

## Remaining minor (polish backlog)
- Filters lack explicit reset button.
- No loading skeletons (Livewire; acceptable).
- Free-text signataire_nom risks spelling inconsistency.
