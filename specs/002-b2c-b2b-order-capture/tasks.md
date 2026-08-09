# Tasks: B2C and B2B Order Capture

**Input**: Design documents from `specs/002-b2c-b2b-order-capture/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`,
`contracts/web-routes-and-props.md`, and `quickstart.md`

**Tests**: Mandatory. Write every Action test before its Action implementation and every
HTTP behavior test before wiring its route. Run the focused command named by the task and
confirm the expected failure before implementing; do not proceed past a checkpoint while
its tests fail.

**Organization**: Work in numeric order. `[P]` means different files can be prepared in
parallel only after all lower-numbered dependencies are complete. Story labels map to the
three prioritized user stories in `spec.md`.

## Junior-Agent Execution Contract

1. Complete one checkbox at a time. Read the named design section and target file before
   editing.
2. Use `php artisan make:* --no-interaction` for PHP classes, migrations, factories, and
   Pest tests. Never hand-create generated PHP scaffolds.
3. Stop immediately at T001 if Phase 1 is absent or failing. Implement
   `specs/001-transparent-product-offer/tasks.md`; do not create temporary offer code.
4. Do not add dependencies or files not named below. Do not refactor unrelated code.
5. Actions own domain logic and transactions. Requests validate/authorize. Controllers
   only pass validated input to Actions and map results to responses.
6. Never use floating-point math, accept client prices/statuses, serialize models into
   public output, log PII/tokens, or edit generated Wayfinder files.
7. When a command fails, record its exact output and stop. Never mark the task complete
   based on an assumption.

## Phase 1: Setup and Hard Prerequisite

**Purpose**: Verify Phase 1 exists and establish the exact rules before any Phase 2 file
is generated.

- [ ] T001 Run the five-file preflight from `specs/002-b2c-b2b-order-capture/quickstart.md` and stop unless `app/Models/ProductOffer.php`, `app/Actions/ProductOffers/ShowPublicProductOfferAction.php`, `routes/offers.php`, `resources/js/pages/offers/show.tsx`, and `tests/Feature/ProductOffers/PublicProductOfferTest.php` all exist
- [ ] T002 Read `AGENTS.md`, `.ai/rules/index.md`, `.ai/rules/actions.md`, `.ai/rules/controllers-requests.md`, `.ai/rules/unit-feature.md`, and all six documents under `specs/002-b2c-b2b-order-capture/` before editing application files
- [ ] T003 Confirm exact installed versions with `composer show --direct`, `npm.cmd ls --depth=0`, `composer.json`, and `package.json`; use Laravel Boost `search-docs` for transactions/locks, encrypted casts, validation, rate limiting, Inertia `useHttp`, Wayfinder forms, and Pest patterns before writing code
- [ ] T004 Inspect the completed Phase 1 patterns in `app/Actions/ProductOffers/`, `app/Http/Controllers/Operator/`, `app/Http/Requests/Operator/`, `app/Models/ProductOffer.php`, `app/Policies/ProductOfferPolicy.php`, `resources/js/pages/offers/show.tsx`, `resources/js/pages/operator/offers/`, and `tests/{Unit,Feature}/ProductOffers/`; match their method names, response shapes, test style, and UI components
- [ ] T005 Run `php artisan test --compact tests/Unit/Actions/ProductOffers tests/Feature/ProductOffers` against the Phase 1 files and stop until the suite passes

**Checkpoint**: Phase 1 is present, passing, and understood. No Phase 2 behavior exists yet.

---

## Phase 2: Foundational Order and Delivery-Slot Infrastructure

**Purpose**: Add only the shared types, schema, models, price math, slots, conflicts, and
rate limiters that all three stories require.

**CRITICAL**: Do not begin a user-story phase until T006–T028 are complete and the Phase
1 regression tests pass.

### Shared enums and exact arithmetic

- [ ] T006 [P] Generate and implement the string-backed `DeliveryZone` enum with only the three codes/labels from `data-model.md` in `app/Enums/DeliveryZone.php`
- [ ] T007 [P] Generate and implement the string-backed `OrderChannel` enum with only `B2c`/`b2c` and `B2b`/`b2b` in `app/Enums/OrderChannel.php`
- [ ] T008 [P] Generate and implement the seven-case string-backed `OrderStatus` enum and its explicit allowed-transition helper from `data-model.md` in `app/Enums/OrderStatus.php`
- [ ] T009 Generate `tests/Unit/Support/Pricing/OrderTotalCalculatorTest.php` and add datasets for `5`, `5.2`, `5.25`, the 28.88 MAD half-cent case, zero/negative/malformed inputs, and multiplication overflow; run it and confirm failure because the calculator is missing
- [ ] T010 Generate `app/Support/Pricing/OrderTotalCalculator.php` and implement only decimal-string-to-hundredths parsing, integer half-up total calculation, formatting, and overflow guards from `data-model.md`; run `php artisan test --compact tests/Unit/Support/Pricing/OrderTotalCalculatorTest.php`

### Operations permission and delivery slots

- [ ] T011 Generate `database/migrations/*_add_is_operations_operator_to_users_table.php` with a non-null indexed boolean defaulting false and a reversible `down()`; do not modify the existing Phase 1 sourcing flag
- [ ] T012 Add the non-fillable boolean cast in `app/Models/User.php` and an `operationsOperator()` factory state in `database/factories/UserFactory.php`; verify profile fillable input cannot set the flag in `tests/Feature/Settings/ProfileUpdateTest.php`
- [ ] T013 Generate `database/migrations/*_create_offer_delivery_slots_table.php` with the columns, foreign key, unique constraints, and indexes exactly listed under `OfferDeliverySlot` in `data-model.md`
- [ ] T014 Generate `app/Models/OfferDeliverySlot.php`, add its UUID generation, casts, fillable allowlist, `ProductOffer`/`Order` relationships, and public-ID route key; add the inverse relationship to `app/Models/ProductOffer.php`
- [ ] T015 Generate `database/factories/OfferDeliverySlotFactory.php` with a valid future slot inside its parent offer window and no production side effects
- [ ] T016 Add failing slot tests before implementation: create/update replacement, 1–14 bounds, duplicate/out-of-window rejection, publication requirement, published immutability, and public allowlist assertions in `tests/Unit/Actions/ProductOffers/CreateProductOfferDraftActionTest.php`, `tests/Unit/Actions/ProductOffers/UpdateProductOfferDraftActionTest.php`, `tests/Unit/Actions/ProductOffers/PublishProductOfferActionTest.php`, `tests/Unit/Actions/ProductOffers/ShowPublicProductOfferActionTest.php`, and `tests/Feature/ProductOffers/OperatorProductOfferTest.php`
- [ ] T017 Extend only `app/Http/Requests/Operator/StoreProductOfferRequest.php` and `app/Http/Requests/Operator/UpdateProductOfferRequest.php` with the bounded Casablanca-local delivery-slot payload, UTC normalization, duplicate detection, and offer-window validation from `contracts/web-routes-and-props.md`
- [ ] T018 Extend only `app/Actions/ProductOffers/CreateProductOfferDraftAction.php` and `app/Actions/ProductOffers/UpdateProductOfferDraftAction.php` to replace the complete slot collection inside their existing draft transaction; do not create separate slot CRUD Actions
- [ ] T019 Extend `app/Actions/ProductOffers/PublishProductOfferAction.php`, `app/Actions/ProductOffers/ShowProductOfferDraftAction.php`, and `app/Actions/ProductOffers/ShowPublicProductOfferAction.php` to require future slots at publication and return only the bounded slot/order props defined in the contract
- [ ] T020 Extend `resources/js/types/product-offer.ts` and `resources/js/pages/operator/offers/manage.tsx` with 1–14 slot rows, existing input/error components, Casablanca labels, and no client-side business validation beyond usability; do not add a separate slot screen
- [ ] T021 Run `php artisan test --compact tests/Unit/Actions/ProductOffers tests/Feature/ProductOffers` and stop until all Phase 1 plus delivery-slot tests pass

### Order persistence and shared failures

- [ ] T022 Generate `database/migrations/*_create_orders_table.php` with every field, foreign key, default, unique constraint, and composite index from the `Order` section of `data-model.md`; all six encrypted attributes must use `TEXT`
- [ ] T023 Generate `database/migrations/*_create_order_status_transitions_table.php` with append-only fields/indexes from `data-model.md`, nullable actor FK using null-on-delete, and no `updated_at`
- [ ] T024 Generate `app/Models/Order.php` and `app/Models/OrderStatusTransition.php` with exact enum/date/encrypted casts, hidden attributes, fillable allowlists, route key, default status, ordered relationships, and no update/delete helpers for transition rows
- [ ] T025 [P] Generate `database/factories/OrderFactory.php` with valid B2C defaults plus explicit B2B/status states, and `database/factories/OrderStatusTransitionFactory.php` with a valid parent transition; keep factory values compatible with encrypted casts
- [ ] T026 Generate `app/Exceptions/OrderConflictException.php` with only the fixed safe codes/messages from the failure contract and register JSON 409 plus safe operator redirect rendering in `bootstrap/app.php`; never include submitted values, PII, tokens, SQL, or model arrays
- [ ] T027 Add the three named limiters with exact limits/keys from the contract in `app/Providers/AppServiceProvider.php`: preview 30/min/IP, confirmation 10/min/IP, operator 60/min/user-ID-plus-IP
- [ ] T028 Run `php artisan migrate --no-interaction`, inspect the resulting tables with Laravel Boost `database-schema`, then run `php artisan test --compact tests/Unit/Support/Pricing/OrderTotalCalculatorTest.php tests/Feature/Settings/ProfileUpdateTest.php tests/Feature/ProductOffers`

**Checkpoint**: Shared schema, enums, slot publication, arithmetic, encryption-ready
models, safe conflicts, and throttles exist without any order endpoint.

---

## Phase 3: User Story 1 — Consumer Confirms an Order (Priority: P1) MVP

**Goal**: A guest reviews and confirms a valid 5 kg B2C order from an active offer, sees
the server-confirmed 5.50 MAD/kg and 27.50 MAD total, and receives one non-enumerable
reference without an account.

**Independent Test**: Use one active tomato offer with a future slot and at least 5 kg.
Review writes no rows. Confirmation writes exactly one immutable confirmed B2C order and
two initial transition rows. Repeating the same token returns the same reference.

### Tests for User Story 1

- [ ] T029 [P] [US1] Generate `tests/Unit/Actions/Orders/ReviewOrderActionTest.php` with failing B2C cases for authoritative 5 kg total, no database writes, inactive/withdrawn/superseded offer, foreign/past slot, and insufficient remaining quantity
- [ ] T030 [P] [US1] Generate `tests/Unit/Actions/Orders/CreateOrderActionTest.php` with failing B2C happy-path assertions for immutable snapshots, encrypted raw PII, `pending -> confirmed` history, eligibility, and no customer account
- [ ] T031 [US1] Extend `tests/Unit/Actions/Orders/CreateOrderActionTest.php` with failing capacity/idempotency cases: exact remainder succeeds, one hundredth above fails, two attempts never exceed capacity, identical retry returns one reference, changed-token payload conflicts, and every failure leaves no partial row
- [ ] T032 [US1] Generate `tests/Feature/Orders/PublicOrderTest.php` with failing B2C HTTP cases for 200 review/confirmation shapes, 422 fields, tampered price/status/eligibility exclusion, generic 404/409 failures, recursive privacy allowlists, CSRF behavior, and both public 429 limits

### Implementation for User Story 1

- [ ] T033 [US1] Generate and implement the read-only `app/Actions/Orders/ReviewOrderAction.php` using the six ordered steps in `data-model.md`, explicit allowlisted arrays, one captured UTC time, and no writes or HTTP dependencies; run its unit test
- [ ] T034 [US1] Generate and implement `app/Actions/Orders/CreateOrderAction.php` for B2C using the nine ordered transaction steps in `data-model.md`, SHA-256 idempotency hash, offer row lock, non-cancelled aggregate, server snapshots, encrypted PII, and explicit confirmation array; run its unit test
- [ ] T035 [P] [US1] Generate `app/Http/Requests/Public/ReviewOrderRequest.php` and `app/Http/Requests/Public/StoreOrderRequest.php`; implement exact allowlists, normalization, UUID/enum/decimal rules, B2C required/optional fields, and prohibit all server-owned fields
- [ ] T036 [P] [US1] Generate `app/Http/Controllers/PublicOrderReviewController.php` and `app/Http/Controllers/PublicOrderController.php` with one `store` method each that passes only `validated()` data to its Action and returns only the contract JSON
- [ ] T037 [US1] Create `routes/orders.php`, add only the two scoped public POST routes and named throttles from the contract, then require the file once from `routes/web.php`; do not add a public order GET route
- [ ] T038 [US1] Update `app/Actions/ProductOffers/ShowPublicProductOfferAction.php` and `resources/js/types/product-offer.ts` with `canOrder`, three zones, future slots, and one submission token only for the current orderable offer; create the public review/confirmation shapes in `resources/js/types/order.ts` and export them from `resources/js/types/index.ts`; preserve all Phase 1 privacy assertions
- [ ] T039 [US1] Add the B2C review/confirm UI to `resources/js/pages/offers/show.tsx` using two `useHttp` hooks, Wayfinder `.url()`, existing form/Card/Input/Error components, server totals only, review reset on every input change, disabled processing states, and a PII-free success summary with no tracking link
- [ ] T040 [US1] Run `php artisan wayfinder:generate --with-form --no-interaction`, then run `php artisan test --compact tests/Unit/Actions/Orders/ReviewOrderActionTest.php tests/Unit/Actions/Orders/CreateOrderActionTest.php tests/Feature/Orders/PublicOrderTest.php` and `npm.cmd run types:check`; stop until all pass

**Checkpoint**: User Story 1 is deployable as the MVP. A guest B2C order works without
User Stories 2 or 3.

---

## Phase 4: User Story 2 — Professional Buyer Confirms an Order (Priority: P2)

**Goal**: A guest professional buyer reviews and confirms a 40 kg B2B order using
business identity, buyer contact, phone, delivery zone, and an offer slot while sharing
the same authoritative price/availability rules as B2C.

**Independent Test**: Against the same tomato offer, review and confirm 40 kg at 5.50
MAD/kg and 220.00 MAD. The order stores encrypted business/contact data, null B2C-only
address/note fields, and the same immutable snapshot/history guarantees.

### Tests for User Story 2

- [ ] T041 [P] [US2] Add failing B2B review/confirmation Action cases to `tests/Unit/Actions/Orders/ReviewOrderActionTest.php` and `tests/Unit/Actions/Orders/CreateOrderActionTest.php` for the 40 kg total, encrypted business/buyer fields, null B2C-only fields, idempotency, and shared availability rules
- [ ] T042 [P] [US2] Add failing B2B HTTP datasets to `tests/Feature/Orders/PublicOrderTest.php` for required business/name/phone/zone/slot, optional email, prohibited B2C address/note, unexpected fields, privacy allowlist, and 220.00 MAD confirmation

### Implementation for User Story 2

- [ ] T043 [US2] Extend conditional channel validation in `app/Http/Requests/Public/StoreOrderRequest.php` so B2B requires `business_name` and buyer `customer_name`, permits optional email, and prohibits `delivery_address`/`delivery_note`; keep B2C rules unchanged
- [ ] T044 [US2] Extend `app/Actions/Orders/CreateOrderAction.php` to normalize/compare/store B2B business and buyer fields while reusing the existing lock, capacity, total, snapshot, history, idempotency, and confirmation code path; do not create a B2B-specific Action
- [ ] T045 [US2] Extend `resources/js/pages/offers/show.tsx` and `resources/js/types/order.ts` with the B2B channel toggle and conditional business/buyer fields, clearing stale channel-only values and the previous review whenever channel/input changes; keep totals server-only
- [ ] T046 [US2] Run `php artisan test --compact tests/Unit/Actions/Orders/ReviewOrderActionTest.php tests/Unit/Actions/Orders/CreateOrderActionTest.php tests/Feature/Orders/PublicOrderTest.php` plus `npm.cmd run types:check`; manually confirm B2C still works before continuing

**Checkpoint**: User Stories 1 and 2 independently confirm orders against the same offer
without accounts and without duplicating order logic.

---

## Phase 5: User Story 3 — Operator Monitors and Cancels Orders (Priority: P3)

**Goal**: An authorized operations user lists/filters orders without index PII, opens one
private detail with transition history, and cancels only a confirmed ungrouped order.

**Independent Test**: With one 5 kg B2C and one 40 kg B2B order, an operations user sees
45 kg eligible for the selected date, can inspect authorized PII, cancels the B2C order
once, preserves its snapshot, and releases exactly 5 kg.

### Tests for User Story 3

- [ ] T047 [P] [US3] Generate `tests/Unit/Actions/Orders/ListOrdersActionTest.php` with failing cases for newest-first 25-row pagination, all five filters, retained query parameters, 45 kg eligibility, bounded 50-offer options, eager loading, and no decrypted/index PII
- [ ] T048 [P] [US3] Generate `tests/Unit/Actions/Orders/ShowOrderActionTest.php` with failing cases for allowlisted price/delivery/contact/history props, generalized actor labels, ordered transitions, and no internal actor IDs
- [ ] T049 [P] [US3] Generate `tests/Unit/Actions/Orders/CancelOrderActionTest.php` with failing cases for confirmed cancellation, one transition/one release, identical repeat, row locking, and rejection from grouped/allocated/dispatched/delivered/pending states without snapshot mutation
- [ ] T050 [US3] Generate `tests/Feature/Orders/OperatorOrderTest.php` with failing route tests for guest redirect, unverified/ordinary user denial, operations-user list/show/cancel, filter validation, 25-row pagination, private-detail-only PII, conflict display, 404 binding, toast redirect, and operator 429 limit

### Implementation for User Story 3

- [ ] T051 [P] [US3] Generate `app/Policies/OrderPolicy.php` with `viewAny`, `view`, and `cancel` using only `is_operations_operator`, and ensure `cancel` additionally requires current confirmed status; do not reuse sourcing permission
- [ ] T052 [P] [US3] Generate `app/Http/Requests/Operator/ListOrdersRequest.php` and `app/Http/Requests/Operator/CancelOrderRequest.php` with operations authorization, exact filter enum/date/UUID/page rules, and no editable cancellation body
- [ ] T053 [P] [US3] Generate and implement `app/Actions/Orders/ListOrdersAction.php` and `app/Actions/Orders/ShowOrderAction.php` with the exact contract arrays, 25-row pagination, query preservation, bounded options, selected columns/eager loads, index PII exclusion, and authorized-detail decryption only
- [ ] T054 [US3] Generate and implement `app/Actions/Orders/CancelOrderAction.php` with one transaction, order then offer lock, confirmed-only transition, append-only actor history, idempotent already-cancelled return, derived release, and immutable snapshot preservation
- [ ] T055 [US3] Generate `app/Http/Controllers/Operator/OrderController.php` and `app/Http/Controllers/Operator/OrderCancellationController.php`; keep methods to policy/Request -> Action -> Inertia/redirect mapping with no query, status, or transaction logic
- [ ] T056 [US3] Add only the three authenticated/verified/policy/throttled operator routes from the contract to `routes/orders.php`, using public-ID binding and scoped route names; do not add update/delete/bulk routes
- [ ] T057 [US3] Create all order paginator, summary, detail, transition, filter, enum, and confirmation TypeScript shapes from the contract in `resources/js/types/order.ts` and export them from `resources/js/types/index.ts`
- [ ] T058 [P] [US3] Create `resources/js/pages/operator/orders/index.tsx` with `AppLayout`, Wayfinder filters/links, 25-row pagination, empty state, status badges, no contact fields, and a mobile scroll container using existing Tailwind v4/UI patterns
- [ ] T059 [P] [US3] Create `resources/js/pages/operator/orders/show.tsx` with price/delivery/private-contact/history Cards, generalized actor labels, policy-controlled cancellation dialog/form, server conflict display, and no optimistic update
- [ ] T060 [US3] Add one policy-aware Orders Wayfinder navigation item without hardcoded URLs in `resources/js/components/app-sidebar.tsx`; leave existing Product Offers navigation unchanged
- [ ] T061 [US3] Run `php artisan wayfinder:generate --with-form --no-interaction`, then run `php artisan test --compact tests/Unit/Actions/Orders tests/Feature/Orders/OperatorOrderTest.php` and `npm.cmd run types:check`; stop until all pass

**Checkpoint**: All three user stories work. Operations can reconcile 45 kg, inspect only
authorized PII, and cancel only the permitted order state.

---

## Phase 6: Cross-Cutting Security, Quality, and Acceptance

**Purpose**: Prove the complete feature, prevent privacy/scope regressions, and run every
required project gate.

- [ ] T062 Add recursive response/privacy regression assertions for all forbidden PII/internal keys and raw database ciphertext checks in `tests/Feature/Orders/PublicOrderTest.php` and `tests/Feature/Orders/OperatorOrderTest.php`; inspect `storage/logs/laravel.log` only for test-generated safe context
- [ ] T063 Add the combined 5 kg B2C + 40 kg B2B acceptance case, immutable offer-change assertions, 500-order pagination/filter dataset, lifecycle terminal-state dataset, and exact 45 kg eligibility reconciliation in `tests/Unit/Actions/Orders/ListOrdersActionTest.php` and `tests/Feature/Orders/OperatorOrderTest.php`
- [ ] T064 Review `routes/orders.php`, `app/Actions/Orders/`, `app/Http/Controllers/{PublicOrderReviewController.php,PublicOrderController.php,Operator/}`, and `app/Http/Requests/{Public,Operator}/` against the plan guardrails; remove any public order GET, client-owned commercial field, controller business logic, Request leakage, or out-of-scope workflow
- [ ] T065 Run `vendor/bin/pint --dirty --format agent` and `vendor/bin/phpstan analyse`; fix only Phase 2 and directly affected Phase 1 PHP files named in `plan.md`, then rerun both commands
- [ ] T066 Run `npm.cmd run format`, `npm.cmd run lint`, `npm.cmd run types:check`, and `npm.cmd run build`; fix only `resources/js/pages/offers/show.tsx`, `resources/js/pages/operator/orders/`, `resources/js/types/`, and `resources/js/components/app-sidebar.tsx`
- [ ] T067 Run `php artisan test --compact tests/Unit/Actions/ProductOffers tests/Unit/Actions/Orders tests/Unit/Support/Pricing/OrderTotalCalculatorTest.php tests/Feature/ProductOffers tests/Feature/Orders tests/Feature/Settings/ProfileUpdateTest.php` and stop until the complete affected suite passes
- [ ] T068 Run `composer audit` and `npm.cmd audit --omit=dev`; do not change dependencies without approval, and report any known exploitable vulnerability as a release blocker
- [ ] T069 Execute every manual success, retry, stale-offer, past-slot, insufficient-quantity, cancellation, privacy, mobile, keyboard, dark-mode, empty, loading, and conflict step in `specs/002-b2c-b2b-order-capture/quickstart.md`; record exact failures before changing code
- [ ] T070 Re-read `.specify/memory/constitution.md`, verify every Constitution Check row in `specs/002-b2c-b2b-order-capture/plan.md`, inspect the final diff for unrelated files/secrets/PII/logging/out-of-scope work, and do not mark complete until all relevant tests and checks pass

---

## Dependencies and Execution Order

### Phase dependencies

```text
Phase 1 preflight
    -> Phase 2 shared foundation
        -> US1 B2C MVP
            -> US2 B2B extension
                -> US3 operator workflow
                    -> cross-cutting verification
```

- Phase 1 blocks everything. If Phase 1 is missing, this feature must stop.
- Phase 2 blocks every story because all stories share offer slots, exact totals, orders,
  encrypted storage, status history, conflicts, and limits.
- US1 establishes the common public review/confirmation path.
- US2 depends on US1 and adds only conditional B2B validation/data/UI to that path.
- US3 depends on confirmed orders from US1/US2 for meaningful list/detail/cancel tests.
- Cross-cutting verification depends on all selected stories.

### Within each story

1. Write the named Action/HTTP tests and confirm expected failures.
2. Implement the Action and rerun its direct unit test.
3. Implement the Request and thin controller.
4. Add routes and regenerate Wayfinder.
5. Implement the Inertia page using only server props/results.
6. Run the phase checkpoint before moving forward.

## Parallel Opportunities

Parallel work is optional. A single junior agent should prefer numeric order.

- Foundation: T006, T007, and T008 touch separate enum files.
- US1: T029 and T030 start separate Action test files; T035 and T036 scaffold separate
  HTTP-layer files after Action contracts are stable.
- US2: T041 and T042 touch unit versus feature test files.
- US3: T047, T048, and T049 touch separate Action tests; T051, T052, and T053 touch
  policy, Request, and Action files after tests define behavior; T058 and T059 are
  separate pages after backend props/types are stable.
- Never parallelize tasks that edit `ShowPublicProductOfferAction.php`,
  `PublicOrderTest.php`, `CreateOrderAction.php`, `routes/orders.php`, or
  `offers/show.tsx`; those are shared integration points.

## Parallel Example: User Story 3

```text
Worker A: T047 -> ListOrdersActionTest.php
Worker B: T048 -> ShowOrderActionTest.php
Worker C: T049 -> CancelOrderActionTest.php

Join and review tests, then:

Worker A: T058 -> operator/orders/index.tsx
Worker B: T059 -> operator/orders/show.tsx
```

Do not start the second group until T051–T057 are complete.

## Delivery Strategy

### MVP first

1. Complete T001–T028 foundation.
2. Complete T029–T040 User Story 1.
3. Stop and demonstrate B2C review, confirmation, idempotency, privacy, and exact totals.

### Incremental delivery

1. Add T041–T046 for B2B without duplicating the common path.
2. Add T047–T061 for authorized operator visibility and cancellation.
3. Complete T062–T070 only after all selected stories pass their checkpoints.

No task authorizes customer accounts, customer cancellation, notifications, payments,
public tracking, consolidation execution, allocation, dispatch, delivery, bulk actions,
new packages, or unrelated refactoring.
