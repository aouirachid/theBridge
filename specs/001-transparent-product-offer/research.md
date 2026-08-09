# Phase 0 Research: Transparent Product Offer

## Decision 1: Minimal authorization model

- **Decision**: Add `users.is_sourcing_operator` as a boolean, expose an `operator()`
  factory state, and authorize all staff operations through `ProductOfferPolicy`.
- **Rationale**: Fortify authentication exists, but the repository has no roles,
  permissions, gates, or staff model. One non-fillable flag and one policy meet Phase 1
  without a dependency or general permissions system.
- **Alternatives considered**: A permissions package was rejected because dependencies
  require approval and Phase 1 has one staff role. A string role system was rejected as
  speculative future scope. Controller-only checks were rejected as insecure.

## Decision 2: Three-table domain model

- **Decision**: Use `product_offers`, `offer_cost_components`, and
  `benchmark_comparisons`. A product-offer row starts as a draft and becomes an immutable
  publication. A benchmark-comparison row starts recorded and becomes a reviewed,
  published comparison.
- **Rationale**: This preserves inputs/results and separate benchmark refreshes with the
  fewest tables. One-way timestamps make lifecycle states explicit.
- **Alternatives considered**: Separate draft and snapshot tables were rejected as
  duplication. Separate market-observation and published-comparison tables were rejected
  because the same row can safely represent review state. JSON cost storage was rejected
  because uniqueness, ordering, validation, and public breakdown queries become opaque.

## Decision 3: Exact money without a dependency

- **Decision**: Accept decimal strings, parse them into integer centimes, store all
  MAD/kg amounts as integers, store percentage hundredths as integers, and calculate with
  one pure integer calculator.
- **Rationale**: PHP floats are ambiguous and `bcmath` is not a Composer requirement.
  Integer centimes exactly represent the specification's two-decimal inputs and work on
  every supported database.
- **Alternatives considered**: PHP floats were rejected for reproducibility. `bcmath`
  was rejected because production availability is not guaranteed. `brick/math` is only a
  transitive dependency and was rejected to avoid relying on an undeclared package.

## Decision 4: Immutable one-way publication

- **Decision**: Use transactions and row locks in publish/replacement actions. Draft
  fields and costs may change only while `published_at` is null. Publishing a replacement
  sets the prior offer's `superseded_at`. Publishing a comparison sets the prior current
  comparison's `superseded_at`. Repeating an already completed request returns the same
  result and creates nothing.
- **Rationale**: This gives all-or-nothing, reproducible, idempotent behavior without
  queues, events, or distributed locks.
- **Alternatives considered**: Editable published rows were rejected because they rewrite
  claims. Application-only checks without transactions were rejected because concurrent
  requests could duplicate publications. Event sourcing belongs to Phase 6.

## Decision 5: Timestamp-based visibility, no cleanup job

- **Decision**: Store timestamps in UTC and compare rolling durations. A current
  comparison is shown only while its observation is no more than 24 hours old. Superseded
  offers/comparisons are historical (never used as the current claim), marked
  superseded, and public for 30 days after supersession; afterward they are staff-only.
  Retain all rows indefinitely for this phase.
- **Rationale**: Read-time visibility is deterministic and needs no scheduler. Retaining
  longer than 30 days satisfies the minimum retention requirement and supports later
  ledger work.
- **Alternatives considered**: Scheduled deletion was rejected as needless risk and
  infrastructure. Showing a stale comparison as current was rejected as misleading.
  Hiding an entire offer when its benchmark expires was rejected by clarification.

## Decision 6: Explicit Actions and thin HTTP adapters

- **Decision**: Implement the ten concrete Actions mapped in `plan.md`; each read or
  mutation controller method invokes exactly one Action. Use dedicated mutation Form
  Requests and a pure calculator helper.
- **Rationale**: This satisfies the constitution and leaves a low-capability agent an
  explicit, independently testable path.
- **Alternatives considered**: Business logic in controllers conflicts with project
  rules. A generic CRUD service or repository would obscure use-case boundaries.

## Decision 7: Inertia UI using existing primitives

- **Decision**: Build `operator/offers/index`, `operator/offers/manage`, and
  `offers/show`. Use existing AppLayout for operator pages, no app layout for the public
  page, existing UI components, `<Form>`, nested input names, and Wayfinder form variants.
- **Rationale**: These are already installed and used. The manage page can render fixed
  standard costs, optional custom rows, saved server review values, benchmark recording,
  and publish buttons without a client state architecture.
- **Alternatives considered**: A JSON API, Axios, React Query, optimistic updates, and
  client-side calculation were rejected as unnecessary or contrary to server authority.

## Decision 8: Focused automated verification

- **Decision**: Direct Pest unit tests for every Action and the calculator; three grouped
  HTTP/Inertia feature files; existing frontend format/lint/type/build checks.
- **Rationale**: This is the project's mandated test layering. Frozen time covers the
  24-hour/30-day boundaries. Inertia assertions verify public allowlisting.
- **Alternatives considered**: Browser tests were rejected because no browser plugin or
  suite exists and adding one needs approval. Snapshot tests were rejected because exact
  behavior assertions are clearer.

## Decision 9: Bounded queries and request limits

- **Decision**: Operator index returns the latest 50 offers; public show returns one
  offer, at most 14 costs, and at most 30 historical comparisons. Use eager loading and
  selected columns. Apply 120 public views/minute/IP and 60 operator requests/minute per
  user+IP.
- **Rationale**: Bounds prevent accidental unbounded loads and are ample for the demo.
- **Alternatives considered**: Pagination was rejected for Phase 1's small operator
  dataset. Unlimited queries and unthrottled public routes conflict with project rules.

## Documentation findings

- Laravel 13 supports decimal migrations, Form Request authorization, policies, named
  route limiters, scoped/custom-key route binding, transactions, and HTTP assertions.
- Inertia 3 supports nested form arrays, redirect-based server validation, explicit page
  props, and detailed `assertInertia`/`missing` assertions.
- Wayfinder form variants are already enabled in `vite.config.ts`; generated files must
  not be edited manually.
- Tailwind 4 is CSS-first and the project already supplies semantic theme tokens and UI
  primitives.
- Pest 5 and the Laravel plugin are installed; browser testing is not installed.

No unresolved research items remain.
