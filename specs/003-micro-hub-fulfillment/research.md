# Research: Micro-Hub Fulfillment

## Decision 1: Treat all upstream commercial phases as hard prerequisites

**Decision**: Do not implement this feature until Phase 1, Phase 2, and midnight
consolidation provide the actual `ProductOffer`, `Order`, `OrderStatusTransition`,
`ProcurementRequirement`, and `OrderGroup` models. Extend those exact models.

**Rationale**: The current application database contains only starter/auth tables.
Temporary procurement or order models would create a second domain and mislead later
implementation.

**Alternatives considered**: Add hub-local stubs (rejected: duplicates domain); absorb
all earlier phases here (rejected: destroys feature boundaries and is not implementable
as a small slice).

## Decision 2: Use one operations role with separate policy abilities

**Decision**: Reuse planned `users.is_operations_operator` for all hub operations in the
MVP. Define separate `receive`, `correct`, `recordDamage`, `allocate`, `release`, and
`prepare` policy abilities even though the same flag currently grants them.

**Rationale**: This meets staff-only authorization without adding a permissions package
or multiple role flags. Separate abilities preserve a clean future split.

**Alternatives considered**: Add receiver/fulfillment columns (rejected: extra schema for
one demo operator); permission package (rejected: dependency and abstraction overhead).

## Decision 3: Store kilograms as integer hundredths

**Decision**: Reuse Phase 2 integer hundredths (`5.25 kg` -> `525`) for receipt, stock,
loss, group, and allocation quantities. One `KilogramQuantity` support class parses and
formats decimal strings.

**Rationale**: Integers make equality, subtraction, compare-and-set updates, and
cross-engine behavior exact. They prevent PHP/JavaScript/SQL rounding drift.

**Alternatives considered**: Floats (rejected: inaccurate); database decimal plus string
casts (rejected: conflicts with planned order quantities and complicates atomic math);
quantity package (rejected: new dependency).

## Decision 4: Use four domain tables and stored receipt counters

**Decision**: Add `hub_receipts`, `hub_receipt_corrections`, `handling_losses`, and
`stock_allocations`. `hub_receipts` owns the authoritative current available, allocated,
damaged, and dispatched counters. The other rows preserve append-only correction, loss,
allocation, release, and preparation facts.

**Rationale**: Stored counters make work-queue reads and atomic availability checks
simple. Focused audit rows show why counters changed without the complexity of replaying
an event stream.

**Alternatives considered**: Full event sourcing (rejected: projection/replay failure
modes); a separate one-to-one balance table (rejected: extra relation with no value); one
generic operations table (rejected: nullable polymorphic fields and weaker constraints).

## Decision 5: Finalize one receipt per procurement requirement

**Decision**: A procurement requirement receives one finalized `HubReceipt`. The server
calculates overage as `max(received - required, 0)`, requires
`accepted + quality_rejected = min(received, required)`, and initializes available to
accepted. A short receipt remains a visible shortage; later receipts are deferred.

**Rationale**: The specification excludes complex batch splitting/merging and does not
define partial-receipt reconciliation. A unique procurement FK is the smallest
unambiguous demo rule.

**Alternatives considered**: Multiple partial receipts (rejected: undefined closing and
remaining requirement rules); reject short receipt finalization (rejected: hides actual
operations); accept overage (rejected by clarification).

## Decision 6: Preserve corrections with immutable initial values and snapshots

**Decision**: `HubReceipt` stores immutable initial quantities and the current corrected
projection. Each correction appends before/after quantities, reason, actor, time, and
token/payload hashes. Correction is allowed only while all accepted stock remains
available and no downstream activity has begun.

**Rationale**: Staff can fix a weighing/data-entry mistake without erasing the original
receipt or rewriting loss/allocation history.

**Alternatives considered**: Edit receipt columns only (rejected: no audit); correction
after allocation (rejected: cascades into groups/orders); replacement receipt (rejected:
breaks one-receipt identity).

## Decision 7: Use optimistic versions plus conditional claims

**Decision**: Every mutation uses a short transaction. Lock rows where supported, but
also compare-and-set `hub_receipts.inventory_version`, conditionally claim/clear
`order_groups.current_stock_allocation_id`, and conditionally update allocation lifecycle
fields. Zero affected rows is a conflict and rolls back the transaction.

**Rationale**: Laravel documents transactional `lockForUpdate()`, but SQLite does not
provide equivalent row locks. Version and conditional updates provide a portable lost-
update/oversell boundary and remain useful on MySQL/PostgreSQL.

**Alternatives considered**: `lockForUpdate()` alone (rejected: false confidence on
SQLite); cache lock (rejected: second consistency system); database-specific partial
unique active-allocation index (rejected: not portable).

## Decision 8: Hash a replay token for every mutation

**Decision**: Each form receives a random UUID operation token. Store only SHA-256 token
and canonical payload hashes in the receipt, correction, loss, or allocation record.
Same token and payload returns the existing result; changed payload returns
`operation_mismatch`.

**Rationale**: It prevents double submission for operations without natural uniqueness
while keeping raw tokens and payloads out of storage and logs.

**Alternatives considered**: UI button disabling only (rejected: network retry remains);
raw token (rejected: avoidable exposure); one generic idempotency table (rejected:
additional lifecycle and cleanup complexity).

## Decision 9: Use a current allocation pointer on each order group

**Decision**: Add nullable unique `order_groups.current_stock_allocation_id` and a small
fulfillment status. Allocation creates a history row and conditionally claims the null
pointer. Release clears only the matching pointer. A later allocation creates a new row,
so release history is never overwritten.

**Rationale**: This is portable across SQLite, MySQL, and PostgreSQL and enforces one
active allocation per group while still permitting release and reallocation.

**Alternatives considered**: One mutable allocation row per group (rejected: overwrites
history); partial unique index (rejected: MySQL portability); application check without a
conditional claim (rejected: race).

## Decision 10: Derive overdue status during reads

**Decision**: Capture one current time per Action. A receipt is overdue when
`received_at <= now - 24 hours` and `available + allocated > 0`. Store UTC; display
Casablanca time. Do not persist an overdue flag or schedule a job.

**Rationale**: The rule is deterministic and cheap over 500 indexed receipts. Derived
status cannot become stale or require queue/scheduler infrastructure.

**Alternatives considered**: Scheduled status updates (rejected: stale/failure-prone);
browser calculation (rejected: non-authoritative and timezone-sensitive).

## Decision 11: Use three pages and ordinary Inertia forms

**Decision**: Implement one work queue, one receive form, and one receipt workspace.
Every mutation uses Inertia `<Form>` with a Wayfinder action and redirects back with the
existing toast/error pattern. No JSON API, polling, modal workflow, or optimistic update.

**Rationale**: Three pages cover every story while reusing the installed starter-kit
layout and primitives. Server redirects keep current stock authoritative.

**Alternatives considered**: Separate page per operation (rejected: navigation/file
sprawl); one modal-heavy dashboard (rejected: complex state); client state store
(rejected: duplicate inventory truth).

## Decision 12: Keep preparation separate from dispatch

**Decision**: Preparation sets exact prepared quantity and readiness on the allocation
and group. It does not change receipt buckets or Order status. No dispatch Action or
route exists in this feature.

**Rationale**: This implements the clarification and gives delivery orchestration one
exclusive owner for physical handoff and allocated-to-dispatched transitions.

**Alternatives considered**: Dispatch from the hub feature (rejected: duplicate owner);
allow both phases to dispatch (rejected: replay/race ambiguity).

## Decision 13: Verify with Action/HTTP layers and a real-engine concurrency gate

**Decision**: Use direct Pest tests for every Action and focused HTTP feature tests for
the request boundary. Use manual browser acceptance because Pest Browser is not
installed. Run allocation concurrency coverage against MySQL/PostgreSQL before release;
SQLite tests exercise compare-and-set and unique constraints but cannot prove row locks.

**Rationale**: This satisfies the constitution without a new test dependency or a false
concurrency claim.

**Alternatives considered**: Browser package (rejected: dependency approval required);
SQLite-only concurrency claim (rejected: engine semantics differ); one end-to-end test
(rejected: poor failure isolation).
