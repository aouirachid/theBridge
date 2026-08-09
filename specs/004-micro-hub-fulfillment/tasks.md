# Tasks: Micro-Hub Fulfillment

**Input**: Design documents from `specs/003-micro-hub-fulfillment/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`,
`contracts/web-routes-and-props.md`, and `quickstart.md`

**Tests**: Tests are mandatory. Write the named Pest test before its implementation,
run it once to confirm it fails for the missing behavior, then implement only enough to
make that test pass.

**Junior-agent rule**: Complete tasks in numeric order unless a task has `[P]`. Do not
start the next phase until its checkpoint passes. Do not create a placeholder upstream
model, add a package, or broaden the feature when a prerequisite is missing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Safe to perform in parallel after all earlier non-parallel dependencies pass.
- **[Story]**: The user story served by the task.
- Every task names the exact file or file pattern that it changes or verifies.

---

## Phase 1: Setup and Hard Prerequisite Gate

**Purpose**: Prove that the earlier roadmap phases exist and load the project rules
before creating any hub code.

- [ ] T001 Run the hard prerequisite checks from `specs/003-micro-hub-fulfillment/quickstart.md`; verify `app/Models/ProductOffer.php`, `app/Models/Order.php`, `app/Models/OrderStatusTransition.php`, `app/Models/ProcurementRequirement.php`, and `app/Models/OrderGroup.php` exist, and STOP this feature immediately if any file or its grouped-order workflow is missing.
- [ ] T002 Verify the upstream fields, relationships, states, and operations-user factory state against the contract in `specs/003-micro-hub-fulfillment/plan.md`; adapt later hub foreign keys to the real names and never create hub-local substitutes.
- [ ] T003 Confirm installed versions in `composer.lock` and `package.json`, then use Laravel Boost `search-docs` for transactions, conditional updates, Form Requests, policies, rate limiting, encrypted casts, Inertia forms, and Wayfinder before editing implementation files.
- [ ] T004 Read `.ai/rules/index.md`, `.ai/rules/actions.md`, `.ai/rules/controllers-requests.md`, and `.ai/rules/unit-feature.md`, then inspect the nearest sibling Action, Request, controller, policy, model, factory, Pest test, Inertia page, and sidebar component before copying their conventions.
- [ ] T005 Verify the existing quality commands referenced by `specs/003-micro-hub-fulfillment/quickstart.md` exist in `composer.json` and `package.json`; do not add or upgrade a dependency when a command is absent.

**Checkpoint**: All upstream models and lifecycle contracts exist. If this checkpoint
fails, stop here and implement the earlier roadmap feature first.

---

## Phase 2: Foundational Domain and Security Boundaries

**Purpose**: Add the exact quantity type, enums, schema, models, authorization, replay
fingerprints, and safe conflict behavior shared by all stories.

**CRITICAL**: No user-story Action, route, or UI work begins until this phase passes.

- [ ] T006 Create failing parse/format/invalid-input/overflow tests for integer hundredths in `tests/Unit/Support/Hub/KilogramQuantityTest.php`.
- [ ] T007 Implement decimal-string parsing and two-decimal formatting without floats in `app/Support/Hub/KilogramQuantity.php` until T006 passes.
- [ ] T008 [P] Create the Grade A/B/C backed enum and labels in `app/Enums/HubQualityGrade.php`.
- [ ] T009 [P] Create the quality, transit damage, other, and server-only procurement-overage backed enum in `app/Enums/ReceiptRejectionReason.php`.
- [ ] T010 [P] Create the weighing-error, data-entry-error, and other backed enum in `app/Enums/ReceiptCorrectionReason.php`.
- [ ] T011 [P] Create the handling-damage, storage-damage, spoilage, and other backed enum in `app/Enums/HandlingLossReason.php`.
- [ ] T012 [P] Create the allocation-error, stock-issue, and other backed enum in `app/Enums/AllocationReleaseReason.php`.
- [ ] T013 [P] Create the grouped, allocated, ready-for-dispatch, and dispatched backed enum in `app/Enums/OrderGroupFulfillmentStatus.php`.
- [ ] T014 [P] Add the fixed 24-hour target, receipt page size 25, auxiliary list limit 50, and Casablanca display timezone in `config/hub.php` without environment secrets or mutable business settings.
- [ ] T015 [P] Implement SHA-256 token hashing and deterministic ordered scalar-payload hashing in `app/Support/Hub/HubOperationFingerprint.php`; never persist or log the raw token or encoded payload.
- [ ] T016 Generate and implement the receipts migration first in `database/migrations/*_create_hub_receipts_table.php`, matching every column, index, FK action, and integer-hundredths type in `specs/003-micro-hub-fulfillment/data-model.md`.
- [ ] T017 Generate and implement the correction migration second in `database/migrations/*_create_hub_receipt_corrections_table.php`, including append-only before/after values, encrypted-note text columns, actor FK, and unique operation-token hash.
- [ ] T018 Generate and implement the handling-loss migration third in `database/migrations/*_create_handling_losses_table.php`, including positive integer quantity storage, occurrence time, actor FK, and replay hashes.
- [ ] T019 Generate and implement the allocation migration fourth in `database/migrations/*_create_stock_allocations_table.php`, including allocation/release/preparation facts, lifecycle version, public reference, indexes, and replay hashes.
- [ ] T020 Add fulfillment status and the nullable unique current-allocation pointer fifth in `database/migrations/*_add_fulfillment_fields_to_order_groups_table.php`, with a reversible down method that removes the FK before its columns.
- [ ] T021 Add the nullable allocation FK and unique order-transition guard sixth in `database/migrations/*_add_stock_allocation_to_order_status_transitions_table.php`, preserving existing transition rows and reversing it before allocation tables are dropped.
- [ ] T022 [P] Implement receipt casts, guarded/fillable rules, public route binding, relationships, and balanced factory states in `app/Models/HubReceipt.php` and `database/factories/HubReceiptFactory.php`.
- [ ] T023 [P] Implement append-only correction casts/relationships and valid factory states in `app/Models/HubReceiptCorrection.php` and `database/factories/HubReceiptCorrectionFactory.php`.
- [ ] T024 [P] Implement append-only handling-loss casts/relationships and valid factory states in `app/Models/HandlingLoss.php` and `database/factories/HandlingLossFactory.php`.
- [ ] T025 [P] Implement allocation lifecycle casts, encrypted notes, public route binding, relationships, and allocated/released/prepared factory states in `app/Models/StockAllocation.php` and `database/factories/StockAllocationFactory.php`.
- [ ] T026 Add fulfillment casts plus `currentStockAllocation()` and `stockAllocations()` relationships to the existing `app/Models/OrderGroup.php`; change no unrelated consolidation behavior.
- [ ] T027 Add the nullable allocation relationship and cast needed by hub transitions to the existing `app/Models/OrderStatusTransition.php`; retain its append-only convention.
- [ ] T028 Define separate view, receive, correct, recordDamage, allocate, release, and prepare abilities backed by the existing operations flag in `app/Policies/HubReceiptPolicy.php`, and register it only if model discovery is not already used in `app/Providers/AppServiceProvider.php`.
- [ ] T029 [P] Create fixed safe conflict codes/messages with no payload, token, note, SQL, PII, or internal ID in `app/Exceptions/HubConflictException.php`, then map it to the documented back-redirect operation error in `bootstrap/app.php`.
- [ ] T030 Register the named 60-per-minute authenticated-user-plus-IP limiter in `app/Providers/AppServiceProvider.php`; use the name `hub-operations` required by `routes/hub.php`.

**Checkpoint**: The quantity test passes; migrations run up and down on a fresh test
database; factories create balanced records; shared authorization and errors are safe.

---

## Phase 3: User Story 1 - Receive and Grade Consolidated Produce (Priority: P1) MVP

**Goal**: Authorized receivers can inspect one outstanding requirement, finalize one
balanced receipt, see accepted/rejected/overage quantities, and append an allowed
pre-downstream correction.

**Independent Test**: Finalize a 100 kg receipt with 96 kg accepted and 4 kg quality
rejected; verify 96 kg available. Also finalize a 110 kg delivery against a 100 kg
requirement and verify 10 kg is server-derived procurement overage.

### Tests for User Story 1

- [ ] T031 [P] [US1] Write direct tests for balance validation, overage derivation, short receipts, replay, mismatch, rollback, and one-receipt uniqueness in `tests/Unit/Actions/Hub/FinalizeHubReceiptActionTest.php`.
- [ ] T032 [P] [US1] Write direct tests for bounded safe requirement props, labels, and operation token generation in `tests/Unit/Actions/Hub/ShowReceiveProduceFormActionTest.php`.
- [ ] T033 [P] [US1] Write direct tests for explicit safe receipt props, formatted quantities, correction history bounds, and no model serialization in `tests/Unit/Actions/Hub/ShowHubReceiptActionTest.php`.
- [ ] T034 [P] [US1] Write direct tests for allowed pre-downstream correction, appended before/after history, recalculated overage, replay, mismatch, and blocked post-downstream correction in `tests/Unit/Actions/Hub/CorrectHubReceiptActionTest.php`.
- [ ] T035 [P] [US1] Write guest, unverified, unauthorized, validation, unknown-field, successful finalization/correction, idempotency, redirect, and Inertia-prop tests in `tests/Feature/Hub/HubReceiptTest.php`.

### Implementation for User Story 1

- [ ] T036 [US1] Implement finalization in one three-attempt transaction with requirement locking, replay lookup, exact integer balance checks, derived overage, receipt creation, and requirement-state update in `app/Actions/Hub/FinalizeHubReceiptAction.php`.
- [ ] T037 [P] [US1] Build the explicit receive-page requirement/options/token prop array in `app/Actions/Hub/ShowReceiveProduceFormAction.php`.
- [ ] T038 [US1] Build the explicit receipt, quantities, turnaround, correction history, options, permissions, and bounded-list prop array in `app/Actions/Hub/ShowHubReceiptAction.php`; return empty later-story lists until their tables contain records, not fake data.
- [ ] T039 [US1] Implement append-only correction with replay checks, downstream-state rejection, recalculated projection, invariant assertions, and inventory-version compare-and-set in `app/Actions/Hub/CorrectHubReceiptAction.php`.
- [ ] T040 [P] [US1] Validate and authorize only the documented receive payload, normalize local Casablanca time, and reject unknown/derived fields in `app/Http/Requests/Operator/Hub/ReceiveProduceRequest.php`.
- [ ] T041 [P] [US1] Validate and authorize only the documented correction payload, including reason/note rules and decimal strings, in `app/Http/Requests/Operator/Hub/CorrectHubReceiptRequest.php`.
- [ ] T042 [US1] Implement only `create`, `store`, and `show` HTTP mapping, Action calls, safe redirects, and existing toast shape in `app/Http/Controllers/Operator/HubReceiptController.php`.
- [ ] T043 [P] [US1] Implement only correction `store` HTTP mapping, validated data forwarding, and safe redirect/toast behavior in `app/Http/Controllers/Operator/HubReceiptCorrectionController.php`.
- [ ] T044 [US1] Create `routes/hub.php`, require it from `routes/web.php`, and add the receive-create, receive-store, receipt-show, and correction-store routes under the exact middleware, prefix, names, and public route bindings in `specs/003-micro-hub-fulfillment/contracts/web-routes-and-props.md`.
- [ ] T045 [P] [US1] Define shared stock, turnaround, option, receive-page, and receipt-page TypeScript contracts in `resources/js/types/hub.ts`, then export them from `resources/js/types/index.ts`.
- [ ] T046 [P] [US1] Build the immutable requirement summary and one Wayfinder `<Form>` with field errors, processing state, operation token, and no client quantity math in `resources/js/pages/operator/hub/receipts/create.tsx`.
- [ ] T047 [US1] Build the receipt reconciliation, quality/rejection display, correction form, correction history, safe empty states, and server-driven permission visibility in `resources/js/pages/operator/hub/receipts/show.tsx`.
- [ ] T048 [US1] Run `php artisan wayfinder:generate --with-form --no-interaction`, then run `tests/Unit/Actions/Hub/FinalizeHubReceiptActionTest.php`, `tests/Unit/Actions/Hub/ShowReceiveProduceFormActionTest.php`, `tests/Unit/Actions/Hub/ShowHubReceiptActionTest.php`, `tests/Unit/Actions/Hub/CorrectHubReceiptActionTest.php`, and `tests/Feature/Hub/HubReceiptTest.php`; fix only files in this story until all pass.

**Checkpoint**: User Story 1 works by itself and is the suggested MVP demo slice.

---

## Phase 4: User Story 2 - Control Stock and Handling Losses (Priority: P2)

**Goal**: Authorized operators can move an exact quantity from available to damaged and
see a separate, auditable loss record.

**Independent Test**: Record 1 kg damage against 96 kg available and verify 95 kg
available, 1 kg damaged, unchanged accepted quantity, and a visible reasoned audit row.

### Tests for User Story 2

- [ ] T049 [P] [US2] Write direct success, excess/zero quantity, time bounds, replay, mismatch, stale-version, invariant, and rollback tests in `tests/Unit/Actions/Hub/RecordHandlingLossActionTest.php`.
- [ ] T050 [P] [US2] Write HTTP authorization, validation, unknown-field, persistence, conflict, redirect, audit-history, and stock-prop tests in `tests/Feature/Hub/HubStockTest.php`.

### Implementation for User Story 2

- [ ] T051 [US2] Implement append-only loss creation plus available-to-damaged compare-and-set movement in one three-attempt transaction in `app/Actions/Hub/RecordHandlingLossAction.php`.
- [ ] T052 [P] [US2] Validate and authorize only operation token, positive quantity, allowed reason, bounded note, and receipt-to-now occurrence time in `app/Http/Requests/Operator/Hub/RecordHandlingLossRequest.php`.
- [ ] T053 [US2] Add the thin loss `store` adapter in `app/Http/Controllers/Operator/HubHandlingLossController.php` and the exact handling-loss POST route in `routes/hub.php`.
- [ ] T054 [US2] Extend `app/Actions/Hub/ShowHubReceiptAction.php` with at most 50 explicitly shaped loss rows, then add the damage form and audit table with errors/empty state to `resources/js/pages/operator/hub/receipts/show.tsx`.
- [ ] T055 [US2] Regenerate Wayfinder and run `tests/Unit/Actions/Hub/RecordHandlingLossActionTest.php`, `tests/Unit/Actions/Hub/ShowHubReceiptActionTest.php`, and `tests/Feature/Hub/HubStockTest.php`; confirm every successful and failed path preserves both stock invariants.

**Checkpoint**: User Stories 1 and 2 pass independently and losses are never hidden in
available stock or pricing.

---

## Phase 5: User Story 3 - Allocate Stock Without Overselling (Priority: P3)

**Goal**: Reserve one complete compatible order group from one receipt, advance every
included order exactly once, and release the full unprepared allocation exactly once.

**Independent Test**: Allocate a 45 kg group from 95 kg available, verify 50 available
and 45 allocated, then release it and verify the exact reverse movement and states.

### Tests for User Story 3

- [ ] T056 [P] [US3] Write direct tests for compatibility, full-group-only quantity, insufficient stock, order count checks, replay/mismatch, conditional group claim, stale inventory, rollback, and competing allocations in `tests/Unit/Actions/Hub/AllocateOrderGroupActionTest.php`.
- [ ] T057 [P] [US3] Write direct tests for exact full release, reason audit, replay/mismatch, prepared/dispatched rejection, conditional pointer clear, order transitions, stale versions, and rollback in `tests/Unit/Actions/Hub/ReleaseStockAllocationActionTest.php`.
- [ ] T058 [P] [US3] Write HTTP authorization, validation, unknown-field, allocation/release success, safe conflict, no partial mutation, one-group/one-receipt, and transition-count tests in `tests/Feature/Hub/HubAllocationTest.php`.

### Implementation for User Story 3

- [ ] T059 [US3] Implement stable lock order, server-derived full group quantity, conditional group claim, receipt compare-and-set, exact order bulk update, and append-only transitions in `app/Actions/Hub/AllocateOrderGroupAction.php`.
- [ ] T060 [US3] Implement active-unprepared release, matching-pointer clear, exact receipt reversal, exact order rollback, transition append, and lifecycle compare-and-set in `app/Actions/Hub/ReleaseStockAllocationAction.php`.
- [ ] T061 [P] [US3] Validate and authorize only operation token plus public order-group reference, never a client quantity or state, in `app/Http/Requests/Operator/Hub/AllocateStockRequest.php`.
- [ ] T062 [P] [US3] Validate and authorize only operation token, allowed release reason, and bounded note in `app/Http/Requests/Operator/Hub/ReleaseStockAllocationRequest.php`.
- [ ] T063 [US3] Add thin allocation/release adapters in `app/Http/Controllers/Operator/HubAllocationController.php` and `app/Http/Controllers/Operator/HubAllocationReleaseController.php`, then add their exact POST routes to `routes/hub.php`.
- [ ] T064 [US3] Extend `app/Actions/Hub/ShowHubReceiptAction.php` with at most 50 compatible groups and 50 explicitly shaped allocation rows, deriving compatibility and permissions on the server.
- [ ] T065 [US3] Add one allocation form per compatible group plus allocation history/release forms and clear conflict/empty states to `resources/js/pages/operator/hub/receipts/show.tsx`; do not calculate stock or compatibility in React.
- [ ] T066 [US3] Regenerate Wayfinder and run `tests/Unit/Actions/Hub/AllocateOrderGroupActionTest.php`, `tests/Unit/Actions/Hub/ReleaseStockAllocationActionTest.php`, and `tests/Feature/Hub/HubAllocationTest.php` until all pass.
- [ ] T067 [US3] Run the focused `competing allocations` test from `specs/003-micro-hub-fulfillment/quickstart.md` with file-backed SQLite and then the supported MySQL/PostgreSQL test connection; record the exact environment error and leave this task incomplete if the real-engine run cannot execute.

**Checkpoint**: Competing writes cannot oversell, groups are never split or combined,
and release restores the entire unprepared allocation once.

---

## Phase 6: User Story 4 - Prepare Produce for Dispatch (Priority: P4)

**Goal**: Mark an exact complete allocation ready for dispatch while leaving stock and
orders allocated for the later delivery feature.

**Independent Test**: Prepare a 45 kg allocation once and verify the group is ready for
dispatch, the receipt still has 45 kg allocated, and no Order becomes dispatched.

### Tests for User Story 4

- [ ] T068 [P] [US4] Write direct tests for exact prepared quantity, active allocation, replay/mismatch, lifecycle compare-and-set, unchanged stock/order status, and no dispatch fact in `tests/Unit/Actions/Hub/PrepareOrderGroupActionTest.php`.
- [ ] T069 [P] [US4] Write HTTP authorization, validation, unknown-field, success, conflict, fulfillment-summary, unchanged allocation, and route-scope tests in `tests/Feature/Hub/HubPreparationTest.php`.

### Implementation for User Story 4

- [ ] T070 [US4] Implement exact-quantity preparation, allocation lifecycle compare-and-set, and group ready-for-dispatch transition in one transaction in `app/Actions/Hub/PrepareOrderGroupAction.php`; do not update receipt buckets or Order statuses.
- [ ] T071 [P] [US4] Validate and authorize only operation token plus positive prepared quantity in `app/Http/Requests/Operator/Hub/PrepareStockAllocationRequest.php`.
- [ ] T072 [US4] Add the thin preparation adapter in `app/Http/Controllers/Operator/HubPreparationController.php` and the exact prepare POST route in `routes/hub.php`; add no dispatch route.
- [ ] T073 [US4] Extend `app/Actions/Hub/ShowHubReceiptAction.php` and `resources/js/pages/operator/hub/receipts/show.tsx` with the safe fulfillment summary and preparation form; label prepared rows `Ready for dispatch` while still displaying allocated stock.
- [ ] T074 [US4] Regenerate Wayfinder and run `tests/Unit/Actions/Hub/PrepareOrderGroupActionTest.php` plus `tests/Feature/Hub/HubPreparationTest.php`; also assert `routes/hub.php` contains no dispatch endpoint.

**Checkpoint**: Preparation is complete and auditable, while physical dispatch remains
entirely outside this feature.

---

## Phase 7: User Story 5 - Identify Overdue Produce (Priority: P5)

**Goal**: Show a bounded, filterable hub queue with reconciled totals and a server-derived
24-hour warning for receipts that still contain available or allocated produce.

**Independent Test**: At exactly 24 hours a receipt with undispatched stock is overdue;
a 23:59:59 receipt and a fully damaged/dispatched receipt are not overdue.

### Tests for User Story 5

- [ ] T075 [P] [US5] Write direct tests for all filters, totals, exact 24-hour boundary, Casablanca labels, depleted exclusion, 25-row pagination, 50-row requirement cap, selected columns, eager loading, and stable query count with 500 receipts in `tests/Unit/Actions/Hub/ListHubWorkQueueActionTest.php`.
- [ ] T076 [P] [US5] Write route auth/authorization, filter validation, unknown-field removal, Inertia prop allowlist, pagination, totals, overdue, empty-state inputs, rate-limit 429, and 500-receipt integration tests in `tests/Feature/Hub/HubWorkQueueTest.php`.

### Implementation for User Story 5

- [ ] T077 [US5] Implement one captured current time, conditional aggregates, bounded eager loads, filters, oldest outstanding requirements, and 25-row receipt pagination in `app/Actions/Hub/ListHubWorkQueueAction.php`.
- [ ] T078 [P] [US5] Validate/authorize only product public reference, receipt state, allocation state, service date, overdue boolean, and page in `app/Http/Requests/Operator/Hub/ListHubWorkQueueRequest.php`.
- [ ] T079 [US5] Add the thin index adapter in `app/Http/Controllers/Operator/HubDashboardController.php` and the exact GET index route in `routes/hub.php`.
- [ ] T080 [US5] Build summary cards, filters, bounded outstanding requirements, paginated responsive receipt table, overdue badge, remaining-time text, and empty states in `resources/js/pages/operator/hub/index.tsx`; use only server totals/labels and Wayfinder links.
- [ ] T081 [US5] Add the server-permission-controlled `Hub fulfillment` link using Wayfinder in `resources/js/components/app-sidebar.tsx`; keep policies and middleware as the authorization boundary.
- [ ] T082 [US5] Regenerate Wayfinder, run `tests/Unit/Actions/Hub/ListHubWorkQueueActionTest.php` and `tests/Feature/Hub/HubWorkQueueTest.php`, then run the `500 active receipts` check from `specs/003-micro-hub-fulfillment/quickstart.md` and confirm pagination/query bounds.

**Checkpoint**: All five stories work, and overdue/filtering logic is authoritative,
bounded, and independently tested.

---

## Final Phase: Privacy, Quality, and Cross-Cutting Verification

**Purpose**: Prove the full feature is secure, exact, responsive, and within scope.

- [ ] T083 [P] Add public-response, unauthorized-response, Inertia-prop, exception, and log assertions proving no supplier/customer PII, private actor IDs, notes, raw tokens, payload hashes, SQL, or internal IDs leak in `tests/Feature/Hub/HubPrivacyTest.php`.
- [ ] T084 Review every class in `app/Actions/Hub/` for one use case, HTTP independence, one short `DB::transaction(..., attempts: 3)` per mutation, stable lock order, replay behavior, invariant checks, and optimistic/conditional updates; fix only hub files.
- [ ] T085 Review every class in `app/Http/Requests/Operator/Hub/`, `app/Http/Controllers/Operator/`, `app/Policies/HubReceiptPolicy.php`, `routes/hub.php`, and `bootstrap/app.php` for allowlisted input, server authorization, thin mapping, safe errors, CSRF middleware, and throttling.
- [ ] T086 Review `resources/js/pages/operator/hub/`, `resources/js/types/hub.ts`, and `resources/js/components/app-sidebar.tsx` for Wayfinder-only URLs, no client stock/time/compatibility math, no optimistic inventory, no polling, responsive tables, labels, focus, errors, dark mode, and no dispatch control.
- [ ] T087 Run `php artisan test --compact tests/Unit/Support/Hub/KilogramQuantityTest.php`, `php artisan test --compact tests/Unit/Actions/Hub`, `php artisan test --compact tests/Feature/Hub`, and then `php artisan test --compact`; leave this task incomplete for any failing test.
- [ ] T088 Run `vendor/bin/pint --dirty --format agent` and `vendor/bin/phpstan analyse` against the modified PHP files; report the exact command error and do not claim success if either command cannot run.
- [ ] T089 Run `npm.cmd run format:check`, `npm.cmd run lint:check`, `npm.cmd run types:check`, and `npm.cmd run build` for `resources/js/pages/operator/hub/`, `resources/js/types/hub.ts`, and `resources/js/components/app-sidebar.tsx`; fix only feature-related failures.
- [ ] T090 Run `composer audit` for `composer.lock` and `npm.cmd audit` for `package-lock.json`, then resolve or explicitly block completion on any exploitable dependency result without changing package versions unless the user approves it.
- [ ] T091 Complete the manual desktop/mobile/keyboard/dark-mode/demo/privacy flow in `specs/003-micro-hub-fulfillment/quickstart.md`, re-run the Constitution Check from `specs/003-micro-hub-fulfillment/plan.md`, and confirm there is no dispatch, partial allocation, multi-receipt fulfillment, queue, scheduler, transfer, or new dependency in the diff.

---

## Dependencies and Execution Order

### Phase Dependencies

1. Phase 1 has no dependency and is a hard stop gate.
2. Phase 2 depends on Phase 1 and blocks every user story.
3. US1 depends on Phase 2 and is the MVP.
4. US2 depends on US1 because handling loss mutates a finalized receipt.
5. US3 depends on US2 because allocation uses the current available/damaged balance.
6. US4 depends on US3 because only an active allocation can be prepared.
7. US5 depends on US1 for receipts; implement it after US4 so its allocation-state and
   ready-for-dispatch filters can be verified completely.
8. The final phase depends on every selected user story.

### User Story Dependency Graph

```text
Setup gate -> Foundation -> US1 Receive -> US2 Loss -> US3 Allocate/Release
                                                   -> US4 Prepare -> US5 Queue/Overdue
                                                                    -> Final checks
```

### Within Each User Story

1. Write the named Action and HTTP tests first and confirm the expected failure.
2. Implement the Action and make its direct test pass.
3. Implement the Form Request, thin controller, and route.
4. Extend server props and the Inertia page using Wayfinder.
5. Regenerate Wayfinder; never hand-edit generated files.
6. Run the story checkpoint before starting the next story.

## Parallel Opportunities

- **Foundation**: T008-T015, T022-T025, and T029 use separate files after their earlier
  prerequisites are complete. T028 and T030 both may edit `AppServiceProvider.php`, so
  keep them sequential.
- **US1**: T031-T035 can be authored in separate test files; T037, T040, T041, T043,
  T045, and T046 are separate implementation files after their inputs exist.
- **US2**: T049 and T050 can be authored together; T052 can proceed while T051 is being
  implemented.
- **US3**: T056-T058 can be authored together; T061 and T062 can proceed together after
  the Action contracts are fixed.
- **US4**: T068 and T069 can be authored together; T071 is separate from T070.
- **US5**: T075 and T076 can be authored together; T078 is separate from T077.
- Never parallelize tasks that both edit `routes/hub.php`,
  `app/Actions/Hub/ShowHubReceiptAction.php`, or
  `resources/js/pages/operator/hub/receipts/show.tsx`.

## Delivery Strategy

### MVP First

1. Complete Phase 1 and stop if any upstream prerequisite is absent.
2. Complete Phase 2.
3. Complete US1 only.
4. Demonstrate receipt finalization, overage rejection, correction, authorization, and
   exact reconciliation before accepting more scope.

### Incremental Delivery

1. Add US2 and prove handling loss separately.
2. Add US3 and do not pass its checkpoint without the real-engine concurrency run.
3. Add US4 while keeping dispatch outside the feature.
4. Add US5 and prove bounded reads at 500 receipts.
5. Run the final privacy and quality gates.

Do not mark a task complete while its test fails, its required command did not run, or
its checkpoint is not true.
