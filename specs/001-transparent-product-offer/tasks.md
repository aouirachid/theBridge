# Tasks: Transparent Product Offer

**Input**: Design documents from `specs/001-transparent-product-offer/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`,
`contracts/web-routes-and-props.md`, and `quickstart.md`

**Tests**: Mandatory. Write each direct Action unit test and HTTP feature test before
implementing the corresponding behavior. Confirm the new test fails for the expected
missing behavior, then implement until it passes.

**Organization**: Work in numeric order. Finish and verify one task before starting the
next unless it is explicitly marked `[P]`. Each user-story phase ends in a runnable,
independently testable checkpoint.

## Junior-Agent Execution Rules

1. Read the exact referenced design section before editing a task's file.
2. Do not change files outside the named task paths unless a generated import file is
   explicitly mentioned.
3. Generate Laravel files with the stated `php artisan make:* --no-interaction` command;
   never hand-create framework files when Artisan can create them.
4. Do not add dependencies, APIs, queues, repositories, events, observers, soft deletes,
   client-side price math, or abstractions not listed in `plan.md`.
5. Never use PHP/JavaScript floats for money. Follow only the integer-centime algorithm in
   `data-model.md`.
6. Actions never accept HTTP Request objects or return HTTP responses. Controllers contain
   only request/model mapping, one Action call, and render/redirect code.
7. Never hand-edit generated Wayfinder files under `resources/js/actions/` or
   `resources/js/routes/`.
8. If a test or command fails, report its exact output and fix it before checking the task.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: May run in parallel only after all earlier non-parallel dependencies pass.
- **[US1]**, **[US2]**, **[US3]**: Maps directly to the prioritized specification story.
- Every task names the exact file or generated migration suffix it may change.

---

## Phase 1: Setup and Orientation

**Purpose**: Give the implementing agent the exact project rules and version context
before it edits code.

- [ ] T001 Read `specs/001-transparent-product-offer/spec.md`, `specs/001-transparent-product-offer/plan.md`, `specs/001-transparent-product-offer/data-model.md`, `specs/001-transparent-product-offer/contracts/web-routes-and-props.md`, and `specs/001-transparent-product-offer/quickstart.md`; write no code and note that the plan's Implementation Guardrails are mandatory
- [ ] T002 [P] Read `.ai/rules/actions.md`, `.ai/rules/controllers-requests.md`, and `.ai/rules/unit-feature.md`; confirm every planned Action, controller/Request, and Pest layer follows these rules before editing any scoped file
- [ ] T003 [P] Confirm installed versions and existing conventions from `composer.lock`, `package-lock.json`, `app/Models/User.php`, `app/Http/Controllers/Settings/ProfileController.php`, `resources/js/pages/settings/profile.tsx`, `vite.config.ts`, and `tests/Feature/Settings/ProfileUpdateTest.php`; do not change dependencies

**Checkpoint**: The agent can state the exact architecture, money representation, test
layers, and prohibited additions before beginning schema work.

---

## Phase 2: Foundational Schema, Models, and Security

**Purpose**: Create the shared persistence and authorization foundation that blocks all
three user stories.

**CRITICAL**: Do not start a user-story phase until T014 passes.

- [ ] T004 Run `php artisan make:migration add_is_sourcing_operator_to_users_table --table=users --no-interaction` and implement the boolean default-false indexed column with a reversible rollback in the generated `database/migrations/*_add_is_sourcing_operator_to_users_table.php`
- [ ] T005 Run `php artisan make:migration create_product_offers_table --create=product_offers --no-interaction` and implement the exact columns, self-replacement uniqueness, foreign keys, and indexes from `data-model.md` in the generated `database/migrations/*_create_product_offers_table.php`
- [ ] T006 Run `php artisan make:migration create_offer_cost_components_table --create=offer_cost_components --no-interaction` and implement the exact cost columns, foreign key, standard-code uniqueness, normalized-name uniqueness, and indexes in the generated `database/migrations/*_create_offer_cost_components_table.php`
- [ ] T007 Run `php artisan make:migration create_benchmark_comparisons_table --create=benchmark_comparisons --no-interaction` and implement the exact observation/publication columns, signed saving fields, self-supersession uniqueness, foreign keys, and indexes in the generated `database/migrations/*_create_benchmark_comparisons_table.php`
- [ ] T008 Update `app/Models/User.php` with a boolean cast but no fillable role field, and update `database/factories/UserFactory.php` with an `operator()` state that sets `is_sourcing_operator` to true
- [ ] T009 [P] Run `php artisan make:model ProductOffer --factory --no-interaction` and implement only the fillable attributes, casts, random `public_id`, relationships, ordered cost/comparison relations, and lifecycle helpers from `data-model.md` in `app/Models/ProductOffer.php` and `database/factories/ProductOfferFactory.php`
- [ ] T010 [P] Run `php artisan make:model OfferCostComponent --factory --no-interaction` and implement the four standard-code/label/position constants, fillable attributes, casts, and offer relationship in `app/Models/OfferCostComponent.php` and `database/factories/OfferCostComponentFactory.php`
- [ ] T011 [P] Run `php artisan make:model BenchmarkComparison --factory --no-interaction` and implement fillable attributes, casts, offer/actor/self-supersession relationships, and lifecycle helpers in `app/Models/BenchmarkComparison.php` and `database/factories/BenchmarkComparisonFactory.php`
- [ ] T012 Run `php artisan make:policy ProductOfferPolicy --model=ProductOffer --no-interaction` and implement sourcing-operator-only abilities for view-any, view staff records, create, update draft, publish, replace, record/publish benchmark, and withdraw in `app/Policies/ProductOfferPolicy.php`
- [ ] T013 Add named `public-offers` (120 requests/minute/IP) and `operator-offers` (60 requests/minute/user+IP) rate limiters without logging request payloads in `app/Providers/AppServiceProvider.php`
- [ ] T014 Run the non-destructive `php artisan migrate --no-interaction` and `php artisan migrate:status`, inspect each new migration's `down()` method against its `up()` method, and fix only the four new generated migration files and three new model/factory pairs; do not run `migrate:fresh` or rollback against any non-disposable database

**Checkpoint**: The database migrates forward/backward, factories create valid related
records, the role cannot be mass-assigned through profile input, and the policy/rate
limiters are available.

---

## Phase 3: User Story 1 — Publish a Reproducible Offer (Priority: P1) MVP

**Goal**: An authorized sourcing operator can create/edit a draft, record a fresh
benchmark, review exact server-calculated results, and publish one immutable offer.

**Independent Test**: Enter the tomato scenario from `quickstart.md`; publication must
store `5.50 MAD/kg`, `2.50 MAD/kg`, `31.25%`, and `50.91%`, reject invalid/unauthorized
input, and remain idempotent when repeated.

### Tests for User Story 1

> Create these tests first. Each direct Action test that uses Eloquent must declare
> `uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)`.

- [ ] T015 [P] [US1] Run `php artisan make:test --pest --unit Support/Pricing/OfferPriceCalculatorTest --no-interaction` and add failing datasets for decimal-string parsing, formatting, final sum, positive/zero/negative savings, half-up ties, zero denominators, and the tomato acceptance values in `tests/Unit/Support/Pricing/OfferPriceCalculatorTest.php`
- [ ] T016 [P] [US1] Run `php artisan make:test --pest --unit Actions/ProductOffers/ListProductOffersActionTest --no-interaction` and add failing direct-Action tests for latest-first ordering, the 50-row bound, status labels, and no actor details in `tests/Unit/Actions/ProductOffers/ListProductOffersActionTest.php`
- [ ] T017 [P] [US1] Run `php artisan make:test --pest --unit Actions/ProductOffers/ShowProductOfferDraftActionTest --no-interaction` and add failing direct-Action tests for exact saved inputs, ordered costs, calculated preview, newest 30 benchmark rows, and published read-only state in `tests/Unit/Actions/ProductOffers/ShowProductOfferDraftActionTest.php`
- [ ] T018 [P] [US1] Run `php artisan make:test --pest --unit Actions/ProductOffers/CreateProductOfferDraftActionTest --no-interaction` and add failing direct-Action tests for centime conversion, four standard costs including zero, unique normalized custom names, maximum 10 custom rows, UUID generation, and atomic persistence in `tests/Unit/Actions/ProductOffers/CreateProductOfferDraftActionTest.php`
- [ ] T019 [P] [US1] Run `php artisan make:test --pest --unit Actions/ProductOffers/UpdateProductOfferDraftActionTest --no-interaction` and add failing direct-Action tests for atomic draft replacement of costs, recomputed preview values, and rejection of published-offer edits in `tests/Unit/Actions/ProductOffers/UpdateProductOfferDraftActionTest.php`
- [ ] T020 [P] [US1] Run `php artisan make:test --pest --unit Actions/ProductOffers/RecordBenchmarkComparisonActionTest --no-interaction` and add failing direct-Action tests proving recording stores attribution/demo/time but never changes public comparison state in `tests/Unit/Actions/ProductOffers/RecordBenchmarkComparisonActionTest.php`
- [ ] T021 [P] [US1] Run `php artisan make:test --pest --unit Actions/ProductOffers/PublishProductOfferActionTest --no-interaction` and add failing direct-Action tests for the tomato calculation, fresh/future/stale benchmark rejection, four-cost invariant, transaction rollback, row locking outcome, immutable snapshot fields, and repeated-call idempotency in `tests/Unit/Actions/ProductOffers/PublishProductOfferActionTest.php`
- [ ] T022 [US1] Run `php artisan make:test --pest ProductOffers/OperatorProductOfferTest --no-interaction` and add failing HTTP/Inertia tests for guest redirect, verified normal-user 403, operator success, nested validation errors, create/edit preview props, record benchmark, publish redirect/toast/persistence, duplicate publish safety, and operator throttle in `tests/Feature/ProductOffers/OperatorProductOfferTest.php`

### Implementation for User Story 1

- [ ] T023 [US1] Implement the only decimal-to-centime parser, money formatter, signed integer half-up divider, final-price calculation, farmer-share calculation, and saving calculation exactly as documented in `app/Support/Pricing/OfferPriceCalculator.php` until T015 passes; do not use floats or external math helpers
- [ ] T024 [P] [US1] Implement latest-50 eager-loaded operator summaries with explicit selected fields in `app/Actions/ProductOffers/ListProductOffersAction.php` until T016 passes
- [ ] T025 [P] [US1] Implement create/edit review data loading, ordered costs, server preview values, newest-30 benchmark rows, and capability booleans in `app/Actions/ProductOffers/ShowProductOfferDraftAction.php` until T017 passes
- [ ] T026 [US1] Implement transactional draft creation, input centime conversion, fixed standard labels/positions, normalized custom-name checks, and cost persistence in `app/Actions/ProductOffers/CreateProductOfferDraftAction.php` until T018 passes
- [ ] T027 [US1] Implement draft-only transactional updates that replace the complete cost collection and recalculate stored preview values in `app/Actions/ProductOffers/UpdateProductOfferDraftAction.php` until T019 passes
- [ ] T028 [P] [US1] Implement staff-only observation recording with no publication side effect in `app/Actions/ProductOffers/RecordBenchmarkComparisonAction.php` until T020 passes
- [ ] T029 [US1] Implement transactional one-way offer publication with locked offer/comparison rows, exact derived snapshot values, fresh-benchmark boundary, initial comparison publication, and repeated-call idempotency in `app/Actions/ProductOffers/PublishProductOfferAction.php` until T021 passes
- [ ] T030 [P] [US1] Run `php artisan make:request Operator/StoreProductOfferRequest --no-interaction` and implement authorization, whitespace/Casablanca-time normalization, exact offer/standard/custom-cost validation, maximum custom rows, and normalized-name collision errors in `app/Http/Requests/Operator/StoreProductOfferRequest.php`
- [ ] T031 [P] [US1] Run `php artisan make:request Operator/UpdateProductOfferRequest --no-interaction` and implement draft-update authorization plus the same explicit input rules without accepting publication fields in `app/Http/Requests/Operator/UpdateProductOfferRequest.php`
- [ ] T032 [P] [US1] Run `php artisan make:request Operator/StoreBenchmarkComparisonRequest --no-interaction` and implement offer authorization, positive two-decimal price, source-type conditional validation, public-safe length bounds, non-future time, and required demo boolean in `app/Http/Requests/Operator/StoreBenchmarkComparisonRequest.php`
- [ ] T033 [P] [US1] Run `php artisan make:request Operator/PublishProductOfferRequest --no-interaction` and validate/authorize only a recorded fresh benchmark ID belonging to the route-bound draft in `app/Http/Requests/Operator/PublishProductOfferRequest.php`
- [ ] T034 [P] [US1] Run `php artisan make:controller Operator/ProductOfferController --no-interaction` and implement only index/create/store/edit/update Action calls plus Inertia renders/redirects/toasts in `app/Http/Controllers/Operator/ProductOfferController.php`
- [ ] T035 [P] [US1] Run `php artisan make:controller Operator/BenchmarkComparisonController --no-interaction` and implement only `store()` with the validated record-Action call and edit-page redirect/toast in `app/Http/Controllers/Operator/BenchmarkComparisonController.php`
- [ ] T036 [P] [US1] Run `php artisan make:controller Operator/ProductOfferPublicationController --no-interaction` and implement only `store()` with the validated publish-Action call and edit-page redirect/toast in `app/Http/Controllers/Operator/ProductOfferPublicationController.php`
- [ ] T037 [US1] Create the authenticated/verified/throttled operator index, create, store, edit, update, benchmark-store, and offer-publication routes with the exact names in `specs/001-transparent-product-offer/contracts/web-routes-and-props.md` inside `routes/offers.php`, then require `routes/offers.php` once from `routes/web.php`
- [ ] T038 [US1] Run `php artisan wayfinder:generate --with-form --no-interaction` from the completed routes/controllers without hand edits and verify the expected generated functions exist in `resources/js/actions/App/Http/Controllers/Operator/` and `resources/js/routes/operator/offers/`
- [ ] T039 [P] [US1] Add only the operator summary/editor, cost-row, benchmark-row, capability, and page-prop types from the contract to `resources/js/types/product-offer.ts`, then export them from `resources/js/types/index.ts`
- [ ] T040 [P] [US1] Implement the latest-50 offer table/cards, status badges, create/edit links, and empty state using existing components and Wayfinder in `resources/js/pages/operator/offers/index.tsx`
- [ ] T041 [US1] Implement create/edit modes, fixed standard-cost inputs, maximum-10 removable custom rows, nested field errors, saved server preview, benchmark recording, benchmark selection, and publish form using existing components plus Wayfinder in `resources/js/pages/operator/offers/manage.tsx`; render published inputs read-only and perform no price math
- [ ] T042 [US1] Add one Wayfinder-generated “Product offers” navigation item without hardcoded URLs in `resources/js/components/app-sidebar.tsx`
- [ ] T043 [US1] Run `php artisan test --compact tests/Unit/Support/Pricing/OfferPriceCalculatorTest.php`, `php artisan test --compact tests/Unit/Actions/ProductOffers`, `php artisan test --compact tests/Feature/ProductOffers/OperatorProductOfferTest.php`, and `npm.cmd run types:check`; fix only US1 files until all pass

**Checkpoint**: User Story 1 is the suggested MVP. An operator can complete and verify
the exact tomato publication without manual database edits in the offer workflow.

---

## Phase 4: User Story 2 — Inspect the Public Price Breakdown (Priority: P2)

**Goal**: A guest can open a privacy-safe published offer, reproduce its current price
and fresh comparison, and see a clear unavailable state after the 24-hour boundary.

**Independent Test**: Signed out, open the tomato public ID and reproduce every value
from allowlisted props; freeze time immediately after 24 hours and confirm the offer stays
visible while benchmark/source/savings disappear.

### Tests for User Story 2

- [ ] T044 [P] [US2] Run `php artisan make:test --pest --unit Actions/ProductOffers/ShowPublicProductOfferActionTest --no-interaction` and add failing direct-Action tests for allowlisted props, exact tomato values, fresh at exactly 24 hours, unavailable immediately afterward, negative-saving labeling data, bounded eager-loaded history, and identical not-found behavior in `tests/Unit/Actions/ProductOffers/ShowPublicProductOfferActionTest.php`
- [ ] T045 [P] [US2] Run `php artisan make:test --pest --unit Actions/ProductOffers/WithdrawProductOfferActionTest --no-interaction` and add failing direct-Action tests for one-way withdrawal, repeated-call idempotency, and immediate public invisibility in `tests/Unit/Actions/ProductOffers/WithdrawProductOfferActionTest.php`
- [ ] T046 [US2] Run `php artisan make:test --pest ProductOffers/PublicProductOfferTest --no-interaction` and add failing HTTP/Inertia tests for random public-ID binding, exact allowlisted props, missing actor/internal/private fields, demo label data, source/time/units, positive/zero/negative comparisons, fresh-unavailable state, draft/withdrawn/missing 404 equality, and public throttle in `tests/Feature/ProductOffers/PublicProductOfferTest.php`
- [ ] T047 [US2] Extend `tests/Feature/ProductOffers/OperatorProductOfferTest.php` with failing HTTP tests for operator-only withdrawal, normal-user forbidden response, toast/redirect, repeat safety, and post-withdraw public 404

### Implementation for User Story 2

- [ ] T048 [US2] Implement the public visibility query, one captured UTC clock value, explicit allowlisted array mapping, formatted values, fresh comparison selection, unavailable flag, and maximum-30 history bound in `app/Actions/ProductOffers/ShowPublicProductOfferAction.php` until T044 passes; never return Eloquent models or actor fields
- [ ] T049 [US2] Implement transactional one-way idempotent withdrawal in `app/Actions/ProductOffers/WithdrawProductOfferAction.php` until T045 passes
- [ ] T050 [P] [US2] Run `php artisan make:request Operator/WithdrawProductOfferRequest --no-interaction` and authorize only an operator allowed to withdraw the route-bound published offer while accepting no editable fields in `app/Http/Requests/Operator/WithdrawProductOfferRequest.php`
- [ ] T051 [P] [US2] Run `php artisan make:controller PublicProductOfferController --no-interaction` and implement only `show()` with the public-Action call plus `offers/show` Inertia render/not-found outcome in `app/Http/Controllers/PublicProductOfferController.php`
- [ ] T052 [P] [US2] Run `php artisan make:controller Operator/ProductOfferWithdrawalController --no-interaction` and implement only `store()` with the authorized withdrawal-Action call and index redirect/toast in `app/Http/Controllers/Operator/ProductOfferWithdrawalController.php`
- [ ] T053 [US2] Add the throttled public `{productOffer:public_id}` show route and operator withdrawal route with the exact contract names to `routes/offers.php`, run `php artisan wayfinder:generate --with-form --no-interaction`, and do not hand-edit generated files under `resources/js/actions/` or `resources/js/routes/`
- [ ] T054 [P] [US2] Add the exact `PublicMoney`, `PublicCost`, `PublicComparison`, and public-show prop types from the contract to `resources/js/types/product-offer.ts`
- [ ] T055 [US2] Implement the responsive semantic public cost breakdown, units, farmer share, demo/source/time labels, positive/zero/negative comparison wording, fresh-benchmark-unavailable message, and privacy-safe page metadata in `resources/js/pages/offers/show.tsx`; use existing UI/Tailwind conventions and no client calculation
- [ ] T056 [US2] Update the global Inertia layout selection so `offers/` pages do not receive the authenticated app shell while all existing page mappings stay unchanged in `resources/js/app.tsx`
- [ ] T057 [US2] Run `php artisan test --compact tests/Unit/Actions/ProductOffers/ShowPublicProductOfferActionTest.php`, `php artisan test --compact tests/Unit/Actions/ProductOffers/WithdrawProductOfferActionTest.php`, `php artisan test --compact tests/Feature/ProductOffers/PublicProductOfferTest.php`, `php artisan test --compact tests/Feature/ProductOffers/OperatorProductOfferTest.php`, and `npm.cmd run types:check`; fix only US2 files until all pass

**Checkpoint**: User Stories 1 and 2 form a demonstrable publication-to-public-transparency
slice with privacy and stale-data behavior proven.

---

## Phase 5: User Story 3 — Correct Without Rewriting History (Priority: P3)

**Goal**: An operator can clone and publish a corrected replacement or publish a reviewed
benchmark refresh while prior snapshots remain reproducible and follow the 30-day public
history window.

**Independent Test**: Publish a replacement and a benchmark refresh; prove the prior
offer economics never change, prior records are marked superseded and public through
exactly 30 days, become staff-only immediately afterward, and duplicate requests create
nothing extra.

### Tests for User Story 3

- [ ] T058 [P] [US3] Run `php artisan make:test --pest --unit Actions/ProductOffers/CreateReplacementOfferDraftActionTest --no-interaction` and add failing direct-Action tests for cloning immutable offer inputs/costs into one editable draft, unique predecessor link, private actor assignment, published-source requirement, and duplicate replacement prevention in `tests/Unit/Actions/ProductOffers/CreateReplacementOfferDraftActionTest.php`
- [ ] T059 [P] [US3] Run `php artisan make:test --pest --unit Actions/ProductOffers/PublishBenchmarkComparisonActionTest --no-interaction` and add failing direct-Action tests for explicit review, unchanged offer economics, fresh boundary, exact signed savings, locked supersession, immutable prior comparison, and repeated-call idempotency in `tests/Unit/Actions/ProductOffers/PublishBenchmarkComparisonActionTest.php`
- [ ] T060 [US3] Extend `tests/Feature/ProductOffers/OperatorProductOfferTest.php` with failing HTTP tests for operator-only replacement cloning, editable clone values, corrected publication superseding the predecessor, predecessor/replacement links, and duplicate prevention
- [ ] T061 [US3] Run `php artisan make:test --pest ProductOffers/BenchmarkComparisonTest --no-interaction` and add failing HTTP/Inertia tests for operator review/publish, normal-user 403, nested route scoping, stale/future rejection, unchanged offer price, superseded comparison history, idempotency, and operator throttle in `tests/Feature/ProductOffers/BenchmarkComparisonTest.php`
- [ ] T062 [US3] Extend `tests/Feature/ProductOffers/PublicProductOfferTest.php` with failing frozen-time tests proving superseded offer/comparison labels and links, public visibility at exactly 30 days, public 404/history removal immediately afterward, and continued authorized staff access

### Implementation for User Story 3

- [ ] T063 [US3] Implement transactional cloning of one published offer and its costs into one linked editable draft without copying publication/supersession state in `app/Actions/ProductOffers/CreateReplacementOfferDraftAction.php` until T058 passes
- [ ] T064 [US3] Implement transactional reviewed comparison publication with locked offer/candidate/current rows, exact calculation, prior-comparison supersession, unchanged offer fields, and repeated-call idempotency in `app/Actions/ProductOffers/PublishBenchmarkComparisonAction.php` until T059 passes
- [ ] T065 [P] [US3] Run `php artisan make:request Operator/CreateProductOfferReplacementRequest --no-interaction` and authorize only replacement of a published, non-withdrawn offer while accepting no editable body fields in `app/Http/Requests/Operator/CreateProductOfferReplacementRequest.php`
- [ ] T066 [P] [US3] Run `php artisan make:request Operator/PublishBenchmarkComparisonRequest --no-interaction` and authorize/validate only the route-bound recorded comparison scoped to the unchanged published offer in `app/Http/Requests/Operator/PublishBenchmarkComparisonRequest.php`
- [ ] T067 [P] [US3] Run `php artisan make:controller Operator/ProductOfferReplacementController --no-interaction` and implement only `store()` with the replacement-Action call and redirect/toast to the new draft editor in `app/Http/Controllers/Operator/ProductOfferReplacementController.php`
- [ ] T068 [P] [US3] Run `php artisan make:controller Operator/BenchmarkComparisonPublicationController --no-interaction` and implement only `store()` with the reviewed publish-Action call and offer-edit redirect/toast in `app/Http/Controllers/Operator/BenchmarkComparisonPublicationController.php`
- [ ] T069 [US3] Add the replacement-store and nested benchmark-publication routes with scoped binding and exact contract names to `routes/offers.php`, run `php artisan wayfinder:generate --with-form --no-interaction`, and do not hand-edit generated files under `resources/js/actions/` or `resources/js/routes/`
- [ ] T070 [US3] Add replacement, comparison-review/publish, superseded status, and public-link controls using Wayfinder and server capability props in `resources/js/pages/operator/offers/manage.tsx`; do not make publication optimistic
- [ ] T071 [US3] Add predecessor/replacement links and maximum-30 superseded comparison history with prominent historical labels to `resources/js/pages/offers/show.tsx`; never present a history row as the current 24-hour claim
- [ ] T072 [US3] Run `php artisan test --compact tests/Unit/Actions/ProductOffers`, `php artisan test --compact tests/Feature/ProductOffers`, and `npm.cmd run types:check`; fix only US3 files until every story remains green

**Checkpoint**: All three user stories are independently tested, prior claims cannot be
silently rewritten, and the clarified 24-hour/30-day behavior is complete.

---

## Phase 6: Cross-Cutting Review and Release Verification

**Purpose**: Verify security, architecture, formatting, compilation, and the complete demo
without expanding feature scope.

- [ ] T073 Review the public allowlist and remove any accidental model/actor serialization from `app/Actions/ProductOffers/ShowPublicProductOfferAction.php`, `app/Http/Controllers/PublicProductOfferController.php`, `resources/js/pages/offers/show.tsx`, and `tests/Feature/ProductOffers/PublicProductOfferTest.php`; require explicit `missing()` assertions for every forbidden field in the contract
- [ ] T074 Review transaction, lock, immutability, and idempotency paths in `app/Actions/ProductOffers/PublishProductOfferAction.php`, `app/Actions/ProductOffers/PublishBenchmarkComparisonAction.php`, `app/Actions/ProductOffers/CreateReplacementOfferDraftAction.php`, and their same-named files under `tests/Unit/Actions/ProductOffers/`; add only missing edge assertions
- [ ] T075 Review `app/Http/Controllers/Operator/ProductOfferController.php`, all invokable operator controllers in `app/Http/Controllers/Operator/`, every file in `app/Http/Requests/Operator/`, and every file in `app/Actions/ProductOffers/` against `.ai/rules/actions.md` and `.ai/rules/controllers-requests.md`; move any business logic out of controllers and ensure Actions receive no Request/Response objects
- [ ] T076 Execute every scenario in `specs/001-transparent-product-offer/quickstart.md`, report the exact command/error to the user if an environment problem prevents validation, and do not claim completion until the tomato, stale benchmark, replacement/history, privacy, and abuse cases succeed
- [ ] T077 Run `php artisan test --compact tests/Unit/Support/Pricing/OfferPriceCalculatorTest.php`, `php artisan test --compact tests/Unit/Actions/ProductOffers`, `php artisan test --compact tests/Feature/ProductOffers`, and finally `php artisan test --compact`; fix failing feature files before proceeding
- [ ] T078 Run `vendor/bin/pint --dirty --format agent` on all modified PHP files, then rerun the focused ProductOffers unit and feature suites to ensure formatting introduced no failure
- [ ] T079 Run `composer run types:check` and fix only type issues caused by the new models, Actions, Requests, controllers, policy, factories, or tests
- [ ] T080 Run `npm.cmd run format:check`, `npm.cmd run lint:check`, `npm.cmd run types:check`, and `npm.cmd run build`; fix only `resources/js/app.tsx`, `resources/js/components/app-sidebar.tsx`, `resources/js/pages/operator/offers/`, `resources/js/pages/offers/show.tsx`, and `resources/js/types/product-offer.ts`
- [ ] T081 Run `composer audit` and `npm.cmd audit --audit-level=high`, then re-read `specs/001-transparent-product-offer/spec.md`, `specs/001-transparent-product-offer/plan.md`, and `.specify/memory/constitution.md`; report any vulnerability, skipped check, or unmet requirement and leave the feature incomplete until resolved or explicitly approved

**Final Checkpoint**: All relevant tests and checks pass, the quickstart succeeds, no new
dependency or prohibited architecture was introduced, and every Constitution gate passes.

---

## Dependencies and Execution Order

### Phase dependencies

```text
Phase 1 Setup
    -> Phase 2 Foundation
        -> Phase 3 US1 Publish (MVP)
            -> Phase 4 US2 Public transparency
                -> Phase 5 US3 Corrections/history
                    -> Phase 6 Release verification
```

- Phase 1 has no dependency.
- Phase 2 depends on Phase 1 and blocks every story.
- US1 depends on Phase 2 and creates the offer/publication behavior used by later stories.
- US2 depends on US1 because a public view requires a published offer.
- US3 depends on US1 for immutable publications and US2 for public historical display.
- Phase 6 depends on every selected story.

### Within each user story

1. Create direct Action tests and HTTP tests; confirm expected failures.
2. Implement the pure helper/Actions until their direct tests pass.
3. Implement dedicated Form Requests.
4. Implement thin controllers and named routes.
5. Regenerate Wayfinder; never edit generated output.
6. Implement Inertia UI using only server-calculated props.
7. Run the phase's exact checks before moving forward.

## Parallel Opportunities

Use parallel execution sparingly. A junior or low-performance agent should prefer numeric
order unless separate agents are available.

### Foundation

- After T008, T009-T011 may run in parallel because they edit different model/factory
  pairs. T012 waits for the model interfaces to stabilize.

### User Story 1

- T015-T021 may be authored in parallel because every task owns a different test file.
- After T023, T024/T025/T028 may run in parallel because they own different read/record
  Actions; T026/T027/T029 should remain sequential around shared persistence rules.
- T030-T033 and T034-T036 may run in parallel within their own groups because each task
  owns a different file.
- T039 and T040 may run in parallel; T041 waits for types, routes, and server props.

### User Story 2

- T044 and T045 may run in parallel; T046/T047 should remain sequential because later
  phases also modify those feature files.
- T050-T052 and T054 may run in parallel after Actions stabilize.

### User Story 3

- T058 and T059 may run in parallel.
- T065-T068 may run in parallel after T063/T064 because they own different files.
- T070 and T071 may run in parallel after routes/types stabilize.

## Delivery Strategy

### MVP first

Stop after Phase 3 if time is constrained. US1 proves the core business rule: an authorized
operator can publish a mathematically exact, reproducible tomato offer from explicit costs
and a fresh attributed benchmark.

### Incremental delivery

1. Deliver US1 and verify exact publication plus operator security.
2. Add US2 and verify public privacy, disclosure, negative comparisons, and stale state.
3. Add US3 and verify replacement/comparison history without rewriting prior records.
4. Run Phase 6 only after all selected stories pass their checkpoints.

Do not mark a task complete while its relevant test is failing, its command was skipped,
or its output differs from the documented contract.
