# Quickstart: Delivery Orchestration Validation

This is a validation/run guide, not implementation code. Complete checks in order. Stop
on the first failure and report the exact output. Do not claim Phase 005 complete from
SQLite-only concurrency tests or while an upstream prerequisite is missing.

## 1. Upstream Gate

The current repository is expected to fail this gate until Phases 001–004 are
implemented. Before any Phase 005 source work, verify:

```text
app/Models/Order.php
app/Models/OrderGroup.php
app/Models/StockAllocation.php
app/Models/HubReceipt.php
app/Models/OrderStatusTransition.php
app/Enums/OrderChannel.php
app/Enums/OrderStatus.php
app/Enums/DeliveryZone.php
app/Enums/OrderGroupFulfillmentStatus.php
app/Support/Quantities/KilogramQuantity.php
```

Verify schema contains the exact upstream fields in `data-model.md`, especially:

```text
orders.order_group_id
order_groups.fulfillment_status
order_groups.current_stock_allocation_id
stock_allocations.prepared_quantity_hundredths
stock_allocations.prepared_at
stock_allocations.dispatched_at
stock_allocations.lifecycle_version
hub_receipts.allocated_quantity_hundredths
hub_receipts.dispatched_quantity_hundredths
hub_receipts.inventory_version
order_status_transitions.stock_allocation_id
users.is_operations_operator
```

Run all focused upstream Action/Feature suites for phases 001–004. Stop if any file,
field, enum case, relationship, permission, factory state, or test is absent/failing. Do
not create a Phase 005 substitute.

## 2. Schema and Static Contract

After implementation:

```text
php artisan migrate --no-interaction
php artisan route:list --path=operator/dispatches --except-vendor
php artisan config:show dispatch
php artisan wayfinder:generate --with-form --no-interaction
```

Inspect schema with Laravel Boost and confirm:

- four new tables in documented order;
- unique Dispatch public/scope keys and unique DispatchOrder `order_id`;
- attempt and status operation-token uniqueness;
- foreign keys restrict domain deletion and null private actors;
- stock allocation dispatched progress defaults to zero;
- nullable Dispatch FK exists on OrderStatusTransition;
- indexes match list, detail, history, and ready-work query shapes;
- no customer/business/contact/address/note/provider-payload column exists in any
  dispatch table.

Rollback/migrate on a disposable test database. Never run `migrate:fresh` against a
database containing user data.

## 3. Focused Automated Tests

Run smallest suites first:

```text
php artisan test --compact tests/Unit/Support/Dispatches
php artisan test --compact tests/Unit/Actions/Dispatches/GenerateDispatchesActionTest.php
php artisan test --compact tests/Unit/Actions/Dispatches/SubmitDispatchActionTest.php
php artisan test --compact tests/Unit/Actions/Dispatches/AdvanceDispatchActionTest.php
php artisan test --compact tests/Unit/Actions/Dispatches/ListDispatchesActionTest.php
php artisan test --compact tests/Unit/Actions/Dispatches/ShowDispatchActionTest.php
php artisan test --compact tests/Feature/Dispatches
```

Then run every affected Phase 002–004 regression suite plus the full test suite:

```text
php artisan test --compact
```

Required Action/helper coverage:

- exact MAD parse/format boundaries and overflow rejection;
- largest-remainder ties by safe Order UUID and exact zero/non-zero totals;
- deterministic mock reference/quote/outcomes, no credentials, no HTTP/network use;
- 500 accepted / 501 rejected generation boundary;
- exactly one B2B manifest per date/zone and one B2C Dispatch per Order;
- ready B2B refresh, submitted freeze, and late-work flag;
- repeated/concurrent generation with no duplicate membership;
- B2B flat-cost and B2C mock-quote submission;
- token replay, mismatched token payload, distinct concurrent tokens, stale version;
- all valid/invalid status paths;
- partial B2C group pickup, final group pickup, and exact HubReceipt counter changes;
- pickup rollback on any Order/allocation/receipt mismatch;
- delivery changes Orders only;
- pre-pickup retry and terminal post-pickup failure;
- index/show page bounds, query counts, exact props, and PII boundaries.

Required HTTP coverage:

- guest redirect, ordinary authenticated 403, verified operator success;
- allowlisted filter/mutation fields and 422 validation;
- UUID route binding and safe 404;
- 60/minute mutation limiter and 429 response;
- thin controller -> Request -> Action mapping and redirects/toast;
- Inertia component names and exact prop shapes;
- fixed safe conflict codes/messages;
- no PII/provider secret/raw payload in aggregate props, errors, exceptions, or logs.

## 4. Exact Acceptance Dataset

Create data through upstream factories/actions, never manual SQL:

- one verified operations operator and one ordinary verified user;
- published tomato offer and confirmed price snapshots;
- one Casablanca service date;
- prepared B2B Orders across all three zones, including at least one zone with three
  Orders of different quantities and multiple products/groups;
- one additional B2B Order in one zone that becomes prepared after its manifest submits;
- at least two prepared B2C Orders inside the same prepared OrderGroup/StockAllocation;
- HubReceipt counters exactly reconciled before dispatch;
- canary PII values unique enough to search logs/output, for example names/phones/notes
  that do not resemble application labels.

Do not use real customer information.

## 5. Generation Scenario

1. As the operator, choose the service date and generate dispatches.
2. Verify exactly one ready B2B manifest per represented zone.
3. Verify each prepared B2C Order has exactly one ready simulated Dispatch.
4. Verify every eligible Order belongs to exactly one Dispatch and quantities/counts
   reconcile to source Orders.
5. Prepare another B2B Order in a zone whose manifest is still ready and generate again.
   Verify the same manifest refreshes, includes the new Order once, and preserves stable
   sequence.
6. Repeat and concurrently invoke generation. Verify no duplicate Dispatch or membership.
7. Verify index/summary contains no canary PII.

## 6. Submission and Cost Scenario

1. Submit one B2B manifest with a flat cost designed to leave centime remainders.
2. Verify membership freezes and allocations use largest fractional remainder first,
   safe UUID for ties, and sum exactly to the flat cost.
3. Verify the manifest current state is submitted and history contains one submit event.
4. Prepare a late B2B Order in the same date/zone. Generate again and verify the submitted
   manifest is unchanged and late count/quantity is flagged without a supplementary
   manifest.
5. Submit one B2C Dispatch with no cost field and no provider credentials/network.
6. Verify the provider is visibly simulated, reference is `MOCK-<12 hex>`, quote is
   25.00 MAD, current state is accepted, and the one Order receives the full 25.00 MAD.
7. Replay both submissions with the same token/payload and verify no duplicate attempt,
   transition, provider reference, or cost.
8. Replay a token with changed cost/version and verify `operation_mismatch` with no write.
9. Verify immutable Order price snapshots and Phase 003 estimated delivery allocation
   remain byte-for-byte unchanged.

## 7. Status, Stock, and Retry Scenario

1. Advance B2B submitted -> accepted -> picked up -> delivered.
2. At pickup verify all included Orders advance Allocated -> Dispatched once, each
   StockAllocation progress increases by exact included quantities, and each HubReceipt
   moves the same quantity allocated -> dispatched.
3. Verify a fully moved allocation/group becomes Dispatched; partial allocation/group
   remains ready for its other B2C Dispatches.
4. Pick up the first B2C Order in a two-Order group. Verify only that Order and quantity
   move. Pick up the second and verify the allocation/group completes exactly.
5. Simulate B2C delivery and verify Order becomes Delivered with no second stock change.
6. Create failure from submitted/accepted, retry the same Dispatch, and verify identity,
   frozen cost, membership, and provider reference remain unchanged.
7. Create failure after pickup. Verify status is terminal, Order/stock remain Dispatched,
   no retry/delivered target is offered, and manual-follow-up flag is visible.
8. Repeat every advance token and run competing stale versions. Verify one effect and
   safe conflicts only.

After each step assert:

```text
available + allocated + damaged + dispatched = accepted
0 <= allocation dispatched progress <= allocation quantity
sum DispatchOrder costs = Dispatch cost
each Order has at most one Dispatch and one transition per accepted lifecycle event
```

## 8. Privacy and Safe Diagnostics

Search responses, Inertia props, logs, exceptions, attempts, transitions, and dispatch
tables for every canary PII value and for raw operation tokens.

Expected:

- only the authorized show page contains minimum canary handoff fields for that
  Dispatch's Orders;
- index/summary, unauthorized/404/422/409/429 outcomes, toast, attempts, transitions,
  database dispatch rows, and logs contain none;
- mock reference/quote/outcome do not change when PII changes but safe Dispatch identity
  remains the same;
- no credential, token, signature, raw provider request/response, private hash, SQL, or
  model dump exists;
- ordinary user cannot infer whether a protected Dispatch exists.

## 9. Frontend and Manual Demo

Run:

```text
vendor/bin/pint --dirty --format agent
composer run types:check
npm run format:check
npm run lint:check
npm run types:check
npm run build
```

Using the real app in desktop and narrow mobile widths, light and dark modes, verify:

- operations-only sidebar navigation and correct Wayfinder links;
- index filters, summaries, no-work/over-limit/late-work empty states;
- generation confirmation and processing-disabled button;
- responsive manifest/request table without clipped controls;
- B2C `Simulated provider` label is always visible;
- B2B cost form appears only on first submission;
- B2C has no operator cost field and shows 25.00 MAD quote after submit;
- allowed status actions match server props; B2C labels say Simulate;
- field/conflict errors are keyboard reachable and associated with controls;
- authorized stop PII is readable only on show and absent from DOM attributes/client
  logs/analytics;
- browser console has no JavaScript error.

## 10. Production-Engine Concurrency Gate

Before claiming concurrency safety, rerun competing generation, same/different submit
tokens, simultaneous pickup of two B2C Orders sharing one StockAllocation, stale version,
and forced rollback tests against the supported production database engine
(MySQL/PostgreSQL as selected for deployment).

Verify real unique indexes, FK behavior, row locks, lock ordering, deadlock retries,
affected counts, and final invariants. If this environment is unavailable, report that
the gate is not verified and do not mark concurrency claims complete.

## 11. Final Gate

Do not complete Phase 005 unless:

- upstream and dispatch suites pass;
- migrations and rollback pass on a disposable database;
- Pint, PHPStan, ESLint, Prettier, TypeScript, and build pass;
- no dependency or live integration was added;
- privacy search finds zero forbidden values outside the authorized detail boundary;
- the complete B2B/B2C demo works without credentials/network;
- production-engine concurrency gate passes or is explicitly reported as blocking.
