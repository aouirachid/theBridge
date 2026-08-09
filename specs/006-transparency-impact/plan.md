# Implementation Plan: Transparency Ledger and Impact Dashboard

**Branch**: `N/A (no branch hook configured)` | **Date**: 2026-08-09 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/006-transparency-impact/spec.md`

## Summary

Add one platform-wide append-only hash chain and two small transparency screens. Existing
Phase 001-005 Actions append allowlisted, privacy-safe event payloads inside their current
database transactions. A singleton `LedgerChain` row is locked before each append so
concurrent events receive one position without forks. `LedgerEntry` rows are protected
against update/delete by the application and by SQLite triggers. A synchronous,
operator-only verification Action checks at most 10,000 entries and records a scoped
valid, invalid, or incomplete result.

The public page derives price and consolidation figures from existing immutable source
records; it does not create a reporting copy. One operator page runs verification and
publishes versioned trip-reduction indicators. Keep the implementation literal: four
tables, six Actions, four controllers, four Requests, one policy, two React pages, one
canonical hashing helper, and one demo seeder. Do not add a queue, listener, observer,
cache, repository, API, blockchain, external service, or dependency.

**Hard implementation prerequisite**: the current working tree is still the Laravel
starter/auth application. Phases 001-005 are specified but not implemented. Phase 006
implementation MUST stop until every upstream model, field, Action, permission, and
focused test listed in the Upstream Contract Gate exists. Never create a temporary Phase
006 substitute for an upstream concept.

## Technical Context

**Language/Version**: PHP 8.4; TypeScript 5.9.3; React 19.2.8

**Primary Dependencies**: Laravel 13.24.0, Inertia Laravel 3.3.1,
`@inertiajs/react` 3.6.1, Wayfinder 0.1.21 / Vite plugin 0.1.7, Tailwind CSS
4.3.3, Pest 5.0.4. No dependency changes.

**Storage**: Existing SQLite for the local hackathon app and tests. Four new tables:
`ledger_chains`, `ledger_entries`, `ledger_verifications`, and `impact_indicators`.
One separate data migration inserts the singleton chain head before immutable-entry
triggers are created. No cache, object storage, or queue.

**Testing**: Direct Pest unit tests for every Action and the canonical hash helper; Pest
feature tests for HTTP/Inertia integration, authorization, validation, throttling,
privacy, trigger-backed immutability, and safe errors. The browser plugin is not
installed, so use the existing PHP/Inertia assertions, frontend static checks, and the
bounded manual responsive/dark-mode flow in `quickstart.md`.

**Target Platform**: Ordinary desktop/mobile browsers and the local Casablanca
hackathon demonstration

**Project Type**: Laravel/Inertia React web application

**Architecture**: Dedicated Form Request -> thin controller -> one use-case Action for
all HTTP inputs. `AppendLedgerEntryAction` is an internal Action called directly by
upstream Actions and has no route or controller. `LedgerEventData` is the only event
input type. `LedgerHasher` is the only hash/canonicalization helper.

**Frontend**: `transparency/show.tsx` is the guest page for one published offer and its
related completed operational scope. `operator/transparency/index.tsx` uses AppLayout
for verification history and one impact-indicator form. Use Wayfinder-generated
controller actions, Inertia `<Form>`, existing Card/Badge/Alert/Button/Input/Select,
responsive grids, semantic definition lists/tables, and existing dark mode. Do not use
deferred props, polling, optimistic updates, custom CSS, charts, or a third page.

**Security**: Public output uses explicit arrays and random public IDs; it never
serializes models. Ledger payload schemas exclude names, contacts, addresses, notes,
credentials, tokens, raw provider data, and internal actor IDs. Operator routes require
`auth`, `verified`, operations permission, policy/Request authorization, CSRF, and a
named 10/minute user+IP mutation limiter. Ledger entries expose no update/delete route;
the model rejects mutation and SQLite triggers reject raw UPDATE/DELETE through the app
connection. The controlled corruption test drops and restores only the update trigger
inside an isolated test cleanup block.

**Performance Goals**: Public page under 2 seconds locally for 95% of demo requests;
full verification of 10,000 entries under 30 seconds; operator history paginated at 25;
public data limited to one offer and one completed reporting scope; no N+1 queries.

**Constraints**: Money and percentages remain upstream integer minor units/basis points;
quantities remain integer hundredths; ledger payload JSON contains only integers,
booleans, nulls, safe strings, and ordered arrays. Never use floats. Store UTC timestamps
to seconds and display Casablanca time. SHA-256 provides tamper evidence, not legal
certification or resistance to a database administrator who rewrites the entire chain.
SQLite is the scoped demo engine; a later server database must replace the SQLite
triggers with equivalent table grants/triggers before production use.

**Scale/Scope**: One chain, one head row, at most 10,000 entries per verification, one
public page, one operator page, one active trip-reduction indicator per completed cycle,
one deterministic tomato demo, and no environmental model beyond documented trip
reduction.

## Constitution Check

*GATE: Passed before Phase 0 research and passed again after Phase 1 design, subject to
the hard upstream implementation prerequisite.*

- **Framework conventions - PASS**: Installed versions were confirmed with Laravel
  Boost and `package.json`. The design uses Eloquent, transactions, Form Requests,
  policies, rate limiting, Inertia `<Form>`, Wayfinder, existing UI primitives, and Pest.
- **Security boundary - PASS**: Public allowlists, private actor isolation, payload
  schemas, no ledger mutation routes, model/trigger immutability, operator permission,
  CSRF, throttling, safe failures, and privacy tests are explicit.
- **Action-first design - PASS**: Six named business use cases map to six Actions.
  Controllers adapt HTTP only; `LedgerHasher` performs a pure deterministic operation.
- **Form Request boundary - PASS**: All public/operator filters and both mutations have
  dedicated Requests. The internal append Action accepts only `LedgerEventData`, never
  an HTTP Request or arbitrary model serialization.
- **Layered tests - PASS**: Every Action has a direct Pest test; feature tests prove
  middleware, authorization, validation, mapping, responses, mutations, and privacy.
  Static checks plus one manual flow cover the two pages without adding a dependency.
- **Operational quality - PASS**: A locked chain head, unique event key and position,
  bounded cursor verification, indexed aggregates, pagination, exact source scopes,
  short transactions, and safe rollback/conflict behavior are specified.

Any failed gate MUST be resolved before Phase 0 or documented in Complexity Tracking
with explicit approval.

## Upstream Contract Gate

Before creating any Phase 006 source file, verify every row and run the focused Phase
001-005 tests. Stop on the first missing item. Adapt a name only when the implemented
upstream contract is semantically identical; update these artifacts before coding if it
is not.

| Required upstream item | Exact minimum contract consumed here |
|---|---|
| `ProductOffer`, costs, comparison | Random offer public ID; crop/origin; farmer, component, margin, final-price and farmer-share snapshots; publication state/time; published fresh benchmark with source/demo/saving snapshots |
| Offer Actions | `PublishProductOfferAction` and `PublishBenchmarkComparisonAction`, each with one transaction that can call the append Action before commit |
| `Order` and transitions | Random public ID; safe channel/zone/service date; quantity, crop, unit-price and total snapshots; confirmed time; append-only status transitions |
| Order Actions | `CreateOrderAction` and `CancelOrderAction` with atomic status/history writes |
| Consolidation records | Completed `ConsolidationCycle`, its random public ID, cutoff/service date, source-order membership, `OrderGroup` rows, product/channel/zone counts and integer quantities |
| Consolidation Action | `RunConsolidationCycleAction` with deterministic replay and one final commit point |
| Hub records | Random public IDs for `HubReceipt` and `StockAllocation`; append-only correction/loss history with fixed reason enums; received/accepted/rejected/damaged/allocated quantities and occurrence times |
| Hub Actions | `FinalizeHubReceiptAction`, `CorrectHubReceiptAction`, `RecordHandlingLossAction`, `AllocateOrderGroupAction`, and `ReleaseStockAllocationAction` |
| Dispatch records | Random public ID, channel/zone/service date/status, frozen membership, exact delivery cost, order cost allocations, status transitions, and safe provider reference |
| Dispatch Actions | `SubmitDispatchAction` and `AdvanceDispatchAction` with atomic cost/status/history writes |
| Staff permission | Non-fillable `users.is_operations_operator`, `isOperationsOperator()` helper, and factory state |
| Shared formatters | Upstream pricing and quantity formatters; Phase 006 must not introduce a second money/percentage/kilogram calculator |

The gate currently fails because none of these application-domain files exists. The plan
is ready for later use; implementation is not authorized to work around this state.

## Project Structure

### Documentation (this feature)

```text
specs/006-transparency-impact/
|-- plan.md
|-- research.md
|-- data-model.md
|-- quickstart.md
|-- contracts/
|   |-- internal-ledger-events.md
|   `-- web-routes-and-props.md
`-- tasks.md
```

### Source Code (repository root)

Replace or extend this tree with the concrete files used by the feature. Remove unused
directories from the delivered plan.

```text
app/
|-- Actions/Transparency/
|   |-- AppendLedgerEntryAction.php
|   |-- VerifyLedgerAction.php
|   |-- ShowPublicTransparencyAction.php
|   |-- ShowTransparencyOperationsAction.php
|   |-- PublishImpactIndicatorAction.php
|   `-- SeedTransparencyDemoAction.php
|-- Enums/
|   |-- ImpactIndicatorClassification.php
|   |-- ImpactIndicatorCode.php
|   |-- LedgerEventType.php
|   |-- LedgerVerificationFailure.php
|   `-- LedgerVerificationStatus.php
|-- Exceptions/LedgerConflictException.php
|-- Http/Controllers/
|   |-- PublicTransparencyController.php
|   `-- Operator/Transparency/
|       |-- TransparencyController.php
|       |-- LedgerVerificationController.php
|       `-- ImpactIndicatorController.php
|-- Http/Requests/Transparency/
|   |-- ShowPublicTransparencyRequest.php
|   `-- Operator/
|       |-- ShowTransparencyOperationsRequest.php
|       |-- VerifyLedgerRequest.php
|       `-- PublishImpactIndicatorRequest.php
|-- Models/
|   |-- LedgerChain.php
|   |-- LedgerEntry.php
|   |-- LedgerVerification.php
|   `-- ImpactIndicator.php
|-- Policies/LedgerChainPolicy.php
`-- Support/Transparency/
    |-- LedgerEventData.php
    `-- LedgerHasher.php

config/transparency.php
routes/transparency.php

database/
|-- factories/
|   |-- ImpactIndicatorFactory.php
|   |-- LedgerChainFactory.php
|   |-- LedgerEntryFactory.php
|   `-- LedgerVerificationFactory.php
|-- migrations/
|   |-- *_add_is_demo_to_product_offers_table.php
|   |-- *_create_ledger_chains_table.php
|   |-- *_create_ledger_entries_table.php
|   |-- *_create_ledger_verifications_table.php
|   |-- *_create_impact_indicators_table.php
|   |-- *_insert_platform_ledger_chain.php
|   `-- *_protect_ledger_entries_from_mutation.php
`-- seeders/TransparencyDemoSeeder.php

resources/js/
|-- components/app-sidebar.tsx
|-- pages/
|   |-- offers/show.tsx                 # add transparency Wayfinder link only
|   |-- transparency/show.tsx
|   `-- operator/transparency/index.tsx
|-- types/transparency.ts
`-- types/index.ts

tests/
|-- Feature/Transparency/
|   |-- PublicTransparencyTest.php
|   |-- OperatorTransparencyTest.php
|   |-- LedgerEntryImmutabilityTest.php
|   `-- TransparencyPrivacyTest.php
`-- Unit/
    |-- Actions/Transparency/
    |   |-- AppendLedgerEntryActionTest.php
    |   |-- VerifyLedgerActionTest.php
    |   |-- ShowPublicTransparencyActionTest.php
    |   |-- ShowTransparencyOperationsActionTest.php
    |   |-- PublishImpactIndicatorActionTest.php
    |   `-- SeedTransparencyDemoActionTest.php
    `-- Support/Transparency/
        |-- LedgerEventDataTest.php
        `-- LedgerHasherTest.php
```

Existing Phase 001-005 Actions and their direct tests are modified only at the exact
integration points in `contracts/internal-ledger-events.md`. Generated Wayfinder files
are omitted because they must never be hand-edited.

Phase 006 also extends existing `ProductOffer` with one server-owned `is_demo` boolean,
updates its cast/factory, and lets only `SeedTransparencyDemoAction` pass `true` through
the existing draft-creation Action. No HTTP Request accepts this field.

**Structure Decision**: Use two pages and two pure support objects. `LedgerChain` is a
real persisted lock/head, not a service abstraction. No reporting model or generic event
bus is added. The extra data migration and trigger migration keep schema creation, seed
data, and immutability concerns separate.

## Use-Case Mapping

| Use Case | Form Request | Controller | Action | Unit Test | Feature Test |
|----------|--------------|------------|--------|-----------|--------------|
| Show public transparency | `ShowPublicTransparencyRequest` | `PublicTransparencyController@show` | `ShowPublicTransparencyAction` | `ShowPublicTransparencyActionTest.php` | `PublicTransparencyTest.php` |
| Show operator workspace | `ShowTransparencyOperationsRequest` | `TransparencyController@index` | `ShowTransparencyOperationsAction` | `ShowTransparencyOperationsActionTest.php` | `OperatorTransparencyTest.php` |
| Append one source event | none; internal typed input | none | `AppendLedgerEntryAction` | `AppendLedgerEntryActionTest.php` plus each upstream Action test | no standalone route; source feature tests |
| Verify current chain endpoint | `VerifyLedgerRequest` | `LedgerVerificationController@store` | `VerifyLedgerAction` | `VerifyLedgerActionTest.php` | `OperatorTransparencyTest.php` |
| Publish indicator version | `PublishImpactIndicatorRequest` | `ImpactIndicatorController@store` | `PublishImpactIndicatorAction` | `PublishImpactIndicatorActionTest.php` | `OperatorTransparencyTest.php` |
| Seed exact demo scenario | none; seeder-only typed defaults | none; `TransparencyDemoSeeder` adapter | `SeedTransparencyDemoAction` | `SeedTransparencyDemoActionTest.php` | `PublicTransparencyTest.php` |

## Security Design

| Boundary/Risk | Control | Verification |
|---------------|---------|--------------|
| Missing Phases 001-005 | Hard preflight gate; no stand-ins or direct domain inserts | Quickstart file/schema/test gate |
| PII enters ledger | Named `LedgerEventData` constructors accept only safe scalars; no model/request serialization | Canary tests across every event type and stored payload |
| Entry update/delete | No routes; model throws; SQLite UPDATE/DELETE triggers | Eloquent, query-builder, and raw SQL rejection tests |
| Fork/duplicate append | Lock singleton head; unique event key; unique chain position; one transaction | Replay, rollback, and concurrent append tests |
| Misleading verification | Capture exact head; verify beginning/order/link/hash/count; result scoped to endpoint | Tamper, gap, stale-head, incomplete, and exact-endpoint tests |
| Guest invokes costly work | `auth`, `verified`, policy, Request authorization, CSRF, 10/min user+IP limit | redirect, 403, 419/validation, and 429 tests |
| Dashboard leaks internals | Explicit nested arrays; safe public IDs; no `toArray()` on models | Recursive prop/HTML/metadata/privacy canary tests |
| Unsupported impact claim | Enum allows trip reduction only; measured requires evidence; estimate requires method/assumptions | Request/Action datasets and public label assertions |
| Aggregate double count | Count distinct frozen memberships/groups; sum immutable integer quantities for one completed scope | Replay scenarios and zero-variance assertions |
| Unsafe diagnostics | Fixed exception/failure codes and safe event/position references only | Log and exception canary tests |

## Implementation Order for a Low-Capability Agent

The implementation agent MUST follow this order and MUST NOT work ahead:

1. Run the Upstream Contract Gate. Stop immediately on the first missing file, field,
   permission, or focused test. Do not generate Phase 006 PHP files while it fails.
2. Read `research.md`, `data-model.md`, and both contracts completely. Do not rename a
   field, event type, Action, route, prop, or status without updating all artifacts first.
3. Create and test only the five enums, `LedgerHasher`, and `LedgerEventData`. Prove the
   exact tomato payload, recursive key ordering, scalar restrictions, and stable hash.
4. Generate the offer demo-flag migration, then the four Phase 006 tables, singleton
   head, indexes, and two SQLite triggers. Migrate and inspect them before writing Actions.
5. Implement/test `AppendLedgerEntryAction` only. Cover first entry, replay, distinct
   append, rollback, forbidden payload, trigger protection, and concurrency.
6. Modify one upstream Action at a time in contract order. Add its ledger append inside
   the existing transaction, update its direct test, run that phase's focused tests, and
   continue only after they pass. Never batch all integrations before testing.
7. Implement/test `VerifyLedgerAction` only. Cover valid, altered payload, altered link,
   gap, duplicate, stale endpoint, incomplete run, safe failure, and 10,001 rejection.
8. Implement/test `PublishImpactIndicatorAction`, then the two read Actions. Use explicit
   selected columns and aggregate queries; assert query counts and exact public arrays.
9. Add policy, Requests, thin controllers, routes, limiter, trigger-safe exception
   rendering, and HTTP tests. No controller may calculate, hash, aggregate, or mutate.
10. Generate Wayfinder, build the two pages from the exact prop contract, then add only
    the required operator sidebar link and public offer-to-transparency link. Do not add
    another page or custom component.
11. Implement `SeedTransparencyDemoAction` and its seeder last, calling existing Phase
    Actions only. Never insert source or ledger rows directly from the seeder.
12. Run the full quickstart: all transparency and modified upstream tests, Pint, PHPStan,
    ESLint, Prettier, TypeScript, build, privacy scan, immutability checks, manual UI, and
    the production-like concurrency gate. Do not claim completion if any check fails.

## Guardrails for the Implementation Agent

1. Use the exact four tables, six Actions, event types, fields, routes, props, and
   algorithms in these artifacts. Do not invent a service/repository/event/listener layer.
2. Generate PHP files with `php artisan make:* --no-interaction`. Never hand-create
   migration timestamps or edit generated Wayfinder files.
3. `AppendLedgerEntryAction` is the only class allowed to insert `LedgerEntry` or update
   the `LedgerChain` head. No other class computes `entry_hash` or `position`.
4. Every source Action calls the append Action before its existing transaction commits.
   Never append after commit, via `defer()`, a queue, observer, or listener.
5. Never pass a model, Request, `toArray()` result, encrypted attribute, name, contact,
   address, note, token, credential, or provider payload into `LedgerEventData`.
6. Never update/delete a ledger entry. Corrections append `CorrectionRecorded`. Only the
   controlled test may drop a trigger, and it must restore it in `finally`.
7. Do not use floats. Do not recalculate source prices or quantities. Reuse upstream
   formatters for display and preserve integer source snapshots in ledger payloads.
8. A public integrity status is `valid` only when the newest completed valid verification
   endpoint equals the current chain head position and hash. New entries make it `stale`.
9. Do not cache dashboard or verification results in this phase. Bounded indexed queries
   are simpler and prevent stale claims.
10. Only `trip_reduction` is publishable. No numeric CO2/emissions claim, upload, external
    evidence fetch, or arbitrary indicator code is allowed.
11. Build explicit Inertia arrays and server-provided labels/permissions. Never serialize
    a model or put business formulas in React.
12. SQLite triggers are part of the MVP acceptance gate. A future database engine requires
    equivalent grants/triggers and tests before deployment; do not silently skip them.

## Complexity Tracking

> Fill ONLY when a Constitution Check violation requires explicit justification.

No Constitution violations. No entries required.
