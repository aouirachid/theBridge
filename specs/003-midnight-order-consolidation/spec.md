# Feature Specification: Midnight Order Consolidation

**Feature Branch**: `N/A (no branch hook configured)`

**Created**: 2026-08-08

**Status**: Draft

**Input**: User description: "Consolidate confirmed agricultural orders at a configurable midnight cutoff in Casablanca time. Group orders deterministically by service date, product, channel, and delivery zone; calculate quantities and order counts; create farmer procurement requirements and delivery groups; preserve source-order price snapshots; provide full reconciliation; and make repeated execution safe without duplicates. Authorized operators need a manual run control for demonstrations and recovery."

## Clarifications

### Session 2026-08-08

- Q: How should the MVP handle a delivery zone that does not reach its configured
  minimum quantity? → A: Require explicit operator approval before including the
  under-minimum delivery group.
- Q: What happens when an operator does not approve an under-minimum candidate group?
  → A: Exclude it from that cycle, leave its orders confirmed and ungrouped, and record
  the decision for operational follow-up.
- Q: What should "Run consolidation now" do before the scheduled midnight cutoff? → A:
  Close the ordering window early for a selected service date and consolidate orders
  confirmed through the current Casablanca time.
- Q: Which service dates should an automatic midnight cycle consolidate? → A: Only the
  next Casablanca calendar date.
- Q: How should minimum quantities be configured for under-minimum approval checks? →
  A: Use a separate minimum for each channel-and-delivery-zone combination.
- Q: Who should be able to change the scheduled cutoff from midnight? → A: An
  authorized operations manager through a protected control, with changes affecting
  future cycles only.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consolidate Eligible Demand (Priority: P1)

At the configured daily cutoff, the platform closes the applicable ordering window and
consolidates every eligible confirmed order. Demand for the same service date and
product becomes one farmer procurement requirement, while its orders are divided into
deterministic delivery groups by channel and delivery zone. The completed cycle shows
exact quantities and order counts without changing any customer's confirmed commercial
terms.

**Why this priority**: Consolidated demand is the essential bridge from customer orders
to procurement and hub operations, and it proves that the platform can replace manual
aggregation with a reproducible daily result.

**Independent Test**: Create 20 eligible tomato orders totaling 100 kg for one service
date across three delivery zones, run the applicable cutoff cycle, and verify that one
100 kg procurement requirement and the expected channel-and-zone delivery groups are
created with all 20 orders accounted for exactly once.

**Acceptance Scenarios**:

1. **Given** 20 eligible tomato orders totaling 100 kg share one service date and span
   three delivery zones within one channel, **When** their cutoff cycle runs, **Then**
   exactly one tomato procurement requirement for 100 kg and exactly three delivery
   groups are created, and all 20 orders become grouped.
2. **Given** eligible orders span two products, two channels, and multiple delivery
   zones for the next Casablanca calendar date, while other orders have later service
   dates, **When** the automatic midnight cycle runs, **Then** procurement requirements
   are separated by product, delivery groups are separated by product, channel, and
   delivery zone, and later service dates remain unchanged for their own cycles.
3. **Given** an order was cancelled, already grouped, confirmed after the cycle cutoff,
   or assigned to a different service date, **When** the cycle runs, **Then** that order
   is excluded and its lifecycle and price snapshot remain unchanged.
4. **Given** a valid cycle completes, **When** an operator inspects its results, **Then**
   procurement totals equal delivery-group totals and delivery-group totals equal the
   quantities and counts of their included source orders.
5. **Given** one or more deterministic candidate groups are below the minimum configured
   for their channel and delivery zone, **When** the cycle evaluates eligible demand,
   **Then** the cycle waits for an authorized operator's explicit decision and creates
   no procurement outputs or order transitions until every under-minimum candidate is
   approved or excluded.

---

### User Story 2 - Reconcile Every Consolidated Result (Priority: P2)

An authorized operations manager can inspect a consolidation cycle from its cutoff
summary down to each procurement requirement, delivery group, and included order. The
view explains why orders were included or left ungrouped and proves all quantity,
commercial, and lifecycle totals from immutable source records.

**Why this priority**: Operators need visible evidence that the automated result is
complete and correct before farmers are engaged or delivery work begins.

**Independent Test**: Using a cycle containing differently priced orders, an authorized
operator can trace every grouped kilogram and order total back to its source order,
confirm zero unexplained variance, and verify that no source price snapshot changed.

**Acceptance Scenarios**:

1. **Given** a completed cycle contains orders confirmed at different valid prices,
   **When** an authorized operator views its reconciliation, **Then** each order retains
   its own unit price and total, group commercial totals equal the sum of source-order
   snapshots, and no blended price replaces an accepted customer price.
2. **Given** a completed cycle, **When** an operator drills into a procurement
   requirement or delivery group, **Then** the operator can see its safe reference,
   service date, product, channel and zone where applicable, order count, kilograms,
   source orders, cutoff time, and zero or clearly explained variance.
3. **Given** orders were not eligible for a selected cycle, **When** the operator reviews
   the cycle, **Then** excluded orders remain unchanged and the operator sees bounded
   counts by exclusion reason without receiving unrelated customer personal data.

---

### User Story 3 - Retry Without Duplicates (Priority: P3)

An authorized operator can safely repeat a completed or interrupted consolidation cycle
for demonstration or recovery. Repeating the same cycle returns the same business
result, while a retry after interruption completes only missing work and never counts an
order twice.

**Why this priority**: A midnight process must tolerate retries, concurrent triggers,
and demonstration reruns without corrupting procurement or fulfillment demand.

**Independent Test**: Run the same cutoff cycle repeatedly and concurrently, including
one forced failure, and verify that every eligible order belongs to at most one delivery
group and contributes once to one procurement requirement.

**Acceptance Scenarios**:

1. **Given** a cycle completed successfully, **When** the automatic process or an
   operator runs the same cycle again, **Then** no duplicate requirement, group,
   membership, quantity, order count, or lifecycle transition is created.
2. **Given** a cycle fails before completion, **When** an authorized operator retries
   it, **Then** the cycle reaches one complete reconciled outcome or leaves all affected
   orders and outputs unchanged, without partial totals.
3. **Given** automatic and manual requests target the same cycle concurrently, **When**
   both are processed, **Then** at most one logical cycle result is produced and both
   requests report that same final outcome safely.

---

### User Story 4 - Run Consolidation Manually (Priority: P4)

An authorized operations manager can close the ordering window early for a selected
service date, or select a due or previously attempted cutoff cycle, review its effective
Casablanca cutoff and expected scope, and invoke the same consolidation used by the
daily run. The control supports a hackathon demonstration and operational recovery
without allowing arbitrary users to group orders or invent a historical time boundary.

**Why this priority**: The demo and recovery path cannot depend on waiting for midnight,
but it must preserve the same rules and safeguards as normal execution.

**Independent Test**: After orders are confirmed during a live demonstration, an
authorized operator closes their selected service date early and consolidates them
through the displayed current Casablanca time, while an ordinary authenticated user and
a guest cannot access or trigger the operation.

**Acceptance Scenarios**:

1. **Given** orders have been confirmed during a live demonstration and their scheduled
   midnight cutoff has not arrived, **When** an authorized operator selects their
   service date and explicitly confirms "Run consolidation now," **Then** its ordering
   window closes at the displayed current Casablanca time and the standard consolidation
   runs using that preserved early cutoff.
2. **Given** a prior cycle is incomplete or failed, **When** an authorized operator
   selects and reruns it, **Then** its original cutoff boundary is preserved and recovery
   does not absorb later orders.
3. **Given** a guest or an authenticated actor without operations permission, **When**
   they attempt to view or invoke manual consolidation, **Then** access is denied and no
   cycle or order changes.
4. **Given** a cycle is awaiting approval for an under-minimum candidate group, **When**
   an authorized operator approves that candidate and confirms continuation, **Then**
   the standard cycle completes atomically and visibly records the approval in its
   reconciliation.
5. **Given** a cycle is awaiting a decision for an under-minimum candidate group,
   **When** an authorized operator excludes that candidate and confirms continuation,
   **Then** the decision is recorded, its source orders remain confirmed and ungrouped
   for operational follow-up, and the cycle completes using only approved demand.
6. **Given** a service date was closed by an early manual run, **When** another buyer
   attempts to confirm an order for that service date, **Then** confirmation is rejected
   with a clear closed-window outcome and the later scheduled trigger creates no second
   cycle for that closed window.
7. **Given** the daily cutoff is midnight, **When** an authorized operations manager
   changes it to another valid time and confirms the effective date, **Then** the change
   is recorded, begins on the next Casablanca operating day, and does not alter any
   existing, awaiting-decision, running, failed, or completed cycle.

### Edge Cases

- The cutoff is interpreted in `Africa/Casablanca` operating time, including offset
  changes. Every cycle preserves both its local operating date/time and its unambiguous
  instant so later configuration changes cannot move the historical boundary.
- An order confirmed exactly at the cutoff instant is eligible; one confirmed after the
  cutoff is not. Eligibility is evaluated from authoritative recorded times, never the
  operator's device clock.
- The configured cutoff must be a valid time of day. Changing it affects only future,
  not already created or completed, cycles. A change becomes effective on the next
  Casablanca operating day and cannot retroactively close the current ordering window.
- A scheduled midnight cycle closes and consolidates only the next Casablanca calendar
  date. Orders for later service dates remain confirmed and ungrouped until the midnight
  cycle immediately preceding their service date or an authorized early closure.
- An authorized early manual run uses the current authoritative Casablanca instant; the
  operator selects the service date but cannot backdate or postdate the cutoff. Explicit
  confirmation closes that service date's ordering window, so later orders cannot enter
  a second cycle for the same closed window.
- Only confirmed, ungrouped, uncancelled orders for the cycle's service scope are
  eligible. Pending, grouped, allocated, dispatched, delivered, and cancelled orders
  cannot be absorbed or advanced by consolidation.
- A cycle with no eligible orders completes with zero requirements and zero groups and
  remains safe to rerun.
- A below-minimum candidate group does not silently proceed, postpone, or cancel its
  orders. Until an authorized operator approves or excludes it, the cycle remains
  awaiting a decision, source orders remain confirmed and ungrouped, and no procurement
  requirement or delivery group from that cycle is presented as complete.
- Excluding a below-minimum candidate applies only to the selected cycle. Its orders
  remain confirmed and ungrouped, the reason and actor are available for operational
  follow-up, and the cycle does not count their quantities or commercial totals as
  included demand.
- Repeating an approval or exclusion for the same candidate is safe and does not create
  another decision. If demand or lifecycle state changes before continuation, the prior
  preview is stale and the cycle must reevaluate eligibility rather than applying a
  decision to a different set of orders.
- In this MVP, the immutable `ProductOffer` reference is the stable product identity.
  Orders with different ProductOffer references are never combined, even when their
  crop labels match. Labels are display-only and never grouping keys.
- Multiple source prices or price-component values may exist inside one group. Totals
  are summed from source snapshots; weighted per-kilogram estimates are labelled as
  aggregates and never replace an order's confirmed unit price or total.
- Decimal kilogram quantities and MAD totals must reconcile exactly to their supported
  precision without floating-point drift or intermediate rounding.
- Phase 002 database constraints guarantee a product offer, service date, channel, and
  delivery zone on every Order. If corrupt legacy data violates that upstream contract,
  the cycle fails with the safe `invalid_source_order` category; consolidation never
  guesses or places the Order in a fallback group.
- Concurrent order cancellation and consolidation cannot both succeed for the same
  confirmed order. The final result must contain either one cancelled ungrouped order or
  one grouped order, never both or neither through a partial update.
- A failed validation, authorization, lifecycle, or concurrency check leaves all source
  orders, requirements, groups, memberships, and transitions unchanged.
- Missing cycles or safe references return not-found outcomes; stale run requests or
  conflicting cycle state return conflict outcomes without exposing private records.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST support a configurable daily ordering cutoff interpreted
  in Casablanca operating time, initially 00:00. An authorized operations manager MUST
  be able to change it to another valid time of day through a protected control. Each
  change MUST record the previous and new values, responsible actor, decision time, and
  effective date; MUST begin on the next Casablanca operating day; and MUST NOT alter
  any existing cycle or retroactively close the current ordering window. Every cycle
  MUST preserve the effective cutoff boundary it used.
- **FR-002**: A cycle MUST include only orders that are confirmed, not cancelled, not
  already grouped, assigned to its applicable service date, and confirmed at or before
  its preserved cutoff instant. For an automatic midnight cycle, the applicable service
  date MUST be the next Casablanca calendar date; orders for all later dates MUST remain
  unchanged.
- **FR-003**: The system MUST derive eligibility from authoritative order and cycle data
  and MUST NOT allow a manual caller to submit source-order identifiers, quantities,
  prices, group keys, or a caller-chosen cutoff timestamp. For an early manual run, the
  system MUST derive the cutoff from the authoritative current Casablanca instant.
- **FR-004**: For the cycle's one service date and each stable product represented by
  eligible demand, the system MUST create exactly one farmer procurement requirement
  containing the total required kilograms and contributing order count across its
  delivery groups.
- **FR-005**: Within each procurement requirement, the system MUST create exactly one
  delivery group for every distinct combination of service date, stable product,
  channel, and delivery zone represented by eligible orders.
- **FR-006**: Each delivery group MUST preserve its deterministic grouping dimensions,
  total kilograms, order count, included source orders, and relationship to its farmer
  procurement requirement.
- **FR-007**: A successful cycle MUST advance every included order from confirmed to
  grouped exactly once and record its membership and lifecycle transition without
  changing its confirmed quantity or price snapshot.
- **FR-008**: Procurement quantities MUST equal the sum of their delivery-group
  quantities; each group quantity and count MUST equal the sum and count of its included
  orders; and each included order MUST contribute to exactly one group and one
  requirement.
- **FR-009**: Reconciliation MUST preserve and expose source-order unit prices and totals
  to authorized operators, calculate group and cycle commercial totals only by summing
  those immutable snapshots, and report an explicit zero or explained variance.
- **FR-010**: Where source snapshots provide delivery-allocation cost components, the
  system MUST calculate the group's estimated delivery allocation per kilogram as a
  quantity-weighted aggregate, label it as an estimate, and retain the exact source
  values used to reproduce it.
- **FR-011**: Consolidation MUST NOT reprice an order, merge customer price snapshots,
  reserve additional offer quantity, initiate farmer purchasing, allocate hub stock,
  dispatch a delivery, or collect payment.
- **FR-012**: Each deterministic candidate delivery group MUST be evaluated against the
  minimum configured for its channel-and-delivery-zone combination. When a candidate is
  below that minimum, the cycle MUST enter an awaiting-decision state, show the
  candidate's safe grouping dimensions, kilograms, order count, and applicable minimum
  to an authorized operations manager, and require explicit approval before including
  it. The operator MAY instead exclude the candidate from that cycle; exclusion MUST
  leave its source orders confirmed and ungrouped and record the reason, actor, time,
  reviewed demand, and applicable minimum for follow-up. Before every under-minimum
  candidate has an approval or exclusion decision, the cycle MUST NOT create procurement
  requirements, create completed delivery groups, or advance source orders. Neither
  decision may cancel, postpone, reprice, or otherwise modify the source orders.
- **FR-013**: The same cutoff cycle MUST be replay-safe. Sequential, repeated, or
  concurrent execution MUST create at most one logical cycle result and MUST NOT
  duplicate requirements, groups, memberships, quantities, counts, or transitions.
- **FR-014**: Cycle completion MUST be all-or-nothing for its final included set after
  under-minimum decisions. A failed attempt MUST NOT leave partially grouped orders or
  partially reconciled procurement and delivery outputs visible as complete. Excluded
  candidates MUST remain unchanged and appear separately in reconciliation rather than
  being counted as included demand.
- **FR-015**: An authorized operations manager MUST be able to list bounded recent and
  recoverable cycles, inspect the effective cutoff and scope, manually invoke a due or
  previously attempted cycle, close the ordering window early for a selected service
  date, and view the resulting outcome.
- **FR-016**: Manual execution MUST use the same eligibility, grouping, reconciliation,
  lifecycle, atomicity, and replay rules as automatic execution and MUST preserve the
  selected cycle's original cutoff boundary. An early manual run MUST preserve its
  actual Casablanca execution instant as the effective cutoff, close the selected
  service date's ordering window, reject later confirmations for that closed window,
  and prevent the scheduled trigger from creating a duplicate cycle for it.
- **FR-017**: Every under-minimum approval or exclusion MUST be replay-safe, attributable
  to the authorized operator and time, and bound to the exact candidate grouping
  dimensions, source-order set, kilograms, order count, and configured minimum reviewed
  by that operator. A changed or stale candidate MUST require reevaluation and a fresh
  decision.
- **FR-018**: Every cycle MUST record a safe reference, operating date, effective local
  cutoff and unambiguous instant, trigger type, status, start and completion times,
  source-order count and kilograms, created requirement and group counts, exclusion
  counts by safe reason, the effective channel-and-zone minimums used, and actor
  attribution when manually triggered.
- **FR-019**: Authorized operators MUST be able to navigate from a cycle to each
  requirement, group, and included order and reconcile counts, quantities, commercial
  totals, and lifecycle outcomes without exposing unnecessary customer details.
- **FR-020**: The operator view MUST support bounded filtering by cycle date, service
  date, product, channel, delivery zone, and outcome so demonstrations and recovery do
  not require unbounded data loading.
- **FR-021**: Automatic farmer bidding or purchasing, payment, forecasting, AI demand
  prediction, route optimization, hub receiving or allocation, carrier integration,
  delivery execution, and customer notifications are outside this feature.

### Security and Data Requirements *(mandatory)*

- **SR-001**: Viewing detailed consolidation data and manually running a cycle MUST
  require an authenticated actor with operations permission, enforced independently of
  whether the interface displays the control. Automatic execution MUST use a trusted
  system context. Approving or excluding an under-minimum candidate MUST require the
  same operations permission and an explicit confirmation. Viewing or changing the
  cutoff configuration MUST require authenticated operations-manager permission.
  Operations-manager permission is distinct from ordinary operations permission.
- **SR-002**: The cutoff configuration and every manual-run input MUST be allowlisted and
  validated for permitted format, range, cycle identity, due or recoverable state, and
  current lifecycle status. A selected service date for early closure MUST exist, remain
  open, and be eligible for manual closure. Unexpected fields, caller-supplied cutoff
  timestamps, and caller-supplied business totals MUST be rejected.
- **SR-003**: Customer names, phone numbers, email addresses, exact addresses, delivery
  notes, private actor identifiers, and internal record identifiers are private. They
  MUST be excluded from public responses, unauthorized outcomes, page metadata,
  analytics payloads, and logs, and detailed access MUST be limited to authorized staff
  with an operational need.
- **SR-004**: The manual run is a costly staff mutation and MUST use bounded request
  rates plus replay and concurrency controls. Automatic triggering MUST also prevent
  overlapping execution from multiplying work or outputs.
- **SR-005**: Unauthenticated protected requests MUST receive an authentication-required
  outcome; authenticated but unauthorized requests MUST receive a forbidden outcome;
  malformed input MUST receive field-specific validation outcomes; stale, duplicate,
  conflicting, or non-recoverable cycle requests MUST receive a safe conflict outcome;
  and missing safe references MUST receive a not-found outcome.
- **SR-006**: Operational diagnostics MUST identify the safe cycle reference, trigger
  type, status, counts, and safe failure category needed for recovery, but MUST NOT log
  source payloads, customer personal data, raw tokens, authentication material, private
  notes, or full order records.
- **SR-007**: Selection, requirement creation, group creation, order membership, and
  lifecycle advancement MUST be atomic and concurrency-safe so no failure or competing
  operation can produce double counting, an order in multiple groups, or a completed
  cycle with unexplained variance.
- **SR-008**: Reconciliation records MUST retain the non-personal historical facts needed
  to reproduce the cycle even if customer personal data is later removed under the
  platform's retention policy.

### Key Entities *(include if feature involves data)*

- **Consolidation Cutoff Configuration**: The authorized, historically traceable
  operating rule that defines the daily Casablanca cutoff for future cycles, initially
  midnight, including previous and new values, actor, decision time, and effective date.
- **Delivery Minimum Rule**: The authorized operating rule that defines the minimum
  kilograms for one channel-and-delivery-zone combination and whose effective value is
  preserved with each cycle that evaluates it.
- **Consolidation Cycle**: One replay-safe processing boundary for exactly one service
  date, with a safe reference, effective cutoff, trigger, status, counts, totals,
  exclusions, and completion outcome. A scheduled midnight cycle targets the next
  Casablanca calendar date; an early manual cycle targets its selected open date.
- **Order**: Upstream confirmed B2C or B2B demand with stable product, service date,
  channel, delivery zone, quantity, lifecycle, and immutable confirmed price snapshot.
- **Farmer Procurement Requirement**: Consolidated demand for one stable product and
  service date, including required kilograms, source-order count, reconciliation state,
  and the delivery groups it supplies.
- **Delivery Group**: Deterministic demand for one service date, product, channel, and
  delivery zone, including kilograms, order count, membership, and procurement
  relationship.
- **Group Membership**: The unique, traceable association that proves one source order
  contributed once to one delivery group and requirement in one cycle.
- **Consolidation Reconciliation**: The cycle, requirement, and group-level comparison of
  source counts, kilograms, immutable commercial totals, estimated delivery allocation,
  outputs, exclusions, under-minimum decisions, and variance.
- **Operations Actor**: An authenticated staff member authorized to inspect detailed
  consolidation data and invoke manual demonstration or recovery runs; identity remains
  private outside authorized operational views.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Given 20 eligible tomato orders totaling 100 kg for one service date and
  three zones within one channel, one cycle produces exactly one 100 kg procurement
  requirement, three delivery groups, 20 unique memberships, and zero quantity or count
  variance.
- **SC-002**: Across sequential retries, concurrent automatic and manual triggers, and a
  recovery after forced failure, 100% of eligible orders contribute at most once and no
  duplicate requirement, group, membership, quantity, count, or transition is created.
- **SC-003**: For 100% of completed cycles, procurement kilograms equal delivery-group
  kilograms, delivery-group kilograms and counts equal included-order kilograms and
  counts, and every unexplained variance equals zero.
- **SC-004**: For 100% of consolidated orders, the confirmed quantity, unit price, total,
  and other price-snapshot facts remain byte-for-byte equivalent to their values before
  consolidation, while aggregate commercial totals remain reproducible from them.
- **SC-005**: An authorized operator can run a due demonstration cycle and reach its
  reconciled result, or close a selected service date early and consolidate live demo
  orders, in under 2 minutes without direct data changes or technical assistance.
- **SC-006**: An authorized operator can trace any grouped order to its cycle, delivery
  group, and procurement requirement, or reconcile a cycle of up to 500 eligible orders,
  in under 2 minutes using the operational views.
- **SC-007**: For 100% of rejected authorization, validation, stale-state, concurrency,
  and forced-failure scenarios, no source-order price snapshot changes and no partial or
  double-counted consolidation output is presented as complete.
- **SC-008**: A privacy review of public responses, unauthorized outcomes, page metadata,
  analytics payloads, and logs finds zero customer contact details, exact addresses,
  delivery notes, private actor identifiers, raw replay material, or full submitted
  payloads.
- **SC-009**: In 100% of below-minimum scenarios, no procurement output or grouped-order
  transition is completed before every candidate has an explicit authorized decision.
  After continuation, approved demand reconciles with zero unexplained variance,
  excluded demand remains confirmed and ungrouped, and each candidate has exactly one
  recorded decision.
- **SC-010**: An authorized operations manager can change the future daily cutoff in
  under 1 minute; 100% of tested changes begin on the next Casablanca operating day and
  alter zero existing cycle boundaries or historical results.

## Assumptions

- This is roadmap Phase 003. The existing micro-hub feature has been renumbered to 004
  and consumes the procurement requirements and delivery groups produced here.
- Phase 002 supplies confirmed orders whose `ProductOffer` reference is the MVP stable
  product identity,
  Casablanca service dates derived from offer-defined delivery slots, mandatory channel
  and delivery zone, quantities in kilograms with up to two decimal places, immutable
  MAD price snapshots, and the confirmed-to-grouped lifecycle transition.
- A farmer procurement requirement aggregates one ProductOffer for one service date
  across channels and zones. Delivery groups subdivide that requirement by channel and
  delivery zone; selected delivery times remain on source orders for later orchestration.
- Each consolidation cycle covers exactly one service date. A scheduled midnight cycle
  targets the next Casablanca calendar date, while an authorized early closure targets
  one explicitly selected open service date.
- An automatic daily trigger will exist in the operating environment, while the exact
  scheduling mechanism is a planning decision. The specification governs the observable
  cycle behavior regardless of trigger mechanism.
- Manual execution may target a due or previously attempted cycle or close one selected
  service date early at the authoritative current Casablanca instant. It cannot use an
  arbitrary caller-selected time and does not create a second cycle for the same closed
  ordering window.
- Delivery minimums are existing authorized operating configuration with one threshold
  for each channel-and-delivery-zone combination. Defining and editing those thresholds
  is outside this feature; this feature validates and preserves the effective values it
  applies during a cycle.
- The cycle records bounded aggregate counts for `confirmed_after_cutoff`,
  `not_confirmed`, `already_grouped`, and `operator_excluded_below_minimum`. It does not
  copy customer personal data into reconciliation records or expose unrelated excluded
  orders.
- Quantity and monetary calculations retain the precision established by upstream
  offers and orders. Rounding and representation details are planning decisions, but
  exact reconciliation and no floating-point drift are required outcomes.
- One micro-hub serves the MVP. Multi-hub procurement splitting and inventory allocation
  belong to later scope.
