# Quickstart: Transparency Ledger and Impact Dashboard

This is a validation guide, not an implementation script. Run commands from the project
root. Stop on the first failure and report its exact output. Never use raw SQL or tinker
to manufacture missing Phase 001-005 data.

## 1. Current Hard Stop

The repository currently contains only starter authentication code. Confirm before any
Phase 006 implementation work:

```powershell
rg --files app database/migrations tests resources/js routes | Sort-Object
```

Expected today: the Phase 001-005 models and Actions named in `plan.md` are absent.
Therefore implementation must stop. Do not generate Phase 006 source files until the
earlier phases have been implemented and verified.

## 2. Upstream Gate After Phases 001-005 Are Implemented

Confirm the exact source Actions exist:

```powershell
$requiredActions = @(
    'app/Actions/ProductOffers/PublishProductOfferAction.php',
    'app/Actions/ProductOffers/PublishBenchmarkComparisonAction.php',
    'app/Actions/Orders/CreateOrderAction.php',
    'app/Actions/Orders/CancelOrderAction.php',
    'app/Actions/Consolidations/RunConsolidationCycleAction.php',
    'app/Actions/Hub/FinalizeHubReceiptAction.php',
    'app/Actions/Hub/CorrectHubReceiptAction.php',
    'app/Actions/Hub/RecordHandlingLossAction.php',
    'app/Actions/Hub/AllocateOrderGroupAction.php',
    'app/Actions/Hub/ReleaseStockAllocationAction.php',
    'app/Actions/Dispatches/SubmitDispatchAction.php',
    'app/Actions/Dispatches/AdvanceDispatchAction.php'
)
$missingActions = $requiredActions | Where-Object { -not (Test-Path $_) }
if ($missingActions.Count -gt 0) { $missingActions; throw 'Phase 006 upstream Action gate failed.' }
```

Then run the focused earlier-phase suites. Use the real filenames if implementation
artifacts were reconciled before coding:

```powershell
php artisan test --compact tests/Feature/ProductOffers tests/Feature/Orders tests/Feature/Consolidations tests/Feature/Hub tests/Feature/Dispatches
```

Expected: all pass. A missing suite, model, field, permission, random public reference,
or fixed safe reason code is a design reconciliation task, not permission to create a
Phase 006 substitute.

## 3. Schema and Trigger Gate

After Phase 006 migrations are implemented:

```powershell
php artisan migrate:status
php artisan migrate --pretend
php artisan migrate --no-interaction
```

Expected schema:

- `product_offers.is_demo` exists, defaults false, and is not accepted by ordinary offer
  HTTP input;
- exactly one `ledger_chains` row with key `platform`, position `0`, and null hash before
  source events;
- unique event idempotency hash and unique `(chain, position)` on `ledger_entries`;
- indexed endpoint/history fields on `ledger_verifications`;
- versioned `impact_indicators` scoped to one completed consolidation cycle;
- SQLite triggers `ledger_entries_no_update` and `ledger_entries_no_delete`.

Do not test triggers by mutating development/demo history. Automated tests use isolated
test transactions/databases.

## 4. Narrow Test Order

Run in this order. Do not continue when a layer fails.

```powershell
php artisan test --compact tests/Unit/Support/Transparency/LedgerHasherTest.php
php artisan test --compact tests/Unit/Actions/Transparency/AppendLedgerEntryActionTest.php
php artisan test --compact tests/Feature/Transparency/LedgerEntryImmutabilityTest.php
php artisan test --compact tests/Unit/Actions/Transparency/VerifyLedgerActionTest.php
php artisan test --compact tests/Unit/Actions/Transparency/PublishImpactIndicatorActionTest.php
php artisan test --compact tests/Unit/Actions/Transparency/ShowPublicTransparencyActionTest.php
php artisan test --compact tests/Unit/Actions/Transparency/ShowTransparencyOperationsActionTest.php
php artisan test --compact tests/Unit/Actions/Transparency/SeedTransparencyDemoActionTest.php
php artisan test --compact tests/Feature/Transparency
```

Expected critical assertions:

- same logical event and content returns one entry; mismatched replay is a safe conflict;
- first and concurrent entries form one contiguous chain with no fork;
- source Action rollback also removes its would-be ledger entry;
- Eloquent, query-builder, and raw SQL update/delete attempts fail;
- a valid chain verifies; altered payload/link/position/count fails deterministically;
- a verification becomes publicly stale immediately after a new append;
- estimate/measured validation never permits a numeric unsupported claim;
- public props/logs/errors contain no canary PII or private actor data.

## 5. Controlled Corruption Test Safety

Only `LedgerEntryImmutabilityTest` or `VerifyLedgerActionTest` may perform controlled
corruption, against the test database only:

1. Build and verify a valid chain.
2. Drop only `ledger_entries_no_update`.
3. Alter one protected payload or link with a direct test query.
4. Assert verification is invalid at the exact safe position.
5. Recreate `ledger_entries_no_update` in a `finally` block even when an assertion fails.
6. Assert the test connection still rejects update afterward.

Never expose a test-only tamper route, command, Action, service, or UI control.

## 6. Exact Tomato Demo

Seed only after upstream and Phase 006 tests pass:

```powershell
php artisan db:seed --class='Database\Seeders\TransparencyDemoSeeder' --no-interaction
```

Run it twice. Expected: the second run returns/reuses the same logical scenario and does
not duplicate offers, orders, groups, dispatches, indicators, or ledger events.

Required values:

| Claim | Expected |
|---|---:|
| Farmer payment | 2.80 MAD/kg |
| Collection | 0.30 MAD/kg |
| Quality control | 0.20 MAD/kg |
| Hub handling and storage | 0.30 MAD/kg |
| Delivery allocation | 0.90 MAD/kg |
| Platform margin | 1.00 MAD/kg |
| Final price | 5.50 MAD/kg |
| Demo benchmark | 8.00 MAD/kg |
| Saving | 2.50 MAD/kg / 31.25% |
| Farmer share | 50.91% |
| Grouped orders | 20 |
| Consolidated quantity | 100.00 kg |
| Delivery groups | 3 |
| Estimated trip reduction | 17 trips |

The benchmark and trip reduction must be visibly labeled demo/estimate. There is no CO2
value in the scenario.

## 7. Route and Wayfinder Gate

```powershell
php artisan route:list --name=transparency --except-vendor
php artisan wayfinder:generate --with-form --no-interaction
npm run types:check
```

Expected:

- one guest GET route;
- one protected operator GET route;
- exactly two protected POST routes;
- no ledger entry update/delete routes;
- React imports generated controller actions, not literal application paths.

Open the current demo offer through the normal Phase 001 public route and use its
`View transparency` link. The jury page must not require authentication. Open the
operator workspace through the sidebar as a verified operations user.

## 8. Manual Public Page Check

At mobile and desktop widths, in light and dark mode:

1. Confirm price cards appear first and every cost row, including zero values, is visible.
2. Recalculate 5.50, 2.50, 31.25%, and 50.91% from visible values.
3. Confirm market/source/observed time and `Demo data` are adjacent to the benchmark.
4. Confirm order count, kilograms, group count, rejected, and damaged values have units
   and one stated reporting scope.
5. Confirm `Estimate` is beside 17 trips and method/assumptions/limitation are readable.
6. Confirm integrity is `stale` or `not yet verified` before verification, `valid` after
   operator verification, and never exposes hashes or a failure position publicly.
7. Confirm keyboard focus, semantic headings/lists/tables, wrapping, empty states, and
   negative-value warning treatment.

## 9. Operator and Abuse Check

1. Guest access to operator routes redirects to login.
2. Verified ordinary user receives 403.
3. Verified operations user can verify and publish an indicator.
4. Estimate requests cannot submit counts/method/source/evidence.
5. Measured requests require counts, method, source, evidence, period, and limitation.
6. The 11th mutation inside one minute receives 429 without a partial record.
7. Repeated submit while processing does not create duplicate verification/indicator
   effects.

## 10. Full Quality Gate

After focused tests pass:

```powershell
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test --compact
npm run lint:check
npm run format:check
npm run types:check
npm run build
composer audit
npm audit
```

Also run a repository privacy scan limited to source/config/routes/tests and review every
match rather than blindly asserting strings are absent:

```powershell
rg -n -i 'customer_name|business_name|phone|email|delivery_address|delivery_note|token|secret|raw_payload' app config routes resources/js tests
```

Expected: only protected upstream handling, explicit privacy assertions, or prohibited
field lists; none in ledger payload construction, public props, logs, or error context.

## 11. Concurrency and Database Deployment Gate

Run representative concurrent append/replay tests using separate connections/processes,
not only one transaction-bound test connection. For 100 distinct concurrent logical
events and repeated duplicates, require exactly 100 new entries, contiguous positions,
one head, and a valid verification.

The current MVP is SQLite-specific for immutable triggers. Before deploying on MySQL or
PostgreSQL, stop and add/test equivalent UPDATE/DELETE denial for the application
service account. Do not silently skip protection or claim production append-only safety.

## Completion Evidence

Do not mark Phase 006 complete unless all are true:

- upstream gate and modified upstream suites pass;
- exact hash, replay, rollback, trigger, tamper, stale-result, privacy, and aggregate
  tests pass;
- tomato seeder is replay-safe and all exact values match;
- public/operator manual flow passes at both widths and themes;
- Pint, PHPStan, full Pest, frontend static checks/build, and audits pass;
- concurrent append gate passes on the intended demo/deployment engine;
- no unsupported environmental claim or public PII exists.
