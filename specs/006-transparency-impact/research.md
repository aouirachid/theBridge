# Research: Transparency Ledger and Impact Dashboard

## Decision 1: Stop until Phases 001-005 exist

**Decision**: Treat the current starter-only application as a hard implementation block.
Phase 006 consumes the exact source models and Actions from the earlier artifacts and
must not introduce stand-ins.

**Rationale**: The ledger is evidence about real source operations. Building substitute
tables would create a second, misleading version of price and operational truth.

**Alternatives considered**:

- Build Phase 006 demo-only source tables: rejected because they would duplicate domain
  records and could not prove upstream operations.
- Append synthetic entries without source records: rejected because the dashboard would
  be an illustration, not an audit experience.

## Decision 2: Use one chain with a persisted head row

**Decision**: Use one `LedgerChain` row named `platform`. Lock it before every append,
assign `last_position + 1`, link the prior hash, insert the entry, and update the head in
one transaction.

**Rationale**: A persisted singleton gives the implementation agent one obvious lock and
solves the empty-chain and concurrent-first-entry cases without cache locks or advisory
database features.

**Alternatives considered**:

- Lock the latest entry: rejected because an empty chain has no row to lock.
- Cache lock: rejected because cache and database commit are not one atomic boundary.
- One chain per offer or event type: rejected because verification and public status
  become harder to explain and reconcile.

## Decision 3: Use deterministic SHA-256 over canonical safe data

**Decision**: `LedgerHasher` recursively sorts object keys, preserves ordered lists,
rejects non-scalar/arbitrary objects and floats, encodes one documented ordered envelope,
and returns lowercase SHA-256. The envelope includes version, chain, position, public
event ID, private idempotency key hash, event type, safe subject, canonical payload,
occurrence/append times, private actor ID, and previous hash.

**Rationale**: SHA-256 and canonical JSON are dependency-free, deterministic, easy to
test, and match the roadmap's tamper-evident rather than blockchain requirement.

**Alternatives considered**:

- HMAC/signatures: deferred because secret rotation and key provenance add scope; the
  app already blocks entry mutation and the MVP does not claim legal certification.
- Serialize whole models: rejected because order, hidden fields, and PII can change.
- Blockchain: explicitly out of scope.

## Decision 4: Append inside the source transaction

**Decision**: Each named Phase 001-005 Action constructs `LedgerEventData` from its
already-validated immutable facts and calls `AppendLedgerEntryAction` before its current
transaction commits.

**Rationale**: This is the smallest way to prevent a successful business operation from
existing without its required audit entry. Laravel transactions roll back both when any
part fails.

**Alternatives considered**:

- Queued or after-commit append: rejected because crashes can leave unaudited success.
- Observers/listeners: rejected because actor context, event meaning, and payload
  allowlists become hidden and difficult for a lower-capability agent to follow.
- Database triggers that build business payloads: rejected because domain semantics do
  not belong in database-specific SQL.

## Decision 5: Layer entry immutability for the SQLite MVP

**Decision**: Expose no mutation route, make `LedgerEntry` reject update/delete model
operations, and create SQLite `BEFORE UPDATE` and `BEFORE DELETE` triggers that abort raw
mutations through the configured app connection.

**Rationale**: Model protection alone can be bypassed by bulk/query-builder SQL. Two
small SQLite triggers prove the application service connection cannot mutate entries.

**Alternatives considered**:

- Model events only: rejected as insufficient for raw/bulk writes.
- Per-table database grants: preferred for a later server database, but SQLite has no
  database users or table grants.
- Cross-database trigger branches: deferred to avoid untested deployment complexity.

## Decision 6: Verify synchronously and scope every result to one endpoint

**Decision**: An operator mutation captures the chain head, checks entries from position
1 through that endpoint with a selected-column cursor, records valid/invalid/incomplete,
and redirects back. Limit verification to 10,000 entries and 30 seconds. A result is
current only while its endpoint position/hash equals the live head.

**Rationale**: The demo volume fits a normal request. Avoiding a queue removes worker,
polling, status, retry, and deployment complexity while still making new appends visibly
stale instead of incorrectly valid.

**Alternatives considered**:

- Queue job and polling: rejected as unnecessary infrastructure for 10,000 entries.
- Verify on every public request: rejected because it creates an abuse/cost path.
- Verify only the newest link: rejected because old alterations would go undetected.

## Decision 7: Derive the dashboard from source snapshots

**Decision**: `ShowPublicTransparencyAction` selects one public offer, its current
eligible published comparison, one completed consolidation scope, finalized groups,
dispatch memberships/costs, current indicator, and current integrity result. It formats
an explicit prop array; it does not persist a summary.

**Rationale**: A reporting copy would create reconciliation and invalidation work and
could drift from the data the ledger proves. The bounded one-offer scope makes direct
indexed aggregates sufficient.

**Alternatives considered**:

- Materialized summary table: rejected as duplicate truth for hackathon scale.
- Client-side calculations: rejected because public claims must be server-authoritative.
- Cache: rejected because invalidation around new events and verification can mislabel
  stale claims.

## Decision 8: Use two pages and no advanced Inertia behavior

**Decision**: Provide one guest transparency page and one authenticated operator page.
Use ordinary Inertia props, `<Form>` for two mutations, Wayfinder controller actions,
and existing UI components. No deferred props, polling, optimistic updates, custom CSS,
charts, or page-specific component hierarchy.

**Rationale**: Separate routes make the public/private boundary obvious, and two direct
pages are easier to implement and test than conditional private controls on a guest page.

**Alternatives considered**:

- One page with conditional operator controls: rejected because it complicates caching,
  props, and authorization review.
- JSON API plus React client fetching: rejected because Inertia already supplies the
  required web contract.

## Decision 9: Version only one supported impact indicator

**Decision**: The MVP supports `trip_reduction` only. Publishing inserts a new indicator
version and supersedes the prior version for the same completed cycle. `Measured`
requires source and evidence references; `Estimate` requires method, inputs,
assumptions, and limitation. Mixed inputs are estimated. No CO2 value is supported.

**Rationale**: This implements honest environmental labeling without introducing a
general metrics platform or unsupported emissions model.

**Alternatives considered**:

- Arbitrary indicator codes: rejected because validation could not prevent misleading
  claims.
- CO2 estimator: rejected because no defensible factor source is in scope.

## Decision 10: Seed through production Actions

**Decision**: `TransparencyDemoSeeder` calls `SeedTransparencyDemoAction`, which invokes
the existing Phase 001-005 Actions with deterministic tomato inputs. It never inserts
source or ledger rows directly. One server-owned `ProductOffer.is_demo` flag distinguishes
simulated offer economics from real offers; ordinary Requests cannot set it. Re-running
finds the completed scenario by its private demo actor and exact immutable signature.

**Rationale**: The final demo must prove the real path and append the same events as an
ordinary operation.

**Alternatives considered**:

- Factories in the seeder: rejected because factories may bypass business invariants.
- Raw inserts: rejected because they bypass Actions and the ledger integration.
- Inferring demo status only from the benchmark: rejected because a real offer may be
  compared with a simulated benchmark and the two claims require independent labels.
