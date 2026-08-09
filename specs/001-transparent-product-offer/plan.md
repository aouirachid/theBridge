# Implementation Plan: Transparent Product Offer

**Branch**: `N/A (no branch hook configured)` | **Date**: 2026-08-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/001-transparent-product-offer/spec.md`

## Summary

Build one complete Laravel/Inertia slice in which a sourcing operator saves an offer
draft, records a benchmark, reviews server-calculated values, and publishes an immutable
offer. Public visitors receive only a privacy-safe allowlisted view. Benchmark refreshes
are reviewed and published separately from offer economics. Superseded publications are
public for 30 days, then staff-only.

Keep the implementation deliberately small: three domain models, integer centimes for
all MAD/kg values, one pure integer price calculator, one policy backed by a single user
flag, single-purpose Actions, thin controllers, Inertia pages using existing components,
and Pest unit/feature tests. Do not add packages, queues, repositories, events, APIs,
cleanup jobs, soft deletes, or speculative abstractions.

## Technical Context

**Language/Version**: PHP 8.4.8; TypeScript 5.9.3; React 19.2.8

**Primary Dependencies**: Laravel 13.24.0, Inertia Laravel 3.3.1,
`@inertiajs/react` 3.6.1, Wayfinder 0.1.21 / Vite plugin 0.1.7, Tailwind CSS 4.3.3,
Pest 5.0.4

**Storage**: Existing SQLite database for local/demo and in-memory SQLite for tests;
portable Laravel migrations compatible with MySQL/PostgreSQL. Existing array/database
cache supports named rate limiters. No object storage.

**Testing**: Pest direct Action unit tests and HTTP/Inertia feature tests. No browser
tests because the browser plugin is not installed and adding it would require approval.
Frontend verification uses existing lint, format, type, and production-build commands.

**Target Platform**: Server-rendered Laravel web application with an Inertia React
client, optimized for the local hackathon demonstration and ordinary desktop/mobile
browsers.

**Project Type**: Laravel/Inertia React web application

**Architecture**: Dedicated Form Request -> thin controller -> single-purpose Action ->
Eloquent models. Read workflows also use Actions so controllers do not orchestrate
queries. A small pure `OfferPriceCalculator` performs only deterministic integer math.

**Frontend**: Operator index and manage pages use the existing `AppLayout`, `<Form>`,
Wayfinder-generated controller actions/routes, existing UI primitives, semantic Tailwind
tokens, and server-returned errors. The public offer page has no authenticated app layout
and renders fresh, unavailable, and superseded states from explicit server props. No
client-side price calculation or optimistic publication.

**Security**: Add `users.is_sourcing_operator` because the project has authentication but
no staff authorization model. Enforce `ProductOfferPolicy` plus `auth`, `verified`, and
named throttles on every operator route. Public routes use a random offer `public_id`, an
allowlisted prop mapper, an IP throttle, and identical 404 behavior for private,
withdrawn, expired-history, and missing records. Creator/reviewer IDs, user models, full
request payloads, and private addresses never enter public props or logs.

**Performance Goals**: Under the hackathon dataset, 95% of public offer views complete in
under 500 ms and operator pages in under 1 second. Each show query loads one offer, at
most 14 cost components, one current comparison, and at most 30 recent historical
comparisons with eager loading and selected columns.

**Constraints**: Store money as signed/unsigned 64-bit integer centimes and percentage
hundredths as integers; never use PHP floats, JavaScript math, `bcmath`, or a new money
package. Store timestamps in UTC, compare exact rolling hours/days, and display in
`Africa/Casablanca`. Published offer rows, costs, and published comparison inputs/results
are never updated except for one-way `superseded_at` or `withdrawn_at` transitions.

**Scale/Scope**: Hackathon MVP: latest 50 offers on the operator index, maximum 10 custom
cost components plus four required components per offer, maximum 30 comparison-history
rows returned per page, and no public catalog/search, ordering, scraping, payments,
ledger, or scheduled deletion.

## Constitution Check

*GATE: Passed before research and passed again after design.*

- **Framework conventions — PASS**: Installed versions were confirmed through Laravel
  Boost, Composer, and npm. Existing models, Form Requests, Inertia pages, Wayfinder
  forms, UI components, and Pest tests were inspected and are reused.
- **Security boundary — PASS**: Sourcing authorization, validation, public allowlisting,
  private actor fields, throttles, safe 404/403/422/429 outcomes, UTC freshness, and
  no-payload logging are explicitly mapped.
- **Action-first design — PASS**: Every mutation and read workflow maps to one Action;
  controllers bind models, pass validated input, invoke an Action, and render/redirect.
- **Form Request boundary — PASS**: Each operator mutation has a dedicated Form Request;
  Actions receive only `validated()` data or route-bound authorized models.
- **Layered tests — PASS**: Each Action has direct unit coverage; HTTP wiring and Inertia
  prop privacy have feature coverage; lint/types/build cover frontend compilation.
- **Operational quality — PASS**: Publication/replacement/comparison transitions are
  transactional and row-locked, duplicate transitions are idempotent, query results are
  bounded, relationships are eager loaded, and there are no external calls.

## Project Structure

### Documentation (this feature)

```text
specs/001-transparent-product-offer/
|-- plan.md
|-- research.md
|-- data-model.md
|-- quickstart.md
|-- contracts/
|   `-- web-routes-and-props.md
`-- tasks.md                       # created by speckit-tasks, not this command
```

### Source Code (repository root)

```text
app/
|-- Actions/ProductOffers/
|   |-- ListProductOffersAction.php
|   |-- ShowProductOfferDraftAction.php
|   |-- CreateProductOfferDraftAction.php
|   |-- UpdateProductOfferDraftAction.php
|   |-- CreateReplacementOfferDraftAction.php
|   |-- PublishProductOfferAction.php
|   |-- WithdrawProductOfferAction.php
|   |-- RecordBenchmarkComparisonAction.php
|   |-- PublishBenchmarkComparisonAction.php
|   `-- ShowPublicProductOfferAction.php
|-- Http/Controllers/
|   |-- Operator/ProductOfferController.php
|   |-- Operator/ProductOfferPublicationController.php
|   |-- Operator/ProductOfferReplacementController.php
|   |-- Operator/ProductOfferWithdrawalController.php
|   |-- Operator/BenchmarkComparisonController.php
|   |-- Operator/BenchmarkComparisonPublicationController.php
|   `-- PublicProductOfferController.php
|-- Http/Requests/Operator/
|   |-- StoreProductOfferRequest.php
|   |-- UpdateProductOfferRequest.php
|   |-- PublishProductOfferRequest.php
|   |-- CreateProductOfferReplacementRequest.php
|   |-- WithdrawProductOfferRequest.php
|   |-- StoreBenchmarkComparisonRequest.php
|   `-- PublishBenchmarkComparisonRequest.php
|-- Models/
|   |-- ProductOffer.php
|   |-- OfferCostComponent.php
|   `-- BenchmarkComparison.php
|-- Policies/ProductOfferPolicy.php
`-- Support/Pricing/OfferPriceCalculator.php

database/
|-- factories/
|   |-- ProductOfferFactory.php
|   |-- OfferCostComponentFactory.php
|   `-- BenchmarkComparisonFactory.php
`-- migrations/
    |-- *_add_is_sourcing_operator_to_users_table.php
    |-- *_create_product_offers_table.php
    |-- *_create_offer_cost_components_table.php
    `-- *_create_benchmark_comparisons_table.php

resources/js/
|-- components/app-sidebar.tsx
|-- pages/operator/offers/index.tsx
|-- pages/operator/offers/manage.tsx
|-- pages/offers/show.tsx
|-- types/product-offer.ts
|-- types/index.ts
`-- app.tsx

routes/
|-- web.php
`-- offers.php

tests/
|-- Unit/
|   |-- Actions/ProductOffers/                     # one test file per Action
|   `-- Support/Pricing/OfferPriceCalculatorTest.php
`-- Feature/ProductOffers/
    |-- OperatorProductOfferTest.php
    |-- BenchmarkComparisonTest.php
    `-- PublicProductOfferTest.php
```

**Structure Decision**: Follow existing Laravel/Inertia folders. Add only the
feature-specific subfolders above. Wayfinder output in `resources/js/actions` and
`resources/js/routes` is generated; never hand-edit it. Factories are required for test
setup. No seeder is required because the quickstart creates the demo through the UI.

## Use-Case Mapping

| Use Case | Form Request | Controller | Action | Unit Test | Feature Test |
|----------|--------------|------------|--------|-----------|--------------|
| List operator offers | N/A (read) | `Operator/ProductOfferController@index` | `ListProductOffersAction` | `ListProductOffersActionTest.php` | `OperatorProductOfferTest.php` |
| Open create/edit review | N/A (read) | `Operator/ProductOfferController@create/edit` | `ShowProductOfferDraftAction` | `ShowProductOfferDraftActionTest.php` | `OperatorProductOfferTest.php` |
| Create draft | `StoreProductOfferRequest` | `Operator/ProductOfferController@store` | `CreateProductOfferDraftAction` | `CreateProductOfferDraftActionTest.php` | `OperatorProductOfferTest.php` |
| Update draft | `UpdateProductOfferRequest` | `Operator/ProductOfferController@update` | `UpdateProductOfferDraftAction` | `UpdateProductOfferDraftActionTest.php` | `OperatorProductOfferTest.php` |
| Clone replacement draft | `CreateProductOfferReplacementRequest` | `Operator/ProductOfferReplacementController@store` | `CreateReplacementOfferDraftAction` | `CreateReplacementOfferDraftActionTest.php` | `OperatorProductOfferTest.php` |
| Publish offer | `PublishProductOfferRequest` | `Operator/ProductOfferPublicationController@store` | `PublishProductOfferAction` | `PublishProductOfferActionTest.php` | `OperatorProductOfferTest.php` |
| Withdraw offer | `WithdrawProductOfferRequest` | `Operator/ProductOfferWithdrawalController@store` | `WithdrawProductOfferAction` | `WithdrawProductOfferActionTest.php` | `OperatorProductOfferTest.php` |
| Record benchmark | `StoreBenchmarkComparisonRequest` | `Operator/BenchmarkComparisonController@store` | `RecordBenchmarkComparisonAction` | `RecordBenchmarkComparisonActionTest.php` | `BenchmarkComparisonTest.php` |
| Publish benchmark update | `PublishBenchmarkComparisonRequest` | `Operator/BenchmarkComparisonPublicationController@store` | `PublishBenchmarkComparisonAction` | `PublishBenchmarkComparisonActionTest.php` | `BenchmarkComparisonTest.php` |
| View public offer/history | N/A (read) | `PublicProductOfferController@show` | `ShowPublicProductOfferAction` | `ShowPublicProductOfferActionTest.php` | `PublicProductOfferTest.php` |

## Security Design

| Boundary/Risk | Control | Verification |
|---------------|---------|--------------|
| Staff role does not exist | Add non-fillable boolean `users.is_sourcing_operator`; central `ProductOfferPolicy` | Factory operator state; guest 302/auth, normal user 403, operator success tests |
| Unvalidated nested costs | Dedicated Requests; four fixed standard amount fields; max 10 custom rows; normalized unique names | Validation datasets and Action invariant tests |
| Float/rounding ambiguity | Parse decimal strings to integer centimes; integer half-up calculator; persist outputs | Calculator datasets including positive, zero, negative, and tie cases |
| Partial or duplicate publication | Transaction, row locks, one-way timestamp transitions, unique replacement/supersession links | Repeat and concurrency-conflict behavior tests |
| Stale comparison claim | Current public comparison only when `observed_at >= now()-24h`; otherwise unavailable state | Frozen-time tests at 23:59:59, 24:00:00, and later |
| Historical access window | Public query allows superseded publication through exactly 30 days, then returns no history/staff-only | Frozen-time boundary tests |
| Farmer/staff PII exposure | Random offer `public_id`; explicit array props; never serialize models/users; generalized origin only | Inertia `missing()` assertions for IDs/names/email and source validation tests |
| Enumeration and request floods | `public-offers` 120/min/IP; `operator-offers` 60/min/user+IP | 429 feature tests and identical public 404 tests |
| Sensitive diagnostics | Do not log request arrays or source content; rely on safe validation/authorization exceptions | Review plus feature tests ensuring safe responses |

## Implementation Guardrails

The implementing agent MUST follow these rules literally:

1. Generate Laravel classes/migrations/tests with `php artisan make:* --no-interaction`,
   then edit only the generated files.
2. Do not add or update Composer/npm dependencies.
3. Do not store or calculate money with floats. Use integer centimes and the single
   calculator described in `data-model.md`.
4. Do not calculate prices or percentages in React. Render server-provided strings and
   integer-backed values only.
5. Do not pass Eloquent models directly to public Inertia props. The public Action returns
   the exact allowlisted shape in `contracts/web-routes-and-props.md`.
6. Do not update offer economics, costs, or published benchmark inputs after publication.
   Corrections use replacement drafts; benchmark refreshes use new comparison rows.
7. Do not delete domain rows. Keeping records indefinitely satisfies the 30-day minimum;
   visibility is computed from timestamps at read time, so no cleanup job is needed.
8. Do not create repositories, DTO packages, services, jobs, events, observers, API
   resources, enums, or generic base Actions. Use the concrete files listed above.
9. Keep controller methods as binding/request -> Action -> Inertia/redirect only.
10. Finish each Action with its unit test before wiring its HTTP endpoint. Run the three
    focused feature files, then Pint, PHPStan, frontend format/lint/types/build.

## Complexity Tracking

No Constitution violations. No entries required.
