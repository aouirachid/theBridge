# Research: Delivery Orchestration

## Decision 1: Treat Phases 001–004 as a hard implementation prerequisite

**Decision**: Phase 005 consumes the real upstream `Order`, `OrderGroup`,
`StockAllocation`, `HubReceipt`, enums, transitions, and operations permission. The
implementation must stop before generating Phase 005 code when any prerequisite is
missing.

**Rationale**: The current repository contains only the starter/auth application. Making
local substitutes would create duplicate order and inventory truths that later phases
could not reconcile. A visible gate is the simplest safe instruction for a weaker agent.

**Alternatives considered**:

- Add temporary Phase 005 models: rejected because they would mislead the implementation
  and require destructive reconciliation later.
- Implement missing earlier phases inside Phase 005: rejected because it destroys the
  independently testable Spec Kit phase boundaries.

## Decision 2: Use one Dispatch aggregate for B2B and B2C

**Decision**: One `dispatches` table stores the common lifecycle, cost, provider mode,
safe reference, version, and timestamps. `dispatch_orders` represents included orders,
stable B2B stop sequence, quantity, and exact order-level cost allocation.

**Rationale**: Both channels share the exact status graph, replay rules, pickup/delivery
effects, cost reconciliation, privacy policy, and staff views. One aggregate prevents
two parallel lifecycle implementations. A real membership entity is required because a
B2B manifest has many orders and each membership owns cost.

**Alternatives considered**:

- Separate Manifest and ProviderDispatch models: rejected because it duplicates status,
  cost, history, retry, policy, and UI code.
- Add `dispatch_id` directly to Orders: rejected because it cannot store a stable stop
  sequence and cost allocation cleanly.
- Generic polymorphic delivery entities: rejected as unnecessary abstraction for two
  fixed channels.

## Decision 3: Generate all ready channel work with one deterministic Action

**Decision**: `GenerateDispatchesAction` accepts one service date, creates or refreshes
one ready B2B manifest per zone, and creates one ready B2C Dispatch per eligible Order.
It processes at most 500 Orders in stable group/Order order.

**Rationale**: One operator control matches the demo and gives one bounded, testable
eligibility query. A private unique scope hash provides one B2B dispatch per date/zone
and one B2C dispatch per Order. A unique `dispatch_orders.order_id` is the portable final
defense against duplicate membership.

**Alternatives considered**:

- Generate B2B and B2C from separate screens/actions: rejected as extra UI and duplicate
  eligibility logic.
- Create dispatch rows on GET: rejected because reads must not mutate state.
- Create supplementary late B2B manifests: rejected by clarification; submitted
  manifests are frozen and late work is flagged.

## Decision 4: Keep the provider boundary in-process and deterministic

**Decision**: A small `DeliveryProvider` interface has `submit` and `simulate` methods
with explicit array-shape contracts. `MockDeliveryProvider` is the only binding. It
performs no HTTP call, uses no secret, returns a 25.00 MAD demo quote, and derives
`MOCK-<12 hex>` from the safe Dispatch UUID. Authorized controls deterministically
simulate pickup, delivery, or failure.

**Rationale**: Laravel 13 container interface binding supplies substitution without a
registry or package. Fixed safe inputs make the demo credential-free and repeatable. The
mock may run inside the database transaction because it performs no I/O; this makes the
MVP easier to implement atomically.

**Alternatives considered**:

- Use Laravel HTTP client against a local fake endpoint: rejected because it adds a
  network boundary and failure modes without demo value.
- Queue provider work: rejected because the mock is immediate, the feature is bounded,
  and a queue would complicate idempotency and the demo.
- Add Glovo/Yassir classes or credentials: rejected as dishonest and explicitly out of
  scope.

## Decision 5: Persist no copied customer PII in dispatch tables

**Decision**: Dispatch rows store only internal relationships, safe UUIDs, coded zone,
service date, quantities, costs, status, and provider-safe references. Authorized detail
views and the provider submission transiently read the minimum encrypted Order fields.
The mock ignores them and does not return, persist, hash, or log them.

**Rationale**: Upstream Orders already protect the source PII. Copying it creates a
second retention and encryption surface. Laravel encrypted casts produce variable-size,
non-queryable ciphertext, so the source record remains the correct owner.

**Alternatives considered**:

- Persist a full provider payload: rejected for privacy, replay, and logging risk.
- Send only redacted placeholders to the mock: rejected because it would not exercise
  the provider-neutral minimum-data boundary.
- Put PII in jobs or request context: rejected; there is no queue and hidden context is
  unnecessary for an in-process call.

## Decision 6: Use one explicit status graph and append-only transition history

**Decision**: Current status lives on Dispatch; every accepted change appends a
`DispatchStatusTransition`. Valid paths are ready -> submitted; submitted -> accepted or
failed; accepted -> picked_up or failed; picked_up -> delivered or failed; and a
pre-pickup failed dispatch -> submitted on retry. Delivered and post-pickup failed are
terminal.

**Rationale**: One graph exactly matches the clarified specification and allows a simple
conditional update. Transition rows retain the audit history while current status keeps
queries cheap.

**Alternatives considered**:

- Generic state-machine package: rejected because six fixed states do not justify a
  dependency.
- Derive current status from history: rejected because every list/filter would require
  a latest-transition query.
- Reverse stock after a post-pickup failure: rejected because it contradicts physical
  custody and the clarification.

## Decision 7: Track partial dispatch progress on StockAllocation

**Decision**: Phase 005 adds `dispatched_quantity_hundredths` to StockAllocation. Pickup
increments it by included Order quantities, decrements the HubReceipt allocated counter,
increments its dispatched counter, and marks the allocation/group fully dispatched only
when the full allocation quantity has moved.

**Rationale**: Phase 003 may put several B2C Orders in one OrderGroup, while Phase 005
creates one B2C Dispatch per Order. A single `dispatched_at` flag cannot represent
partial pickup. One counter is the smallest extension that preserves Phase 004's stock
equation.

**Alternatives considered**:

- Mark the whole group dispatched on the first B2C pickup: rejected because stock and
  remaining Orders would become false.
- Split upstream groups/allocations per Order: rejected because it rewrites completed
  earlier phases.
- Add a new inventory-movement subsystem: rejected as overbuilt for one counter and
  existing append-only dispatch/order history.

## Decision 8: Use database constraints plus operation fingerprints for idempotency

**Decision**: Private scope hashes and unique order membership protect generation.
Submission attempts and operator/mock status controls hash raw UUID operation tokens and
canonical safe scalar payloads. Same-token/same-payload returns the existing result;
same-token/different-payload conflicts. Dispatch lifecycle versions and affected counts
protect stale/concurrent writes.

**Rationale**: Cache locks and UI disabled states are not correctness boundaries. Unique
indexes, conditional writes, locks where supported, and rollback work portably. Payload
fingerprints exclude all PII.

**Alternatives considered**:

- Trust client buttons or cache locks: rejected because retries and concurrent workers
  can bypass both.
- Store raw operation tokens/payloads: rejected because tokens and PII must not persist
  or reach logs.
- Create a generic idempotency framework: rejected as unnecessary scope.

## Decision 9: Allocate money with integers and largest remainders

**Decision**: Store all dispatch costs in MAD centimes. B2C assigns the full cost to its
single Order. B2B computes `base = intdiv(totalCost * orderQuantity, totalQuantity)`,
ranks fractional remainders descending, ties by Order public UUID, and distributes one
centime to each highest remainder until the exact cost is exhausted.

**Rationale**: This implements the clarification exactly, preserves proportionality,
and always reconciles. Bounds in `config/dispatch.php` keep 64-bit multiplication safe.

**Alternatives considered**:

- Floats/decimal database division: rejected because rounding would vary and violate
  fixed-precision project rules.
- Put every remainder on the first/largest Order: rejected as less proportional.
- Let operators assign remainders: rejected as non-deterministic.

## Decision 10: Use two ordinary Inertia pages and synchronous redirects

**Decision**: Index owns filters, generation, summaries, and dispatch pagination. Show
owns stops/orders, attempts, status history, submission/retry, and allowed outcomes.
Mutations use `<Form>` with Wayfinder and redirect back with the existing toast shape.
No polling, deferred data, optimistic updates, or custom CSS.

**Rationale**: The workload is staff-only, bounded, and manually driven. Ordinary page
refreshes keep server state authoritative and are easier for a low-capability agent to
implement and test. Existing Tailwind v4 primitives already cover responsive tables,
focus, and dark mode.

**Alternatives considered**:

- Dashboard plus separate manifest/request/history pages: rejected as unnecessary
  navigation and prop duplication.
- JSON API with `useHttp`: rejected because ordinary Inertia form redirects already fit
  the project.
- Polling/live updates: rejected because provider events are operator-triggered in the
  mock demo.

## Decision 11: Test Actions directly and keep browser verification manual

**Decision**: Each of five Actions has direct Pest unit coverage. Two feature files
cover HTTP wiring and privacy. Pure allocator/money/provider tests cover exact math and
mock determinism. Run static frontend checks and the manual quickstart UI flow.

**Rationale**: The constitution requires distinct Action and HTTP layers. Pest 5 is
installed, but the browser plugin is not; adding it would violate the no-new-dependency
constraint. Laravel's HTTP fakes are not needed because Phase 005 performs no HTTP call;
tests should prevent or detect any stray provider network usage.

**Alternatives considered**:

- Add Pest browser/Playwright dependencies: rejected because this phase does not need a
  new dependency.
- Test only controllers: rejected because it misses Action business rules and violates
  the constitution.
- Test only Actions: rejected because middleware, authorization, validation, and Inertia
  props would remain unverified.
