# Quickstart: B2C and B2B Order Capture

## 1. Required preflight

Phase 1 must be implemented first. Confirm these exist before starting Phase 2:

```text
app/Models/ProductOffer.php
app/Actions/ProductOffers/ShowPublicProductOfferAction.php
routes/offers.php
resources/js/pages/offers/show.tsx
tests/Feature/ProductOffers/PublicProductOfferTest.php
```

If any are missing, stop and implement `specs/001-transparent-product-offer/tasks.md`.
Do not scaffold substitute offer files.

Read in this order:

1. [spec.md](./spec.md)
2. [research.md](./research.md)
3. [data-model.md](./data-model.md)
4. [contracts/web-routes-and-props.md](./contracts/web-routes-and-props.md)
5. [plan.md](./plan.md) implementation guardrails

## 2. Implementation checkpoint order

Use this exact sequence; do not wire UI before the underlying Action passes.

1. Enums and `OrderTotalCalculator` with calculator test.
2. Operations permission, delivery-slot migration/model/factory, then extend Phase 1
   draft/create/update/publication tests and code.
3. Order and transition migrations/models/factories.
4. `ReviewOrderAction` unit test/action, then Request/controller/route and public HTTP
   review assertions.
5. `CreateOrderAction` unit test/action, then Request/controller/route and public HTTP
   confirmation assertions.
6. `ListOrdersAction` and `ShowOrderAction` unit tests/actions, then policy/operator
   Requests/controllers/routes/pages and HTTP assertions.
7. `CancelOrderAction` unit test/action, then Request/controller/route/UI and HTTP
   assertions.
8. Public React review/confirmation form and final frontend verification.

## 3. Database and generated routes

After implementation files exist:

```powershell
php artisan migrate --no-interaction
php artisan wayfinder:generate --with-form --no-interaction
```

The Vite Wayfinder plugin already has `formVariants: true`; generated files under
`resources/js/actions` and `resources/js/routes` must never be edited manually.

## 4. Focused automated verification

Run the narrowest tests first:

```powershell
php artisan test --compact tests/Unit/Support/Pricing/OrderTotalCalculatorTest.php
php artisan test --compact tests/Unit/Actions/Orders
php artisan test --compact tests/Feature/Orders/PublicOrderTest.php
php artisan test --compact tests/Feature/Orders/OperatorOrderTest.php
php artisan test --compact tests/Feature/ProductOffers/OperatorProductOfferTest.php
php artisan test --compact tests/Feature/ProductOffers/PublicProductOfferTest.php
```

Required behavior coverage:

- totals: whole kilograms, one decimal, two decimals, half-cent tie, invalid/overflow
- slot collection: required, maximum 14, distinct, in offer window, immutable after publish
- review: B2C/B2B, no write, authoritative total, inactive/stale slot/depleted failures
- confirmation: 5 kg and 40 kg happy paths, encrypted raw storage, immutable snapshots
- availability: exact remainder succeeds; one hundredth above fails; cancellation releases
- idempotency: identical retry returns same reference; changed payload conflicts
- privacy: recursive public/JSON assertions exclude every PII/internal key
- operator: guest redirect, ordinary user forbidden, operations user list/show/cancel
- lifecycle: confirmed cancellation once; grouped/allocated/dispatched/delivered reject
- throttles: preview, confirmation, and operator limits return 429

## 5. Manual acceptance path

Start the existing development workflow:

```powershell
composer run dev
```

Use the Phase 1 operator flow to publish tomatoes with:

```text
available quantity: at least 45.00 kg
unit price: 5.50 MAD/kg
delivery slots: at least one future Casablanca slot
```

Then validate:

1. Open the current public tomato offer as a guest.
2. Select B2C, enter 5 kg, a slot, Casablanca Centre, name, and phone.
3. Review: expect 5.50 MAD/kg and 27.50 MAD; confirm once; save the reference.
4. Retry confirmation without changing input: expect the same reference and no duplicate.
5. Select B2B, enter business/contact data and 40 kg against the same offer.
6. Review: expect 5.50 MAD/kg and 220.00 MAD; confirm.
7. Sign in as an operations operator and open Orders.
8. Filter the service date; expect two confirmed orders totaling 45.00 kg and both
   eligible for the next cycle.
9. Open each detail and verify contact data is visible only there and prices match.
10. Cancel the 5 kg order; expect cancelled status, unchanged price snapshot, and released
    availability. A second cancellation must not append another transition.

## 6. Manual failure/privacy checks

- Change quantity after review: old review must disappear and confirmation stay disabled.
- Withdraw/supersede the offer after review: confirmation must return a safe conflict.
- Let the selected slot pass after review: confirmation must return slot unavailable.
- Request more than remaining quantity: confirmation must not create an order.
- Inspect public offer props, review JSON, confirmation JSON, browser history, and recent
  logs: no full name, phone, email, business, address, note, raw token, internal ID, or
  actor identifier may appear outside the current user's form inputs.
- Open operator routes logged out and as a normal user: private data must remain hidden.
- Check mobile width, keyboard focus, dark mode, empty results, validation, conflict, and
  loading states.

## 7. Final quality gates

```powershell
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
npm.cmd run format
npm.cmd run lint
npm.cmd run types:check
npm.cmd run build
php artisan test --compact tests/Unit/Actions/Orders tests/Feature/Orders tests/Feature/ProductOffers
```

Do not mark implementation complete if a command fails. Report the exact error and stop.
