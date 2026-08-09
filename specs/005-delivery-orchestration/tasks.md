# Tasks: Delivery Orchestration

**Input**: Design documents from `specs/005-delivery-orchestration/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`,
`contracts/web-routes-and-props.md`, and `quickstart.md`

**Tests**: Tests are mandatory. Write the named test first, confirm that it fails for
the expected missing behavior, implement only that behavior, and rerun the named test.

**Junior-agent rule**: Complete tasks in ID order unless a task is marked `[P]`. Do not
combine tasks, rename planned classes, add abstractions, or work ahead after a failure.

> **HARD STOP**: T001-T004 are an upstream gate. If any Phase 001-004 prerequisite is
> missing or failing, stop Phase 005 and report the exact missing file, field, enum case,
> permission, factory state, or failing test. Do not create a Phase 005 substitute.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Safe to do in parallel only after all earlier blocking tasks are complete.
- **[Story]**: The user story served by the task.
- Every task names the exact file it reads, creates, or changes.

---

## Phase 1: Setup and Upstream Gate

**Purpose**: Prove that Phases 001-004 provide the exact order, grouping, stock, and
operations contracts consumed by this feature.

- [ ] T001 Check every prerequisite class and enum listed in section 1 of `specs/005-delivery-orchestration/quickstart.md`; stop and report the first missing path without creating Phase 005 source files
- [ ] T002 Check every required upstream column, relationship, enum case, operations permission, and factory state against `specs/005-delivery-orchestration/data-model.md`; stop on the first mismatch
- [ ] T003 Confirm the installed PHP and JavaScript package versions match the Technical Context in `specs/005-delivery-orchestration/plan.md`; do not add or update dependencies
- [ ] T004 Run the focused Phase 001-004 Action and HTTP suites required by `specs/005-delivery-orchestration/quickstart.md`; stop and report the exact failing command and output

**Checkpoint**: Continue only when the complete upstream gate passes.

---

## Phase 2: Foundational Domain and Persistence

**Purpose**: Build the shared fixed-value types, integer helpers, provider boundary,
schema, relationships, authorization, and safe failure boundary used by every story.

**CRITICAL**: Do not start a user story until T005-T041 pass.

- [ ] T005 [P] Create the six-case lifecycle enum exactly as documented in `app/Enums/DispatchStatus.php`
- [ ] T006 [P] Create the internal-manifest and mock-provider enum in `app/Enums/DispatchProviderKind.php`
- [ ] T007 [P] Create the flat-rate and provider-quote enum in `app/Enums/DeliveryCostSource.php`
- [ ] T008 [P] Create the four attempt outcomes in `app/Enums/DispatchAttemptOutcome.php`
- [ ] T009 [P] Create the operator and mock-provider transition sources in `app/Enums/DispatchTransitionSource.php`
- [ ] T010 Create the exact non-secret limits, pagination sizes, mock quote, timezone, and transaction attempts in `config/dispatch.php`
- [ ] T011 [P] Write failing parse, format, invalid-input, maximum, and overflow tests in `tests/Unit/Support/Dispatches/MadMoneyTest.php`
- [ ] T012 Implement integer-only MAD parsing and formatting until T011 passes in `app/Support/Dispatches/MadMoney.php`
- [ ] T013 [P] Write failing zero-cost, weighted-share, remainder, UUID tie-break, invalid-quantity, and overflow tests in `tests/Unit/Support/Dispatches/LargestRemainderCostAllocatorTest.php`
- [ ] T014 Implement the integer largest-remainder algorithm until T013 passes in `app/Support/Dispatches/LargestRemainderCostAllocator.php`
- [ ] T015 Implement raw-token and ordered safe-scalar payload hashing with no PII/model/Request input in `app/Support/Dispatches/DispatchOperationFingerprint.php`
- [ ] T016 Define only the documented `submit` and `simulate` array-shape methods in `app/Support/Dispatches/DeliveryProvider.php`
- [ ] T017 Write failing deterministic reference, fixed quote, simulated outcome, changed-PII, no-credential, and no-network tests in `tests/Unit/Support/Dispatches/MockDeliveryProviderTest.php`
- [ ] T018 Implement the in-process deterministic provider until T017 passes in `app/Support/Dispatches/MockDeliveryProvider.php`
- [ ] T019 Generate and implement the first migration with Dispatch columns, casts-compatible types, indexes, unique keys, and restrictive FKs in `database/migrations/*_create_dispatches_table.php`
- [ ] T020 Generate and implement the second migration with unique Order membership, stable sequence, quantity, cost allocation, indexes, and restrictive FKs in `database/migrations/*_create_dispatch_orders_table.php`
- [ ] T021 Generate and implement the third migration with append-only attempt fields, safe hashes/results, indexes, and restrictive/private-actor FKs in `database/migrations/*_create_dispatch_attempts_table.php`
- [ ] T022 Generate and implement the fourth migration with append-only lifecycle history, replay hashes, indexes, and restrictive/private-actor FKs in `database/migrations/*_create_dispatch_status_transitions_table.php`
- [ ] T023 Generate and implement the fifth migration adding default-zero dispatched progress and the documented index in `database/migrations/*_add_dispatch_progress_to_stock_allocations_table.php`
- [ ] T024 Generate and implement the sixth migration adding nullable Dispatch attribution and its history index in `database/migrations/*_add_dispatch_to_order_status_transitions_table.php`
- [ ] T025 [P] Create Dispatch fillable/guarded fields, hidden hashes, enum/date casts, UUID route binding, and relationships in `app/Models/Dispatch.php`
- [ ] T026 [P] Create DispatchOrder casts and parent/source relationships in `app/Models/DispatchOrder.php`
- [ ] T027 [P] Create the append-only DispatchAttempt model with no `updated_at`, hidden hashes, casts, and relationships in `app/Models/DispatchAttempt.php`
- [ ] T028 [P] Create the append-only DispatchStatusTransition model with no `updated_at`, hidden hashes, enum casts, and relationships in `app/Models/DispatchStatusTransition.php`
- [ ] T029 [P] Add safe defaults and reusable B2B/B2C lifecycle states in `database/factories/DispatchFactory.php`
- [ ] T030 [P] Add valid membership defaults without PII in `database/factories/DispatchOrderFactory.php`
- [ ] T031 [P] Add valid append-only attempt defaults without tokens, payloads, or PII in `database/factories/DispatchAttemptFactory.php`
- [ ] T032 [P] Add valid append-only transition defaults without tokens, payloads, or PII in `database/factories/DispatchStatusTransitionFactory.php`
- [ ] T033 Add the dispatch membership relationships only, without changing price snapshots or encrypted PII casts, in `app/Models/Order.php`
- [ ] T034 Add dispatched-progress casting, remaining/full helpers, and relationships in `app/Models/StockAllocation.php`
- [ ] T035 Add only the dispatch-related stock relationship required by the plan in `app/Models/HubReceipt.php`
- [ ] T036 Add the nullable Dispatch relationship while preserving existing append-only behavior in `app/Models/OrderStatusTransition.php`
- [ ] T037 Run migrate, rollback, migrate, and inspect all six affected tables against `specs/005-delivery-orchestration/data-model.md`; stop on any column, FK, unique key, index, or PII-column mismatch
- [ ] T038 Create a fixed allowlisted-code/message conflict exception with no submitted values or models in `app/Exceptions/DispatchConflictException.php`
- [ ] T039 Implement operations-only view, generate, submit, and advance abilities in `app/Policies/DispatchPolicy.php`
- [ ] T040 Bind `DeliveryProvider` to `MockDeliveryProvider` and define the 60/minute user-plus-IP limiter in `app/Providers/AppServiceProvider.php`
- [ ] T041 Render only fixed safe dispatch conflict outcomes, without tokens, hashes, PII, SQL, or model dumps, in `bootstrap/app.php`

**Checkpoint**: Migrations round-trip, helper/provider tests pass, and shared security
boundaries exist before business Actions are started.

---

## Phase 3: User Story 1 - Generate Zone-Based B2B Manifests (Priority: P1) MVP

**Goal**: Generate one ready B2B manifest per date/zone, refresh it while ready, freeze
it after submission, flag late work, and create no duplicate membership.

**Independent Test**: Prepare eligible B2B Orders across three zones, generate twice,
then add work before and after freeze. Verify three stable manifests, exact reconciliation,
ready refresh, submitted freeze, a late-work count, and no duplicate/ineligible Order.

### Tests for User Story 1

- [ ] T042 [US1] Write failing tests for eligibility, three-zone generation, stable sequence, exact totals, ready refresh, submitted freeze, late work, 500/501 bounds, replay, and rollback in `tests/Unit/Actions/Dispatches/GenerateDispatchesActionTest.php`
- [ ] T043 [P] [US1] Write failing bounded pagination, filter, stable-sort, summary, generation-preview, and no-PII tests in `tests/Unit/Actions/Dispatches/ListDispatchesActionTest.php`
- [ ] T044 [US1] Add failing guest, ordinary-user 403, operator generation, validation, 501 conflict, redirect, and Inertia index assertions in `tests/Feature/Dispatches/OperatorDispatchTest.php`

### Implementation for User Story 1

- [ ] T045 [US1] Implement the bounded transaction, B2B scope lock/re-read, ready membership rebuild, reconciliation, freeze protection, late-work result, and B2C ready-row creation until T042 passes in `app/Actions/Dispatches/GenerateDispatchesAction.php`
- [ ] T046 [US1] Implement selected-column filters, summaries, late-work preview, stable ordering, and page-size 25 until T043 passes in `app/Actions/Dispatches/ListDispatchesAction.php`
- [ ] T047 [P] [US1] Allow only a valid `service_date` and authorize generation in `app/Http/Requests/Operator/Dispatches/GenerateDispatchesRequest.php`
- [ ] T048 [P] [US1] Allow only documented list filters/page, apply Casablanca-date default, and authorize viewing in `app/Http/Requests/Operator/Dispatches/ListDispatchesRequest.php`
- [ ] T049 [US1] Add only the index adapter that passes validated filters to ListDispatchesAction and returns exact props in `app/Http/Controllers/Operator/DispatchController.php`
- [ ] T050 [US1] Add the thin generation adapter, safe conflict redirect, preserved date, and success toast in `app/Http/Controllers/Operator/DispatchGenerationController.php`
- [ ] T051 [US1] Define the five named operator routes in contract order with auth, verified, and dispatch limiter middleware in `routes/dispatches.php`
- [ ] T052 [US1] Require the dispatch route file once in `routes/web.php`
- [ ] T053 [US1] Rerun T044 and implement only missing HTTP wiring until its US1 cases pass in `tests/Feature/Dispatches/OperatorDispatchTest.php`
- [ ] T054 [US1] Generate controller/form Wayfinder functions and verify generated output without hand-editing files using the command documented in `specs/005-delivery-orchestration/contracts/web-routes-and-props.md`
- [ ] T055 [P] [US1] Define exact shared dispatch status, channel, option, paginator, index, and show prop types in `resources/js/types/dispatch.ts`
- [ ] T056 [US1] Export the new dispatch types without changing unrelated exports in `resources/js/types/index.ts`
- [ ] T057 [US1] Build the index summaries, filters, generation confirmation, late/limit/no-work states, responsive table, processing/error states, and Wayfinder forms in `resources/js/pages/operator/dispatches/index.tsx`
- [ ] T058 [US1] Add one operations-only Dispatches link using the generated Wayfinder route in `resources/js/components/app-sidebar.tsx`
- [ ] T059 [US1] Run the exact US1 Action and feature tests plus TypeScript check for files named in `specs/005-delivery-orchestration/quickstart.md`; stop on the first failure

**Checkpoint**: The B2B manifest MVP is independently usable and tested.

---

## Phase 4: User Story 2 - Demonstrate B2C Provider Dispatch (Priority: P2)

**Goal**: Submit one prepared B2C Order through the provider-neutral in-process mock,
show a stable simulated reference and 25.00 MAD quote, and replay safely without secrets.

**Independent Test**: With no provider credentials/network, submit one prepared B2C
Order twice and after changing its PII. Verify one Dispatch, one stable `MOCK-<12 hex>`
reference, one 25.00 MAD allocation, accepted status, and a visible simulated label.

### Tests for User Story 2

- [ ] T060 [US2] Write failing B2C first-submit, transient minimum PII, deterministic mock, accepted transition, quote allocation, replay/mismatch, uncertain reconcile, stale-version, and rollback tests in `tests/Unit/Actions/Dispatches/SubmitDispatchActionTest.php`
- [ ] T061 [P] [US2] Write failing exact-prop, pagination, allowed-action, simulated-label, and safe-reference tests for one B2C Dispatch in `tests/Unit/Actions/Dispatches/ShowDispatchActionTest.php`
- [ ] T062 [US2] Add failing submit validation, authorization, redirect/toast, replay, and simulated show-page HTTP assertions in `tests/Feature/Dispatches/OperatorDispatchTest.php`

### Implementation for User Story 2

- [ ] T063 [US2] Implement B2C token replay, locked reconciliation, transient provider input, safe provider result validation, full quote allocation, submitted/accepted history, and one version increment until T060 passes in `app/Actions/Dispatches/SubmitDispatchAction.php`
- [ ] T064 [US2] Implement exact bounded detail props, distinct paginator names, server-derived capabilities/tokens, and simulated provider labels until T061 passes in `app/Actions/Dispatches/ShowDispatchAction.php`
- [ ] T065 [US2] Validate only UUID token, positive expected version, and channel/state-appropriate cost input; authorize submission in `app/Http/Requests/Operator/Dispatches/SubmitDispatchRequest.php`
- [ ] T066 [US2] Add the thin submit adapter and safe success/conflict redirects in `app/Http/Controllers/Operator/DispatchSubmissionController.php`
- [ ] T067 [US2] Add only the policy-authorized show adapter using ShowDispatchAction to `app/Http/Controllers/Operator/DispatchController.php`
- [ ] T068 [US2] Rerun T062 and implement only missing B2C HTTP wiring until its cases pass in `tests/Feature/Dispatches/OperatorDispatchTest.php`
- [ ] T069 [US2] Build the show header, simulated warning, B2C submit/retry card, orders/attempts/history tables, empty states, errors, and Wayfinder form in `resources/js/pages/operator/dispatches/show.tsx`
- [ ] T070 [US2] Run the provider, SubmitDispatchAction, ShowDispatchAction, and feature tests listed in `specs/005-delivery-orchestration/quickstart.md`; stop on the first failure

**Checkpoint**: The credential-free B2C submission demo works independently.

---

## Phase 5: User Story 3 - Track Handoff and Delivery Outcomes (Priority: P3)

**Goal**: Progress B2B and B2C through valid statuses, move Orders and stock exactly
once at pickup, move Orders only at delivery, and make post-pickup failure terminal.

**Independent Test**: Advance one B2B and two same-group B2C Dispatches through success,
pre-pickup failure/retry, stale/replayed controls, and post-pickup failure. Verify exact
history, partial/full stock counters, rollback, and terminal/manual-follow-up behavior.

### Tests for User Story 3

- [ ] T071 [US3] Write failing tests for every valid/invalid status edge, B2C simulate correlation, replay/mismatch, lock order, partial/full pickup, exact stock equations, delivery-only Order changes, rollback, and terminal failure in `tests/Unit/Actions/Dispatches/AdvanceDispatchActionTest.php`
- [ ] T072 [US3] Add failing advance authorization, validation, allowed-target, safe-conflict, replay, and persistence HTTP cases in `tests/Feature/Dispatches/OperatorDispatchTest.php`

### Implementation for User Story 3

- [ ] T073 [US3] Implement server-derived transitions and the exact Dispatch-to-Orders-to-Groups-to-Allocations-to-Receipts lock/update order until T071 passes in `app/Actions/Dispatches/AdvanceDispatchAction.php`
- [ ] T074 [US3] Validate only UUID token, positive expected version, and server-allowed target; authorize advancement in `app/Http/Requests/Operator/Dispatches/AdvanceDispatchRequest.php`
- [ ] T075 [US3] Add the thin advance adapter and safe success/conflict redirects in `app/Http/Controllers/Operator/DispatchStatusController.php`
- [ ] T076 [US3] Rerun T072 and implement only missing lifecycle HTTP wiring until its cases pass in `tests/Feature/Dispatches/OperatorDispatchTest.php`
- [ ] T077 [US3] Add server-provided allowed-target confirmation forms, B2C Simulate labels, terminal state, and manual-follow-up state in `resources/js/pages/operator/dispatches/show.tsx`
- [ ] T078 [US3] Run AdvanceDispatchAction and affected Phase 002-004 regression suites named in `specs/005-delivery-orchestration/quickstart.md`; stop on the first failure

**Checkpoint**: Lifecycle, stock, Order, history, retry, and terminal rules are verified.

---

## Phase 6: User Story 4 - Reconcile Actual Delivery Cost (Priority: P4)

**Goal**: Freeze one actual Dispatch cost, allocate B2C cost wholly and B2B cost by
prepared kilograms with exact largest remainders, and never rewrite price snapshots.

**Independent Test**: Submit B2B costs with unequal and tied remainders plus a B2C quote;
verify exact centime sums, stable UUID tie-breaks, replay stability, explicit zero versus
missing cost, and byte-identical confirmed price snapshots/estimated delivery values.

### Tests for User Story 4

- [ ] T079 [US4] Add failing B2B flat/zero/invalid cost, weighted allocation, equal-remainder UUID tie, frozen retry, exact-sum, and immutable-price tests in `tests/Unit/Actions/Dispatches/SubmitDispatchActionTest.php`
- [ ] T080 [US4] Add failing B2B cost validation, first-submit form, retry-without-cost, exact display, and no-price-rewrite HTTP cases in `tests/Feature/Dispatches/OperatorDispatchTest.php`

### Implementation for User Story 4

- [ ] T081 [US4] Extend first B2B submission to parse/freeze flat cost, allocate with LargestRemainderCostAllocator, append submitted history, and reuse cost on retry until T079 passes in `app/Actions/Dispatches/SubmitDispatchAction.php`
- [ ] T082 [US4] Complete conditional first-B2B/never-B2C/never-retry flat-cost validation until T080 passes in `app/Http/Requests/Operator/Dispatches/SubmitDispatchRequest.php`
- [ ] T083 [US4] Add the B2B first-submit MAD field, cost source/allocation display, and cost-free retry form in `resources/js/pages/operator/dispatches/show.tsx`
- [ ] T084 [US4] Run MadMoney, allocator, SubmitDispatchAction, and exact-cost HTTP tests listed in `specs/005-delivery-orchestration/quickstart.md`; stop on the first failure

**Checkpoint**: Every Dispatch cost reconciles exactly without changing historical prices.

---

## Phase 7: User Story 5 - Inspect Dispatch Safely (Priority: P5)

**Goal**: Let operations staff list and inspect only needed handoff data while aggregate,
unauthorized, error, diagnostic, mock-provider, and log surfaces reveal no sensitive data.

**Independent Test**: Seed unique canary PII and exercise operator, guest, ordinary-user,
not-found, 422, 429, provider failure, mismatch, stale, and unexpected-error paths. Only
the authorized detail Order rows may contain minimum handoff PII.

### Tests for User Story 5

- [ ] T085 [US5] Write recursive canary tests for database rows, index/detail props, unauthorized/not-found/validation/conflict/rate-limit outcomes, exceptions, and logs in `tests/Feature/Dispatches/DispatchPrivacyTest.php`
- [ ] T086 [P] [US5] Add exact index allowlist, selected-column, page bound, query-count, and zero-PII assertions in `tests/Unit/Actions/Dispatches/ListDispatchesActionTest.php`
- [ ] T087 [P] [US5] Add exact authorized-detail allowlist, cross-scope 404, three page bounds, query-count, and minimum-PII-only assertions in `tests/Unit/Actions/Dispatches/ShowDispatchActionTest.php`

### Implementation for User Story 5

- [ ] T088 [US5] Tighten explicit aggregate arrays, selected columns, bounds, and safe labels until T086 passes in `app/Actions/Dispatches/ListDispatchesAction.php`
- [ ] T089 [US5] Tighten explicit detail arrays, dispatch-scoped PII lookup, safe actor labels, and distinct bounded paginators until T087 passes in `app/Actions/Dispatches/ShowDispatchAction.php`
- [ ] T090 [US5] Remove any PII from hidden fields, data attributes, toasts, client logging, and aggregate markup until T085 passes in `resources/js/pages/operator/dispatches/index.tsx`
- [ ] T091 [US5] Keep minimum PII only in visible authorized Order cells and remove it from hidden fields, dialogs, toasts, and client logging until T085 passes in `resources/js/pages/operator/dispatches/show.tsx`
- [ ] T092 [US5] Run both Dispatch feature files and all five Action tests named in `specs/005-delivery-orchestration/quickstart.md`; stop on the first failure

**Checkpoint**: Privacy and authorization boundaries pass with canary values.

---

## Final Phase: Cross-Cutting Verification

**Purpose**: Prove formatting, types, build, regressions, privacy, demo behavior, and
real database concurrency before claiming completion.

- [ ] T093 Review every controller and Request against `.ai/rules/controllers-requests.md`, and every Action against `.ai/rules/actions.md`; fix only Phase 005 violations in the named files
- [ ] T094 Run `vendor/bin/pint --dirty --format agent` and review only modified Phase 005 PHP paths listed in `specs/005-delivery-orchestration/plan.md`
- [ ] T095 Run the configured PHP static analysis command and fix only Phase 005 findings, using the command inventory in `specs/005-delivery-orchestration/quickstart.md`
- [ ] T096 Run `composer run types:check`, `npm run format:check`, `npm run lint:check`, `npm run types:check`, and `npm run build`; fix only Phase 005 frontend findings listed in `specs/005-delivery-orchestration/quickstart.md`
- [ ] T097 Run all focused helper, provider, Action, and HTTP suites in section 3 of `specs/005-delivery-orchestration/quickstart.md`; stop and report the first exact failure
- [ ] T098 Run `php artisan test --compact` and all affected Phase 001-004 regression tests required by `specs/005-delivery-orchestration/quickstart.md`; do not mark complete with any failure
- [ ] T099 Execute the full canary privacy search, manual responsive/light/dark demo, and production-engine concurrency gate in `specs/005-delivery-orchestration/quickstart.md`; report the production engine used and any unverified gate
- [ ] T100 Re-run the Constitution Check and every Final Gate item in `specs/005-delivery-orchestration/quickstart.md`; mark Phase 005 complete only when every required item passes

---

## Dependencies & Execution Order

### Phase Dependencies

```text
Phase 1 upstream gate
    -> Phase 2 shared foundation
        -> US1 manifest generation (MVP)
            -> US2 B2C provider submission
                -> US3 lifecycle and stock movement
                    -> US4 complete B2B cost behavior
                        -> US5 privacy hardening
                            -> Final verification
```

- Phase 1 is a hard external prerequisite; failure blocks every later task.
- Phase 2 depends on a passing upstream gate and blocks all user stories.
- US1 creates Dispatch membership and the operator index required by later stories.
- US2 creates submission and the detail page required by US3 and US4.
- US3 depends on submitted/accepted Dispatches from US2.
- US4 extends the already-tested submit path with the B2B flat-cost branch.
- US5 verifies and tightens the complete list/show/error surface after all behaviors exist.
- Final verification depends on all selected stories.

### Safe Parallel Work

- After T004 passes, T005-T009 may run together because each creates a different enum.
- T011 and T013 may run together; implement T012 and T014 only after their own test exists.
- T025-T032 may run together only after T019-T024 are complete and each agent owns one file.
- In US1, T043 may be written while T042 is written; T047 and T048 may run together.
- In US2, T061 may be written while T060 is written.
- In US5, T086 and T087 may run together because they modify different test files.
- Never parallelize two tasks that edit `SubmitDispatchActionTest.php`,
  `OperatorDispatchTest.php`, `DispatchController.php`, `show.tsx`, or the same migration.

## Delivery Strategy

### MVP First

1. Pass the upstream gate (T001-T004).
2. Complete the shared foundation (T005-T041).
3. Complete and demonstrate US1 (T042-T059).
4. Stop for review. This is the smallest useful zone-manifest MVP.

### Incremental Delivery

1. Add US2 for the credential-free B2C provider demo.
2. Add US3 for traceable status, pickup, delivery, and failure behavior.
3. Add US4 for complete B2B actual-cost entry and allocation.
4. Add US5 privacy verification after every output/error path exists.
5. Run T093-T100 before claiming Phase 005 complete.

### Per-Task Working Rule

1. Read the exact referenced design section and sibling project file.
2. For behavior tasks, write or extend the named failing test first.
3. Change only the exact target file and the smallest required dependency.
4. Run the narrowest named test immediately.
5. Stop and report the exact output if the same approach fails twice.
6. Never mark a task complete while its test or required gate fails.
