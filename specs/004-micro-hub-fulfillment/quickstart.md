# Quickstart: Micro-Hub Fulfillment Validation

This guide validates the planned feature after implementation. It is not an instruction
to bypass the prerequisite phases.

## 1. Hard Prerequisite Check

From the repository root, verify that the real upstream implementation exists:

```powershell
Get-Item app/Models/ProductOffer.php
Get-Item app/Models/Order.php
Get-Item app/Models/OrderStatusTransition.php
Get-Item app/Models/ProcurementRequirement.php
Get-Item app/Models/OrderGroup.php
php artisan route:list --except-vendor
```

Expected: every file exists and routes expose offer/order/consolidation workflows. In the
current working tree these files are absent, so hub implementation must not start yet.
Do not create placeholder versions merely to make this check pass.

## 2. Implementation Preflight

After prerequisites and hub code exist:

```powershell
php artisan config:show app.timezone
php artisan config:show database.default
php artisan route:list --path=operator/hub --except-vendor
php artisan wayfinder:generate --with-form --no-interaction
```

Expected:

- application timestamps persist in UTC and hub display rules use Casablanca time;
- local/demo database is SQLite;
- exactly the nine hub routes in `contracts/web-routes-and-props.md` appear;
- Wayfinder generates controller form variants without errors.

## 3. Focused Automated Tests

Run the smallest suites first:

```powershell
php artisan test --compact tests/Unit/Support/Hub/KilogramQuantityTest.php
php artisan test --compact tests/Unit/Actions/Hub
php artisan test --compact tests/Feature/Hub
```

Critical expected coverage:

1. `100 received / 96 accepted / 4 quality rejected` initializes
   `96 available / 0 allocated / 0 damaged / 0 dispatched`.
2. Requirement `100`, received `110`, quality rejected `0` produces `100 accepted` and
   `10 procurement overage rejected`.
3. Requirement `100`, received `110`, quality rejected `4` produces `96 accepted`,
   `4 quality rejected`, and `10 overage rejected`.
4. Correction appends before/after history before downstream work and fails unchanged
   after damage, allocation, preparation, or dispatch.
5. One kilogram damage from 96 leaves 95 available and 1 damaged; excess damage fails.
6. Allocating a 45 kg group from 95 leaves 50 available and 45 allocated and advances
   every included Order exactly once.
7. A second receipt cannot contribute to that group; partial allocation is impossible.
8. Release before preparation restores the full quantity/group/Orders once; release
   after preparation fails.
9. Preparation requires the exact quantity, leaves all buckets and Orders allocated,
   and creates no dispatch action.
10. Same-token retries create no duplicate; same token with a changed payload conflicts.
11. At 23:59:59 the receipt is not overdue; exactly 24 hours with available/allocated
    stock is overdue; fully damaged/dispatched receipt is not.
12. Guest/ordinary staff cannot access or mutate hub data, and public/log outputs contain
    no supplier/customer PII, private notes, raw tokens, or internal actor IDs.

## 4. Concurrency Gate

First run the portable compare-and-set test locally:

```powershell
php artisan test --compact --filter="competing allocations"
```

Use a file-backed SQLite test database when the test opens two connections; two separate
in-memory SQLite connections do not share state. The test must prove exactly one of two
competing allocations succeeds and both stock invariants remain true.

Before release, run that same focused test using the project's supported MySQL or
PostgreSQL test connection. A SQLite-only pass does not prove production row-lock
behavior and must not be reported as complete concurrency verification.

## 5. Bounded Query/Scale Check

```powershell
php artisan test --compact --filter="500 active receipts"
```

Expected: 25 receipt rows/page, at most 50 outstanding requirements/compatible groups,
correct server totals and filters, stable query count without N+1 access, and no
unbounded relationship serialization. Use the manual timing below for the human
two-minute criterion; do not add a flaky wall-clock HTTP assertion.

## 6. Full Quality Gates

```powershell
php artisan test --compact
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
npm.cmd run format:check
npm.cmd run lint:check
npm.cmd run types:check
npm.cmd run build
composer audit
npm.cmd audit
```

All commands must pass. If one cannot run, report its exact error and do not claim the
feature is fully verified.

## 7. Manual Demo Flow

Prerequisite demo data: one verified operations user, one 100 kg tomato procurement
requirement, and grouped B2C/B2B orders totaling 45 kg under that requirement.

1. Sign in as the operations user and open `Hub fulfillment` from the sidebar.
2. Open the 100 kg requirement and submit 100 received, 96 accepted, 4 quality rejected,
   Grade A, a quality reason, and current Casablanca receive time.
3. Confirm the receipt page shows 96 available and 4 rejected with zero unexplained
   variance.
4. Record 1 kg handling loss and confirm 95 available / 1 damaged.
5. Allocate the 45 kg group and confirm 50 available / 45 allocated and every order is
   allocated.
6. Attempt a duplicate and an over-allocation; confirm safe errors and unchanged totals.
7. Prepare exactly 45 kg. Confirm `Ready for dispatch`, while 45 remains allocated and
   no Order or stock is marked dispatched.
8. Use a receipt time exactly 24 hours old with remaining stock and confirm the overdue
   Badge/filter. Confirm a fully damaged/dispatched fixture is not flagged.
9. Repeat the flow at a narrow mobile viewport and by keyboard. Check focus, labels,
   errors, table scrolling, empty states, dark mode, and browser console.
10. With 500 active receipt fixtures, time an operator filtering and reconciling all six
    quantity buckets. The result must be under 2 minutes.

## 8. Privacy and Scope Review

Before handoff, confirm:

- no hub route is public;
- sidebar visibility is not the authorization boundary;
- Inertia props contain only the contract allowlist;
- encrypted notes are not searchable, logged, or placed in metadata/analytics;
- no raw operation token or payload is stored or logged;
- there is no dispatch button, route, Action, carrier integration, scheduler, queue,
  multi-hub transfer, partial allocation, or multi-receipt group fulfillment.
