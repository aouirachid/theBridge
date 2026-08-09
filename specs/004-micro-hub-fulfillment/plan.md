# Implementation Plan: Micro-Hub Fulfillment

**Branch**: `N/A (no branch hook configured)` | **Date**: 2026-08-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/003-micro-hub-fulfillment/spec.md`

## Summary

Add one small, staff-only hub workspace that receives a consolidated procurement
requirement, keeps exact stock buckets, records corrections and handling losses,
allocates one receipt to a complete order group, releases an unprepared allocation, and
marks an allocation ready for dispatch. Use integer hundredths of a kilogram, stored
receipt counters plus append-only audit rows, short database transactions, optimistic
version checks, and a current-allocation pointer on each order group. This feature stops
at `ready_for_dispatch`; delivery orchestration owns dispatch.

**Hard implementation prerequisite**: the working tree currently contains only the
Laravel starter/authentication application. Phase 1 and Phase 2 are planned but not
implemented, and midnight consolidation has neither a feature directory nor code.
Implementation MUST stop until `ProductOffer`, `Order`, `OrderStatusTransition`,
`ProcurementRequirement`, and `OrderGroup` exist with the upstream contract documented
below. Do not create temporary or duplicate versions of those models in this feature.

Keep the slice literal and dependency-free: four new domain tables, six small enums,
two small support classes, nine Actions, seven mutation/filter Requests, nine thin
controller methods, three Inertia pages, and focused Pest tests. Do not add services,
repositories, DTO packages, queues, scheduled jobs, event sourcing, warehouse concepts,
or generic workflow frameworks.

## Technical Context

**Language/Version**: PHP 8.4.8; TypeScript 5.9.3; React 19.2.8

**Primary Dependencies**: Laravel 13.24.0, Inertia Laravel 3.3.1,
`@inertiajs/react` 3.6.1, Wayfinder 0.1.21 / Vite plugin 0.1.7, Tailwind CSS 4.3.3,
Pest 5.0.4

**Storage**: Existing SQLite database for the local demo and tests; portable migrations
for MySQL/PostgreSQL. Four hub tables plus two small additions to consolidation/order
history tables. No cache correctness boundary, object storage, queue, or external call.

**Testing**: Pest Action tests invoke every Action directly; Pest feature tests cover
routes, middleware, policies, Form Requests, transactions, Inertia props, privacy, and
safe conflicts. No browser plugin is installed, so use existing frontend checks plus the
manual responsive/accessibility flow in `quickstart.md`; adding a browser dependency is
not authorized.

**Target Platform**: Laravel/Inertia web application for ordinary desktop/mobile
browsers and a local Casablanca hackathon demonstration.

**Project Type**: Laravel/Inertia React web application

**Architecture**: Dedicated Form Request -> thin controller -> single-purpose Action ->
Eloquent models. Actions own validation of domain state, transactions, optimistic
compare-and-set updates, stock math, replay handling, and order transitions. Controllers
only invoke Actions and return Inertia responses or redirects.

**Frontend**: Three operator pages under `resources/js/pages/operator/hub`: work queue,
receive form, and receipt workspace. Use Inertia `<Form>` with Wayfinder-generated
controller actions, existing layout/UI components, server-calculated quantities,
field/operation errors, empty states, responsive tables, visible focus, and existing
dark mode. No polling, optimistic inventory updates, client stock math, or custom CSS.

**Security**: Reuse Phase 2's non-fillable `users.is_operations_operator` permission for
both receiving and fulfillment in the MVP; policies retain separate abilities so roles
can split later. Require `auth`, `verified`, policy/Form Request authorization, CSRF,
and a named 60/minute user+IP limiter. Store optional operational notes with encrypted
casts, hash raw replay tokens, construct explicit Inertia arrays, and never expose or log
supplier/customer PII, raw tokens, payloads, internal IDs, or model dumps.

**Performance Goals**: With 500 active receipts, the work queue returns 25 receipts per
page, at most 50 outstanding requirements, and summary aggregates without N+1 queries;
95% of operator page requests complete in under 1 second locally. An operator can
reconcile the dataset in under 2 minutes during timed acceptance.

**Constraints**: Quantity is integer hundredths of a kilogram; never use floats. Store
timestamps in UTC and interpret/display operator time in `Africa/Casablanca`. Every write
is atomic and retries deadlocks up to three times. `lockForUpdate()` assists engines that
support row locks, but optimistic version/conditional updates are the portable
correctness boundary because SQLite does not provide equivalent row locks.

**Scale/Scope**: Hackathon MVP: one hub, one finalized receipt per procurement
requirement, one receipt per order group, full-group allocation only, at most 500 active
receipts, 25 receipts/page, 50 outstanding requirements or compatible groups per page,
and small per-receipt histories. No partial receipt, multi-receipt group, substitution,
post-preparation release, or dispatch execution.

## Constitution Check

*GATE: Passed before research and passed again after design, subject to the hard upstream
implementation prerequisite.*

- **Framework conventions — PASS**: Installed versions were confirmed with Laravel
  Boost, Composer, and npm. The design reuses existing Inertia `<Form>`, App layout,
  UI primitives, toast flash, Wayfinder form variants, Eloquent, policies, and Pest.
- **Security boundary — PASS**: Authentication, verified staff access, server-side
  abilities, allowlisted Requests, encrypted notes, hashed replay tokens, safe props,
  throttling, privacy exclusions, and failure outcomes are explicit.
- **Action-first design — PASS**: Each read or write use case maps to exactly one Action.
  Transactions, counters, replay, concurrency, and lifecycle decisions remain out of
  controllers.
- **Form Request boundary — PASS**: Every mutation and the filtered work queue have a
  dedicated Request. Read-only detail pages have no user payload beyond authorized route
  binding.
- **Layered tests — PASS**: Every Action has direct Pest coverage. HTTP feature tests
  prove authentication, authorization, validation, wiring, persistence, and Inertia
  responses without duplicating all Action permutations.
- **Operational quality — PASS**: Writes use transactions, deterministic lock order,
  optimistic versions, conditional claims, unique replay hashes, and rollback on partial
  failure. Reads are indexed, bounded, eager-loaded, and explicitly shaped.

## Upstream Contract Gate

Before creating any hub file, verify all of the following. If one is absent, stop and
implement the earlier feature first.

| Required upstream item | Minimum contract used by this plan |
|---|---|
| `ProductOffer` | Stable internal identity and safe crop/product label |
| `Order` | `quantity_hundredths`, `status`, and relationship to one order group |
| `OrderStatus` | Existing `Grouped`, `Allocated`, and `Dispatched` cases |
| `OrderStatusTransition` | Append-only transitions with actor and occurrence time |
| `ProcurementRequirement` | Random public reference, product/service scope, service date, required quantity, and outstanding/received state |
| `OrderGroup` | Random public reference, procurement requirement FK, channel, zone, service date, total quantity, included orders, and grouped state |
| Operations permission | Non-fillable `users.is_operations_operator` and factory state |

Do not rename these concepts in the hub slice. If the consolidation implementation uses
different field names, adapt hub foreign keys and relationships to the real model rather
than introducing adapters or parallel tables.

## Project Structure

### Documentation (this feature)

```text
specs/003-micro-hub-fulfillment/
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
|-- Actions/Hub/
|   |-- ListHubWorkQueueAction.php
|   |-- ShowReceiveProduceFormAction.php
|   |-- FinalizeHubReceiptAction.php
|   |-- ShowHubReceiptAction.php
|   |-- CorrectHubReceiptAction.php
|   |-- RecordHandlingLossAction.php
|   |-- AllocateOrderGroupAction.php
|   |-- ReleaseStockAllocationAction.php
|   `-- PrepareOrderGroupAction.php
|-- Enums/
|   |-- AllocationReleaseReason.php
|   |-- HandlingLossReason.php
|   |-- HubQualityGrade.php
|   |-- OrderGroupFulfillmentStatus.php
|   |-- ReceiptCorrectionReason.php
|   `-- ReceiptRejectionReason.php
|-- Exceptions/HubConflictException.php
|-- Http/Controllers/Operator/
|   |-- HubDashboardController.php
|   |-- HubReceiptController.php
|   |-- HubReceiptCorrectionController.php
|   |-- HubHandlingLossController.php
|   |-- HubAllocationController.php
|   |-- HubAllocationReleaseController.php
|   `-- HubPreparationController.php
|-- Http/Requests/Operator/Hub/
|   |-- ListHubWorkQueueRequest.php
|   |-- ReceiveProduceRequest.php
|   |-- CorrectHubReceiptRequest.php
|   |-- RecordHandlingLossRequest.php
|   |-- AllocateStockRequest.php
|   |-- ReleaseStockAllocationRequest.php
|   `-- PrepareStockAllocationRequest.php
|-- Models/
|   |-- HandlingLoss.php
|   |-- HubReceipt.php
|   |-- HubReceiptCorrection.php
|   |-- OrderGroup.php                  # upstream model: relationships/cast only
|   `-- StockAllocation.php
|-- Policies/HubReceiptPolicy.php
|-- Providers/AppServiceProvider.php    # named limiter only
`-- Support/Hub/
    |-- HubOperationFingerprint.php
    `-- KilogramQuantity.php

bootstrap/app.php                       # safe HubConflictException rendering
config/hub.php                          # fixed 24-hour target and page bounds

database/
|-- factories/
|   |-- HandlingLossFactory.php
|   |-- HubReceiptCorrectionFactory.php
|   |-- HubReceiptFactory.php
|   `-- StockAllocationFactory.php
`-- migrations/
    |-- *_create_hub_receipts_table.php
    |-- *_create_hub_receipt_corrections_table.php
    |-- *_create_handling_losses_table.php
    |-- *_create_stock_allocations_table.php
    |-- *_add_fulfillment_fields_to_order_groups_table.php
    `-- *_add_stock_allocation_to_order_status_transitions_table.php

resources/js/
|-- components/app-sidebar.tsx
|-- pages/operator/hub/index.tsx
|-- pages/operator/hub/receipts/create.tsx
|-- pages/operator/hub/receipts/show.tsx
|-- types/hub.ts
`-- types/index.ts

routes/
|-- web.php
`-- hub.php

tests/
|-- Unit/
|   |-- Actions/Hub/
|   |   |-- ListHubWorkQueueActionTest.php
|   |   |-- ShowReceiveProduceFormActionTest.php
|   |   |-- FinalizeHubReceiptActionTest.php
|   |   |-- ShowHubReceiptActionTest.php
|   |   |-- CorrectHubReceiptActionTest.php
|   |   |-- RecordHandlingLossActionTest.php
|   |   |-- AllocateOrderGroupActionTest.php
|   |   |-- ReleaseStockAllocationActionTest.php
|   |   `-- PrepareOrderGroupActionTest.php
|   `-- Support/Hub/KilogramQuantityTest.php
`-- Feature/Hub/
    |-- HubWorkQueueTest.php
    |-- HubReceiptTest.php
    |-- HubStockTest.php
    |-- HubAllocationTest.php
    |-- HubPreparationTest.php
    `-- HubPrivacyTest.php
```

**Structure Decision**: Use the existing Laravel/Inertia folders. Keep one page per
operator decision point and one model per audit fact. Do not create a service layer,
repository layer, API routes, reusable hub component folder, generic inventory engine,
or separate fulfillment-summary table/page.

## Use-Case Mapping

| Use Case | Form Request | Controller | Action | Unit Test | Feature Test |
|---|---|---|---|---|---|
| List/filter hub work | `ListHubWorkQueueRequest` | `HubDashboardController@index` | `ListHubWorkQueueAction` | `ListHubWorkQueueActionTest.php` | `HubWorkQueueTest.php` |
| Show receive form | none; route binding + policy | `HubReceiptController@create` | `ShowReceiveProduceFormAction` | `ShowReceiveProduceFormActionTest.php` | `HubReceiptTest.php` |
| Finalize receipt | `ReceiveProduceRequest` | `HubReceiptController@store` | `FinalizeHubReceiptAction` | `FinalizeHubReceiptActionTest.php` | `HubReceiptTest.php` |
| Show receipt workspace | none; route binding + policy | `HubReceiptController@show` | `ShowHubReceiptAction` | `ShowHubReceiptActionTest.php` | `HubStockTest.php` |
| Append receipt correction | `CorrectHubReceiptRequest` | `HubReceiptCorrectionController@store` | `CorrectHubReceiptAction` | `CorrectHubReceiptActionTest.php` | `HubReceiptTest.php` |
| Record handling loss | `RecordHandlingLossRequest` | `HubHandlingLossController@store` | `RecordHandlingLossAction` | `RecordHandlingLossActionTest.php` | `HubStockTest.php` |
| Allocate one group | `AllocateStockRequest` | `HubAllocationController@store` | `AllocateOrderGroupAction` | `AllocateOrderGroupActionTest.php` | `HubAllocationTest.php` |
| Release allocation | `ReleaseStockAllocationRequest` | `HubAllocationReleaseController@store` | `ReleaseStockAllocationAction` | `ReleaseStockAllocationActionTest.php` | `HubAllocationTest.php` |
| Prepare allocation | `PrepareStockAllocationRequest` | `HubPreparationController@store` | `PrepareOrderGroupAction` | `PrepareOrderGroupActionTest.php` | `HubPreparationTest.php` |

## Security Design

| Boundary/Risk | Control | Verification |
|---|---|---|
| Missing upstream domain | Hard preflight gate; never create hub-local stand-ins | Quickstart file/model/route checks |
| Ordinary user reaches staff data | `auth`, `verified`, `HubReceiptPolicy`, authorized Requests | Guest redirect, ordinary-user 403, operator success tests |
| Client submits quantities/statuses | Requests accept documented fields only; Actions derive overage, rejected total, allocation quantity, bucket changes, and states | Extra-field, tampered-quantity, and state-conflict tests |
| Overselling under competing writes | Short transaction, stable lock order, receipt `inventory_version` compare-and-set, group current-allocation conditional claim | Competing/stale Action tests; production-engine concurrency gate |
| Duplicate form/retry | Raw UUID token hashed; canonical payload hash; unique per-operation columns; same payload returns existing result, mismatch conflicts | Same-token same/different payload tests |
| Partial order/group mutation | Receipt, group, allocation, orders, transitions, and audit fact updated in one transaction | Forced-conflict rollback assertions |
| Private operational data | Encrypted note casts, explicit Inertia arrays, safe public UUIDs, no model serialization | Privacy response/prop/log tests |
| Operator mutation abuse | Named `hub-operations` limiter at 60/minute by user ID and IP | 429 feature test |
| Unsafe diagnostics | Fixed `HubConflictException` codes/messages; no payload, token, note, actor, SQL, or model context | Exception/log assertions |
| Dispatch scope leakage | No dispatch route/Action; preparation leaves stock/orders allocated | Route absence and preparation-state tests |

## Implementation Order

The implementation agent MUST follow this order and finish each test before moving on:

1. Run the upstream contract gate. Stop on the first missing prerequisite.
2. Add `KilogramQuantity`, enums, and their focused unit tests.
3. Generate migrations/models/factories with `php artisan make:* --no-interaction` in
   the exact data-model order; run migration/factory smoke tests.
4. Implement receipt finalization and correction Actions with direct tests.
5. Implement handling loss with direct tests.
6. Implement allocation, release, and preparation one Action at a time with direct tests.
7. Implement read Actions and bounded query tests.
8. Add policies, Requests, thin controllers, routes, limiter, safe exception rendering,
   and HTTP feature tests.
9. Generate Wayfinder output, then implement the three React pages and sidebar link.
10. Run the complete verification sequence in `quickstart.md` and perform the manual
    demo flow. Do not mark complete if the production-engine concurrency gate is skipped.

## Implementation Guardrails

1. Do not start until upstream models and grouped-order lifecycle exist.
2. Do not add Composer/npm packages, placeholder upstream models, services, repositories,
   DTO libraries, queues, jobs, commands, event sourcing, or generic state machines.
3. Generate Laravel files with `php artisan make:* --no-interaction`; then edit the
   generated files. Never hand-edit Wayfinder output.
4. Store every kilogram value as integer hundredths. Parse/format only through
   `KilogramQuantity`; never use PHP, SQL, or JavaScript floating-point math.
5. Derive `overage = max(received - required, 0)` server-side. The client submits quality
   rejection only; overage is always rejected and never allocatable.
6. Keep both invariants true after every successful Action:
   `accepted + quality_rejected + overage_rejected = received` and
   `available + allocated + damaged + dispatched = accepted`.
7. One procurement requirement has one finalized receipt. One group is allocated in
   full from exactly one receipt. Never split or combine.
8. Grade is informational. Do not use it to filter or reject an allocation.
9. Corrections append history and are allowed only before any downstream stock activity.
10. Damage moves quantity from available to damaged only. Allocation moves available to
    allocated only. Release reverses the full unprepared allocation only.
11. Preparation requires the exact allocation quantity, changes readiness only, and
    leaves receipt buckets and Order statuses allocated.
12. Never add a dispatch endpoint or write dispatched quantity in this feature.
13. Every mutation uses one `DB::transaction(..., attempts: 3)` and rechecks domain state
    inside it. Lock requirement/receipt/group/allocation/orders in that stable order.
14. Never rely only on `lockForUpdate()`. Use receipt version and conditional group/
    allocation updates; zero affected rows is a safe conflict and rolls back everything.
15. Store only hashes of operation tokens. Never log tokens or full request payloads.
16. Build explicit response arrays. Never return `Model::toArray()` or expose internal
    IDs, supplier/customer PII, private notes, raw actor IDs, or replay hashes.
17. Use existing `<Form>`, Wayfinder `.form()`, Cards, Badges, inputs, tables, toast,
    responsive/dark-mode conventions, and server-provided `can` props.
18. Do not calculate stock, overage, elapsed time, or compatibility in React. Do not use
    optimistic inventory updates or polling.
19. Paginate receipts at 25, cap auxiliary work lists at 50, select only needed columns,
    eager-load bounded relations, and use conditional aggregates for summary totals.
20. Write direct Action tests first, then thin HTTP tests. A passing SQLite suite does
    not replace the required MySQL/PostgreSQL concurrency run.

## Complexity Tracking

No Constitution violations. No entries required.
