---
description: "Dependency-ordered Laravel/Inertia feature task template"
---

# Tasks: [FEATURE NAME]

**Input**: Design documents from `/specs/[###-feature-name]/`

**Prerequisites**: `plan.md` and `spec.md`; use `research.md`, `data-model.md`,
`quickstart.md`, and `contracts/` when present.

**Tests**: Tests are MANDATORY. Every Action behavior requires a direct Pest unit test,
and every HTTP behavior requires a Pest feature integration test.

**Organization**: Group tasks by user story so each story can be implemented, tested,
and delivered independently.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: May run in parallel because it changes different files and has no dependency.
- **[Story]**: User story identifier, such as US1, US2, or US3.
- Every task MUST name its exact target file path.

## Path Conventions

- Actions: `app/Actions/`
- Controllers: `app/Http/Controllers/`
- Form Requests: `app/Http/Requests/`
- Policies: `app/Policies/`
- Inertia React pages/components: `resources/js/pages/`, `resources/js/components/`
- Wayfinder-generated calls: `resources/js/actions/`, `resources/js/routes/`
- Action unit tests: `tests/Unit/`
- HTTP feature tests: `tests/Feature/`
- Browser tests when PHP cannot prove frontend behavior: `tests/Browser/`

The examples below are placeholders. `/speckit-tasks` MUST replace them with concrete
tasks derived from the specification and plan.

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm conventions and prepare only shared prerequisites needed by the
feature. Do not add dependencies without approval.

- [ ] T001 Confirm installed package versions and inspect comparable project code
- [ ] T002 Read applicable `.ai/rules` and relevant skill guidance
- [ ] T003 [P] Identify exact formatting, linting, type, build, and test commands

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Complete cross-story prerequisites before user story implementation.

**CRITICAL**: No user story work begins until this phase is complete.

- [ ] T004 Create required database migrations, models, and factories
- [ ] T005 [P] Implement shared policies and authorization boundaries
- [ ] T006 [P] Configure routes, middleware, and required rate limiters
- [ ] T007 Define contracts for external boundaries that require substitution in tests
- [ ] T008 Configure safe error handling and non-sensitive logging
- [ ] T009 Configure environment-backed settings without hardcoded secrets

**Checkpoint**: Shared foundations are ready and security boundaries are testable.

---

## Phase 3: User Story 1 - [Title] (Priority: P1) MVP

**Goal**: [Describe the user value delivered.]

**Independent Test**: [Explain how to verify this story alone.]

### Tests for User Story 1 (MANDATORY)

> Write tests first and confirm they fail for the expected reason before implementation.

- [ ] T010 [P] [US1] Add direct Action success, rule-failure, and edge-case unit tests in `tests/Unit/[Action]Test.php`
- [ ] T011 [P] [US1] Add HTTP auth, authorization, validation, response, and persistence integration tests in `tests/Feature/[Feature]Test.php`
- [ ] T012 [P] [US1] Add component or browser coverage in `[path]`, or document why PHP tests fully prove the UI behavior

### Implementation for User Story 1

- [ ] T013 [P] [US1] Create story-specific model/factory changes in `[paths]`
- [ ] T014 [P] [US1] Create story-specific policy changes in `[paths]`
- [ ] T015 [US1] Implement the single-purpose Action in `app/Actions/[Action].php`
- [ ] T016 [US1] Implement validation/authorization in `app/Http/Requests/[Request].php`
- [ ] T017 [US1] Implement the thin HTTP adapter in `app/Http/Controllers/[Controller].php`
- [ ] T018 [US1] Implement the Inertia React UI using Wayfinder in `[paths]`
- [ ] T019 [US1] Run the US1 unit, feature, and applicable frontend tests

**Checkpoint**: User Story 1 works and is independently verified.

---

## Phase 4: User Story 2 - [Title] (Priority: P2)

**Goal**: [Describe the user value delivered.]

**Independent Test**: [Explain how to verify this story alone.]

### Tests for User Story 2 (MANDATORY)

- [ ] T020 [P] [US2] Add direct Action unit tests in `tests/Unit/[Action]Test.php`
- [ ] T021 [P] [US2] Add HTTP integration tests in `tests/Feature/[Feature]Test.php`
- [ ] T022 [P] [US2] Add applicable component/browser tests or record why they are N/A

### Implementation for User Story 2

- [ ] T023 [P] [US2] Create required model, factory, and policy changes in `[paths]`
- [ ] T024 [US2] Implement the Action and injected dependencies in `[paths]`
- [ ] T025 [US2] Implement the Form Request and thin controller in `[paths]`
- [ ] T026 [US2] Implement the Inertia React UI using Wayfinder in `[paths]`
- [ ] T027 [US2] Run the US2 unit, feature, and applicable frontend tests

**Checkpoint**: User Stories 1 and 2 both work independently.

---

## Phase 5: User Story 3 - [Title] (Priority: P3)

**Goal**: [Describe the user value delivered.]

**Independent Test**: [Explain how to verify this story alone.]

### Tests for User Story 3 (MANDATORY)

- [ ] T028 [P] [US3] Add direct Action unit tests in `tests/Unit/[Action]Test.php`
- [ ] T029 [P] [US3] Add HTTP integration tests in `tests/Feature/[Feature]Test.php`
- [ ] T030 [P] [US3] Add applicable component/browser tests or record why they are N/A

### Implementation for User Story 3

- [ ] T031 [P] [US3] Create required model, factory, and policy changes in `[paths]`
- [ ] T032 [US3] Implement the Action and injected dependencies in `[paths]`
- [ ] T033 [US3] Implement the Form Request and thin controller in `[paths]`
- [ ] T034 [US3] Implement the Inertia React UI using Wayfinder in `[paths]`
- [ ] T035 [US3] Run the US3 unit, feature, and applicable frontend tests

**Checkpoint**: All selected stories work and remain independently testable.

---

[Add further user story phases using the same order and mandatory test layers.]

## Final Phase: Cross-Cutting Verification

- [ ] TXXX Review controllers for HTTP mapping only and Actions for HTTP independence
- [ ] TXXX Review all Action inputs for validated/safe data boundaries
- [ ] TXXX Review authorization, sensitive data, logs, output safety, and abuse controls
- [ ] TXXX Review query count, bounds, transactions, locks, timeouts, and retries
- [ ] TXXX Run affected Pest suites and all required frontend checks
- [ ] TXXX Run `vendor/bin/pint --dirty --format agent` for modified PHP
- [ ] TXXX Run dependency audits required by the plan
- [ ] TXXX Validate the feature quickstart and re-run the Constitution Check

---

## Dependencies & Execution Order

### Phase Dependencies

- Setup has no dependencies.
- Foundational depends on Setup and blocks all user stories.
- User stories may proceed independently after Foundational.
- Cross-cutting verification depends on all selected stories.

### Within Each User Story

1. Write Action unit tests and HTTP feature tests; confirm expected failures.
2. Create required models, factories, policies, and external-boundary contracts.
3. Implement the Action.
4. Implement the Form Request and thin controller.
5. Implement the Inertia React UI with Wayfinder.
6. Run the story's tests before moving to another story.

Tasks marked `[P]` may run in parallel only when they do not edit the same files or depend
on unfinished behavior. Different user stories may run in parallel after the Foundational
phase when their files and domain invariants do not conflict.

## Delivery Strategy

Deliver the smallest valuable P1 story first. Stop at each checkpoint to verify the story
independently. Add later stories incrementally without weakening previous security or test
coverage. Do not mark any task complete while relevant tests fail or required checks have
not run.
