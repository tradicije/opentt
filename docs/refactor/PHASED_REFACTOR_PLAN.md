# LibreTT Phased Refactor Plan (Stability-First)

This plan is intentionally conservative:

- no new features during refactor
- behavior parity first
- small, reversible steps
- one subsystem at a time

## Global Rules

1. Do not change public contracts listed in `docs/refactor/API_CONTRACT.md`.
2. Do not mix refactor and feature work in the same PR.
3. Keep each PR focused on one subsystem boundary.
4. Every PR must pass the same smoke checklist.
5. Prefer extraction + delegation over rewrites.

## Phase 0 - Freeze and Baseline

Goal: create a safe runway for iterative refactor.

Tasks:

- Freeze feature development temporarily.
- Define and keep a smoke checklist (frontend shortcodes + critical admin flows).
- Capture architecture metrics baseline (largest files, module boundaries, static entry points).
- Add this phased plan and reference it from contributing docs.

Definition of done:

- Documented plan accepted.
- Team follows no-feature rule during refactor window.
- Baseline smoke checklist available and used for each PR.

## Phase 1 - Query Layer Extraction (Low Risk, High Return)

Goal: move DB read logic from monolith/trait to small PSR-4 query services.

Order:

1. Matches read queries
2. Standings/stats read queries
3. Club/player read queries

Rules:

- zero output changes
- method signatures in public shortcode entry points remain stable
- monolith delegates to new services

Definition of done:

- New query service classes are primary implementation.
- Monolith query methods become thin delegates or wrappers.
- Smoke checklist passes with no visible behavior changes.

## Phase 2 - Shortcode Wiring Decomposition

Goal: replace mega shortcode trait wiring with smaller providers/factories.

Order:

1. Match shortcodes provider
2. Club/player shortcodes provider
3. Standings/statistics shortcodes provider

Rules:

- preserve shortcode tags and attributes
- preserve rendering parity

Definition of done:

- `trait-opentt-unified-shortcodes.php` reduced to compatibility adapter only.
- Dependency wiring is split by domain.

## Phase 3 - Core Decomposition

Goal: shrink `OpenTT_Unified_Core` into an orchestrator.

Order:

1. Routing/bootstrap extraction
2. Admin action handler extraction
3. Search/discovery extraction
4. Legacy helpers extraction

Rules:

- keep existing hooks/actions/filters intact
- retain static compatibility wrappers where needed

Definition of done:

- Core file mostly bootstraps modules and delegates.
- No critical domain logic remains in giant methods.

## Phase 4 - User Portal Split

Goal: split `UserPortalManager` by responsibility.

Suggested modules:

- AuthController
- ProfileController
- RoleAccessService
- FrontendActionController

Definition of done:

- `UserPortalManager` becomes coordination layer.
- Behavior and routes unchanged.

## Phase 5 - Legacy Bridge Cleanup

Goal: keep `includes/` as compatibility facade only.

Tasks:

- remove dead wrappers after migration parity
- mark compatibility methods clearly
- document old->new map

Definition of done:

- clear separation:
  - `src/` = active implementation
  - `includes/` = legacy bridge

## Phase 6 - Hardening

Goal: prevent architectural regression.

Tasks:

- add PHPCS/PHPStan configs (incremental strictness)
- add max file size / complexity guardrails
- document architecture boundaries and PR checklist

Definition of done:

- baseline static checks run locally and in CI.
- architecture rules are enforced, not just documented.

## Recommended PR Size

- 200-600 LOC net change for normal refactor PRs
- avoid >1000 LOC unless pure mechanical extraction with proof

## Suggested Branching

- `codex/refactor-phase0-*`
- `codex/refactor-phase1-*`
- etc.

