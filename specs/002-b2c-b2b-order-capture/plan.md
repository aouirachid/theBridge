# Implementation Plan: B2C and B2B Order Capture

**Branch**: `N/A (no branch hook configured)` | **Date**: 2026-08-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/002-b2c-b2b-order-capture/spec.md`

## Summary

Extend the published product offer from Phase 1 with a bounded list of delivery slots,
then let guests review and confirm B2C or B2B orders without accounts. The server owns
all price, availability, slot, and lifecycle decisions. Confirmation locks the offer,
rechecks remaining quantity, writes an immutable price snapshot and status history, and
returns a privacy-safe summary. Authorized operations staff receive a paginated order
list, a private detail page, and one pre-grouping cancellation action.

Keep the slice intentionally small. Use three domain tables, three small backed enums,
one pure order-total calculator, five single-purpose Actions, four Form Requests, five
controller methods, three Inertia page changes, and no new packages. Do not add customer
accounts, notifications, payments, queues, APIs, repositories, DTO packages, a generic
state-machine framework, or later-phase fulfillment behavior.

**Implementation prerequisite**: Phase 1 is designed but not implemented in the current
working tree. Complete `specs/001-transparent-product-offer/tasks.md` first. Phase 2 must
extend those exact ProductOffer files; it must not create a second offer model, public
offer route, pricing calculator, or operator role system.

## Technical Context

**Language/Version**: PHP 8.4.8; TypeScript 5.9.3; React 19.2.8

**Primary Dependencies**: Laravel 13.24.0, Inertia Laravel 3.3.1,
`@inertiajs/react` 3.6.1, Wayfinder 0.1.21 / Vite plugin 0.1.7, Tailwind CSS 4.3.3,
Pest 5.0.4

**Storage**: Existing SQLite database for local/demo and in-memory SQLite for tests;
portable Laravel migrations for MySQL/PostgreSQL. Existing cache backs named rate
limiters. No object storage, queue, or external service.

**Testing**: Pest unit tests invoke every Order Action directly and test the pure total
calculator. Pest feature tests cover routes, Requests, policies, Inertia/JSON contracts,
privacy, throttles, and persistence. Update the Phase 1 Action and feature tests affected
by delivery slots. Do not add browser-test dependencies; use the existing frontend
format, lint, type, and production-build checks plus the manual quickstart.

**Target Platform**: Laravel/Inertia web application for ordinary desktop/mobile
browsers and the local hackathon demonstration.

**Project Type**: Laravel/Inertia React web application

**Architecture**: Dedicated Form Request -> thin controller -> single-purpose Action ->
Eloquent models. Read workflows also use Actions. One safe domain exception represents
offer, quantity, slot, idempotency, and lifecycle conflicts. Controllers only convert
Action results or exceptions into the documented web response.

**Frontend**: Extend `offers/show` with one responsive order form using two Inertia v3
`useHttp` requests: review, then confirm. A successful review displays only server-made
totals and enables confirmation; any input change clears the review. Add operator order
index/detail pages using `AppLayout`, existing UI primitives, Tailwind v4 utilities, and
Wayfinder-generated action/route functions. Never hardcode application URLs or calculate
prices in React.

**Security**: Guest endpoints use CSRF, strict allowlist validation, separate IP-based
preview/confirmation throttles, non-enumerable public IDs, and a per-page idempotency
token stored only as a hash. Names, phone, email, address, notes, and business
identity use encrypted casts backed by `TEXT` columns and are hidden by default.
Operator pages require `auth`, `verified`, a new non-fillable
`users.is_operations_operator` flag, `OrderPolicy`, and a user/IP throttle. Public and
JSON responses use explicit arrays and never serialize Order models or PII. Logs and
exceptions contain only safe codes and public references.

**Performance Goals**: Under the 500-order MVP dataset, 95% of public review and
confirmation requests complete in under 500 ms and operator pages in under 1 second.
Operator lists use 25-row pagination. Offer availability uses one indexed aggregate
inside one locked transaction; detail/history relationships are eager loaded with
selected columns.

**Constraints**: Money is integer centimes; quantity is integer hundredths of a kilogram;
totals use integer half-up rounding once and never floats. Store timestamps in UTC,
derive service dates in `Africa/Casablanca`, and store enum values as short strings.
Published offers and delivery slots are immutable. Confirmed quantity/price/slot
snapshots never change. PII cannot be searched because it is encrypted; operator filters
therefore use only channel, status, date, offer, and delivery-zone codes.

**Scale/Scope**: Hackathon MVP: three fixed Casablanca demo zones, maximum 14 delivery
slots per offer, 500-order acceptance dataset, 25 orders per operator page, one current
order state plus append-only transition history, and no bulk actions or external calls.

## Constitution Check

*GATE: Passed before research and passed again after design.*

- **Framework conventions — PASS**: Exact installed versions were confirmed. The plan
  extends the Phase 1 Laravel/Inertia/Wayfinder design and existing starter-kit UI/forms
  rather than introducing a second pattern.
- **Security boundary — PASS**: Guest and operator actors, validation, authorization,
  encrypted PII, allowlisted outputs, CSRF, throttling, idempotency, conflict behavior,
  and safe diagnostics are mapped and testable.
- **Action-first design — PASS**: Review, confirm, list, inspect, and cancel each map to
  one concrete Action. Existing offer create/update/publication Actions own slot changes
  because slots are part of that same offer-draft use case.
- **Form Request boundary — PASS**: Both guest POST endpoints, operator filtering, and
  cancellation have dedicated Requests. Controllers pass only validated/safe data.
- **Layered tests — PASS**: Each Order Action has a direct unit test; calculator math has
  a focused test; public/operator HTTP behavior has focused feature tests; affected
  Phase 1 tests are updated.
- **Operational quality — PASS**: Confirmation is transactional and offer-row locked;
  idempotency is unique and hashed; queries are indexed/paginated/eager-loaded; no
  external failures or background workers are introduced.

## Project Structure

### Documentation (this feature)

```text
specs/002-b2c-b2b-order-capture/
|-- plan.md
|-- research.md
|-- data-model.md
|-- quickstart.md
|-- contracts/
|   `-- web-routes-and-props.md
`-- tasks.md                       # created later by speckit-tasks
```

### Source Code (repository root)

```text
app/
|-- Actions/Orders/
|   |-- ReviewOrderAction.php
|   |-- CreateOrderAction.php
|   |-- ListOrdersAction.php
|   |-- ShowOrderAction.php
|   `-- CancelOrderAction.php
|-- Actions/ProductOffers/          # Phase 1 files modified for delivery slots
|   |-- CreateProductOfferDraftAction.php
|   |-- UpdateProductOfferDraftAction.php
|   |-- ShowProductOfferDraftAction.php
|   |-- PublishProductOfferAction.php
|   `-- ShowPublicProductOfferAction.php
|-- Enums/
|   |-- DeliveryZone.php
|   |-- OrderChannel.php
|   `-- OrderStatus.php
|-- Exceptions/OrderConflictException.php
|-- Http/Controllers/
|   |-- PublicOrderReviewController.php
|   |-- PublicOrderController.php
|   |-- Operator/OrderController.php
|   `-- Operator/OrderCancellationController.php
|-- Http/Requests/
|   |-- Public/ReviewOrderRequest.php
|   |-- Public/StoreOrderRequest.php
|   |-- Operator/ListOrdersRequest.php
|   `-- Operator/CancelOrderRequest.php
|-- Models/
|   |-- OfferDeliverySlot.php
|   |-- Order.php
|   |-- OrderStatusTransition.php
|   |-- ProductOffer.php             # relationship only
|   `-- User.php                     # operations flag cast only
|-- Policies/OrderPolicy.php
|-- Providers/AppServiceProvider.php # named throttles
`-- Support/Pricing/OrderTotalCalculator.php

bootstrap/app.php                    # safe OrderConflictException rendering

database/
|-- factories/
|   |-- OfferDeliverySlotFactory.php
|   |-- OrderFactory.php
|   `-- OrderStatusTransitionFactory.php
`-- migrations/
    |-- *_add_is_operations_operator_to_users_table.php
    |-- *_create_offer_delivery_slots_table.php
    |-- *_create_orders_table.php
    `-- *_create_order_status_transitions_table.php

resources/js/
|-- components/app-sidebar.tsx
|-- pages/offers/show.tsx
|-- pages/operator/orders/index.tsx
|-- pages/operator/orders/show.tsx
|-- types/order.ts
`-- types/index.ts

routes/
|-- web.php
`-- orders.php

tests/
|-- Unit/
|   |-- Actions/Orders/              # one file per Order Action
|   `-- Support/Pricing/OrderTotalCalculatorTest.php
`-- Feature/
    |-- Orders/PublicOrderTest.php
    |-- Orders/OperatorOrderTest.php
    `-- ProductOffers/OperatorProductOfferTest.php  # Phase 1 file updated
```

**Structure Decision**: Use the existing Laravel/Inertia folders and extend the Phase 1
public offer page. One `orders` table holds common and channel-conditional fields because
the two channels share lifecycle, pricing, slot, and availability rules; separate detail
tables would add joins and Actions without adding MVP value. Wayfinder output under
`resources/js/actions` and `resources/js/routes` is generated and never hand-edited.

## Use-Case Mapping

| Use Case | Form Request | Controller | Action | Unit Test | Feature Test |
|----------|--------------|------------|--------|-----------|--------------|
| Save offer delivery slots | Existing Phase 1 `StoreProductOfferRequest` / `UpdateProductOfferRequest` | Existing `Operator/ProductOfferController` | Existing `CreateProductOfferDraftAction` / `UpdateProductOfferDraftAction` | Existing Phase 1 Action tests | `ProductOffers/OperatorProductOfferTest.php` |
| Review B2C/B2B order | `Public/ReviewOrderRequest` | `PublicOrderReviewController@store` | `ReviewOrderAction` | `ReviewOrderActionTest.php` | `Orders/PublicOrderTest.php` |
| Confirm B2C/B2B order | `Public/StoreOrderRequest` | `PublicOrderController@store` | `CreateOrderAction` | `CreateOrderActionTest.php` | `Orders/PublicOrderTest.php` |
| List/filter operator orders | `Operator/ListOrdersRequest` | `Operator/OrderController@index` | `ListOrdersAction` | `ListOrdersActionTest.php` | `Orders/OperatorOrderTest.php` |
| Inspect one operator order | none; route binding + policy | `Operator/OrderController@show` | `ShowOrderAction` | `ShowOrderActionTest.php` | `Orders/OperatorOrderTest.php` |
| Cancel confirmed order | `Operator/CancelOrderRequest` | `Operator/OrderCancellationController@store` | `CancelOrderAction` | `CancelOrderActionTest.php` | `Orders/OperatorOrderTest.php` |

## Security Design

| Boundary/Risk | Control | Verification |
|---------------|---------|--------------|
| Phase 1 is absent | Hard prerequisite; do not duplicate offer code | Quickstart preflight checks Phase 1 models/routes/tests |
| Guest submits price/status/eligibility | Requests allow only channel, quantity, slot, channel fields, and token; Actions source commercial fields server-side | Extra-field and tampered-price feature tests |
| Offer changes after review | Confirmation locks and reloads the offer/slot, then recomputes price and availability | Frozen/stale review Action tests and 409 contract test |
| Concurrent overbooking | Transaction + `lockForUpdate()` on offer + indexed reserved-quantity aggregate before insert | Boundary tests for exact capacity, one-over capacity, cancellation release, and two distinct attempts |
| Double submit/retry | Random token hashed with SHA-256; unique hash; same payload returns original confirmation, changed payload conflicts | Repeat and token-reuse tests |
| PII exposure at rest | Laravel encrypted casts on `TEXT`; `$hidden`; no query/filter on encrypted fields | Raw database ciphertext assertions and model round-trip tests |
| PII exposure in responses/logs | Explicit public/JSON arrays; no Eloquent serialization; safe exception codes only | Recursive missing-key assertions and log inspection tests |
| Operator privilege escalation | Non-fillable `is_operations_operator`, policy, `auth` + `verified` | guest 302, ordinary user 403, operator success tests |
| Enumeration/abuse | Random public IDs; no public order-read route; separate preview 30/min/IP and confirm 10/min/IP limits | 404/409 consistency and 429 tests |
| Invalid lifecycle/cancellation | `OrderStatus` transition allowlist; only confirmed -> cancelled in this phase; row lock | Action datasets for every disallowed state and repeat cancellation |

## Implementation Guardrails

The implementing agent MUST follow these rules literally:

1. Finish and verify Phase 1 before starting Phase 2. Stop if `ProductOffer`, its public
   route, or the Phase 1 Actions/tests do not exist.
2. Generate PHP classes, migrations, factories, and Pest tests with
   `php artisan make:* --no-interaction`; then edit only the generated files.
3. Do not add Composer/npm packages or a second offer/public-page implementation.
4. Implement files in this order: enums/calculator -> slots -> order models -> review ->
   confirmation -> operator reads -> cancellation -> React pages.
5. Write and run each Action unit test before adding its controller route.
6. Store money as integer centimes and quantity as integer hundredths. Do not use PHP or
   JavaScript floating-point math for totals or availability.
7. Do not accept unit price, total, status, service date, crop snapshot, or eligibility
   from the client. Derive all of them inside Actions.
8. Do not expose internal IDs or Eloquent models in public/order JSON. Return only the
   shapes in `contracts/web-routes-and-props.md`.
9. Encrypt all listed PII with model casts and `TEXT` columns. Never add encrypted fields
   to operator filters, logs, exception messages, or context.
10. Do not persist a review or reserve quantity during review. Only confirmation writes.
11. Keep confirmation and cancellation transactional, row-locked, and idempotent.
12. Do not implement customer cancellation, public order tracking, email/SMS, payment,
    consolidation, allocation, dispatch, delivery, bulk actions, or PII erasure jobs.
13. Use `useHttp` for review/confirmation and Wayfinder `.url()` values. Clear a review
    whenever any submitted field changes; never calculate totals in React.
14. Keep operator queries paginated and eager loaded. Never decrypt PII for the index;
    decrypt it only on the authorized detail page.
15. Run focused Phase 1 and Phase 2 tests, then Pint, PHPStan, frontend format/lint/types,
    and the production build before handoff.

## Complexity Tracking

No Constitution violations. No entries required.
