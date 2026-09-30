---
target: resources/views/livewire/courrier
total_score: 30
p0_count: 0
p1_count: 2
timestamp: 2026-07-19T16-33-10Z
slug: resources-views-livewire-courrier
---
# Critique — resources/views/livewire/courrier (register: product)

Method: single-context (DEGRADED — sub-agents not spawned per harness policy)

## Design Health Score: 30/40 (Good)

| # | Heuristic | Score | Key issue |
|---|---|---|---|
| 1 | Visibility of System Status | 3 | Toasts+loading+active nav present; no skeletons |
| 2 | Match System / Real World | 4 | Domain language faithful to paper form |
| 3 | User Control & Freedom | 3 | Cancel/back/confirm; cascade & accuse have no undo |
| 4 | Consistency & Standards | 4 | Consistent btn/card/field vocabulary |
| 5 | Error Prevention | 3 | Confirm delete, min:1 validation, anti-double-submit |
| 6 | Recognition vs Recall | 3 | Labeled nav; entity codes & mentions unexplained |
| 7 | Flexibility & Efficiency | 2 | No keyboard shortcuts, no bulk actions |
| 8 | Aesthetic & Minimalist | 3 | Clean; recipient fiche is 5-6 stacked cards |
| 9 | Error Recovery | 3 | Inline errors; some generic messages |
| 10 | Help & Documentation | 2 | No contextual help/tooltips/docs |

## Anti-Patterns
Detector: 3x overused-font (Fraunces) in rh.blade.php (19,28,78), warning only. No gradient-text/eyebrow/side-stripe/hero-metric. LLM: not AI-slop; only soft tell = card density on recipient fiche.

## Priority Issues
- [P1] Muted text contrast ~3.8:1 (--muted #6a7568 on light bg) fails AA for small text. Fix: darken muted toward ink. -> audit/polish
- [P1] Bureau courrier efficiency: accuse/cascade/process one-at-a-time, no bulk, no shortcuts. -> shape
- [P2] No contextual help: entity codes (DOE/DFC), "Soit transmis" mentions unexplained. -> clarify
- [P2] Card density on recipient fiche (5-6 stacked cards). -> layout
- [P3] Fraunces overused font. -> typeset

## Persona Red Flags
- Alex: no keyboard shortcuts, one-at-a-time actions.
- Sam: muted contrast fails AA; but focus-visible, reduced-motion, text-not-color badges, alt/title present.
- Jordan: codes/mentions jargon, no guided help; mitigated by Instructions card + labeled nav.

## Minor
- Filters lack explicit reset button. No loading skeletons. Free-text signataire_nom risks inconsistency.
