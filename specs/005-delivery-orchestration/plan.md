# Implementation Plan: Delivery Orchestration

**Branch**: `N/A (no branch hook configured)` | **Date**: 2026-08-09 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/005-delivery-orchestration/spec.md`

## Summary

Add one staff-only dispatch workspace that deterministically creates ready B2B manifests
per service date and zone and one ready mock-provider request per prepared B2C order.
One unified `Dispatch` aggregate owns status, cost, attempts, immutable submitted
membership, and history. Five Actions list/show work, generate both channel outputs,
submit or retry one dispatch, and advance one dispatch. A small `DeliveryProvider`
interface is bound only to an in-process deterministic mock; this phase performs no
network call and needs no credential.

Keep the implementation literal and small: four new domain tables, two upstream column
extensions, five Actions, four Requests, four thin controllers, one policy, two React
pages, one provider interface/implementation, and three pure helpers. Database unique
constraints, operation fingerprints, lifecycle versions, stable lock order, conditional
updates, and short transactions make generation, submission, retries, pickup, delivery,
cost allocation, and concurrent replays safe.

**Hard implementation prerequisite**: the current working tree contains only the Laravel
starter/auth application. Phases 001–004 are specified but not implemented. Phase 005
implementation MUST stop until their exact Order, grouping, hub-allocation, permissions,
and tests exist. Never create temporary Phase 005 stand-ins for an upstream model, enum,
field, transition, or permission.

## Technical Context

**Language/Version**: PHP 8.4; TypeScript 5.9.3; React 19.2.8

**Primary Dependencies**: Laravel 13.24.0, Inertia Laravel 3.3.1,
`@inertiajs/react` 3.6.1, Wayfinder 0.1.21 / Vite plugin 0.1.7, Tailwind CSS
4.3.3, Pest 5.0.4. No dependency changes.

**Storage**: Existing SQLite for local/demo/tests with portable migrations for
MySQL/PostgreSQL. Four dispatch tables; one dispatched-progress column on
`stock_allocations`; one nullable dispatch FK on `order_status_transitions`. Existing
database cache only if an implementation-time lock optimization is needed; no object
storage and no queue.

**Testing**: Direct Pest unit tests for every Action and pure helper/provider behavior;
Pest feature tests for HTTP/Inertia integration, authorization, validation, throttling,
privacy, and safe errors. The browser plugin is not installed, so do not add it; use
static frontend checks plus the manual responsive/dark-mode flow in `quickstart.md`.

**Target Platform**: Ordinary desktop/mobile browsers and the local Casablanca
hackathon demonstration

**Project Type**: Laravel/Inertia React web application

**Architecture**: Dedicated Form Request -> thin controller -> one use-case Action.
`DeliveryProvider` is the only provider boundary and is bound to `MockDeliveryProvider`.
Actions coordinate Eloquent transactions directly. Pure helpers own operation
fingerprints, MAD parsing/formatting, and largest-remainder allocation only. Do not add a
service, repository, job, event, command, webhook, or generic state-machine layer.

**Frontend**: Two pages under `resources/js/pages/operator/dispatches`: `index.tsx` for
filters, generation, summaries, and paginated dispatches; `show.tsx` for stops/orders,
attempts, history, submission/retry, and allowed status actions. Use existing AppLayout,
`<Form>`, Wayfinder controller actions/routes, Cards, Badges, Dialog, InputError,
AlertError, Button, Select, toast, responsive semantic tables, and dark mode. Server
props supply labels, allowed actions, formatted values, and permission flags. Do not use
optimistic updates, polling, custom CSS, or a third page.

**Security**: Require `auth`, `verified`, existing operations-operator permission,
policy/Form Request authorization, CSRF, and a named 60/minute user+IP mutation limiter.
Use random UUID route bindings and explicit response arrays. Decrypt customer PII only
when building one authorized detail/manifest response or the transient mock request;
never persist it in dispatch tables. The mock ignores PII and derives its reference and
quote only from safe dispatch data. Fixed safe conflict codes and allowlisted log
context exclude PII, payloads, tokens, hashes, provider-private values, SQL, and models.

**Performance Goals**: Generate or refresh all dispatch work for at most 500 eligible
orders in under 2 minutes. Staff list/show views respond within 1 second locally for 95%
of requests. Dispatches paginate at 25; detail orders, attempts, and transitions are
bounded at 50 with selected-column eager loads and no N+1 queries.

**Constraints**: Quantities are integer hundredths of a kilogram; money is integer MAD
centimes; never use floats. Store timestamps in UTC and display Casablanca time.
Generation is one bounded transaction. Submission/advance use transactions with three
deadlock attempts. Lock in documented stable order. The mock is in-process and performs
no I/O, so it may execute inside the submission/advance transaction. A future live
provider must redesign that boundary and is explicitly outside this phase.

**Scale/Scope**: Hackathon MVP, one hub, two channels, three existing zones, at most 500
eligible orders per generation, one B2B manifest per service date/zone, one B2C dispatch
per order, two operator pages, five use cases, and no route optimization, batching,
notifications, payments, returns, disputes, GPS, driver app, or live provider.

## Constitution Check

*GATE: Passed before Phase 0 research and passed again after Phase 1 design, subject to
the hard upstream implementation prerequisite.*

- **Framework conventions — PASS**: Installed versions were confirmed with Laravel
  Boost and npm. Design reuses Eloquent, transactions, container interface binding,
  policies, Form Requests, rate limiting, Inertia `<Form>`, Wayfinder, existing UI
  primitives, integer money patterns, and Pest conventions.
- **Security boundary — PASS**: Authentication, verified operations permission,
  policies, validated allowlists, CSRF, throttling, public UUIDs, transient minimum PII,
  no dispatch-table PII, safe logs, safe failures, and privacy tests are explicit.
- **Action-first design — PASS**: Five named use cases map to five Actions. The provider
  and three pure helpers contain no orchestration; controllers only adapt HTTP.
- **Form Request boundary — PASS**: Each filter or mutation has one dedicated Request.
  Show uses route binding plus policy and accepts no business input. Actions never accept
  HTTP Requests.
- **Layered tests — PASS**: Every Action receives a direct Pest test; HTTP feature tests
  prove middleware, authorization, validation, mapping, responses, and mutations.
  Frontend behavior unavailable to installed test tooling has a bounded manual check.
- **Operational quality — PASS**: Indexed bounded reads, four tables, unique dispatch
  identities, unique order membership, operation fingerprints, lifecycle versions,
  conditional claims, stable locks, atomic stock/order changes, exact cost sums, and
  safe retry outcomes are designed.

Any failed gate MUST be resolved before implementation or documented in Complexity
Tracking with explicit approval.

## Upstream Contract Gate

Before creating any Phase 005 source file, verify every row below and run the affected
Phase 001–004 tests. Stop on the first failure. Adapt to the real upstream name only when
it represents the same contract; never create a duplicate model/table to bypass a gap.

| Required upstream item | Exact minimum contract consumed here |
|---|---|
| `Order` | `id`, random `public_id`, `order_group_id`, `channel`, `status`, `quantity_hundredths`, immutable crop/price/service-window snapshots, service date, zone, and encrypted contact/business/address/note fields |
| Order enums | `OrderChannel` with B2B/B2C; `OrderStatus` with `Allocated`, `Dispatched`, `Delivered`; existing `DeliveryZone` cases |
| `OrderStatusTransition` | Append-only order/from/to/actor/time record and Phase 004 nullable `stock_allocation_id` |
| `OrderGroup` | Random `public_id`, channel, zone, service date, total quantity, orders, `fulfillment_status`, and `current_stock_allocation_id` |
| Group enum | `OrderGroupFulfillmentStatus` with `ReadyForDispatch` and `Dispatched` |
| `StockAllocation` | Random `public_id`, order group, hub receipt, quantity, prepared quantity/time, nullable release/dispatch facts, and `lifecycle_version` |
| `HubReceipt` | Available/allocated/damaged/dispatched integer counters and `inventory_version` |
| Operations permission | Non-fillable `users.is_operations_operator`, `isOperationsOperator()` helper, and factory state |
| Shared quantity formatter | `App\Support\Quantities\KilogramQuantity`; do not create the stale hub-local duplicate described in older Phase 004 drafts |

The current database schema does not satisfy this gate. This plan is ready for later use;
implementation is not authorized to work around the missing upstream phases.

## Project Structure

### Documentation (this feature)

```text
specs/005-delivery-orchestration/
|-- plan.md
|-- research.md
|-- data-model.md
|-- quickstart.md
|-- contracts/
|   `-- web-routes-and-props.md
`-- tasks.md                         # created later by speckit-tasks
```

### Source Code (repository root)

```text
app/
|-- Actions/Dispatches/
|   |-- ListDispatchesAction.php
|   |-- ShowDispatchAction.php
|   |-- GenerateDispatchesAction.php
|   |-- SubmitDispatchAction.php
|   `-- AdvanceDispatchAction.php
|-- Enums/
|   |-- DeliveryCostSource.php
|   |-- DispatchAttemptOutcome.php
|   |-- DispatchProviderKind.php
|   |-- DispatchStatus.php
|   `-- DispatchTransitionSource.php
|-- Exceptions/DispatchConflictException.php
|-- Http/Controllers/Operator/
|   |-- DispatchController.php
|   |-- DispatchGenerationController.php
|   |-- DispatchSubmissionController.php
|   `-- DispatchStatusController.php
|-- Http/Requests/Operator/Dispatches/
|   |-- ListDispatchesRequest.php
|   |-- GenerateDispatchesRequest.php
|   |-- SubmitDispatchRequest.php
|   `-- AdvanceDispatchRequest.php
|-- Models/
|   |-- Dispatch.php
|   |-- DispatchAttempt.php
|   |-- DispatchOrder.php
|   |-- DispatchStatusTransition.php
|   |-- HubReceipt.php                    # dispatch counter relationship only
|   |-- Order.php                         # dispatch membership relationship only
|   |-- OrderStatusTransition.php         # dispatch relationship only
|   `-- StockAllocation.php               # dispatched progress/cast/relationship
|-- Policies/DispatchPolicy.php
|-- Providers/AppServiceProvider.php      # provider binding + named limiter
`-- Support/Dispatches/
    |-- DeliveryProvider.php
    |-- DispatchOperationFingerprint.php
    |-- LargestRemainderCostAllocator.php
    |-- MadMoney.php
    `-- MockDeliveryProvider.php

bootstrap/app.php                         # safe conflict rendering only
config/dispatch.php

database/
|-- factories/
|   |-- DispatchAttemptFactory.php
|   |-- DispatchFactory.php
|   |-- DispatchOrderFactory.php
|   `-- DispatchStatusTransitionFactory.php
`-- migrations/
    |-- *_create_dispatches_table.php
    |-- *_create_dispatch_orders_table.php
    |-- *_create_dispatch_attempts_table.php
    |-- *_create_dispatch_status_transitions_table.php
    |-- *_add_dispatch_progress_to_stock_allocations_table.php
    `-- *_add_dispatch_to_order_status_transitions_table.php

routes/
|-- dispatches.php
`-- web.php                                # require dispatches.php

resources/js/
|-- components/app-sidebar.tsx
|-- pages/operator/dispatches/
|   |-- index.tsx
|   `-- show.tsx
|-- types/dispatch.ts
`-- types/index.ts

tests/
|-- Feature/Dispatches/
|   |-- OperatorDispatchTest.php
|   `-- DispatchPrivacyTest.php
`-- Unit/
    |-- Actions/Dispatches/
    |   |-- AdvanceDispatchActionTest.php
    |   |-- GenerateDispatchesActionTest.php
    |   |-- ListDispatchesActionTest.php
    |   |-- ShowDispatchActionTest.php
    |   `-- SubmitDispatchActionTest.php
    `-- Support/Dispatches/
        |-- LargestRemainderCostAllocatorTest.php
        |-- MadMoneyTest.php
        `-- MockDeliveryProviderTest.php
```

**Structure Decision**: Reuse established Laravel/Inertia directories and keep only two
pages. `DispatchOrder` is necessary because one B2B manifest has many orders and each
membership owns an exact cost allocation; it is not a generic pivot abstraction. The
provider interface and mock stay under the feature Support directory so no new
application-level base folder or dependency is introduced. Generated Wayfinder files
are omitted because they must never be hand-edited.

## Use-Case Mapping

| Use Case | Form Request | Controller | Action | Unit Test | Feature Test |
|---|---|---|---|---|---|
| List/filter dispatch work | `ListDispatchesRequest` | `DispatchController@index` | `ListDispatchesAction` | `ListDispatchesActionTest.php` | `OperatorDispatchTest.php` |
| Show one dispatch | none; route binding + policy | `DispatchController@show` | `ShowDispatchAction` | `ShowDispatchActionTest.php` | `OperatorDispatchTest.php` |
| Generate/refresh B2B manifests and ready B2C requests | `GenerateDispatchesRequest` | `DispatchGenerationController@store` | `GenerateDispatchesAction` | `GenerateDispatchesActionTest.php` | `OperatorDispatchTest.php` |
| Submit or retry one dispatch | `SubmitDispatchRequest` | `DispatchSubmissionController@store` | `SubmitDispatchAction` | `SubmitDispatchActionTest.php` | `OperatorDispatchTest.php` |
| Record B2B or simulate B2C outcome | `AdvanceDispatchRequest` | `DispatchStatusController@store` | `AdvanceDispatchAction` | `AdvanceDispatchActionTest.php` | `OperatorDispatchTest.php` |

## Security Design

| Boundary/Risk | Control | Verification |
|---|---|---|
| Missing upstream phases | Hard preflight gate; never create local stand-ins | Quickstart file/schema/test gate |
| Guest or ordinary user accesses PII | `auth`, `verified`, policy and Request authorization using operations permission | Redirect/403/operator feature tests |
| Client injects membership/status/cost/source | Requests accept only documented filters/date/token/version/flat cost/target; Actions derive all IDs, memberships, provider facts, quantities, and allowed transitions | Extra-field/tampered-input tests |
| PII duplicated or leaked to mock/logs | Dispatch tables contain no PII; transient builder selects minimum encrypted Order fields; mock ignores them; explicit props/log allowlists | Database, recursive prop, log, exception canary tests |
| Duplicate manifests/requests/orders | Private deterministic scope hash, unique order membership, lock/re-read, and affected-count assertions | Repeated/concurrent generation tests |
| Duplicate provider submission/retry | Hashed operation token + safe payload hash, unique attempt number, deterministic provider reference, locked status/version | Same-token same/different payload and concurrent submit tests |
| Partial pickup across stock/orders | One stable-order transaction locks dispatch, memberships, orders, groups, allocations, receipts; conditional counts must match | Forced-conflict rollback and counter-invariant tests |
| Cost rounding drift | Integer MAD only; largest-remainder allocator; tie by safe order UUID; frozen allocations | Pure allocator tests and dispatch reconciliation assertions |
| Request abuse/resource exhaustion | `dispatch-operations` limiter at 60/minute by user ID + IP; 500-order hard bound | 429 and 501st-order rejection tests |
| Unsafe errors/diagnostics | Fixed safe codes/messages, no payload/model dump, only safe dispatch reference/category/timing | Exception and log assertions |

## Implementation Order for a Low-Capability Agent

The implementation agent MUST follow this order and MUST NOT work ahead:

1. Run the upstream gate. Stop immediately if any Phase 001–004 model, enum, column,
   permission, or focused test is missing. Do not generate any Phase 005 PHP file yet.
2. Read `data-model.md` and create/test only the five enums, `MadMoney`, and
   `LargestRemainderCostAllocator`.
3. Generate migrations/models/factories in the exact migration order. Migrate and inspect
   the six affected tables before writing an Action.
4. Implement/test `DispatchOperationFingerprint` and `MockDeliveryProvider`; prove the
   mock is deterministic, performs no HTTP call, ignores PII, and uses no credentials.
5. Implement/test `GenerateDispatchesAction` only. Cover B2B ready refresh/freeze, late
   work, B2C one-per-order, 500 bound, replay, and concurrency before continuing.
6. Implement/test `SubmitDispatchAction` only. Cover B2B flat cost, B2C mock quote,
   largest-remainder allocation, token replay/mismatch, freeze, retry, and rollback.
7. Implement/test `AdvanceDispatchAction` only. Cover every valid/invalid transition,
   partial group pickup, full group completion, order/receipt/allocation invariants,
   delivery, pre-pickup retry, terminal post-pickup failure, and concurrency.
8. Implement/test list and show Actions with pagination, selected fields, exact prop
   allowlists, query-count assertions, and privacy canaries.
9. Add policy, Requests, thin controllers, routes, limiter, provider binding, and safe
   conflict rendering; then run HTTP authorization/validation/wiring/privacy tests.
10. Generate Wayfinder, then build the two pages and sidebar link using only the exact
    props/routes in the contract. Do not derive business states or costs in React.
11. Run the full quickstart verification: focused upstream suites, all dispatch tests,
    Pint, PHPStan, ESLint, Prettier, TypeScript, build, privacy scan, and manual demo.
12. Run the production-engine concurrency gate before claiming replay/concurrency safety;
    SQLite-only success is insufficient for row-lock behavior.

## Guardrails for the Implementation Agent

1. Use the exact models, fields, enums, routes, Actions, props, and algorithms in these
   artifacts. If an upstream implementation differs, stop and reconcile the artifacts;
   do not guess silently.
2. Generate PHP files with `php artisan make:* --no-interaction`. Never hand-create
   migration timestamps or edit generated Wayfinder files.
3. Do not add a package, queue/job, event, webhook, API route, repository/service layer,
   generic adapter registry, state-machine package, notification, or third page.
4. Do not make an HTTP request. `MockDeliveryProvider` is in-process, deterministic, and
   the only bound provider.
5. Do not persist or log names, phones, emails, addresses, notes, raw provider payloads,
   raw operation tokens, hashes, credentials, signatures, SQL, or model dumps.
6. Do not use floats. Quantities are integer hundredths; money is integer centimes. Use
   only `MadMoney` and `LargestRemainderCostAllocator` for dispatch money.
7. Do not rewrite Order price snapshots or Phase 003 delivery estimates. Actual dispatch
   cost is separate and its order allocations must sum exactly.
8. A ready B2B manifest may refresh; a submitted one is frozen. Never create a
   supplementary manifest for late work in this MVP.
9. One B2C Order has one Dispatch. One Order belongs to at most one Dispatch across both
   channels. Enforce this with a real unique database index.
10. Pickup, not submission or acceptance, moves Order and hub quantities to dispatched.
    Delivery changes Orders only. Post-pickup failure never reverses stock or Orders.
11. Lock in this order: dispatch, dispatch orders, Orders by ID, OrderGroups by ID,
    StockAllocations by ID, HubReceipts by ID. Require exact affected counts or rollback.
12. Build explicit Inertia arrays and server-provided `can`/allowed-status props. Never
    serialize an Eloquent model or optimistically change dispatch/stock state.
13. Do not mark implementation complete if the upstream gate, focused suites, full
    affected suite, static checks, build, privacy checks, or production-engine
    concurrency gate fails.

## Complexity Tracking

> Fill ONLY when a Constitution Check violation requires explicit justification.

No Constitution violations. No entries required.
