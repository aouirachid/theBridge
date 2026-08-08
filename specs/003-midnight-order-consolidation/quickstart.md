# Quickstart Validation: Midnight Order Consolidation

This is a validation/run guide, not implementation code. Run commands from the
repository root. Stop on the first failed gate and report the exact output.

## 1. Hard upstream prerequisite

Phase 003 must not start until Phase 001 and Phase 002 are implemented.

Verify required files:

```powershell
$required = @(
    'app/Models/ProductOffer.php',
    'app/Models/OfferCostComponent.php',
    'app/Models/Order.php',
    'app/Models/OrderStatusTransition.php',
    'app/Enums/OrderChannel.php',
    'app/Enums/OrderStatus.php',
    'app/Enums/DeliveryZone.php',
    'app/Actions/Orders/CreateOrderAction.php',
    'tests/Unit/Actions/Orders/CreateOrderActionTest.php',
    'tests/Feature/Orders/PublicOrderTest.php',
    'tests/Feature/Orders/OperatorOrderTest.php'
)

$missing = $required | Where-Object { -not (Test-Path -LiteralPath $_) }
$missing
```

Expected: no output. If any path prints, STOP. Do not generate Phase 003 migrations,
models, or placeholders.

Run upstream suites:

```powershell
php artisan test --compact tests/Unit/Actions/ProductOffers tests/Feature/ProductOffers
php artisan test --compact tests/Unit/Actions/Orders tests/Feature/Orders
```

Expected: all pass. A failing or missing upstream suite blocks Phase 003.

## 2. Environment and schema preparation

Confirm installed versions and commands:

```powershell
php --version
php artisan --version
php artisan list --raw | Select-String -Pattern 'orders:consolidate|schedule:list|wayfinder:generate'
npm.cmd list --depth=0
```

After implementation migrations exist:

```powershell
php artisan migrate --no-interaction
php artisan schedule:list
php artisan route:list --path=operator/consolidations --except-vendor
```

Expected:

- migrations succeed without changing unrelated data
- `orders:consolidate` appears every minute with overlap protection
- exactly the six operator consolidation routes from the contract appear
- every route is authenticated/verified and mutation routes are throttled

Inspect schema with Laravel Boost `database-schema` and compare every column, FK,
index, unique constraint, nullable/default rule, and table order with `data-model.md`.

## 3. Focused test sequence

Run the smallest suite after each implementation layer.

### Enums and pure support

```powershell
php artisan test --compact tests/Unit/Support/Quantities/KilogramQuantityTest.php
php artisan test --compact tests/Unit/Support/Consolidations/ConsolidationScheduleTest.php
php artisan test --compact tests/Unit/Support/Consolidations/ConsolidationPreviewBuilderTest.php
```

Required cases:

- exact hundredths formatting and no floats
- Casablanca midnight/custom cutoff, next-date targeting, early cutoff, and offset change
- deterministic key/sort/fingerprint regardless of input order
- exact order counts, quantity, commercial totals, threshold lookup, and one final
  half-up delivery estimate
- 500 accepted / 501 rejected without partial preview

### Order confirmation window regression

```powershell
php artisan test --compact tests/Unit/Actions/Orders/CreateOrderActionTest.php
php artisan test --compact tests/Feature/Orders
```

Required cases:

- confirmation before cutoff first-creates/reuses one open cycle
- first creation snapshots the cutoff; later configuration changes do not move that row
- confirmation at boundary remains eligible when it wins the row claim
- confirmation after scheduled or early closure returns `ordering_window_closed`
- no price, total, availability, token, PII, or lifecycle regression
- closing and confirmation cannot both commit incompatible outcomes

### Core cycle Action

```powershell
php artisan test --compact tests/Unit/Actions/Consolidations/RunConsolidationCycleActionTest.php
```

Required cases:

- 20 tomato Orders / 100.00 kg / three zones -> one requirement, three groups, 20
  grouped Orders, 20 transitions, zero variance
- separate ProductOffer, channel, and zone keys never merge
- cutoff equality included; after cutoff, cancelled, grouped, wrong date excluded
- fixed aggregate reason counts reconcile for after-cutoff, non-confirmed, already-grouped,
  and operator-excluded demand
- source Order price snapshots unchanged byte-for-byte
- requirement/group/cycle quantity, count, commercial, and delivery estimates reconcile
- under-minimum preview creates candidates but no completed outputs/transitions
- approved candidate included; excluded candidate Orders remain confirmed/null group
- changed source demand creates a new generation and requires fresh decisions
- repeat completed run returns same rows; forced conflict rolls back all final outputs
- failure after committed closure preserves the original schedule/effective-cutoff fields
- cache-lock failure is safe; DB uniqueness/conditional claims still prove invariants

### Remaining Actions

```powershell
php artisan test --compact tests/Unit/Actions/Consolidations
```

Required cases:

- scheduled start runs only when due, targets tomorrow, and reuses one service-date row
- manual early start uses authoritative now and cannot accept caller cutoff/order data
- same/different decision replay, stale candidate, actor/time, and fixed exclusion reason
- cutoff change uses HH:MM, next operating date, immutable history, latest same-day rule,
  same-value idempotency, and no historical-cycle changes
- index status/trigger/operating-date/service-date/ProductOffer/channel/zone filters at
  25/page without duplicate rows; show candidates/groups/orders 50/page; no N+1/PII

### HTTP, command, and schedule integration

```powershell
php artisan test --compact tests/Feature/Consolidations/OperatorConsolidationTest.php
php artisan test --compact tests/Feature/Consolidations/ScheduledConsolidationTest.php
```

Required HTTP cases:

- guest redirect; unverified/ordinary user forbidden; operations user can run/inspect
- only operations managers receive cutoff configuration and may update it
- strict mutation/query validation, nested candidate scoping, safe UUID not-found
- index/show exact allowlisted Inertia props and recursive PII/internal-key absence
- manual run, decision, retry, cutoff redirects/toasts and conflict display
- 60/minute read and 30/minute mutation limits return 429 at the boundary
- command due/no-op/awaiting/completed/failure exit behavior contains no private data

## 4. Production-engine concurrency gate

SQLite is the local fast suite, but `lockForUpdate()` is not equivalent across engines.
Before release, run focused contention tests with the supported MySQL or PostgreSQL test
connection.

Minimum scenarios:

1. Automatic and manual callers target the same service date simultaneously.
2. Order cancellation/confirmation competes with cycle closure/finalization.
3. Two operators decide the same candidate with same and different decisions.
4. A forced deadlock/serialization failure retries without duplicate outputs.

Expected after every scenario:

```text
cycles(service_date) count = 1
each included Order has one order_group_id
each included Order has one confirmed->grouped transition
requirement/group unique keys have one row
all reconciliation variances = 0
excluded Orders remain confirmed with null group
```

If a production database connection is unavailable, record the exact connection error
and leave the gate incomplete. A passing SQLite suite does not replace it.

## 5. Acceptance dataset

Use factories inside tests or an approved demo seeder; do not use manual SQL or tinker to
create production-like records.

Build:

```text
one published tomato ProductOffer with delivery_allocation cost component
one next-day service date
20 confirmed Orders totaling exactly 100.00 kg
three delivery zones, one channel
all confirmed_at <= effective cutoff
all price snapshots valid and immutable
```

Run:

```powershell
php artisan orders:consolidate
```

Expected first run:

```text
one completed cycle
one outstanding ProcurementRequirement for 100.00 kg / 20 Orders
three OrderGroups totaling 100.00 kg / 20 Orders
20 Orders grouped once
zero count, quantity, and commercial variance
```

Run the same command again. Expected: the same cycle/reference and row counts; no new
requirement, group, transition, or quantity.

For the under-minimum path, temporarily use a test configuration threshold above one
candidate's quantity. Expected: awaiting decision with no completed outputs. Approve or
exclude every under-minimum candidate, continue the cycle, then verify approved totals
and unchanged excluded Orders.

## 6. Frontend and static quality gates

Generate route helpers only after final routes/controllers exist:

```powershell
php artisan wayfinder:generate --with-form --no-interaction
npm.cmd run format:check
npm.cmd run lint:check
npm.cmd run types:check
npm.cmd run build
```

Expected: all pass. Never hand-edit generated files. If format/lint check fails, run the
project's mutating command only on the intended frontend scope, inspect its changes, and
rerun the check.

For PHP:

```powershell
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
php artisan test --compact tests/Unit/Actions/Consolidations tests/Unit/Support/Consolidations tests/Unit/Support/Quantities tests/Feature/Consolidations
php artisan test --compact tests/Unit/Actions/Orders tests/Feature/Orders
```

Finally run the full affected project checks configured in Composer. Do not claim
completion with a failing check.

## 7. Manual operator demonstration

Start the app with the existing development command and sign in as a verified operations
user created through an approved factory/seeder path.

Open `/operator/consolidations` and verify:

1. Current/pending cutoff and `Africa/Casablanca` are clear.
2. Change cutoff: invalid value fails; valid value shows next-day effectiveness and does
   not alter an existing cycle.
3. Place live demo Orders for one open service date, select that date, and confirm Run
   now. Verify the exact current Casablanca cutoff is shown.
4. If a candidate is under minimum, verify no completed output appears before all
   decisions. Approve one and exclude one; excluded Order references remain follow-up.
5. Continue/retry and inspect requirement, groups, selected group source Orders, totals,
   estimated delivery allocation label, and zero variance.
6. Repeat run/reload/back navigation. Verify no duplicate or optimistic flicker.
7. Try a new confirmation for the early-closed date. Verify safe closed-window conflict.
8. Repeat as guest and ordinary verified user. Verify no consolidation data/control.

Perform at desktop and narrow mobile width, light and dark mode, and keyboard only:

- focus is visible and logical
- confirmation Dialog traps/returns focus correctly
- processing buttons disable and expose readable state
- tables scroll horizontally without hiding controls
- labels, errors, status Badges, empty/failed/awaiting states remain understandable
- browser console contains no JavaScript error and no response exposes customer PII,
  internal IDs, hashes, raw payloads, or staff identity

No browser-testing plugin is installed, so this manual flow is mandatory unless a
separate dependency is explicitly approved later.
