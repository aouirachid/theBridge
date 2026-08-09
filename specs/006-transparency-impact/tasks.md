# Tasks: Transparency Ledger and Impact Dashboard

**Input**: Design documents from `specs/006-transparency-impact/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`,
`quickstart.md`, and both files under `contracts/`.

**Tests**: Tests are mandatory. Write each listed Pest test before its implementation,
run it, and confirm it fails for the expected missing behavior. Every Action receives a
direct unit test; every HTTP behavior receives a feature test.

**Junior-agent rule**: Work from the first unchecked task downward. Do not skip a failed
checkpoint, do not combine later tasks, and do not create substitutes for missing
upstream code. `[P]` means safe for separate agents, but a single junior agent should
still execute sequentially.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: May run in parallel only after its stated dependencies pass.
- **[Story]**: Maps the task to US1, US2, US3, or US4 from `spec.md`.
- Every task names its exact target or inspection path.

---

## Phase 1: Setup and Hard Upstream Gate

**Purpose**: Confirm that Phase 006 is allowed to start. This phase currently blocks
implementation because the repository does not yet contain Phases 001-005.

- [ ] T001 Read `specs/006-transparency-impact/plan.md`, `specs/006-transparency-impact/research.md`, `specs/006-transparency-impact/data-model.md`, `specs/006-transparency-impact/contracts/internal-ledger-events.md`, `specs/006-transparency-impact/contracts/web-routes-and-props.md`, and `specs/006-transparency-impact/quickstart.md`; write no source code until all six are understood
- [ ] T002 Run the exact required-Action check from section 2 of `specs/006-transparency-impact/quickstart.md` against `app/Actions/`; if any path is missing, report the missing paths and STOP without creating Phase 006 files
- [ ] T003 Compare the implemented Phase 001-005 models/migrations under `app/Models/` and `database/migrations/` with the Upstream Contract Gate in `specs/006-transparency-impact/plan.md`; if any required public ID, snapshot, fixed reason enum, relationship, or permission differs, report the exact mismatch and STOP for artifact reconciliation
- [ ] T004 Run `php artisan test --compact tests/Feature/ProductOffers tests/Feature/Orders tests/Feature/Consolidations tests/Feature/Hub tests/Feature/Dispatches`; require every upstream suite to exist and pass before continuing, and record the exact failing command/output if it does not
- [ ] T005 Confirm installed versions from `composer.lock` and `package-lock.json`, inspect sibling conventions in `app/Actions/`, `app/Http/Requests/`, `app/Http/Controllers/`, `app/Models/`, `resources/js/pages/`, and `tests/`, then use Laravel Boost documentation search for transactions, locking, rate limits, Inertia forms, Wayfinder, and Pest before editing

**Checkpoint**: Continue only when T002-T004 pass. The current starter-only repository
is expected to stop here.

---

## Phase 2: Foundational Ledger and Source Integration

**Purpose**: Build the shared append-only chain and connect every upstream source event.
All four user stories depend on this phase.

**CRITICAL**: Do not start a user-story phase until T051 passes.

### Configuration, enums, and pure contracts

- [ ] T006 Create `config/transparency.php` with only the documented chain key, 10,000-entry verification limit, 30-second limit, 25-row page size, 10/min mutation limit, and 120/min public limit from `specs/006-transparency-impact/data-model.md`; use environment-backed config only where deployment variability is required and add no secrets
- [ ] T007 [P] Generate and implement the exact enum cases/values in `app/Enums/LedgerEventType.php`, `app/Enums/LedgerVerificationStatus.php`, `app/Enums/LedgerVerificationFailure.php`, `app/Enums/ImpactIndicatorClassification.php`, and `app/Enums/ImpactIndicatorCode.php`; do not add generic/custom cases
- [ ] T008 [P] Run `php artisan make:test --pest --unit Support/Transparency/LedgerHasherTest --no-interaction`, then add failing datasets for recursive object-key sorting, list-order preservation, UTC second timestamps, Unicode/slash encoding, stable SHA-256 output, and rejection of floats/objects/resources/invalid UTF-8 in `tests/Unit/Support/Transparency/LedgerHasherTest.php`
- [ ] T009 Implement only the canonicalization and exact version-1 envelope hash contract in `app/Support/Transparency/LedgerHasher.php`, then run `php artisan test --compact tests/Unit/Support/Transparency/LedgerHasherTest.php`
- [ ] T010 Run `php artisan make:test --pest --unit Support/Transparency/LedgerEventDataTest --no-interaction`, then add failing tests for all 14 named event constructors, exact required/extra-key rejection, safe UUID subjects, exact idempotency strings, deterministic list ordering, integer-only money/quantity values, and PII canaries in `tests/Unit/Support/Transparency/LedgerEventDataTest.php`
- [ ] T011 Implement the 14 named constructors and readonly accessors defined by `specs/006-transparency-impact/contracts/internal-ledger-events.md` in `app/Support/Transparency/LedgerEventData.php`; accept no Request/model serialization or generic arbitrary payload constructor, then run its unit test

### Demo flag and database structure

- [ ] T012 Add failing coverage for server-owned demo status, default false behavior, factory state, ordinary HTTP rejection, and post-publication immutability in `tests/Unit/Actions/ProductOffers/CreateProductOfferDraftActionTest.php`, `tests/Feature/ProductOffers/OperatorProductOfferTest.php`, and `tests/Feature/ProductOffers/PublicProductOfferTest.php`
- [ ] T013 Generate `database/migrations/*_add_is_demo_to_product_offers_table.php`, add the boolean cast/factory state in `app/Models/ProductOffer.php` and `database/factories/ProductOfferFactory.php`, pass true only from the later demo Action through `app/Actions/ProductOffers/CreateProductOfferDraftAction.php`, keep all ordinary Requests unable to set it, and run the tests from T012
- [ ] T014 Run `php artisan make:model LedgerChain -mf --no-interaction`, then implement the exact fields, unique key, casts, fillable allowlist, relationships, and factory defaults in `database/migrations/*_create_ledger_chains_table.php`, `app/Models/LedgerChain.php`, and `database/factories/LedgerChainFactory.php`
- [ ] T015 Run `php artisan make:model LedgerEntry -mf --no-interaction`, then implement the exact fields, foreign keys, unique event key/chain position, indexes, hidden attributes, no `updated_at`, mutation guard hooks, relationships, and safe factory in `database/migrations/*_create_ledger_entries_table.php`, `app/Models/LedgerEntry.php`, and `database/factories/LedgerEntryFactory.php`
- [ ] T016 Run `php artisan make:model LedgerVerification -mf --no-interaction`, then implement the exact endpoint/status/failure fields, indexes, enum/date casts, no `updated_at`, relationships, and factory states in `database/migrations/*_create_ledger_verifications_table.php`, `app/Models/LedgerVerification.php`, and `database/factories/LedgerVerificationFactory.php`
- [ ] T017 Run `php artisan make:model ImpactIndicator -mf --no-interaction`, then implement the exact cycle/version/classification/count/provenance fields, foreign keys, indexes, casts, relationships, and estimate/measured factory states in `database/migrations/*_create_impact_indicators_table.php`, `app/Models/ImpactIndicator.php`, and `database/factories/ImpactIndicatorFactory.php`
- [ ] T018 Generate `database/migrations/*_insert_platform_ledger_chain.php` with `php artisan make:migration insert_platform_ledger_chain --no-interaction`; insert exactly one `platform` head with position 0/null hash in `up()` and remove only that untouched empty head in `down()`, keeping DML separate from table creation
- [ ] T019 Run `php artisan make:test --pest Transparency/LedgerEntryImmutabilityTest --no-interaction`, then add failing Eloquent, bulk query-builder, and raw SQLite UPDATE/DELETE rejection tests plus the trigger-restoration `finally` assertion in `tests/Feature/Transparency/LedgerEntryImmutabilityTest.php`
- [ ] T020 Generate `database/migrations/*_protect_ledger_entries_from_mutation.php` with `php artisan make:migration protect_ledger_entries_from_mutation --no-interaction`, create exactly the SQLite triggers `ledger_entries_no_update` and `ledger_entries_no_delete`, implement reversible `down()`, finish model-level guards in `app/Models/LedgerEntry.php`, and run T019
- [ ] T021 Create `app/Exceptions/LedgerConflictException.php` with fixed safe codes/messages only; expose no payload, hash, SQL, model, actor, or raw exception context, and defer HTTP rendering until T066
- [ ] T022 Run migrations on the test database and add schema/relationship assertions to `tests/Feature/Transparency/LedgerEntryImmutabilityTest.php` for four tables, singleton head, required unique/index constraints, null/positive head invariants, and trigger names; run only this file and stop on any mismatch

### Append Action

- [ ] T023 Run `php artisan make:test --pest --unit Actions/Transparency/AppendLedgerEntryActionTest --no-interaction`, then add failing direct-Action tests for genesis, second entry, exact hash envelope, private actor reference, identical replay, mismatched replay, source rollback, append rollback, forbidden payload, head mismatch, 10,000 sequential entries, and zero PII in `tests/Unit/Actions/Transparency/AppendLedgerEntryActionTest.php`
- [ ] T024 Generate `app/Actions/Transparency/AppendLedgerEntryAction.php` with `php artisan make:class Actions/Transparency/AppendLedgerEntryAction --no-interaction`; implement only the seven-step locked-head algorithm from `data-model.md`, make it the sole entry inserter/head updater, and run T023
- [ ] T025 Add separate-connection/process cases for 100 distinct events plus duplicate replays to `tests/Unit/Actions/Transparency/AppendLedgerEntryActionTest.php`; require one contiguous chain, exactly 100 new entries, no fork/gap/duplicate, and one valid head
- [ ] T026 Add only the minimum transaction retry/conflict handling needed to make T025 pass in `app/Actions/Transparency/AppendLedgerEntryAction.php` and `config/transparency.php`; never add a cache lock, queue, listener, observer, or ignored arbitrary database exception

### Upstream event integrations

> For every pair below: write the direct upstream Action test first, confirm the expected
> missing-event failure, then add exactly one append call inside the existing source
> transaction. Do not change unrelated source behavior.

- [ ] T027 [P] Add OfferPublished/initial BenchmarkPublished success, replay, ordering, rollback, exact payload, demo flag, and privacy assertions to `tests/Unit/Actions/ProductOffers/PublishProductOfferActionTest.php`
- [ ] T028 Inject and call `AppendLedgerEntryAction` inside the existing transaction in `app/Actions/ProductOffers/PublishProductOfferAction.php` using sections 1-2 of `contracts/internal-ledger-events.md`; append offer before initial benchmark and run T027 plus the Phase 001 feature tests
- [ ] T029 [P] Add refreshed BenchmarkPublished success, replay, rollback, exact source/demo/saving payload, and no internal comparison ID assertions to `tests/Unit/Actions/ProductOffers/PublishBenchmarkComparisonActionTest.php`
- [ ] T030 Integrate section 2 of `contracts/internal-ledger-events.md` inside `app/Actions/ProductOffers/PublishBenchmarkComparisonAction.php`, then run T029 plus `tests/Feature/ProductOffers/BenchmarkComparisonTest.php`
- [ ] T031 [P] Add OrderConfirmed exact immutable snapshot, replay, rollback, and PII-canary assertions to `tests/Unit/Actions/Orders/CreateOrderActionTest.php`
- [ ] T032 Integrate section 3 of `contracts/internal-ledger-events.md` inside the existing transaction in `app/Actions/Orders/CreateOrderAction.php`, then run T031 plus the Phase 002 order feature tests
- [ ] T033 [P] Add OrderCancelled exact status/released-quantity payload, replay, rollback, and private actor isolation assertions to `tests/Unit/Actions/Orders/CancelOrderActionTest.php`
- [ ] T034 Integrate section 4 of `contracts/internal-ledger-events.md` inside `app/Actions/Orders/CancelOrderAction.php`, then run T033 plus the Phase 002 cancellation feature tests
- [ ] T035 [P] Add ConsolidationCompleted exact distinct count/quantity/group ordering, replay, rollback, and excluded-order assertions to `tests/Unit/Actions/Consolidations/RunConsolidationCycleActionTest.php`
- [ ] T036 Integrate section 5 of `contracts/internal-ledger-events.md` at the one final commit point in `app/Actions/Consolidations/RunConsolidationCycleAction.php`, then run T035 plus all Phase 003 consolidation tests
- [ ] T037 [P] Add HubReceiptFinalized quantity/grade/fixed-reason, replay, rollback, and encrypted-note exclusion assertions to `tests/Unit/Actions/Hub/FinalizeHubReceiptActionTest.php`
- [ ] T038 Integrate section 6 of `contracts/internal-ledger-events.md` inside `app/Actions/Hub/FinalizeHubReceiptAction.php`, then run T037 plus the relevant Phase 004 feature tests
- [ ] T039 [P] Add HubReceiptCorrected before/after/fixed-reason, replay, rollback, and internal-ID/encrypted-note exclusion assertions to `tests/Unit/Actions/Hub/CorrectHubReceiptActionTest.php`
- [ ] T040 Integrate section 7 of `contracts/internal-ledger-events.md` inside `app/Actions/Hub/CorrectHubReceiptAction.php`, then run T039 plus the correction feature tests
- [ ] T041 [P] Add HandlingLossRecorded safe quantity/reason/counter, replay, rollback, and free-text-note exclusion assertions to `tests/Unit/Actions/Hub/RecordHandlingLossActionTest.php`
- [ ] T042 Integrate section 8 of `contracts/internal-ledger-events.md` inside `app/Actions/Hub/RecordHandlingLossAction.php`, then run T041 plus the loss feature tests
- [ ] T043 [P] Add StockAllocated exact safe references/quantity/counters, replay, rollback, and token/actor exclusion assertions to `tests/Unit/Actions/Hub/AllocateOrderGroupActionTest.php`
- [ ] T044 Integrate section 9 of `contracts/internal-ledger-events.md` inside `app/Actions/Hub/AllocateOrderGroupAction.php`, then run T043 plus the allocation feature tests
- [ ] T045 [P] Add StockAllocationReleased exact quantity/fixed reason/counters, replay, rollback, and release-note/token exclusion assertions to `tests/Unit/Actions/Hub/ReleaseStockAllocationActionTest.php`
- [ ] T046 Integrate section 10 of `contracts/internal-ledger-events.md` inside `app/Actions/Hub/ReleaseStockAllocationAction.php`, then run T045 plus the release feature tests
- [ ] T047 [P] Add DispatchSubmitted frozen membership/cost/allocation/status, replay, rollback, and provider/customer PII exclusion assertions to `tests/Unit/Actions/Dispatches/SubmitDispatchActionTest.php`
- [ ] T048 Integrate section 11 of `contracts/internal-ledger-events.md` inside `app/Actions/Dispatches/SubmitDispatchAction.php`, then run T047 plus the submission/retry feature tests
- [ ] T049 [P] Add DispatchStatusChanged exact transition/count/quantity/cost/fixed-failure, replay, rollback, and provider/customer PII exclusion assertions to `tests/Unit/Actions/Dispatches/AdvanceDispatchActionTest.php`
- [ ] T050 Integrate section 12 of `contracts/internal-ledger-events.md` inside `app/Actions/Dispatches/AdvanceDispatchAction.php`, then run T049 plus the dispatch lifecycle feature tests
- [ ] T051 Run the complete Phase 001-005 focused suites plus `tests/Unit/Support/Transparency/LedgerHasherTest.php`, `tests/Unit/Support/Transparency/LedgerEventDataTest.php`, `tests/Unit/Actions/Transparency/AppendLedgerEntryActionTest.php`, and `tests/Feature/Transparency/LedgerEntryImmutabilityTest.php`; require zero failures before US1

**Checkpoint**: One immutable, privacy-safe, replay-safe chain now records every required
source operation. No public or operator page exists yet.

---

## Phase 3: User Story 1 - Understand the Complete Price (Priority: P1) MVP

**Goal**: A guest can reproduce the full price, benchmark comparison, saving, farmer
share, demo status, and current integrity label from one privacy-safe page.

**Independent Test**: With one published tomato offer and current benchmark, a guest sees
2.80 farmer payment, every cost, 1.00 margin, 5.50 final price, 8.00 benchmark, 2.50/31.25%
saving, 50.91% farmer share, source/time/demo labels, and no PII. Stale/no/negative
benchmark states remain truthful.

### Tests for User Story 1

- [ ] T052 [US1] Run `php artisan make:test --pest --unit Actions/Transparency/ShowPublicTransparencyActionTest --no-interaction`, then add failing direct-Action tests for exact tomato price props, all ordered/zero costs, benchmark source/time/demo/freshness, stale withholding, zero/negative comparison language data, `is_demo`, not-yet/stale/current integrity labels, explicit allowlists, and safe 404s in `tests/Unit/Actions/Transparency/ShowPublicTransparencyActionTest.php`
- [ ] T053 [P] [US1] Run `php artisan make:test --pest Transparency/PublicTransparencyTest --no-interaction`, then add failing guest route binding, Inertia component/prop, public limiter, safe 404, page metadata, and exact price/benchmark assertions in `tests/Feature/Transparency/PublicTransparencyTest.php`
- [ ] T054 [P] [US1] Define the exact TypeScript interfaces from the public portion of `contracts/web-routes-and-props.md` in `resources/js/types/transparency.ts` and export them from `resources/js/types/index.ts`; do not add fields absent from the contract

### Implementation for User Story 1

- [ ] T055 [US1] Generate `app/Actions/Transparency/ShowPublicTransparencyAction.php` and implement only bounded selected-column/eager-loaded offer, costs, fresh comparison, and current integrity reads with upstream formatters and explicit arrays; return consolidation/indicator as null for now, then run T052
- [ ] T056 [US1] Generate `app/Http/Requests/Transparency/ShowPublicTransparencyRequest.php` and `app/Http/Controllers/PublicTransparencyController.php`, add the guest GET route and 120/min IP limiter in `routes/transparency.php` and `app/Providers/AppServiceProvider.php`, require `routes/transparency.php` from `routes/web.php`, and make the controller only invoke T055 and render `transparency/show`
- [ ] T057 [US1] Implement `resources/js/pages/transparency/show.tsx` with Head, existing Card/Badge/Alert/Separator primitives, a one-column mobile and responsive larger grid, semantic price breakdown, visible demo/freshness/integrity states, dark mode, and no chart/custom CSS/client calculation/deferred prop
- [ ] T058 [US1] Add one Wayfinder `<Link>` labeled `View transparency` to the existing public offer page in `resources/js/pages/offers/show.tsx`; do not hardcode the path or change other offer behavior
- [ ] T059 [US1] Run `php artisan make:test --pest Transparency/TransparencyPrivacyTest --no-interaction`, then add recursive public-prop, rendered HTML, metadata, log, exception, hash/position, internal-ID, actor, and canary PII assertions in `tests/Feature/Transparency/TransparencyPrivacyTest.php`
- [ ] T060 [US1] Run `php artisan test --compact tests/Unit/Actions/Transparency/ShowPublicTransparencyActionTest.php tests/Feature/Transparency/PublicTransparencyTest.php tests/Feature/Transparency/TransparencyPrivacyTest.php` plus `npm run types:check`; fix only US1 files until all pass

**Checkpoint**: US1 is a usable public pricing-transparency MVP. It can display integrity
state but operators cannot run verification until US2.

---

## Phase 4: User Story 2 - Verify Historical Integrity (Priority: P1)

**Goal**: An authorized operations user verifies one exact chain endpoint; normal entry
mutation is blocked; guests see only a simple truthful current/stale result.

**Independent Test**: A valid representative chain verifies, a controlled altered entry
reports invalid at a safe position, a new append makes the result stale, guests/ordinary
users cannot verify, and no public response exposes private diagnostics.

### Tests for User Story 2

- [ ] T061 [US2] Run `php artisan make:test --pest --unit Actions/Transparency/VerifyLedgerActionTest --no-interaction`, then add failing tests for empty/valid chains, genesis/order/gap/previous-hash/entry-hash/count/endpoint failures, 10,001 limit, 30-second cutoff, safe read interruption, stale endpoint, controlled trigger drop/restore, newest-result precedence, and 10,000-entry timing in `tests/Unit/Actions/Transparency/VerifyLedgerActionTest.php`
- [ ] T062 [P] [US2] Run `php artisan make:test --pest --unit Actions/Transparency/ShowTransparencyOperationsActionTest --no-interaction`, then add failing bounded pagination/filter/current-head/safe failure/authorized actor-label/explicit-array/query-count tests in `tests/Unit/Actions/Transparency/ShowTransparencyOperationsActionTest.php`
- [ ] T063 [P] [US2] Run `php artisan make:test --pest Transparency/OperatorTransparencyTest --no-interaction`, then add failing login, verified-user, operations-permission, policy, empty-body validation, CSRF, 10/min throttle, redirect/flash, Inertia props, safe 409/404, and thin-controller assertions in `tests/Feature/Transparency/OperatorTransparencyTest.php`

### Implementation for User Story 2

- [ ] T064 [US2] Generate `app/Actions/Transparency/VerifyLedgerAction.php` and implement the six-step selected-column cursor algorithm from `data-model.md`; record scoped Valid/Invalid/Incomplete results, never repair history, and run T061
- [ ] T065 [US2] Generate `app/Actions/Transparency/ShowTransparencyOperationsAction.php` and implement only the chain summary plus 25-row newest-first verification pagination/filter with explicit private props, then run T062
- [ ] T066 [US2] Generate `app/Policies/LedgerChainPolicy.php`, `app/Http/Requests/Transparency/Operator/ShowTransparencyOperationsRequest.php`, `app/Http/Requests/Transparency/Operator/VerifyLedgerRequest.php`, `app/Http/Controllers/Operator/Transparency/TransparencyController.php`, and `app/Http/Controllers/Operator/Transparency/LedgerVerificationController.php`; add protected GET/POST routes, 10/min user+IP limiter, and safe `LedgerConflictException` rendering in `routes/transparency.php`, `app/Providers/AppServiceProvider.php`, and `bootstrap/app.php`, keeping controllers HTTP-only
- [ ] T067 [P] [US2] Extend `resources/js/types/transparency.ts` with the exact operator chain/verification/can props from the web contract; keep public and private interfaces distinct
- [ ] T068 [US2] Implement the verification/history portion of `resources/js/pages/operator/transparency/index.tsx` with AppLayout, Wayfinder `<Form>`, disabled processing state, errors, status badges, safe pagination, responsive table/list fallback, and no polling/optimistic update; leave the indicator form for US4
- [ ] T069 [US2] Extend `tests/Feature/Transparency/TransparencyPrivacyTest.php` with protected/public separation, safe failure/log context, actor-label operator-only, no hash/full payload, and controlled corruption cleanup assertions
- [ ] T070 [US2] Run `php artisan test --compact tests/Unit/Actions/Transparency/VerifyLedgerActionTest.php tests/Unit/Actions/Transparency/ShowTransparencyOperationsActionTest.php tests/Feature/Transparency/OperatorTransparencyTest.php tests/Feature/Transparency/LedgerEntryImmutabilityTest.php tests/Feature/Transparency/TransparencyPrivacyTest.php` plus `npm run types:check`; fix only US2/shared defects until all pass

**Checkpoint**: US1 and US2 together prove the core auditable transparency claim.

---

## Phase 5: User Story 3 - See Consolidation Impact (Priority: P2)

**Goal**: The public page shows exact distinct grouped orders, consolidated kilograms,
delivery groups, rejected kilograms, and damaged kilograms for one completed scope.

**Independent Test**: A replayed completed cycle with multiple channels/zones counts each
order/group once, sums immutable grouped quantities exactly, labels excluded/loss values
separately, and shows incomplete/no-data rather than false zero impact.

### Tests for User Story 3

- [ ] T071 [US3] Extend `tests/Unit/Actions/Transparency/ShowPublicTransparencyActionTest.php` with failing completed/latest-scope selection, distinct order/group counts, exact integer quantity sums, channel/zone group ordering, rejected/damaged separation, replay de-duplication, incomplete/no-data, explicit allowlist, and bounded query-count tests
- [ ] T072 [P] [US3] Extend `tests/Feature/Transparency/PublicTransparencyTest.php` with failing Inertia consolidation prop, visible unit/scope, no order-level PII, no duplicate replay, and incomplete/no-data response assertions
- [ ] T073 [P] [US3] Add the exact consolidation/group TypeScript interfaces from the web contract to `resources/js/types/transparency.ts`

### Implementation for User Story 3

- [ ] T074 [US3] Extend `app/Actions/Transparency/ShowPublicTransparencyAction.php` with one-offer/one-completed-cycle indexed aggregate queries using selected columns, distinct source memberships/groups, immutable `quantity_hundredths`, and upstream formatters; do not add a reporting table/cache or load collections merely to count them
- [ ] T075 [US3] Extend `resources/js/pages/transparency/show.tsx` with a responsive consolidation section showing service date, completion, order count, kilograms, delivery groups, safe group rows, rejected/damaged quantities, and explicit incomplete/no-data Alerts; do not expose source orders
- [ ] T076 [US3] Add operator reconciliation assertions for safe cycle/group/event references and zero variance to `tests/Unit/Actions/Transparency/ShowTransparencyOperationsActionTest.php`, then expose only those bounded safe references from `app/Actions/Transparency/ShowTransparencyOperationsAction.php`
- [ ] T077 [US3] Run `php artisan test --compact tests/Unit/Actions/Transparency/ShowPublicTransparencyActionTest.php tests/Unit/Actions/Transparency/ShowTransparencyOperationsActionTest.php tests/Feature/Transparency/PublicTransparencyTest.php tests/Feature/Transparency/TransparencyPrivacyTest.php` plus `npm run types:check`; require zero duplicate-count or unexplained-variance failures

**Checkpoint**: US3 independently proves the consolidation impact without environmental
claims.

---

## Phase 6: User Story 4 - Distinguish Estimated and Measured Impact (Priority: P2)

**Goal**: An operator publishes only supported trip-reduction evidence, and guests can
immediately distinguish estimate from measurement with provenance and limitations.

**Independent Test**: Estimate derives 20 grouped orders minus 3 finalized groups as 17
trips with visible assumptions; measured data requires observed counts/method/source/
evidence; arbitrary/CO2 claims are rejected and absent publicly.

### Tests for User Story 4

- [ ] T078 [US4] Run `php artisan make:test --pest --unit Actions/Transparency/PublishImpactIndicatorActionTest --no-interaction`, then add failing estimate derivation, measured validation, signed negative value, completed-cycle ownership, version supersession, identical replay, rollback with ledger append, ImpactIndicatorPublished payload, mixed-input estimate, fixed code/unit, and unsupported CO2 tests in `tests/Unit/Actions/Transparency/PublishImpactIndicatorActionTest.php`
- [ ] T079 [P] [US4] Extend `tests/Feature/Transparency/OperatorTransparencyTest.php` with failing estimate field prohibition, measured required/range/date/public-safety validation, authorization, throttle, stale cycle conflict, redirect/flash, and no partial version tests
- [ ] T080 [P] [US4] Extend `tests/Unit/Actions/Transparency/ShowPublicTransparencyActionTest.php` and `tests/Feature/Transparency/PublicTransparencyTest.php` with failing current-version, estimate/measured label, method/source/evidence/assumption/limitation/period/demo, negative wording data, and no-unsupported-value assertions

### Implementation for User Story 4

- [ ] T081 [US4] Generate `app/Actions/Transparency/PublishImpactIndicatorAction.php`; implement the exact locked-cycle/version rules, server-derived estimate counts/method, measured evidence rules, signed value, supersession, and same-transaction ImpactIndicatorPublished append from `data-model.md`, then run T078
- [ ] T082 [US4] Generate `app/Http/Requests/Transparency/Operator/PublishImpactIndicatorRequest.php` and `app/Http/Controllers/Operator/Transparency/ImpactIndicatorController.php`, add the protected POST route in `routes/transparency.php`, accept only the web-contract fields, and keep derivation/business logic in T081
- [ ] T083 [US4] Extend `app/Actions/Transparency/ShowTransparencyOperationsAction.php` with bounded completed-cycle choices/defaults/capabilities and extend `app/Actions/Transparency/ShowPublicTransparencyAction.php` with the current unsuperseded indicator explicit array; run T079-T080 PHP tests
- [ ] T084 [P] [US4] Add the exact indicator/cycle/default TypeScript interfaces from the web contract to `resources/js/types/transparency.ts`
- [ ] T085 [US4] Extend `resources/js/pages/operator/transparency/index.tsx` with one Wayfinder indicator `<Form>`, estimate-versus-measured conditional fields, InputError/AlertError, processing disablement, accessible labels, responsive/dark styling, and no client-derived counts/value
- [ ] T086 [US4] Extend `resources/js/pages/transparency/show.tsx` with an impact Card that places `Estimate` or `Measured` beside the value and shows unit, period, method, provenance, assumptions, limitation, demo status, truthful negative wording, and an honest no-indicator state; add no CO2 placeholder/value
- [ ] T087 [US4] Extend `tests/Feature/Transparency/TransparencyPrivacyTest.php` with hostile source/evidence strings, private evidence, actor, payload, and arbitrary indicator/CO2 canaries across props, HTML, metadata, logs, and exceptions
- [ ] T088 [US4] Run `php artisan test --compact tests/Unit/Actions/Transparency/PublishImpactIndicatorActionTest.php tests/Unit/Actions/Transparency/ShowPublicTransparencyActionTest.php tests/Unit/Actions/Transparency/ShowTransparencyOperationsActionTest.php tests/Feature/Transparency/OperatorTransparencyTest.php tests/Feature/Transparency/PublicTransparencyTest.php tests/Feature/Transparency/TransparencyPrivacyTest.php` plus `npm run types:check`; require all estimate/measured/unsupported-claim cases to pass

**Checkpoint**: All four user stories work independently and together.

---

## Final Phase: Demo, Navigation, and Cross-Cutting Verification

**Purpose**: Build the required tomato scenario through production Actions, then prove
the complete implementation without weakening security or upstream behavior.

- [ ] T089 Run `php artisan make:test --pest --unit Actions/Transparency/SeedTransparencyDemoActionTest --no-interaction`, then add failing direct-Action tests for the exact 2.80/5.50/8.00 economics, 20 orders, 100.00 kg, 3 groups, 17 estimated trips, visible demo flags, production-Action usage, completed rerun reuse, partial-scenario stop, and zero direct source/ledger inserts in `tests/Unit/Actions/Transparency/SeedTransparencyDemoActionTest.php`
- [ ] T090 Generate `app/Actions/Transparency/SeedTransparencyDemoAction.php`; orchestrate only existing Phase 001-005 production Actions with safe fictional inputs/random operation tokens, locate completed reruns by the private demo actor plus exact immutable signature, stop partial matches with `demo_scenario_incomplete`, and run T089
- [ ] T091 Generate `database/seeders/TransparencyDemoSeeder.php` with `php artisan make:seeder TransparencyDemoSeeder --no-interaction`; find/create `transparency-demo@thebridge.test` with a generated random password and non-fillable sourcing/operations permissions, invoke T090, and never insert ProductOffer/Order/group/hub/dispatch/ledger/indicator rows directly
- [ ] T092 Extend `tests/Feature/Transparency/PublicTransparencyTest.php` with the seeded end-to-end acceptance page and exact 2.80, every named cost, 1.00, 5.50, 8.00, 2.50, 31.25%, 50.91%, 20, 100.00 kg, 3, 17, demo/estimate/integrity labels, rerun identity, and no CO2/PII assertions
- [ ] T093 Add one operations-only `Transparency` Wayfinder navigation item to `resources/js/components/app-sidebar.tsx`; verify ordinary users do not receive the capability/link and do not change unrelated navigation
- [ ] T094 Run `php artisan route:list --name=transparency --except-vendor` and require exactly the four contracted routes/no mutation routes, then run `php artisan wayfinder:generate --with-form --no-interaction` to regenerate `resources/js/actions/` and `resources/js/routes/`; never hand-edit generated files
- [ ] T095 Run `vendor/bin/pint --dirty --format agent` and `vendor/bin/phpstan analyse --memory-limit=1G`; fix only reported files under `app/Actions/Transparency/`, `app/Enums/Transparency/`, `app/Http/Controllers/Transparency/`, `app/Http/Requests/Transparency/`, `app/Models/`, `app/Policies/`, `app/Providers/`, and the explicitly modified Phase 001-005 Actions, then rerun until both pass
- [ ] T096 Run the focused Phase 001-005 suites, every file under `tests/Unit/Actions/Transparency/`, `tests/Unit/Support/Transparency/`, and `tests/Feature/Transparency/`, then `php artisan test --compact`; do not mark complete with any failure or missing upstream suite
- [ ] T097 Run `npm run lint:check`, `npm run format:check`, `npm run types:check`, and `npm run build`; fix only `resources/js/pages/transparency/show.tsx`, `resources/js/pages/operations/transparency/index.tsx`, `resources/js/components/app-sidebar.tsx`, and `resources/js/types/transparency.ts`, then rerun all four until they pass
- [ ] T098 Run `composer audit`, `npm audit`, and the privacy scan from section 10 of `specs/006-transparency-impact/quickstart.md`; review every match and require no exploitable dependency issue, ledger/public PII, hardcoded secret, raw payload, token, private provider value, or unsafe log context
- [ ] T099 Execute sections 6-9 of `specs/006-transparency-impact/quickstart.md` manually at mobile/desktop widths and light/dark mode, including seed rerun, public price reproduction, consolidation, estimate/measured labels, integrity transitions, keyboard focus, operator authorization, validation, and throttling; record exact failures and do not claim completion until resolved
- [ ] T100 Execute the separate-connection 100-event concurrency gate and SQLite trigger gate from section 11 of `specs/006-transparency-impact/quickstart.md`, re-run the Constitution Check against `specs/006-transparency-impact/plan.md`, and stop production deployment on any non-SQLite engine until equivalent application-account UPDATE/DELETE denial is implemented and tested

---

## Dependencies and Execution Order

### Phase Dependencies

```text
Phase 1 upstream gate
    -> Phase 2 ledger foundation and every source integration
        -> US1 public price transparency
            -> US2 integrity verification
        -> US3 consolidation impact
        -> US4 impact classification
            -> Final seeded end-to-end verification
```

- Phase 1 has no implementation dependency and currently blocks all later work.
- Phase 2 depends on every Phase 001-005 prerequisite and blocks every user story.
- US1 and US2 are both P1. Deliver US1 first because it creates the public surface; US2
  then makes its integrity indicator operational.
- US3 depends on Phase 2 and extends US1's public read/page.
- US4 depends on Phase 2, US1's public page, US2's operator page, and US3's completed
  cycle summary.
- Final demo/verification depends on all selected stories.

### Within Every Test/Implementation Pair

1. Create or extend the named test.
2. Run only that test and confirm it fails for the expected missing behavior.
3. Implement only the named production file(s).
4. Run the narrow test until it passes.
5. Run the affected upstream/feature test named in the task.
6. Continue only when both pass.

### Safe Parallel Opportunities

Parallel work is optional and intended for multiple agents, not one junior agent:

- After T006, T007 and T008 touch independent enum/helper-test files.
- After T026, the `[P]` upstream integration test tasks T027, T029, T031, T033, T035,
  T037, T039, T041, T043, T045, T047, and T049 touch separate phase test files. Their
  matching implementation tasks remain sequential for easy review.
- Within US1, T053 and T054 are independent after the web contract is accepted.
- Within US2, T062, T063, and T067 are independent after the foundational models exist.
- Within US3, T072 and T073 are independent after T071 defines expected props.
- Within US4, T079, T080, and T084 are independent after T078 defines domain behavior.

## Delivery Strategy

### Junior-Agent Default

Execute T001 through T100 in order. At every checkpoint, summarize files changed, tests
run, and exact results before continuing. If the same approach fails twice, stop and ask
for direction; do not invent a second architecture.

### Suggested MVP

- **Public-value MVP**: T001-T060 (foundation plus US1).
- **Minimum auditable MVP**: T001-T070 (foundation, US1, and US2). This is the recommended
  first demo checkpoint because it includes a working verification control.
- Add US3 and US4 only after the auditable MVP remains green.

### Non-Negotiable Stop Conditions

Stop immediately when:

- an upstream Phase 001-005 file, field, permission, invariant, or focused test is absent;
- a migration/model/event payload differs from the approved artifacts;
- an append cannot occur inside the source transaction;
- a ledger payload contains PII, a raw token, arbitrary metadata, or a float;
- SQLite update/delete protection cannot be proven;
- relevant tests, formatting, static analysis, frontend checks, build, audit, privacy,
  concurrency, or quickstart validation fails.

Never mark a task complete merely because a file was created. Its named test/check must
also pass.
