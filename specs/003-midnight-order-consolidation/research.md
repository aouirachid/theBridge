# Research: Midnight Order Consolidation

## Upstream prerequisite and product identity

**Decision**: Treat Phase 001 and Phase 002 as hard prerequisites. Implementation stops
unless their ProductOffer, Order, order lifecycle, offer-cost, operations permission,
routes, and tests exist and pass. For the MVP, `ProductOffer.id` is the stable product
scope used for grouping and procurement.

**Rationale**: The repository currently contains only the Laravel starter/auth domain;
Phases 001 and 002 are specifications, not implemented code. Their design contains no
separate Product model. ProductOffer is the only stable identity connected to Order and
its immutable price/cost publication. Grouping by crop text would be unsafe, while adding
a Product catalog would expand three phases and make implementation harder.

**Alternatives considered**:

- Add a Product model and retrofit Phases 001/002: rejected as unnecessary MVP scope.
- Group by normalized crop label: rejected because labels are not identities.
- Build temporary Phase 003 offer/order models: rejected because it creates duplicates
  and breaks the upstream/downstream contracts.

## Architecture and execution model

**Decision**: Use seven explicit Actions: list cycles, show one cycle, start scheduled,
start manual, run/retry the core cycle, decide one under-minimum candidate, and update
the cutoff. One Artisan command calls the scheduled-start Action. The scheduled and
manual start Actions both call the same core run Action. Use no queue, repository,
generic workflow engine, or new dependency.

**Rationale**: This exactly follows the project's Action-first rule while giving every
entry point one obvious file and one direct test. Consolidation is bounded to 500 orders
and must complete synchronously for the demo. A queue would add operations and recovery
states without improving the required outcome.

**Alternatives considered**:

- One large controller or command: rejected because business rules would be difficult to
  test and would violate the constitution.
- A service/repository/DTO layer: rejected because it adds indirection without another
  implementation or persistence boundary.
- Queued consolidation: rejected because no external call or unbounded work requires it.

## Cycle as the ordering-window lock

**Decision**: Use one `consolidation_cycles` row per service date. It begins in `open`
state and is the shared ordering-window record. Order confirmation first-creates and
locks/checks this row. Creation snapshots the applicable scheduled cutoff. Consolidation
locks the same row, records the authoritative effective cutoff without re-resolving the
schedule, and moves it out of `open`. Every non-open status rejects later order
confirmation for that service date.

**Rationale**: A single row serializes order confirmation against scheduled or early
closure. It also avoids a separate ordering-windows table and makes the database unique
constraint on `service_date` the one-cycle-per-window invariant.

**Alternatives considered**:

- Separate ordering-window and cycle tables: correct but redundant for the one-hub MVP.
- Check only whether a completed cycle exists: rejected because a confirmation can race
  with an awaiting or running cycle.
- Rely only on a cache lock: rejected because cache locks are an optimization, not the
  durable data invariant.

## Casablanca cutoff scheduling

**Decision**: Keep application storage time in UTC and interpret business time with
`Africa/Casablanca`. Store cutoff changes as append-only effective-dated rows. With no
row, the default is 00:00. A change made by an operations manager becomes effective on the
next Casablanca operating date. Schedule one command every minute; it derives whether
the current operating date's cutoff is due and targets the next Casablanca calendar
date. Use scheduler `withoutOverlapping(10)` and `onOneServer()` with the existing
database cache, but rely on database invariants for correctness.

**Rationale**: A once-per-minute due check supports database-configured times, scheduler
jitter, and Morocco offset changes without rebuilding the schedule. Each cycle snapshots
its operating date, scheduled local time, timezone, configuration reference, and
scheduled UTC instant when first created. Closure adds its effective instant. A later
setting change therefore never moves an already-created boundary.

**Alternatives considered**:

- Dynamically register `dailyAt()` from the current setting: rejected because schedule
  registration can become stale and catch-up behavior is less explicit.
- Change the application timezone from UTC: rejected because UTC storage is already the
  project convention.
- Run in the background or queue: rejected because overlap protection plus bounded
  synchronous work is sufficient.

## Cutoff configuration history

**Decision**: Store immutable `consolidation_cutoff_changes` rows containing previous
and new minute-of-day values, effective operating date, actor, and decision time. Select
the latest `(effective_on, id)` row applicable to an operating date. Multiple same-day
changes remain in history; the latest row is effective. Repeating the same requested
pending value returns the existing latest change instead of appending a duplicate.

**Rationale**: This is simpler than a mutable singleton plus audit table and directly
preserves the history required by the specification.

**Alternatives considered**:

- Mutable singleton configuration: rejected because a second audit table would still be
  required.
- Environment-only configuration: rejected because clarification requires a protected
  operations control.

## Deterministic preview and under-minimum decisions

**Decision**: Build candidate groups in the fixed sort order `product_offer_id`,
`channel`, `delivery_zone`, then order ID. Persist one non-PII candidate summary per
group and preview generation. A group fingerprint hashes its stable key, sorted order
IDs, immutable quantities/prices, and effective minimum. The generation fingerprint
hashes the ordered group fingerprints. Candidate rows hold approve/exclude decisions,
actor, decision time, and a fixed safe exclusion reason.

**Rationale**: A persisted generation lets an operator approve exactly what was
reviewed. If cancellation or another lifecycle change alters demand, recomputation
produces a new fingerprint; earlier candidates remain audit history but do not authorize
the new generation. No JSON payload or customer data needs to be stored.

**Alternatives considered**:

- Store candidate order lists as JSON: rejected because relational constraints and
  querying become weaker.
- Delete and rebuild stale candidates: rejected because it discards decision history.
- Hold a database transaction open while awaiting an operator: rejected because it is
  unsafe and impractical.

## Delivery minimum configuration

**Decision**: Add `config/consolidation.php` with one integer-hundredths minimum for each
existing OrderChannel and DeliveryZone combination. Use 1,000 hundredths (10.00 kg) as
the explicit demo default for every combination. Persist the effective minimum on each
candidate. Editing these thresholds remains deployment configuration, outside this
feature's operator UI.

**Rationale**: The clarification requires separate channel-zone rules but not an editing
workflow. A small configuration matrix is the least complex implementation and keeps
all quantity math integral and reproducible.

**Alternatives considered**:

- A database CRUD UI for minimum rules: rejected as unrequested scope.
- One global threshold: rejected by clarification.
- Environment variables for six individual values: rejected as cumbersome and harder
  to inspect during the demo.

## Reconciliation and numeric precision

**Decision**: Sum existing integer `quantity_hundredths`, `total_minor`, and source offer
delivery-allocation `amount_minor` values; never use floats. Group commercial totals are
the sum of immutable Order totals. The estimated delivery allocation per kilogram uses
quantity-weighted integer arithmetic and one half-up rounding at the final centime/kg.
Persist candidate and completed group aggregates, while the Order relationship remains
the authoritative source for drill-down and price snapshots.

**Rationale**: This matches upstream price/quantity representation, preserves the
customer's accepted terms, and makes every aggregate testable. With ProductOffer as the
MVP product scope, all orders in one group normally share one delivery-allocation value,
but the weighted formula remains correct and explicit.

**Alternatives considered**:

- Decimal or floating-point arithmetic: rejected because it risks reconciliation drift.
- Copy every order price snapshot into a membership table: rejected because the immutable
  Order already owns the snapshot and one nullable FK proves membership.

## Idempotency, transactions, and concurrency

**Decision**: Serialize a service date opportunistically with a 120-second cache lock
and five-second wait. Enforce correctness with database unique constraints, stable lock
order, short `DB::transaction(..., attempts: 3)` blocks, conditional order updates, and
exact affected-row assertions. `lockForUpdate()` is used where supported but is not the
correctness boundary because SQLite does not provide equivalent row locking.

Required unique constraints:

```text
consolidation_cycles(service_date)
consolidation_candidates(cycle_id, generation_hash, group_key_hash)
procurement_requirements(cycle_id, product_offer_id)
order_groups(procurement_requirement_id, channel, delivery_zone)
```

An Order has one nullable `order_group_id`, so it cannot belong to multiple groups. The
final transaction re-queries eligible orders and recomputes fingerprints, creates all
included outputs, conditionally updates exactly the expected Orders from confirmed/null
group to grouped/group ID, appends transitions for only claimed rows, verifies every
aggregate, and commits. Any mismatch throws and rolls back. A completed retry returns the
existing result.

**Rationale**: Unique and conditional database writes remain correct across SQLite,
MySQL, and PostgreSQL. Scheduler/cache locks reduce duplicate work but are not trusted as
the sole invariant.

**Alternatives considered**:

- Pessimistic locks only: rejected because SQLite treats them differently.
- Upsert without real unique indexes: rejected because engine behavior depends on actual
  indexes.
- Partial commits per product/group: rejected because they expose incomplete cycles.

## Authorization, privacy, and abuse controls

**Decision**: Reuse Phase 002's non-fillable `users.is_operations_operator` for ordinary
consolidation operations and add a distinct non-fillable `users.is_operations_manager`
permission for viewing/changing cutoff configuration. A manager is also an operator.
Require `auth`, `verified`, policy/Form Request authorization, CSRF, and one named
30/minute user-plus-IP mutation limiter. Use explicit Inertia prop arrays and random
public UUID route keys. Never decrypt customer contact fields for cycle lists,
candidates, groups, or reconciliation.

**Rationale**: The clarified specification explicitly separates cutoff authority from
ordinary cycle operation. Two booleans keep that distinction simple and make server-side
policy checks independent of UI visibility.

**Alternatives considered**:

- Reuse `is_operations_operator` for cutoff access: rejected because it contradicts the
  distinct operations-manager requirement.
- Expose full Order models: rejected because encrypted PII and internal identifiers must
  not leak.

## Operator interface

**Decision**: Add exactly two Inertia pages: an index for cutoff, manual run, filters,
and recent cycles; and a show page for candidates, decisions, reconciliation, groups,
and a selected group's paginated source orders. Use existing Cards, Buttons, Badges,
Inputs, Labels, Select, Dialog, Spinner/Skeleton, flash toast, AppLayout, and semantic
tables. All routes/forms use generated Wayfinder functions. Add no CSS or UI dependency.

**Rationale**: Two pages expose the complete demo and recovery flow without a separate
settings area, group page, or component hierarchy.

**Alternatives considered**:

- One page per use case: rejected because it creates unnecessary navigation and files.
- Optimistic decisions or polling: rejected because server state and reconciliation are
  authoritative.
- Browser-test dependency: rejected because none is installed; use feature tests and a
  manual responsive/accessibility flow.

## Testing strategy

**Decision**: Write one direct Pest unit test per Action, focused pure-support tests for
schedule/preview/quantity math, and two HTTP feature files: operator flows and scheduled
command wiring. Use frozen time for Casablanca boundaries, factories for all domain
state, Inertia prop assertions, and Phase 002 regression suites. Run a real MySQL or
PostgreSQL concurrency check before release; SQLite remains the fast local suite.

**Rationale**: This meets the constitution's distinct Action and HTTP layers without
duplicating every business permutation at the route level. A production-engine check is
needed because SQLite concurrency semantics differ.

**Alternatives considered**:

- HTTP tests only: rejected because they hide Action behavior and violate project rules.
- Browser tests: rejected because the plugin is not installed and no dependency was
  approved.
