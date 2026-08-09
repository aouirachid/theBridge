# Implementation Plan: Midnight Order Consolidation

**Branch**: `N/A (no branch hook configured)` | **Date**: 2026-08-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/003-midnight-order-consolidation/spec.md`

## Summary

Add one staff-only consolidation workspace and one scheduled command. A single core
Action closes one service-date ordering window, deterministically groups at most 500
eligible orders by ProductOffer, channel, and zone, pauses for exact under-minimum
decisions, then atomically creates procurement requirements/order groups and advances
included orders. Database unique constraints and conditional writes make automatic,
manual, retry, and concurrent execution replay-safe.

**Hard implementation prerequisite**: the working tree currently contains only the
Laravel starter/auth application. Phase 001 and Phase 002 are planned but not
implemented. Implementation MUST stop until their ProductOffer/Order contracts and tests
exist. Never create temporary or duplicate upstream models inside this feature.

Keep the implementation literal: five new domain tables, one nullable Order FK, four
small enums, seven Actions, six Requests, five thin controllers, one command, two React
pages, three focused support classes, and direct Pest coverage. Do not add a queue,
service/repository layer, Product model, membership pivot, state-machine package, or UI
dependency.

## Technical Context

**Language/Version**: PHP 8.4; TypeScript 5.9.3; React 19.2.8

**Primary Dependencies**: Laravel 13.24.0, Inertia Laravel 3.3.1,
`@inertiajs/react` 3.6.1, Wayfinder 0.1.21 / Vite plugin 0.1.7, Tailwind CSS
4.3.3, Pest 5.0.4

**Storage**: Existing SQLite for local/demo/tests, with portable migrations for
MySQL/PostgreSQL. Five consolidation tables plus `orders.order_group_id`; existing
database cache only for overlap optimization. No object storage or queue.

**Testing**: Direct Pest unit tests for every Action plus schedule/preview/quantity
support; Pest feature tests for HTTP/Inertia and command/schedule integration. No browser
plugin is installed, so use frontend static checks and the manual UI flow in
`quickstart.md`; do not add a dependency.

**Target Platform**: Laravel/Inertia web application for ordinary desktop/mobile
browsers and a local Casablanca hackathon demonstration

**Project Type**: Laravel/Inertia React web application

**Architecture**: Dedicated Form Request -> thin controller -> one use-case Action.
Scheduled command -> scheduled-start Action -> the same core run Action used by manual
start/retry. Pure support classes own cutoff resolution, deterministic preview building,
and kilogram formatting only; they do not become a service layer.

**Frontend**: Two pages under `resources/js/pages/operator/consolidations`: index and
show. Use Inertia `<Form>`, generated Wayfinder controller actions/routes, server-shaped
props, confirmation Dialogs, disabled processing states, field/conflict errors, empty
states, responsive semantic tables, pagination, existing dark mode, and no optimistic
updates/polling/custom CSS.

**Security**: Add a non-fillable `users.is_operations_manager` permission. Managers must
also be operations operators; only managers can view/change cutoff configuration. Require
`auth`, `verified`, policy/Form Request authorization, CSRF, and a named
30/minute user+IP mutation limiter. Use random public UUID bindings, explicit response
arrays, fixed conflict codes, and safe logging. Never expose/decrypt customer PII, raw
payloads, internal IDs, fingerprints, SQL, or model dumps.

**Performance Goals**: One cycle handles up to 500 eligible orders and finishes within
2 minutes; operator pages respond within 1 second locally for 95% of requests; recent
cycles paginate at 25 and selected group orders at 50 with no N+1 queries.

**Constraints**: All quantity is integer hundredths of a kilogram and money is integer
MAD minor units; never use floats. Store timestamps in UTC and derive/display
`Africa/Casablanca`. Finalization is one short transaction with three deadlock retries.
`lockForUpdate()` assists supported engines; unique indexes, conditional updates, and
rollback are the portable correctness boundary. No external calls.

Each cycle snapshots its scheduled cutoff when the row is first created. Closure commits
that preserved boundary before preview/finalization, so a later setting change or failed
attempt cannot move or erase it.

**Scale/Scope**: Hackathon MVP, one hub, one cycle per service date, three existing zones,
two existing channels, at most 500 eligible orders/cycle, two operator pages, and six
staff mutations/reads. No multi-hub split, cross-offer product catalog, routing,
purchasing, payment, stock allocation, dispatch, notification, or delivery integration.

## Constitution Check

*GATE: Passed before research and passed again after design, subject to the hard upstream
implementation prerequisite.*

- **Framework conventions — PASS**: Installed versions were confirmed with Boost,
  Composer, and npm. Design reuses existing Form, Wayfinder, AppLayout, Cards, Dialogs,
  toast, route files, Eloquent, scheduler, cache locks, and Pest patterns.
- **Security boundary — PASS**: Authentication, verified operations permission,
  policies, authorized Requests, CSRF, mutation throttling, allowlisted props, private
  fingerprints, PII exclusion, and safe outcomes are explicit.
- **Action-first design — PASS**: Seven named use cases map to seven Actions. The command
  and controllers only adapt their delivery boundary; core grouping stays in one Action.
- **Form Request boundary — PASS**: Every mutation and both filtered reads have a
  dedicated Request. The scheduled command is trusted input and accepts no business
  arguments.
- **Layered tests — PASS**: Every Action receives a direct test; two feature files cover
  HTTP and scheduled command wiring. Manual UI verification covers frontend behavior
  unavailable to the installed PHP test stack.
- **Operational quality — PASS**: Bounded indexed queries, two pagination limits,
  deterministic fingerprints, unique constraints, conditional claims, transactions,
  overlap locks, safe failures, and recovery are designed.

Any failed gate MUST be resolved before Phase 0 or documented in Complexity Tracking
with explicit approval.

## Project Structure

## Upstream Contract Gate

Before creating any Phase 003 source file, verify every row. Stop on the first failure
and implement/repair the earlier phase. Do not create stand-ins.

| Required upstream item | Exact minimum contract |
|---|---|
| `ProductOffer` / `OfferCostComponent` | Stable `id`, `public_id`, `crop`, immutable published cost rows, and required `delivery_allocation` component |
| `Order` | `product_offer_id`, public UUID, enums, status, quantity, money snapshot, crop/offer snapshot, service date, zone, confirmation time |
| Enums | Existing `OrderChannel`, `DeliveryZone`, and `OrderStatus` including `Confirmed` and `Grouped` |
| `OrderStatusTransition` | Append-only transition with order, nullable actor, from/to status, and occurrence time |
| Operations permission | Non-fillable `users.is_operations_operator`, plus Phase 003's distinct non-fillable `is_operations_manager` for cutoff access |
| Phase 001/002 verification | All focused Action/Feature suites pass before Phase 003 migration or code generation |

MVP "product" means one ProductOffer identity. Do not group by `crop_snapshot`, merge
replacement offers, or add a Product model. Phase 004 must consume the exact
`ProcurementRequirement` and `OrderGroup` names defined here. It must also reuse
`Support\Quantities\KilogramQuantity` instead of creating its currently planned
hub-local duplicate.

## Project Structure

### Documentation (this feature)

```text
specs/003-midnight-order-consolidation/
|-- plan.md
|-- research.md
|-- data-model.md
|-- quickstart.md
|-- contracts/
|   `-- web-routes-and-props.md
`-- tasks.md
```

### Source Code (repository root)

```text
app/
|-- Actions/Consolidations/
|   |-- ListConsolidationCyclesAction.php
|   |-- ShowConsolidationCycleAction.php
|   |-- StartScheduledConsolidationAction.php
|   |-- StartManualConsolidationAction.php
|   |-- RunConsolidationCycleAction.php
|   |-- DecideUnderMinimumCandidateAction.php
|   `-- UpdateConsolidationCutoffAction.php
|-- Console/Commands/ConsolidateOrdersCommand.php
|-- Enums/
|   |-- ConsolidationCandidateDecision.php
|   |-- ConsolidationCycleStatus.php
|   |-- ConsolidationTrigger.php
|   `-- ProcurementRequirementStatus.php
|-- Exceptions/ConsolidationConflictException.php
|-- Http/Controllers/Operator/
|   |-- ConsolidationCycleController.php
|   |-- ManualConsolidationController.php
|   |-- ConsolidationCycleRunController.php
|   |-- UnderMinimumDecisionController.php
|   `-- ConsolidationCutoffController.php
|-- Http/Requests/Operator/Consolidations/
|   |-- ListConsolidationCyclesRequest.php
|   |-- ShowConsolidationCycleRequest.php
|   |-- StartManualConsolidationRequest.php
|   |-- RetryConsolidationCycleRequest.php
|   |-- DecideUnderMinimumCandidateRequest.php
|   `-- UpdateConsolidationCutoffRequest.php
|-- Models/
|   |-- ConsolidationCandidate.php
|   |-- ConsolidationCutoffChange.php
|   |-- ConsolidationCycle.php
|   |-- Order.php                         # relationship + window guard integration
|   |-- OrderGroup.php
|   |-- ProcurementRequirement.php
|   `-- User.php                          # manager permission cast/helper
|-- Policies/ConsolidationCyclePolicy.php
`-- Support/
    |-- Consolidations/ConsolidationPreviewBuilder.php
    |-- Consolidations/ConsolidationSchedule.php
    `-- Quantities/KilogramQuantity.php

config/consolidation.php
bootstrap/app.php                           # fixed safe conflict rendering
routes/
|-- consolidations.php
|-- console.php                             # scheduled command registration
`-- web.php                                 # require route file

database/
|-- factories/
|   |-- ConsolidationCandidateFactory.php
|   |-- ConsolidationCutoffChangeFactory.php
|   |-- ConsolidationCycleFactory.php
|   |-- OrderGroupFactory.php
|   |-- ProcurementRequirementFactory.php
|   `-- UserFactory.php                   # operationsManager() state
`-- migrations/
    |-- *_create_consolidation_cutoff_changes_table.php
    |-- *_create_consolidation_cycles_table.php
    |-- *_create_consolidation_candidates_table.php
    |-- *_create_procurement_requirements_table.php
    |-- *_create_order_groups_table.php
    |-- *_add_order_group_to_orders_table.php
    `-- *_add_operations_manager_to_users_table.php

resources/js/
|-- components/app-sidebar.tsx
|-- pages/operator/consolidations/index.tsx
|-- pages/operator/consolidations/show.tsx
|-- types/consolidation.ts
`-- types/index.ts

tests/
|-- Feature/Consolidations/
|   |-- OperatorConsolidationTest.php
|   `-- ScheduledConsolidationTest.php
`-- Unit/
    |-- Actions/Consolidations/
    |   |-- DecideUnderMinimumCandidateActionTest.php
    |   |-- ListConsolidationCyclesActionTest.php
    |   |-- RunConsolidationCycleActionTest.php
    |   |-- ShowConsolidationCycleActionTest.php
    |   |-- StartManualConsolidationActionTest.php
    |   |-- StartScheduledConsolidationActionTest.php
    |   `-- UpdateConsolidationCutoffActionTest.php
    `-- Support/
        |-- Consolidations/ConsolidationPreviewBuilderTest.php
        |-- Consolidations/ConsolidationScheduleTest.php
        `-- Quantities/KilogramQuantityTest.php
```

**Structure Decision**: Use existing Laravel/Inertia folders and one feature route file.
Two pages cover every operator decision. Candidate rows replace JSON previews;
`orders.order_group_id` replaces a membership pivot. Generated Wayfinder files are not
listed because they must never be hand-edited.

## Use-Case Mapping

| Use Case | Form Request | Controller / command | Action | Unit Test | Feature Test |
|---|---|---|---|---|---|
| List/filter cycles | `ListConsolidationCyclesRequest` | `ConsolidationCycleController@index` | `ListConsolidationCyclesAction` | same-named Action test | `OperatorConsolidationTest` |
| Show cycle/group orders | `ShowConsolidationCycleRequest` | `ConsolidationCycleController@show` | `ShowConsolidationCycleAction` | same-named Action test | `OperatorConsolidationTest` |
| Start scheduled cycle | none; trusted clock | `ConsolidateOrdersCommand` | `StartScheduledConsolidationAction` | same-named Action test | `ScheduledConsolidationTest` |
| Start manual/early cycle | `StartManualConsolidationRequest` | `ManualConsolidationController@store` | `StartManualConsolidationAction` | same-named Action test | `OperatorConsolidationTest` |
| Retry/continue cycle | `RetryConsolidationCycleRequest` | `ConsolidationCycleRunController@store` | `RunConsolidationCycleAction` | same-named Action test | `OperatorConsolidationTest` |
| Approve/exclude candidate | `DecideUnderMinimumCandidateRequest` | `UnderMinimumDecisionController@store` | `DecideUnderMinimumCandidateAction` | same-named Action test | `OperatorConsolidationTest` |
| Change future cutoff (manager only) | `UpdateConsolidationCutoffRequest` | `ConsolidationCutoffController@update` | `UpdateConsolidationCutoffAction` | same-named Action test | `OperatorConsolidationTest` |

## Security Design

| Boundary/Risk | Control | Verification |
|---|---|---|
| Missing upstream domain | Hard preflight; never create local stand-ins | Quickstart file/class/test gate |
| Guest/ordinary user reaches staff data | `auth`, `verified`, policy and Request authorization; manager-only cutoff props/update | redirect/403/operator/manager feature tests |
| Client injects cutoff/orders/totals/status | Requests accept only documented date/time/decision/filter fields; Actions derive all business data | extra-field and tampered-input tests |
| Customer PII leaks during reconciliation | Never decrypt contact columns; explicit arrays; public UUIDs; privacy-safe actor labels | recursive prop/log/exception absence tests |
| Duplicate automatic/manual/retry | Service-date unique cycle, cache lock optimization, state checks, unique outputs | repeated and concurrent Action/command tests |
| Confirmation races window closure | Order confirmation and consolidation lock/check the same service-date cycle row; cutoff check; conditional writes | deterministic race/rollback tests on production engine |
| Stale under-minimum approval | Candidate/generation fingerprints; decisions bound to exact snapshot; changed demand requires fresh decision | cancellation-after-preview and replay tests |
| Partial or double-counted outputs | One final transaction, stable lock order, real unique indexes, exact affected counts, invariant assertions | forced-conflict rollback and reconciliation tests |
| Manual mutation abuse | `consolidation-mutations` at 30/minute by user ID + IP | 429 feature test |
| Unsafe failures/logs | Fixed safe conflict codes; cycle safe failure category only; no payload/PII/hash/model context | exception and log assertions |

## Implementation Order for a Low-Capability Agent

The implementation agent MUST follow this order and MUST NOT work ahead:

1. Run the upstream gate. Stop immediately if Phase 001/002 files or tests are missing.
2. Add enums, `KilogramQuantity`, `ConsolidationSchedule`, and their focused tests.
3. Generate migrations/models/factories in the exact `data-model.md` table order; migrate
   and inspect schema before any Action.
4. Add the Order cycle relationship/window guard and its Phase 002 regression tests.
5. Implement/test `ConsolidationPreviewBuilder` using only already-loaded safe fields.
6. Implement/test `RunConsolidationCycleAction`; do not add HTTP code until its complete,
   awaiting-decision, excluded, retry, stale, and rollback tests pass.
7. Implement/test scheduled start, manual start, candidate decision, and cutoff update one
   Action at a time.
8. Implement/test list and show Actions with pagination/query-count/privacy assertions.
9. Add policy, Requests, thin controllers, routes, limiter, safe exception rendering,
   command, schedule, and HTTP/command feature tests.
10. Generate Wayfinder, then build the two pages and sidebar link from the exact contract.
11. Run the full verification sequence in `quickstart.md`, including the real-engine
   concurrency gate and manual responsive/keyboard/dark-mode demo flow.

## Guardrails for the Implementation Agent

1. Use the exact names, fields, statuses, routes, and files in these artifacts.
2. Generate PHP files with `php artisan make:* --no-interaction`; never hand-create a
   migration timestamp or edit generated Wayfinder files.
3. Do not add packages, Product, membership pivot, service/repository/DTO layer, queue,
   job, event sourcing, generic state machine, custom CSS, or a third React page.
4. Never group by crop text. Product scope is `product_offer_id` for the MVP.
5. Never use floats. Quantities are integer hundredths; money is integer minor units.
6. Never copy or rewrite an Order price snapshot. Reconciliation reads immutable Orders.
7. Never create completed outputs while a current under-minimum candidate lacks a
   decision. Excluded orders stay confirmed with `order_group_id = null`.
8. Never trust `lockForUpdate()` or cache locks alone. Unique indexes, conditional
   updates, affected-row counts, and transaction rollback are mandatory.
9. Lock/query in this order: cycle, eligible Orders by ID, candidates, requirements,
   groups. Create transitions only for Orders successfully claimed in that transaction.
10. Build explicit Inertia arrays; never serialize models or decrypt contact fields.
11. Use existing Form, Wayfinder, AppLayout, UI primitives, toast, responsive/dark-mode
   conventions, and server-provided `can` props. No optimistic mutation or polling.
12. Do not mark implementation complete if the upstream gate, affected Pest suites,
   static checks, build, or production-engine concurrency gate fails.

## Complexity Tracking

> Fill ONLY when a Constitution Check violation requires explicit justification.

No Constitution violations. No entries required.
