# Feature Specification: Delivery Orchestration

**Feature Branch**: `N/A (no branch hook configured)`

**Created**: 2026-08-09

**Status**: Draft

**Input**: User description: "Orchestrate delivery for consolidated agricultural orders. Operations staff generate zone-based B2B manifests and provider-neutral B2C dispatch requests. A deterministic mock provider must support the complete demo without external credentials. Dispatch has traceable statuses, idempotent retries, explicit delivery-cost allocation, restricted personal data, and logs that never reveal sensitive customer or provider information."

## Clarifications

### Session 2026-08-09

- Q: What happens when another B2B order for the same date and zone becomes ready after manifest generation? → A: Refresh the manifest while it is ready; freeze it at submission.
- Q: How should the mock provider demonstrate B2C statuses after acceptance? → A: Authorized operators trigger deterministic simulated pickup, delivery, and failure outcomes.
- Q: What should the MVP allow when a delivery fails after pickup? → A: Keep the failure terminal, retain dispatched stock and order states, and flag manual follow-up outside this phase.
- Q: How should the internal mock provider handle customer personal data? → A: Process only the minimum handoff fields transiently, derive no result from them, and persist only safe references, cost, and status.
- Q: How should proportional B2B delivery-cost remainders be allocated? → A: Use the largest-remainder method and resolve equal remainders by safe order reference.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Generate Zone-Based B2B Manifests (Priority: P1)

An authorized dispatch operator selects prepared B2B work for a service date and
generates one consolidated manifest for each delivery zone. Each manifest presents the
stops in a stable order with the business, contact, delivery window, products, and
quantities needed for a physical handoff. The operator can reconcile every manifest
line with its prepared order and fulfillment allocation before releasing it.

**Why this priority**: Consolidated B2B distribution is the core operational benefit of
grouping professional demand by zone and is required for the end-to-end demonstration.

**Independent Test**: Prepare B2B orders in three zones, generate manifests twice, and
verify that exactly three manifests exist, every eligible order appears once in its
zone, totals reconcile, and the second generation creates no duplicate.

**Acceptance Scenarios**:

1. **Given** fully allocated and prepared B2B orders for one service date span three
   delivery zones, **When** an authorized dispatch operator generates manifests,
   **Then** exactly one manifest per zone is created with all and only the eligible
   orders for that service date and zone.
2. **Given** one zone contains multiple prepared products and buyers, **When** its
   manifest is generated, **Then** its stops are ordered deterministically and its
   order count and product quantities equal the source prepared work with zero
   unexplained variance.
3. **Given** a manifest is still ready, **When** generation is repeated after another
   eligible order in its service date and zone becomes prepared, **Then** the same
   logical manifest is refreshed to include that order once and its totals reconcile.
4. **Given** a manifest has been submitted, **When** another order in its service date
   and zone becomes prepared, **Then** the submitted manifest remains frozen and the
   late order is flagged for explicit operational resolution rather than silently added
   or dispatched.
5. **Given** an order group is not fully prepared or belongs to another channel, zone,
   or service date, **When** manifests are generated, **Then** that work is not included
   and its fulfillment state remains unchanged.

---

### User Story 2 - Demonstrate B2C Provider Dispatch (Priority: P2)

An authorized dispatch operator submits each eligible prepared B2C delivery through a
provider-neutral handoff. The demonstration provider accepts the minimum operational
delivery data, requires no external account or credential, and returns a repeatable
provider reference and outcome for the same logical delivery. The interface clearly
identifies the provider as a simulation rather than a live partner.

**Why this priority**: A credential-free, honest simulation proves the consumer
last-mile workflow without making the hackathon demo depend on an unavailable partner.

**Independent Test**: Submit a prepared B2C order to the demonstration provider in an
environment with no provider credentials and verify that it receives one simulated
reference, quote, and traceable status while repeated submission returns the same
logical dispatch.

**Acceptance Scenarios**:

1. **Given** a fully allocated and prepared B2C order, **When** an authorized operator
   submits it for delivery, **Then** one provider-neutral dispatch request is recorded
   and the demonstration provider returns a deterministic reference and accepted
   outcome without external credentials.
2. **Given** the same logical B2C delivery is submitted repeatedly or concurrently,
   **When** the demonstration provider handles the requests, **Then** one provider
   request and one active dispatch result exist and every response identifies the same
   provider reference.
3. **Given** a B2C order has a zone but no optional exact address, **When** it is sent to
   the demonstration provider, **Then** the demo remains operable using the zone and
   available contact details and does not claim that real-world address validation
   occurred.
4. **Given** a dispatch is shown to staff or in the final demonstration, **When** its
   provider is identified, **Then** it is visibly labeled as simulated and never
   represented as a live Glovo, Yassir, or other partner integration.
5. **Given** the demonstration provider has accepted a B2C dispatch, **When** an
   authorized operator triggers simulated pickup, delivery, or failure, **Then** the
   chosen deterministic provider outcome follows the same lifecycle and replay-safety
   rules as every other dispatch status change.
6. **Given** a B2C request requires customer handoff data, **When** it is processed by
   the demonstration provider, **Then** only the minimum fields are handled transiently,
   no result is derived from personal data, and provider or attempt records retain only
   safe references, cost, and status.

---

### User Story 3 - Track Handoff and Delivery Outcomes (Priority: P3)

An authorized dispatch operator follows B2B manifests and B2C deliveries through the
same traceable lifecycle: ready, submitted, accepted, picked up, delivered, or failed.
The operator sees a chronological history, safe references, the responsible source of
each change, and a clear recovery action. Physical pickup advances the related orders
and hub stock to dispatched exactly once; delivery completion advances the orders to
delivered exactly once.

**Why this priority**: Status traceability turns generated handoffs into an accountable
operation and prevents a screen or provider timeout from corrupting inventory or order
history.

**Independent Test**: Progress one B2B manifest and one B2C delivery from ready through
delivered, inject failures and retries at each non-terminal stage, and verify valid
status histories and exactly-once source order and stock transitions.

**Acceptance Scenarios**:

1. **Given** a ready dispatch, **When** valid handoff outcomes occur, **Then** its status
   follows ready to submitted to accepted to picked up to delivered and every accepted
   change is appended to its visible history with time and origin.
2. **Given** a dispatch has not been picked up, **When** submission or acceptance fails,
   **Then** it becomes failed, the failure is shown using a safe category, and its
   orders and allocated stock remain ready for a replay-safe retry.
3. **Given** a dispatch reaches picked up, **When** that transition is accepted or
   repeated, **Then** each included order and related stock quantity becomes dispatched
   exactly once and no duplicate stock movement or order transition is created.
4. **Given** a picked-up dispatch is confirmed delivered, **When** completion is
   accepted or repeated, **Then** its included orders become delivered exactly once and
   the completed dispatch cannot be resubmitted as a new delivery.
5. **Given** an invalid, stale, out-of-order, or concurrent status change, **When** it is
   attempted, **Then** it receives a safe conflict outcome and creates no partial
   dispatch, inventory, or order-state change.
6. **Given** a dispatch fails after pickup, **When** staff inspect or retry it, **Then**
   the failed state is terminal, its orders and stock remain dispatched, and it is
   flagged for manual follow-up outside this phase without an automatic return,
   redelivery, or delivered transition.

---

### User Story 4 - Reconcile Actual Delivery Cost (Priority: P4)

An authorized dispatch operator records the quoted or approved flat-rate delivery cost
before acceptance. B2C cost belongs to the individual delivery. A B2B manifest cost is
allocated across its included orders in proportion to their prepared kilograms, with
smallest-currency-unit remainders assigned to the largest fractional shares first and
equal fractional shares resolved by safe order reference. Staff can compare the actual
allocation with the delivery amount preserved in each confirmed price snapshot without
rewriting that historical price.

**Why this priority**: Explicit, exact allocation is necessary for the next transparency
report to explain delivery economics without hiding variance in margin.

**Independent Test**: Record B2C and B2B delivery costs containing a rounding remainder,
then verify that order allocations sum exactly to each dispatch cost, remain unchanged
on retry, and leave confirmed order prices untouched.

**Acceptance Scenarios**:

1. **Given** an eligible B2C delivery has an approved quote, **When** it is submitted,
   **Then** the full delivery cost is allocated once to that order and is available for
   later price and variance reporting.
2. **Given** a B2B manifest has a flat cost and includes orders with different kilogram
   quantities, **When** the cost is allocated, **Then** each order receives its
   proportional amount and the allocated smallest currency units sum exactly to the
   manifest cost.
3. **Given** proportional allocation produces a remainder, **When** allocation is
   calculated, **Then** remaining centimes are assigned to the largest fractional shares
   first, equal shares are ordered by safe order reference, and repeated calculation
   produces the same exact total.
4. **Given** a submission or status retry occurs, **When** the existing dispatch is
   replayed, **Then** no second cost or order allocation is created and no confirmed
   unit price or total is changed.

---

### User Story 5 - Inspect Dispatch Safely (Priority: P5)

Authorized dispatch staff can find work by service date, channel, zone, and status,
inspect a manifest or individual delivery, and see only the personal data necessary for
the active handoff. Other staff, public users, operational summaries, and diagnostic
records receive non-sensitive references and aggregates instead of names, phone
numbers, addresses, notes, request payloads, credentials, or provider secrets.

**Why this priority**: Delivery needs contact data, but unnecessary exposure would turn
an operational tool and its logs into a customer privacy risk.

**Independent Test**: Exercise authorized, unauthorized, not-found, validation,
provider-failure, and status-conflict paths and verify useful operator outcomes while
public responses, logs, analytics, and exceptions contain none of the protected values.

**Acceptance Scenarios**:

1. **Given** an authorized dispatch operator opens active delivery work, **When** they
   inspect a B2B stop or B2C request, **Then** they see only the contact, zone, optional
   address or note, quantity, and window needed for that specific handoff.
2. **Given** a guest or authenticated user without dispatch permission requests a
   manifest or delivery, **When** access is evaluated, **Then** access is denied without
   confirming protected record details or exposing any personal data.
3. **Given** validation, provider, concurrency, or unexpected processing fails, **When**
   diagnostics and the operator outcome are recorded, **Then** they contain safe
   dispatch references, operation names, result categories, and timing but no complete
   customer or provider-sensitive values.
4. **Given** staff view an aggregate work queue or reconciliation summary, **When** it is
   displayed, **Then** it contains counts, quantities, costs, zones, statuses, and safe
   references without unrelated customer contact data.

### Edge Cases

- Only fully allocated order groups with confirmed preparation are eligible. Grouped,
  partially allocated, released, cancelled, already dispatched, or delivered work is
  excluded without changing its state.
- A B2B manifest is unique for one service date and delivery zone. While ready, repeated
  generation refreshes that same manifest with newly prepared eligible orders. On
  submission, its stops, sequence, quantities, and cost allocation become frozen. Work
  prepared later is flagged for explicit operational resolution and never silently
  added to the submitted manifest. Every included order appears exactly once, even when
  a buyer has multiple products or orders. Stops remain distinguishable by their safe
  order references because the MVP does not merge buyer orders or optimize routes.
- B2B order capture supplies business identity, contact, zone, and delivery window but
  no exact street address. The manifest must honestly show the available destination
  data and must not invent coordinates, a route, or an address.
- B2C exact address and delivery note are optional upstream. The demonstration provider
  accepts a zone-only request; a future live provider may impose stricter prerequisites,
  but those requirements do not enter this MVP.
- A manifest or request with zero orders, mixed channels, the wrong service date or
  zone, mismatched quantities, or an unexplained fulfillment variance cannot be
  released.
- Delivery cost is a non-negative MAD amount with no more than two decimal places. A
  dispatch cannot advance to accepted without a cost source identified as provider
  quote or approved flat rate.
- A zero delivery cost is allowed only when explicitly recorded; absence of a cost is
  not treated as zero.
- B2B cost allocation uses prepared kilograms. A zero-kilogram line is invalid, and
  allocation never uses order price or contact data as a weighting factor. After each
  proportional share is reduced to whole centimes, remaining centimes go to the largest
  fractional shares first; equal fractional shares are ordered by safe order reference.
- Repeated or concurrent generation, submission, retry, status progression, pickup,
  delivery completion, and cost allocation cannot create duplicate manifests, provider
  requests, histories, stock movements, order transitions, or allocated costs.
- The only valid status changes are ready to submitted; submitted to accepted or failed;
  accepted to picked up or failed; picked up to delivered or failed; and failed back to
  submitted only when the failure occurred before pickup and the source work remains
  eligible for retry. Delivered and a failure after pickup are terminal. A failure
  before pickup leaves stock allocated; a failure after pickup leaves orders and stock
  dispatched and is flagged for manual follow-up outside this phase.
- A timeout or ambiguous provider result must remain safely reconcilable against the
  existing logical request before any retry; uncertainty is never resolved by creating
  a new dispatch identity.
- The deterministic demonstration provider returns the same reference and quote for the
  same logical request and the same result when an outcome control is replayed against
  the same lifecycle state. Different eligible requests receive distinguishable
  references without relying on customer personal data.
- Unsupported status values, invalid times, malformed references, overlong or hostile
  notes, unexpected fields, and attempts to submit another dispatch's identifiers are
  rejected without partial changes.
- Removing personal data under the approved retention policy must not erase the
  non-personal dispatch, quantity, cost, status, or reconciliation history needed for
  auditability.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Authorized dispatch operators MUST be able to list bounded delivery work
  filtered by service date, channel, delivery zone, and dispatch status.
- **FR-002**: Only an order group that is fully allocated, fully prepared, and marked
  ready for dispatch by the fulfillment workflow MUST be eligible for orchestration.
- **FR-003**: The system MUST generate exactly one B2B manifest for each service-date and
  delivery-zone combination represented by eligible B2B work.
- **FR-004**: Each B2B manifest MUST contain a stable safe reference, service date,
  delivery zone, status, cost, aggregate order and kilogram totals, and a deterministic
  sequence of stops.
- **FR-005**: Each B2B stop MUST identify its safe source order, business, operational
  contact, selected delivery window, available destination information, product,
  quantity, and any relevant handoff note without inventing absent location data.
- **FR-006**: B2B manifest order counts and quantities MUST reconcile exactly with the
  eligible prepared source orders and allocations before the manifest can be released.
- **FR-007**: Generating B2B manifests repeatedly or concurrently for the same scope MUST
  refresh the same ready manifest with all currently eligible work without duplicate
  manifests or stops. Submission MUST freeze its stops, sequence, totals, and cost
  allocation; later-prepared work MUST be flagged for explicit operational resolution.
- **FR-008**: The system MUST create exactly one provider-neutral B2C dispatch request
  for each eligible prepared B2C order.
- **FR-009**: A B2C dispatch MUST have a stable non-personal request identity. The
  provider-bound handoff MUST assemble only the minimum product, quantity, service
  window, zone, available destination, contact, and handoff-note data necessary for the
  active delivery from the protected source order; dispatch, attempt, and provider-result
  records MUST NOT duplicate those personal fields.
- **FR-010**: A deterministic demonstration provider MUST complete the full B2C demo
  without external credentials, network availability, or claims of a live partner
  relationship and MUST give authorized operators explicit controls to simulate pickup,
  delivery, and failure outcomes.
- **FR-011**: For the same logical B2C request, the demonstration provider MUST return
  the same provider reference and quote. Repeating the same simulated outcome control
  MUST NOT duplicate a status change or related business effect; distinct requests MUST
  remain distinguishable and all results MUST be derived without using personal data.
- **FR-012**: Every dispatch MUST use the statuses ready, submitted, accepted, picked
  up, delivered, and failed, and MUST reject transitions that do not follow the defined
  lifecycle.
- **FR-013**: Every accepted status change MUST append its previous and resulting
  status, occurrence time, source, safe outcome, and private actor attribution where a
  staff member initiated it.
- **FR-014**: Provider outcomes and authorized staff confirmations MUST be correlated to
  the intended dispatch before they can change status; an unknown, stale, duplicate, or
  mismatched outcome MUST NOT change any dispatch.
- **FR-015**: The picked-up transition MUST move each included source order and its
  corresponding allocated hub quantity to dispatched exactly once as one complete
  operation.
- **FR-016**: The delivered transition MUST move each included dispatched order to
  delivered exactly once and MUST NOT alter hub quantity a second time.
- **FR-017**: A failed pre-pickup dispatch MUST retain its prepared allocation for retry;
  a failure after pickup MUST be terminal for this feature, retain the dispatched stock
  and order states, and be flagged for manual follow-up without automatic return,
  redelivery, or later delivery completion.
- **FR-018**: Retrying a failed or uncertain dispatch MUST reuse its logical dispatch
  identity and MUST reconcile any known provider result before another submission is
  attempted.
- **FR-019**: Repeated or concurrent retries MUST NOT create another provider request,
  manifest, active dispatch, provider reference, status transition, inventory movement,
  order transition, or delivery-cost allocation for the same logical event.
- **FR-020**: Every dispatch MUST record one non-negative MAD delivery cost before it
  advances to accepted and identify whether the cost came from a provider quote or an
  approved flat rate; an explicitly recorded zero MUST remain distinguishable from a
  missing cost.
- **FR-021**: The full B2C dispatch cost MUST be allocated exactly once to its source
  order.
- **FR-022**: A B2B manifest cost MUST be allocated across its included orders in
  proportion to their prepared kilograms. After proportional shares are reduced to
  whole centimes, remaining centimes MUST be assigned to the largest fractional shares
  first, with equal fractional shares ordered by safe order reference, so allocated
  amounts sum exactly to the manifest cost.
- **FR-023**: Delivery-cost allocation MUST be repeatable and reconcilable at dispatch,
  manifest, and order level and MUST expose zero unexplained variance.
- **FR-024**: Actual delivery costs and their allocations MUST be preserved separately
  from the confirmed order price snapshot and its estimated delivery component; this
  feature MUST NOT rewrite either historical value.
- **FR-025**: Authorized operators MUST be able to trace a dispatch from its source
  prepared work through manifest or provider submission, every status, source orders,
  stock handoff, and allocated cost using safe references.
- **FR-026**: Failed validation, authorization, reconciliation, provider processing,
  concurrency, or lifecycle operations MUST leave manifests, requests, costs, stock,
  orders, and histories unchanged except for one safe failure outcome where appropriate.
- **FR-027**: Live delivery-provider integrations, credentials, driver applications,
  GPS tracking, route optimization, notifications, payment, returns, refunds, and
  disputes MUST remain outside this feature.

### Security and Data Requirements *(mandatory)*

- **SR-001**: Manifest generation, dispatch submission, retry, cost entry, private
  inspection, and staff-originated status changes MUST require an authenticated user
  with explicit dispatch-operations permission; interface visibility alone MUST NOT
  grant access.
- **SR-002**: Provider-originated status changes MUST be authenticated and correlated
  before acceptance when a live provider is introduced. The demonstration provider
  MUST use an internal trusted boundary and MUST NOT create or require reusable external
  credentials.
- **SR-003**: Every user-controlled filter, reference, cost, status, time, note, and
  operation input MUST be allowlisted and validated for type, range, format, ownership,
  lifecycle compatibility, expected version, and safe length as applicable.
- **SR-004**: Customer and business names, contact names, phone numbers, email addresses,
  exact addresses, delivery notes, and staff identities are personal or commercially
  sensitive. Provider credentials, tokens, signatures, raw request or response payloads,
  replay material, and private provider references are sensitive provider data.
- **SR-005**: Sensitive delivery data MUST be protected in transit and at rest, disclosed
  only to authorized staff or the minimum trusted delivery boundary for the specific
  handoff, and excluded from public responses, page metadata, analytics, exports not
  intended for dispatch, logs, and exception messages. The demonstration provider MUST
  process its minimum personal handoff fields transiently and MUST persist only safe
  references, cost, and status.
- **SR-006**: B2B manifests and detailed B2C handoff views MUST show only personal data
  required for their included deliveries. Aggregate queues and reconciliation views
  MUST use safe references, counts, quantities, zones, costs, and statuses without
  unrelated contact details.
- **SR-007**: Application and provider-adapter logs MUST never contain customer,
  business, contact, or staff names; phone numbers; email addresses; exact addresses;
  delivery notes; credentials; tokens;
  signatures, raw payloads, or private provider values. Diagnostics MUST use safe
  dispatch references, operation names, result categories, timings, and redacted
  provider identifiers.
- **SR-008**: Submission, retry, bulk manifest generation, and status-update operations
  MUST be throttled and concurrency-bounded to prevent duplicate work, provider cost
  abuse, or resource exhaustion while allowing the planned demo volume.
- **SR-009**: Unauthenticated protected requests MUST receive an
  authentication-required outcome; authenticated but unauthorized requests MUST receive
  a forbidden outcome; malformed input MUST receive field-specific validation outcomes;
  stale, duplicate, incompatible, or invalid lifecycle operations MUST receive safe
  conflict outcomes; missing or cross-scoped records MUST receive safe not-found
  outcomes.
- **SR-010**: Public and unauthorized references MUST be non-sequential and MUST NOT
  permit enumeration of manifests, dispatches, orders, customers, actors, or provider
  records.
- **SR-011**: Sensitive delivery data MUST be retained only under the platform's
  approved fulfillment, support, and legal-retention policy. Removal MUST preserve the
  non-personal quantities, costs, statuses, and history required for reconciliation and
  the later transparency ledger.

### Key Entities *(include if feature involves data)*

- **Dispatch Work Item**: The fully prepared, allocated B2B group or B2C order eligible
  for delivery orchestration, including safe source references, service scope, quantity,
  and readiness state.
- **B2B Delivery Manifest**: The unique zone-based handoff for one service date,
  containing deterministic stops, reconciled quantities, cost, lifecycle, and a safe
  reference.
- **B2B Manifest Stop**: One included B2B source order with its business handoff details,
  delivery window, product, quantity, sequence, and relationship to the manifest.
- **B2C Dispatch Request**: The provider-neutral logical request for one prepared B2C
  order, containing a stable request identity, protected source-order relationship,
  safe service metadata, cost, provider mode, provider reference, and current status.
  Minimum personal handoff data is assembled transiently from the source order and is
  not duplicated here.
- **Dispatch Attempt**: A replay-safe submission or retry of one logical dispatch,
  including attempt time, safe result category, correlation information, and whether
  the provider outcome is known or uncertain.
- **Dispatch Status Transition**: The append-only history of an accepted dispatch state
  change, including previous and resulting state, occurrence time, source, and private
  actor attribution where applicable.
- **Delivery Cost Allocation**: The exact MAD amount assigned from one manifest or B2C
  request to one source order, including its proportional basis and deterministic
  remainder attribution.
- **Demonstration Delivery Provider**: The visibly simulated provider behavior that
  deterministically returns references, quotes, and status outcomes without external
  credentials or customer-derived identifiers.
- **Dispatch Operations Actor**: An authenticated staff member permitted to inspect
  handoff data and perform dispatch operations; identity remains private outside
  authorized accountability views.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Given eligible prepared B2B orders across three zones, one operator action
  produces exactly three manifests in under 2 minutes, with 100% of eligible orders
  represented once, zero ineligible orders, and zero quantity or order-count variance.
- **SC-002**: For 100% of prepared B2C demo orders, the demonstration provider returns a
  dispatch reference, quote, and traceable outcome without external credentials or
  network access, and every staff-facing view labels the provider as simulated.
- **SC-003**: Across repeated and concurrent generation, submission, status, and retry
  tests for up to 500 source orders, each logical B2B manifest and B2C request exists
  once and duplicate provider requests, stops, transitions, costs, and stock or order
  movements remain at zero.
- **SC-004**: For 100% of accepted lifecycle scenarios, dispatches follow only the
  defined statuses, every transition is traceable, pickup moves included stock and
  orders exactly once, and delivery completion advances included orders exactly once.
- **SC-005**: For every B2C request and B2B manifest, allocated order costs sum exactly
  to the recorded dispatch cost to the smallest MAD unit, repeat calculation produces
  the same allocation, and confirmed order unit prices and totals remain unchanged.
- **SC-006**: At least 90% of trained dispatch operators can find ready work, generate or
  submit it, and identify its current status and next valid recovery action in under 3
  minutes without assistance.
- **SC-007**: An authorized operator can reconcile the source preparation, manifest or
  provider handoff, status history, included orders, dispatched kilograms, and delivery
  costs for any dispatch in under 2 minutes with zero unexplained variance.
- **SC-008**: For 100% of rejected validations, unauthorized attempts, provider failures,
  stale updates, and concurrency conflicts, no partial manifest, request, allocation,
  stock movement, order transition, or accepted status history is created.
- **SC-009**: A privacy review of public and unauthorized responses, page metadata,
  aggregate views, logs, analytics, exceptions, and simulated-provider diagnostics finds
  zero customer, business, contact, or staff names; phone numbers; emails; exact
  addresses; delivery notes;
  credentials, tokens, signatures, raw payloads, or private provider values.

## Assumptions

- This is roadmap Phase 005 and uses sequential feature directory
  `005-delivery-orchestration`; no branch-creation hook is configured.
- Phases 001 and 002 supply immutable MAD order price snapshots, mandatory delivery
  zones, selected delivery windows, required phone contact, B2B business identity, and
  optional B2C email, exact address, and delivery note.
- Phase 003 supplies deterministic order groups by product, channel, service date, and
  delivery zone. Phase 004 supplies full stock allocations and preparation records and
  leaves included orders allocated until physical pickup.
- One prepared B2C order maps to one logical provider request. One B2B manifest covers
  all B2B orders eligible when that manifest is submitted for one service date and zone;
  the MVP keeps one stop per order and does not merge a buyer's orders. Work prepared
  after submission is flagged for explicit operational resolution.
- B2B capture does not include an exact address, so the demo manifest uses business
  identity, contact, zone, and delivery window as its available stop information. Route
  planning and geocoding are explicitly outside the MVP.
- The demonstration provider accepts zone-only B2C requests so optional upstream
  addresses do not block the credential-free demo. It processes only minimum handoff
  fields transiently, never derives deterministic results from them, and retains only
  safe references, cost, and status. A real provider may require a later
  address-completion workflow.
- The common dispatch lifecycle applies to both manifests and B2C requests. Authorized
  staff record B2B handoff outcomes; for B2C, authorized staff explicitly trigger the
  demonstration provider's deterministic simulated pickup, delivery, and failure
  outcomes through the same business boundary.
- Picked up represents physical handoff and is the point at which allocated hub stock
  and included orders become dispatched. Delivered is the later successful customer
  outcome.
- MAD is the only currency. Costs support two decimal places, B2C assigns the full cost
  to one order, and B2B uses each order's prepared kilograms for proportional
  allocation with largest-remainder distribution and safe-order-reference tie-breaking.
- Recorded actual delivery costs support later transparency and variance reporting but
  do not alter confirmed prices, collect payment, or settle with a provider.
- The active delivery workload is bounded to 500 source orders for the hackathon demo.
  Route optimization, batching multiple B2C orders into one courier request, and
  multi-hub or multi-provider selection are deferred.
