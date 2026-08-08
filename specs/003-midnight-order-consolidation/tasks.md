# Tasks: Midnight Order Consolidation

**Input**: Design documents from `specs/003-midnight-order-consolidation/`

**Required references**: `plan.md`, `spec.md`, `research.md`, `data-model.md`,
`contracts/web-routes-and-props.md`, and `quickstart.md`

**Tests**: Tests are mandatory. Write the named test before its implementation, run it,
and confirm it fails for the expected missing behavior. After implementation, rerun that
same test and do not continue until it passes.

**Junior-agent rule**: Work on one unchecked task at a time in ID order unless a task is
explicitly marked `[P]`. Read the referenced design section before editing. Do not invent
names, fields, states, routes, or abstractions. If a prerequisite, command, field, or
existing convention differs from the plan, stop and report the exact difference.

## Checklist Format

- `[P]` means the task may be completed in parallel because it edits a different file
  and does not consume unfinished behavior.
- `[US1]` through `[US4]` map directly to the four user stories in `spec.md`.
- Every implementation task names its exact target file or generated migration suffix.
- Use `php artisan make:* --no-interaction` for PHP files where an Artisan generator
  exists. Never hand-create migration timestamps or edit generated Wayfinder files.

## Phase 1: Setup and Hard Upstream Gate

**Purpose**: Prove Phase 001 and Phase 002 exist before creating any Phase 003 source
file. This phase is a hard stop, not optional preparation.

- [ ] T001 Run the exact required-file check from `specs/003-midnight-order-consolidation/quickstart.md`; if any Phase 001/002 path is missing, STOP without generating Phase 003 code and report every missing path
- [ ] T002 Run the two upstream Pest commands from `specs/003-midnight-order-consolidation/quickstart.md`; if either command fails, STOP and report the exact failing output
- [ ] T003 Confirm the installed PHP, Laravel, Inertia, Wayfinder, React, TypeScript, Tailwind, and Pest versions against `specs/003-midnight-order-consolidation/plan.md`; record any mismatch before editing `composer.json` or `package.json`
- [ ] T004 Read `.ai/rules/actions.md`, `.ai/rules/controllers-requests.md`, and `.ai/rules/unit-feature.md`, then inspect one sibling Action, Request, controller, policy, model, factory, Pest unit test, Pest feature test, and Inertia page before editing paths listed in `specs/003-midnight-order-consolidation/plan.md`
- [ ] T005 Verify the format, lint, type, build, PHPStan, Pint, Wayfinder, route, schedule, and Pest commands listed in `specs/003-midnight-order-consolidation/quickstart.md` exist in `composer.json`, `package.json`, or Artisan; do not add a dependency when one is absent

**Checkpoint**: Continue only when all Phase 001/002 files exist, both upstream suites
pass, and the installed stack matches the plan.

---

## Phase 2: Foundational Domain and Security

**Purpose**: Create the shared types, schema, models, factories, numeric/time helpers,
authorization, rate limits, and safe failure boundary used by every story.

**CRITICAL**: No user-story task may begin until T006-T039 are complete and the schema
inspection passes.

- [ ] T006 Create the exact timezone, cutoff, page-size, lock, 500-order cap, and six channel-zone minimum values from `data-model.md` in `config/consolidation.php`; use integer hundredths and no secrets
- [ ] T007 [P] Create the backed enum with only Open, Processing, AwaitingDecision, Completed, and Failed in `app/Enums/ConsolidationCycleStatus.php`
- [ ] T008 [P] Create the backed enum with only Automatic and Manual in `app/Enums/ConsolidationTrigger.php`
- [ ] T009 [P] Create the backed enum with only Approved and Excluded in `app/Enums/ConsolidationCandidateDecision.php`
- [ ] T010 [P] Create the backed enum with only Outstanding and Received in `app/Enums/ProcurementRequirementStatus.php`
- [ ] T011 Generate and implement `database/migrations/*_add_operations_manager_to_users_table.php` with a non-fillable-by-default boolean `is_operations_manager` column defaulting to false
- [ ] T012 Generate and implement `database/migrations/*_create_consolidation_cutoff_changes_table.php` exactly as the ConsolidationCutoffChange table in `data-model.md`, including FK deletion rules and both indexes
- [ ] T013 Generate and implement `database/migrations/*_create_consolidation_cycles_table.php` exactly as the ConsolidationCycle table in `data-model.md`, including immutable schedule snapshot fields, aggregate exclusion counts, FKs, unique service date, and indexes
- [ ] T014 Generate and implement `database/migrations/*_create_consolidation_candidates_table.php` exactly as the ConsolidationCandidate table in `data-model.md`, including private hashes, decision fields, FKs, unique generation key, and indexes
- [ ] T015 Generate and implement `database/migrations/*_create_procurement_requirements_table.php` exactly as the ProcurementRequirement table in `data-model.md`, including public UUID, unique cycle-product key, FKs, and Phase 004 work-queue indexes
- [ ] T016 Generate and implement `database/migrations/*_create_order_groups_table.php` exactly as the OrderGroup table in `data-model.md`, with no Phase 003 status column and with its unique requirement-channel-zone key
- [ ] T017 Generate and implement `database/migrations/*_add_order_group_to_orders_table.php` with nullable indexed `order_group_id`, a restrict-delete FK, and no membership pivot
- [ ] T018 Update `app/Models/User.php` with the boolean manager cast/helper while keeping both operations permission fields out of mass assignment
- [ ] T019 Update `database/factories/UserFactory.php` with `operationsManager()` that sets both `is_operations_operator` and `is_operations_manager` to true
- [ ] T020 Generate and implement casts, route-key UUID behavior, relationships, and append-only rules in `app/Models/ConsolidationCutoffChange.php`
- [ ] T021 Generate realistic default and state data without PII in `database/factories/ConsolidationCutoffChangeFactory.php`
- [ ] T022 Generate and implement defaults, enum/date casts, UUID route binding, hidden private fields, and relationships in `app/Models/ConsolidationCycle.php`
- [ ] T023 Generate valid open, processing, awaiting, completed, and failed states in `database/factories/ConsolidationCycleFactory.php`
- [ ] T024 Generate and implement enum casts, UUID nested binding, hidden hashes/private actor fields, and relationships in `app/Models/ConsolidationCandidate.php`
- [ ] T025 Generate valid above-minimum, undecided-under-minimum, approved, and excluded states in `database/factories/ConsolidationCandidateFactory.php`
- [ ] T026 Generate and implement UUID binding, status/date casts, and cycle/ProductOffer/group relationships in `app/Models/ProcurementRequirement.php`
- [ ] T027 Generate valid outstanding requirement data using integer quantity/money fields in `database/factories/ProcurementRequirementFactory.php`
- [ ] T028 Generate and implement UUID binding plus cycle/requirement/Order relationships in `app/Models/OrderGroup.php`
- [ ] T029 Generate valid group data consistent with its requirement using integer quantity/money fields in `database/factories/OrderGroupFactory.php`
- [ ] T030 Add only the nullable OrderGroup relationship and FK cast needed by this feature to `app/Models/Order.php`; do not change price, customer, lifecycle, or offer behavior
- [ ] T031 Write failing exact parse/format, zero, maximum, invalid-decimal, and no-float tests in `tests/Unit/Support/Quantities/KilogramQuantityTest.php`
- [ ] T032 Implement the shared integer-hundredths parser/formatter until T031 passes in `app/Support/Quantities/KilogramQuantity.php`; Phase 004 must reuse this class
- [ ] T033 Write failing frozen-time tests for default/custom cutoff, next service date, cycle-creation snapshots, early manual cutoff, late scheduler start, and Casablanca offset changes in `tests/Unit/Support/Consolidations/ConsolidationScheduleTest.php`
- [ ] T034 Implement only cutoff resolution and immutable cycle-snapshot calculations until T033 passes in `app/Support/Consolidations/ConsolidationSchedule.php`; never accept a caller cutoff timestamp
- [ ] T035 Create operations-operator view/run/decide abilities and distinct manager-only cutoff abilities in `app/Policies/ConsolidationCyclePolicy.php`
- [ ] T036 Create fixed allowlisted conflict codes/messages with no payload, PII, hash, SQL, or model context in `app/Exceptions/ConsolidationConflictException.php`
- [ ] T037 Add one safe Inertia/HTTP rendering path for `ConsolidationConflictException` without changing unrelated exception behavior in `bootstrap/app.php`
- [ ] T038 Register `operator-consolidations` at 60/minute and `consolidation-mutations` at 30/minute using authenticated user ID plus IP in `app/Providers/AppServiceProvider.php`
- [ ] T039 Run migrations and inspect every new column/FK/index/unique/default against `specs/003-midnight-order-consolidation/data-model.md`; fix every schema mismatch before US1 and leave this task unchecked if inspection cannot run

**Checkpoint**: The database schema matches `data-model.md`, factories create valid
records, numeric/time helper tests pass, and policy/failure/rate-limit boundaries exist.

---

## Phase 3: User Story 1 - Consolidate Eligible Demand (Priority: P1) MVP

**Goal**: Automatically close one next-day service-date window, deterministically group
eligible demand, pause safely for under-minimum candidates, and atomically create exact
procurement requirements, groups, memberships, and lifecycle transitions.

**Independent Test**: Create 20 eligible tomato Orders totaling 100.00 kg for one service
date and three zones, run `orders:consolidate`, and verify one requirement, three groups,
20 unique grouped Orders/transitions, unchanged source prices, and zero variance.

### Tests for User Story 1

- [ ] T040 [P] [US1] Write failing deterministic sort/key/fingerprint, exact aggregate, weighted-estimate, channel-zone minimum, 500/501 limit, and safe-source-field tests in `tests/Unit/Support/Consolidations/ConsolidationPreviewBuilderTest.php`
- [ ] T041 [P] [US1] Write the failing 20-Order happy-path, no-eligible-order, separate ProductOffer/channel/zone, cutoff equality, and immutable-price tests in `tests/Unit/Actions/Consolidations/RunConsolidationCycleActionTest.php`
- [ ] T042 [US1] Add failing awaiting-decision, no-partial-output, exclusion-count precedence, invalid-source, and 501-order failure cases to `tests/Unit/Actions/Consolidations/RunConsolidationCycleActionTest.php`
- [ ] T043 [P] [US1] Write failing due/not-due, tomorrow-only, late-start preserved cutoff, and existing-cycle reuse tests in `tests/Unit/Actions/Consolidations/StartScheduledConsolidationActionTest.php`
- [ ] T044 [P] [US1] Write failing command exit/output, every-minute schedule, overlap name/limit, on-one-server, and no-PII integration tests in `tests/Feature/Consolidations/ScheduledConsolidationTest.php`
- [ ] T045 [US1] Add failing open-cycle creation, immutable cutoff snapshot, boundary equality, closed-window rejection, and confirmation/closure race regression cases to `tests/Unit/Actions/Orders/CreateOrderActionTest.php`

### Implementation for User Story 1

- [ ] T046 [US1] Implement stable Order sorting, ProductOffer/channel/zone grouping, integer totals, weighted delivery estimate, minimum lookup, private hashes, and 500-order rejection until T040 passes in `app/Support/Consolidations/ConsolidationPreviewBuilder.php`
- [ ] T047 [US1] Add the shared cycle-row creation/lock/window guard inside the existing confirmation transaction until T045 passes in `app/Actions/Orders/CreateOrderAction.php`; preserve every existing price, token, availability, PII, and lifecycle rule
- [ ] T048 [US1] Implement the short committed close-boundary transaction in `app/Actions/Consolidations/RunConsolidationCycleAction.php`; preserve the cycle's creation-time schedule snapshot and never clear it on failure
- [ ] T049 [US1] Implement preview recomputation, candidate persistence, aggregate exclusion counts, and awaiting-decision with zero completed outputs in `app/Actions/Consolidations/RunConsolidationCycleAction.php`
- [ ] T050 [US1] Implement stable final locking, unique requirement/group creation, conditional Order claims, and exactly one confirmed-to-grouped transition per claimed Order in `app/Actions/Consolidations/RunConsolidationCycleAction.php`
- [ ] T051 [US1] Implement final count/quantity/commercial reconciliation, zero-variance assertions, three-attempt transactions, rollback, and safe fixed failure recording until T041-T042 pass in `app/Actions/Consolidations/RunConsolidationCycleAction.php`
- [ ] T052 [US1] Implement due detection and tomorrow-only dispatch to the core run Action until T043 passes in `app/Actions/Consolidations/StartScheduledConsolidationAction.php`
- [ ] T053 [US1] Generate and implement the argument-free command as a thin adapter over StartScheduledConsolidationAction in `app/Console/Commands/ConsolidateOrdersCommand.php`
- [ ] T054 [US1] Register `orders:consolidate` every minute with name `midnight-order-consolidation`, ten-minute overlap protection, and one-server execution in `routes/console.php`
- [ ] T055 [US1] Run T040-T045 tests plus the Phase 002 Order suites listed in `specs/003-midnight-order-consolidation/quickstart.md`; do not continue while any regression or story test fails
- [ ] T056 [US1] Build the exact 20-Order acceptance dataset with factories inside `tests/Unit/Actions/Consolidations/RunConsolidationCycleActionTest.php` and assert the full SC-001 result without manual SQL or tinker

**Checkpoint**: US1 works through the scheduled command for above-minimum demand, and an
under-minimum preview stops safely without completed outputs.

---

## Phase 4: User Story 2 - Reconcile Every Consolidated Result (Priority: P2)

**Goal**: Let an authorized operations user list cycles and inspect safe, bounded,
privacy-preserving reconciliation down to one group's source Orders.

**Independent Test**: Open a completed mixed-price cycle, trace its requirement, groups,
and paginated source Orders, and verify immutable prices, aggregate reason counts, and
zero unexplained variance without exposing PII or internal identifiers.

### Tests for User Story 2

- [ ] T057 [P] [US2] Write failing status/trigger/operating-date/service-date/ProductOffer/channel/zone filter, no-duplicate-row, stable-sort, 25/page, manager-only cutoff-prop, safe-array, and bounded-query tests in `tests/Unit/Actions/Consolidations/ListConsolidationCyclesActionTest.php`
- [ ] T058 [P] [US2] Write failing current-candidate, requirement, 50/page group/order drill-down, variance, exclusion-reason, nested-scope, no-N+1, and no-PII tests in `tests/Unit/Actions/Consolidations/ShowConsolidationCycleActionTest.php`
- [ ] T059 [US2] Write failing guest/unverified/ordinary-user denial, operator GET success, strict query validation, safe UUID, exact Inertia props, pagination, and recursive private-key absence tests in `tests/Feature/Consolidations/OperatorConsolidationTest.php`

### Implementation for User Story 2

- [ ] T060 [P] [US2] Implement the exact status, trigger, operating_date, service_date, ProductOffer public UUID, channel, delivery_zone, and page allowlist from the contract plus extra-field rejection and operator authorization in `app/Http/Requests/Operator/Consolidations/ListConsolidationCyclesRequest.php`
- [ ] T061 [P] [US2] Implement allowlisted candidate/group/order page rules, nested group UUID validation, extra-field rejection, and operator authorization in `app/Http/Requests/Operator/Consolidations/ShowConsolidationCycleRequest.php`
- [ ] T062 [US2] Implement stable 25/page cycle queries for every T060 filter using non-duplicating exists/whereHas constraints, retained filters/options, and explicit privacy-safe IndexProps arrays until T057 passes in `app/Actions/Consolidations/ListConsolidationCyclesAction.php`
- [ ] T063 [US2] Implement current-generation candidates, requirements, 50/page groups/selected-group Orders, reconciliation variance, reason counts, and explicit safe arrays until T058 passes in `app/Actions/Consolidations/ShowConsolidationCycleAction.php`
- [ ] T064 [US2] Create only index/show HTTP adaptation and Inertia rendering with validated Request data in `app/Http/Controllers/Operator/ConsolidationCycleController.php`
- [ ] T065 [US2] Create the two authenticated, verified, operator-authorized, read-throttled GET routes with public UUID binding in `routes/consolidations.php`
- [ ] T066 [US2] Require `routes/consolidations.php` after the existing Phase 002 order routes in `routes/web.php`
- [ ] T067 [US2] Run `php artisan wayfinder:generate --with-form --no-interaction` and verify generated controller/route functions under `resources/js/actions/` and `resources/js/routes/`; never edit those generated files
- [ ] T068 [US2] Define only the exact CycleSummary, CandidateSummary, RequirementSummary, GroupSummary, SourceOrderSummary, IndexProps, and ShowProps contracts in `resources/js/types/consolidation.ts`
- [ ] T069 [US2] Export the consolidation types without changing unrelated exports in `resources/js/types/index.ts`
- [ ] T070 [US2] Build all seven read-only contract filters, the 25-row cycle table, status badges, retained Wayfinder pagination, empty state, and responsive/dark-mode layout in `resources/js/pages/operator/consolidations/index.tsx` using server values only
- [ ] T071 [US2] Build the cutoff summary, reconciliation cards, requirements, 50/page groups, selected-group source Orders, estimated labels, pagination, empty/failure states, and responsive/dark-mode layout in `resources/js/pages/operator/consolidations/show.tsx`
- [ ] T072 [US2] Add one policy-aware Consolidations Wayfinder Link without hardcoded URLs in `resources/js/components/app-sidebar.tsx`
- [ ] T073 [US2] Run T057-T059, frontend format/lint/type/build checks, and the read-only UI portion of `specs/003-midnight-order-consolidation/quickstart.md`; fix failures before US3

**Checkpoint**: US2 exposes complete bounded reconciliation to authorized operators and
no customer PII, internal IDs, hashes, or private actor identity.

---

## Phase 5: User Story 3 - Retry Without Duplicates (Priority: P3)

**Goal**: Make completed, failed, repeated, and competing execution converge on one
logical result without duplicate or partial records.

**Independent Test**: Repeat and concurrently invoke one cycle, force one failure, retry
it, and verify one cycle/result, unique memberships/transitions, unchanged cutoff and
price snapshots, and zero variance.

### Tests for User Story 3

- [ ] T074 [US3] Add failing completed replay, forced failure, failed retry, cache-lock busy, stale source generation, conditional-claim mismatch, and duplicate-constraint cases to `tests/Unit/Actions/Consolidations/RunConsolidationCycleActionTest.php`
- [ ] T075 [US3] Add failing guest/unauthorized retry, strict confirm payload, safe conflict, completed no-op, failed recovery, and duplicate-free response cases to `tests/Feature/Consolidations/OperatorConsolidationTest.php`
- [ ] T076 [US3] Add failing automatic-versus-manual same-date and repeated-command cases with safe output to `tests/Feature/Consolidations/ScheduledConsolidationTest.php`

### Implementation for User Story 3

- [ ] T077 [US3] Harden state checks, cache-lock handling, unique-key race recovery, stable lock order, conditional affected-row assertions, and idempotent completed return until T074 and T076 pass in `app/Actions/Consolidations/RunConsolidationCycleAction.php`
- [ ] T078 [US3] Implement an exact `confirm`-only allowlist, operator authorization, extra-field rejection, and recoverable-state validation in `app/Http/Requests/Operator/Consolidations/RetryConsolidationCycleRequest.php`
- [ ] T079 [US3] Implement only validated retry/continue adaptation, safe conflict mapping, and redirect/toast behavior in `app/Http/Controllers/Operator/ConsolidationCycleRunController.php`
- [ ] T080 [US3] Add the authenticated, verified, mutation-throttled cycle run route with public UUID binding in `routes/consolidations.php`
- [ ] T081 [US3] Add the policy-aware retry/continue confirmation Form, processing disablement, and safe conflict display in `resources/js/pages/operator/consolidations/show.tsx`
- [ ] T082 [US3] Regenerate Wayfinder and run T074-T076 plus the US1/US2 suites using the commands in `specs/003-midnight-order-consolidation/quickstart.md`; do not continue while any duplicate, rollback, or regression assertion fails

**Checkpoint**: Sequential and application-level competing executions are replay-safe;
the real-database contention proof remains a final release gate.

---

## Phase 6: User Story 4 - Run Consolidation Manually (Priority: P4)

**Goal**: Let authorized operators close a selected service date early, decide exact
under-minimum candidates, continue/recover a cycle, and let managers change future cutoff
configuration through protected controls.

**Independent Test**: During a live demo, an operator selects an open service date,
confirms early closure at authoritative Casablanca time, resolves every under-minimum
candidate, and reaches one reconciled outcome; guests and ordinary users are denied, and
only managers can view/change cutoff configuration.

### Tests for User Story 4

- [ ] T083 [P] [US4] Write failing early/due start, authoritative-now cutoff, open-date validation, caller-field rejection, actor attribution, existing-cycle reuse, and later-confirmation rejection tests in `tests/Unit/Actions/Consolidations/StartManualConsolidationActionTest.php`
- [ ] T084 [P] [US4] Write failing approve/exclude, exact-generation binding, same-decision replay, different-decision conflict, stale candidate, fixed reason, actor/time, and continuation tests in `tests/Unit/Actions/Consolidations/DecideUnderMinimumCandidateActionTest.php`
- [ ] T085 [P] [US4] Write failing HH:MM conversion, next operating date, manager requirement, immutable history, same-day latest rule, same-value idempotency, and existing-cycle preservation tests in `tests/Unit/Actions/Consolidations/UpdateConsolidationCutoffActionTest.php`
- [ ] T086 [US4] Add failing manual start, candidate decision, manager cutoff, strict extra-field, nested scope, 30/minute limit, redirect/toast, and safe conflict HTTP cases to `tests/Feature/Consolidations/OperatorConsolidationTest.php`

### Implementation for User Story 4

- [ ] T087 [US4] Implement selected-open-date validation, authoritative Casablanca now, persisted schedule reuse, early/due effective cutoff choice, actor attribution, and delegation to RunConsolidationCycleAction until T083 passes in `app/Actions/Consolidations/StartManualConsolidationAction.php`
- [ ] T088 [US4] Implement current nested candidate checks, first-decision-wins, same-decision replay, fixed exclusion reason, actor/time attribution, stale conflict, and safe continuation until T084 passes in `app/Actions/Consolidations/DecideUnderMinimumCandidateAction.php`
- [ ] T089 [US4] Implement manager-only append history, HH:MM integer conversion, next Casablanca operating date, latest same-day rule, same-value replay, and no existing-cycle mutation until T085 passes in `app/Actions/Consolidations/UpdateConsolidationCutoffAction.php`
- [ ] T090 [P] [US4] Implement exact `service_date` plus accepted `confirm` allowlist, operator authorization, available-open-date validation, and extra-field rejection in `app/Http/Requests/Operator/Consolidations/StartManualConsolidationRequest.php`
- [ ] T091 [P] [US4] Implement exact approved/excluded `decision` plus accepted `confirm` allowlist, operator authorization, nested current-candidate validation, and extra-field rejection in `app/Http/Requests/Operator/Consolidations/DecideUnderMinimumCandidateRequest.php`
- [ ] T092 [P] [US4] Implement exact `cutoff_time` plus accepted `confirm` allowlist, manager authorization, HH:MM validation, and extra-field rejection in `app/Http/Requests/Operator/Consolidations/UpdateConsolidationCutoffRequest.php`
- [ ] T093 [P] [US4] Implement only validated manual-start adaptation, safe redirect/conflict mapping, and toast behavior in `app/Http/Controllers/Operator/ManualConsolidationController.php`
- [ ] T094 [P] [US4] Implement only validated nested candidate decision adaptation, safe redirect/conflict mapping, and toast behavior in `app/Http/Controllers/Operator/UnderMinimumDecisionController.php`
- [ ] T095 [P] [US4] Implement only validated manager cutoff-update adaptation, index redirect, safe conflict mapping, and toast behavior in `app/Http/Controllers/Operator/ConsolidationCutoffController.php`
- [ ] T096 [US4] Add manual start, manager cutoff update, and scoped candidate decision routes with auth, verified, operator/manager policy, read/mutation throttles, and exact contract names in `routes/consolidations.php`
- [ ] T097 [US4] Add at most 14 server-derived open service dates, manager-only cutoff props, and exact `can.run`/`can.updateCutoff` values until the related tests pass in `app/Actions/Consolidations/ListConsolidationCyclesAction.php`
- [ ] T098 [US4] Add exact candidate `canDecide`, cycle `can.run`, safe actor label, and no private actor fields until the related tests pass in `app/Actions/Consolidations/ShowConsolidationCycleAction.php`
- [ ] T099 [US4] Add manager-only cutoff Card/form/Dialog and operator manual-run Card/form/Dialog with server-provided dates, warnings, errors, processing states, and Wayfinder calls in `resources/js/pages/operator/consolidations/index.tsx`
- [ ] T100 [US4] Add approve/exclude candidate Dialog/Forms, exclusion warning, disabled shared processing state, conflict refresh guidance, and Wayfinder calls in `resources/js/pages/operator/consolidations/show.tsx`
- [ ] T101 [US4] Regenerate Wayfinder after all six routes are final and verify generated helpers without editing them in `resources/js/actions/` and `resources/js/routes/`
- [ ] T102 [US4] Run T083-T086, all consolidation Action/feature suites, Phase 002 Order regressions, frontend checks, and the full manual operator demonstration in `specs/003-midnight-order-consolidation/quickstart.md`

**Checkpoint**: All four stories work through the protected UI and command paths, with
operator/manager permissions separated and all under-minimum decisions auditable.

---

## Final Phase: Cross-Cutting Verification and Release Gates

**Purpose**: Prove the completed feature obeys its architecture, security, performance,
concurrency, privacy, and quality contracts.

- [ ] T103 Review all seven Actions against `.ai/rules/actions.md` and remove any HTTP Request/response dependency or business logic duplicated outside `app/Actions/Consolidations/`
- [ ] T104 Review all six Requests and five controllers against `.ai/rules/controllers-requests.md`; verify controllers use only `validated()` or selected `safe()` data in `app/Http/Requests/Operator/Consolidations/` and `app/Http/Controllers/Operator/`
- [ ] T105 Verify exactly six contract routes, public UUID bindings, nested scope binding, auth, verified, operator/manager authorization, and both limiters using `routes/consolidations.php`
- [ ] T106 Verify no response/log/exception contains customer PII, private actor identity, internal IDs, hashes, raw payloads, SQL, or model dumps using `tests/Feature/Consolidations/OperatorConsolidationTest.php`
- [ ] T107 Run the real MySQL/PostgreSQL contention scenarios and invariant checks from `specs/003-midnight-order-consolidation/quickstart.md`; if unavailable or failing, record the exact error and leave this task incomplete
- [ ] T108 Run `vendor/bin/pint --dirty --format agent`, PHPStan, focused Pest suites, upstream Order regressions, and full affected Composer checks from `specs/003-midnight-order-consolidation/quickstart.md`; do not mark complete with any failure
- [ ] T109 Run Wayfinder generation plus frontend format, lint, type, and production build checks from `specs/003-midnight-order-consolidation/quickstart.md`; inspect generated changes and do not hand-edit them
- [ ] T110 Complete the desktop/mobile, light/dark, keyboard, focus, table overflow, error/empty/processing, repeated-run, closed-window, and browser-console checks in `specs/003-midnight-order-consolidation/quickstart.md`
- [ ] T111 Re-run the Constitution Check in `specs/003-midnight-order-consolidation/plan.md`, confirm no forbidden scope/dependency was added, and leave every incomplete or failing gate unchecked in `specs/003-midnight-order-consolidation/tasks.md`

---

## Dependencies and Execution Order

### Phase Dependencies

```text
Phase 1: Upstream gate
    -> Phase 2: Shared foundations
        -> Phase 3: US1 core automatic consolidation (MVP)
            -> Phase 4: US2 reconciliation views
            -> Phase 5: US3 replay/recovery
                -> Phase 6: US4 manual controls and decisions
                    -> Final verification
```

- Phase 1 blocks everything. Missing or failing Phase 001/002 work is not repaired inside
  this feature.
- Phase 2 blocks every story because all stories share the same cycle schema and policy.
- US1 is the MVP and creates the business result consumed by every later story.
- US2 and US3 both depend on US1. Complete US2 before US3 in one-agent execution because
  both later modify the show route/page and `OperatorConsolidationTest.php`.
- US4 depends on US1 for processing, US2 for its pages, and US3 for retry behavior.
- Final verification depends on every selected story.

### Within Every Story

1. Write the named failing tests and confirm the failure is caused by missing behavior.
2. Implement only enough domain behavior to pass the direct Action/support test.
3. Add the Form Request and thin controller only after the Action test passes.
4. Add routes and regenerate Wayfinder only after controller signatures are final.
5. Build or extend the React page from exact server props; do not calculate business
   totals or authorization in the browser.
6. Run the story checkpoint tests before starting the next story.

## Safe Parallel Opportunities

Parallel work is optional. A single junior agent should simply follow numeric order.

- T007-T010 may run together because each creates a separate enum file.
- T040, T043, and T044 may run together because they create separate US1 test files;
  T041-T042 share one file and must remain sequential.
- T057 and T058 may run together because they create separate Action test files.
- T060 and T061 may run together after T059 because they create separate Requests.
- T083-T085 may run together because they create separate Action test files.
- T090-T095 may run together only after their Actions pass because each creates a
  separate Request or controller file.
- Do not parallelize migrations, `RunConsolidationCycleAction.php`,
  `OperatorConsolidationTest.php`, `routes/consolidations.php`, or either React page.

## Delivery Strategy

### MVP First

Complete T001-T056 only. This delivers the essential automatic, above-minimum
consolidation result and the safe awaiting-decision stop. Demonstrate it through the
Artisan command and direct reconciliation assertions before adding web views.

### Incremental Delivery

1. T001-T039: prove prerequisites and build the shared safe domain.
2. T040-T056: deliver US1 automatic consolidation.
3. T057-T073: add US2 read-only reconciliation.
4. T074-T082: prove US3 retry and duplicate resistance.
5. T083-T102: add US4 manual, decision, and manager cutoff controls.
6. T103-T111: run all release gates.

Never mark a task complete because code merely exists. Mark it complete only when its
named assertions or verification command pass.
