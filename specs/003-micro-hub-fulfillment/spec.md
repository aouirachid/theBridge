# Feature Specification: Micro-Hub Fulfillment

**Feature Branch**: `N/A (no branch hook configured)`

**Created**: 2026-08-08

**Status**: Draft

**Input**: User description: "Add micro-hub fulfillment for consolidated produce. Authorized staff receive procured quantities, record accepted and rejected weight with quality grade and reasons, track available, allocated, damaged, and dispatched quantities, allocate stock without overselling, expose handling losses explicitly, prepare orders for dispatch, and flag produce held longer than the target turnaround window."

## Clarifications

### Session 2026-08-08

- Q: Can one order group use stock from multiple receipts? → A: No; one order group must be fulfilled completely from one receipt.
- Q: After a receipt is finalized, when can staff correct its accepted or rejected quantities? → A: Only before any accepted stock is damaged, allocated, or dispatched; preserve the original and append the correction.
- Q: Which feature should record the actual dispatch handoff? → A: Delivery orchestration; micro-hub fulfillment stops at ready for dispatch.
- Q: Can any accepted quality grade fulfill a compatible order group? → A: Yes; grade is recorded for transparency only.
- Q: What happens when delivered produce exceeds the procurement requirement? → A: Automatically classify all excess quantity as rejected procurement overage.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Receive and Grade Consolidated Produce (Priority: P1)

An authorized hub receiver selects an outstanding procurement requirement, records the
actual quantity delivered to the micro-hub, separates accepted from rejected weight,
assigns a quality grade to the accepted produce, records why any produce was rejected,
and finalizes the receipt with its actual receiving time. The receiver immediately sees
the quantity that entered usable hub stock and the quantity excluded through quality
control.

**Why this priority**: Fulfillment cannot begin until incoming produce is measured and
quality outcomes are explicit. This establishes the trustworthy stock balance used by
every later operation.

**Independent Test**: An authorized receiver can finalize a 100 kg tomato receipt with
96 kg accepted and 4 kg rejected, see 96 kg initially available, and reproduce the
receipt balance from the displayed quantities alone.

**Acceptance Scenarios**:

1. **Given** an outstanding tomato procurement requirement, **When** an authorized hub
   receiver records 100 kg received, 96 kg accepted, 4 kg rejected, an allowed quality
   grade, a rejection reason, and the actual receiving time, **Then** one finalized
   receipt shows 100 kg received, 96 kg available, 4 kg rejected, and a balanced quality
   reconciliation.
2. **Given** the accepted and rejected quantities do not equal the received quantity,
   **When** the receiver attempts to finalize the receipt, **Then** finalization is
   rejected with quantity-specific guidance and no stock becomes available.
3. **Given** rejected quantity is greater than zero, **When** no meaningful rejection
   reason is supplied, **Then** finalization is rejected and the incomplete receipt does
   not affect stock.
4. **Given** a person is unauthenticated or lacks hub-receiving permission, **When** they
   attempt to view procurement details or record a receipt, **Then** private operational
   data is not disclosed and no stock changes.
5. **Given** a procurement requirement is 100 kg and 110 kg is delivered with no other
   quality rejection, **When** an authorized receiver finalizes the receipt, **Then**
   100 kg is accepted, 10 kg is automatically rejected as procurement overage, and no
   more than 100 kg enters available stock.

---

### User Story 2 - Control Stock and Handling Losses (Priority: P2)

An authorized fulfillment operator monitors each receipt's available, allocated,
damaged, and dispatched quantities. When produce is damaged during handling or temporary
storage, the operator records the exact weight, reason, time, and responsible staff
actor. The loss is removed from available stock and remains separately visible rather
than being hidden in a margin or overwritten balance.

**Why this priority**: A credible micro-hub must show where usable produce went and must
prevent quality loss from appearing as unexplained shrinkage or saleable inventory.

**Independent Test**: Starting from a finalized receipt with 96 kg available, an
authorized operator can record 1 kg of handling damage and see 95 kg available, 1 kg
damaged, and a complete reasoned loss record.

**Acceptance Scenarios**:

1. **Given** 96 kg of accepted tomato stock is available, **When** an authorized
   operator records 1 kg damaged with a reason and occurrence time, **Then** available
   stock becomes 95 kg, damaged stock becomes 1 kg, and the loss remains visible as a
   separate operational event.
2. **Given** only 10 kg is available, **When** an operator attempts to record more than
   10 kg as newly damaged, **Then** the operation is rejected and all stock quantities
   remain unchanged.
3. **Given** an accepted damage record, **When** the stock summary is viewed later,
   **Then** the recorded quantity, reason, time, and private actor attribution remain
   available to authorized staff and are not presented as a pricing or margin amount.

---

### User Story 3 - Allocate Stock Without Overselling (Priority: P3)

An authorized fulfillment operator selects a consolidated order group awaiting stock
and allocates enough compatible produce to cover the group. The operation succeeds only
when the full group quantity is available and has not already been allocated. Successful
allocation reserves that stock, advances the included orders to allocated, and leaves
all unaffected stock available for other groups.

**Why this priority**: Allocation connects physical stock to confirmed demand. It must
be concurrency-safe so the same kilograms cannot be promised twice.

**Independent Test**: With 95 kg available, an operator can allocate a 45 kg order group
once, leaving 50 kg available and 45 kg allocated; attempts to repeat the allocation or
allocate more than the remaining 50 kg fail without changing any balance.

**Acceptance Scenarios**:

1. **Given** 95 kg of compatible stock is available and an unallocated order group
   requires 45 kg, **When** an authorized operator allocates the group, **Then** 45 kg
   moves from available to allocated, the group and its orders are marked allocated,
   and 50 kg remains available.
2. **Given** the full quantity required by an order group is not available, **When** an
   operator attempts allocation, **Then** the entire allocation is rejected, no order
   advances, and no partial reservation is created.
3. **Given** two allocation attempts compete for stock that can satisfy only one of
   them, **When** they are processed concurrently, **Then** at most one succeeds and the
   resulting total allocated quantity never exceeds the stock that was available.
4. **Given** a group has already been allocated, **When** the same allocation attempt is
   repeated, **Then** no duplicate allocation or second order-state transition is
   created.

---

### User Story 4 - Prepare Produce for Dispatch (Priority: P4)

An authorized fulfillment operator reviews an allocated order group, confirms its
prepared quantity, and marks it ready for dispatch. The fulfillment summary shows
receipt, grade, losses, allocation, order group, and dispatch-readiness information
needed by the later delivery workflow without exposing customer contact details
unnecessarily. The later delivery-orchestration feature owns the physical handoff and
the transition from allocated to dispatched stock.

**Why this priority**: Preparation completes the micro-hub journey and provides a clear,
reconciled boundary for delivery orchestration.

**Independent Test**: An allocated 45 kg order group can be prepared once; afterward it
remains allocated, is visibly ready for dispatch, and is eligible for delivery
orchestration without being marked dispatched prematurely.

**Acceptance Scenarios**:

1. **Given** an order group has a complete 45 kg allocation, **When** an authorized
   operator confirms preparation, **Then** the group is marked ready for dispatch and a
   fulfillment summary reconciles its required, allocated, and prepared quantities.
2. **Given** the prepared quantity does not equal the group's allocated requirement,
   **When** an operator attempts to mark it ready, **Then** preparation is rejected and
   the group remains allocated.
3. **Given** a group is marked ready, **When** its fulfillment summary is made available
   to delivery orchestration, **Then** its quantity remains allocated and its included
   orders remain allocated until that later workflow records the physical handoff.
4. **Given** a group is not fully allocated, **When** an operator attempts preparation,
   **Then** the operation is rejected and the stock and order states remain unchanged.

---

### User Story 5 - Identify Overdue Produce (Priority: P5)

Hub staff see which receipts still contain undispatched accepted produce after the
target turnaround window. The warning shows how long the produce has been held and how
much remains in available or allocated stock so staff can prioritize action without
changing the underlying inventory state.

**Why this priority**: The platform promises short-term consolidation rather than
warehousing. A visible turnaround warning makes that promise operationally measurable.

**Independent Test**: A receipt with available or allocated produce older than 24 hours
is visibly overdue, while a fully dispatched receipt and a receipt younger than 24
hours are not.

**Acceptance Scenarios**:

1. **Given** accepted produce remains available or allocated 24 hours after its recorded
   receipt time, **When** authorized staff view the hub work queue, **Then** the receipt
   is flagged overdue with its age and undispatched quantity.
2. **Given** all accepted produce is damaged or dispatched, **When** its age crosses 24
   hours, **Then** it is not flagged as stock awaiting turnaround.
3. **Given** a receipt is less than 24 hours old, **When** staff view it, **Then** the
   remaining time to the target is clear and it is not labeled overdue.

### Edge Cases

- Received, accepted, rejected, damaged, allocated, prepared, and dispatched quantities
  must be non-negative kilogram values with no more than two decimal places.
- A finalized receipt must satisfy `accepted quantity + rejected quantity = received
  quantity`; rejected produce never enters hub stock.
- At all times, accepted stock must reconcile exactly as `available quantity + allocated
  quantity + damaged quantity + dispatched quantity = accepted quantity`.
- A zero rejected quantity does not require a rejection reason; a positive rejected
  quantity requires a meaningful reason. A positive damage quantity always requires a
  reason.
- A receipt time in the future, malformed grade, unsupported reason value, unexpected
  field, or hostile free text is rejected safely without changing inventory.
- A finalized receipt cannot be silently edited or deleted. Accepted or rejected
  quantities may be corrected only while all accepted stock remains available and no
  damage, allocation, preparation, or dispatch has occurred. A permitted correction
  must preserve the original record, identify the actor and reason, and keep every stock
  invariant true.
- The actual received quantity may be lower or higher than the procurement requirement,
  and the variance remains visible without changing consolidated demand. When it is
  higher, the excess is automatically classified as rejected procurement overage and
  never enters accepted or available stock.
- Only produce for the same product and service scope as the order group is compatible
  for allocation. The MVP does not combine multiple receipts to satisfy one group or
  split one group across multiple receipts. Any accepted quality grade is allocatable;
  grade does not restrict group compatibility in this feature.
- Allocation is all-or-nothing for one consolidated order group. Partial group
  allocation and arbitrary reassignment between groups are outside the demo path.
- Damage can be recorded only against currently available stock. Loss discovered after
  allocation must first follow an authorized allocation-release or exception workflow;
  silently reducing a prepared group is prohibited.
- Releasing an allocation before preparation returns its full quantity to available
  stock exactly once and returns included orders to their grouped state. Prepared or
  dispatched allocations cannot be released in this feature.
- Repeated receiving, damage, allocation, release, and preparation submissions must not
  duplicate stock movements or lifecycle transitions.
- Missing procurement requirements, receipts, or groups return a safe not-found outcome;
  stale quantities and invalid lifecycle operations return a conflict outcome.
- The overdue threshold is measured from the actual receipt time in Casablanca operating
  time. A receipt exactly at the 24-hour threshold is overdue when accepted produce
  remains available or allocated.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Authorized hub receivers MUST be able to list outstanding consolidated
  procurement requirements and inspect their product, service scope, required quantity,
  and reconciliation status before receiving produce.
- **FR-002**: A receipt MUST record its procurement requirement, actual receipt time,
  received quantity, accepted quantity, rejected quantity, accepted-produce quality
  grade, and responsible receiver. Quality grade is informational for transparency in
  this feature; every accepted grade MUST remain eligible for allocation.
- **FR-003**: Receipt finalization MUST require accepted quantity plus rejected quantity
  to equal received quantity exactly. A positive rejected quantity MUST include at least
  one allowed rejection reason and an optional concise note; zero rejected quantity MUST
  NOT require a reason. The system MUST assign the rejection reason `procurement
  overage` to the portion delivered above the procurement requirement.
- **FR-004**: Finalizing a receipt MUST add only its accepted quantity to available hub
  stock. Accepted quantity MUST NOT exceed the procurement requirement. Rejected
  quantity, including procurement overage, MUST remain separately visible and MUST NOT
  be allocatable.
- **FR-005**: The system MUST show the variance between the procurement requirement and
  the actual received quantity without rewriting the original requirement. It MUST
  calculate procurement overage as the amount by which received quantity exceeds the
  requirement and automatically include that amount in rejected quantity.
- **FR-006**: A finalized receipt MUST NOT be silently updated or deleted. An authorized
  actor MAY append a correction to its received, accepted, or rejected quantities only
  while all accepted stock remains available and no damage, allocation, preparation, or
  dispatch operation has occurred. The correction MUST preserve the original values,
  correction reason, actor, time, and resulting reconciliation; after any downstream
  stock operation, those receipt quantities are immutable.
- **FR-007**: For every receipt, accepted stock MUST be partitioned into mutually
  exclusive available, allocated, damaged, and dispatched quantities whose sum always
  equals the accepted quantity.
- **FR-008**: Authorized fulfillment operators MUST be able to record damage only from
  currently available stock, with quantity, allowed reason, occurrence time, and actor.
  Each loss MUST reduce available quantity and increase damaged quantity by the same
  amount in one complete operation.
- **FR-009**: Rejected and damaged quantities MUST be reported separately as operational
  quality and handling losses and MUST NOT be presented as platform margin, delivery
  cost, or another price component.
- **FR-010**: An authorized fulfillment operator MUST be able to allocate exactly one
  compatible receipt to one unallocated consolidated order group only when that receipt
  alone has the full group quantity available. Stock from multiple receipts MUST NOT be
  combined to fulfill one group.
- **FR-011**: Allocation MUST be all-or-nothing and MUST move the exact group quantity
  from available to allocated while advancing every included grouped order to allocated
  in the same complete operation.
- **FR-012**: Concurrent, stale, or repeated allocation attempts MUST never make total
  allocated stock exceed the quantity available immediately before successful
  allocation, allocate one group more than once, or advance an order twice.
- **FR-013**: Before preparation, an authorized operator MUST be able to release a full
  allocation with a reason. Release MUST move the quantity from allocated back to
  available and return the group and its orders to grouped exactly once.
- **FR-014**: An authorized fulfillment operator MUST be able to mark a fully allocated
  group ready for dispatch only when its prepared quantity exactly equals its allocated
  requirement.
- **FR-015**: The fulfillment summary for a prepared group MUST show its safe group
  reference, channel, delivery zone, service date or window, product, quality grade,
  required quantity, allocated quantity, prepared quantity, receipt reference, receipt
  time, and separately identified rejected and damaged quantities.
- **FR-016**: This feature MUST stop at ready for dispatch. Marking a group ready MUST
  leave its quantity allocated and its included orders allocated. The later
  delivery-orchestration feature exclusively owns physical handoff, moving quantity from
  allocated to dispatched, and advancing included orders to dispatched.
- **FR-017**: The system MUST flag a receipt as overdue when it reaches 24 hours since
  actual receipt time and any accepted quantity remains available or allocated. The flag
  MUST show elapsed holding time and undispatched quantity without changing stock.
- **FR-018**: Authorized staff MUST be able to view bounded hub work queues and stock
  summaries filtered by product, receipt state, allocation state, service date, and
  overdue status, with totals that reconcile to the underlying receipts.
- **FR-019**: Each accepted receipt finalization, correction, damage, allocation,
  allocation release, and preparation operation MUST record the actor and time and MUST
  be replay-safe so retries do not duplicate quantities or states.
- **FR-020**: A failed validation, authorization, compatibility, quantity, concurrency,
  or lifecycle check MUST leave the receipt, stock buckets, order group, and included
  order states unchanged.
- **FR-021**: IoT monitoring, warehouse-slot optimization, multi-hub transfers, combining
  multiple receipts for one group, splitting a group across receipts, physical dispatch
  handoff, route planning, live carrier integration, delivery tracking, returns, and
  post-dispatch loss handling are outside this feature.

### Security and Data Requirements *(mandatory)*

- **SR-001**: Receipt viewing and finalization MUST require authenticated hub-receiving
  permission. Damage, allocation, release, preparation, and detailed hub-stock views
  MUST require the corresponding authenticated fulfillment permission, enforced
  independently of interface visibility. Dispatch-handoff authorization belongs to the
  later delivery-orchestration feature.
- **SR-002**: Every user-controlled field MUST be allowlisted and validated for presence,
  type, kilogram precision and range, chronological validity, allowed grade or reason,
  compatible procurement or order-group scope, safe text length, and current lifecycle
  state as applicable.
- **SR-003**: Staff identities, supplier details, internal procurement references, free
  text notes, and detailed operational history are private operational data. They MUST
  be protected in transit and at rest as appropriate, visible only to authorized staff,
  and excluded from public responses, page metadata, analytics payloads, and logs.
- **SR-004**: Hub operations are staff-only and low-volume, so public throttling is not
  applicable. Authentication controls MUST throttle abusive sign-in attempts, while
  mutation endpoints MUST use replay protection and bounded request rates sufficient to
  prevent accidental or automated duplicate operational writes.
- **SR-005**: Unauthenticated protected requests MUST receive an authentication-required
  outcome; authenticated but unauthorized requests MUST receive a forbidden outcome;
  malformed input MUST receive field-specific validation outcomes; stale stock,
  incompatible scope, insufficient quantity, replay mismatch, and invalid lifecycle
  operations MUST receive conflict outcomes; missing records MUST receive not-found
  outcomes without disclosing unrelated private data.
- **SR-006**: Diagnostics MUST include safe receipt or group references and operation
  types sufficient for investigation, but MUST NOT log full submitted payloads, staff or
  supplier personal data, free-text notes, authentication material, or internal tokens.
- **SR-007**: Every quantity-changing operation MUST be atomic and concurrency-safe so a
  failure cannot leave stock buckets, allocations, preparation state, dispatch state, or
  included order states partially updated.

### Key Entities *(include if feature involves data)*

- **Procurement Requirement**: The immutable consolidated need for a product and service
  scope, including required quantity and reconciliation status; supplied by the prior
  consolidation phase.
- **Hub Receipt**: A finalized record of produce arriving against one procurement
  requirement, including actual receipt time, received, accepted, and rejected
  quantities, quality grade, rejection reasons, receiver, and procurement variance.
- **Hub Stock Balance**: The reconciled partition of one receipt's accepted produce into
  available, allocated, damaged, and dispatched quantities.
- **Handling Loss**: A reasoned quantity of accepted produce that became unusable while
  available in the hub, including occurrence time and private actor attribution.
- **Order Group**: Consolidated B2B or B2C demand for one compatible product, service
  scope, channel, and delivery zone, with required quantity and included orders.
- **Stock Allocation**: The exclusive reservation of one receipt's stock for one order
  group, including quantity, lifecycle state, actor, times, and optional release reason.
- **Fulfillment Preparation**: Confirmation that an allocated group has been prepared in
  full and is ready for dispatch handoff.
- **Hub Operation Record**: The traceable record of receipt, correction, damage,
  allocation, release, or preparation, including safe references, quantity, time, actor
  attribution, and reason where applicable.
- **Hub Staff Actor**: An authenticated staff member with receiving, fulfillment, or both
  permissions; private identity is retained for accountability but not made public.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: At least 90% of trained hub receivers can find an outstanding procurement
  requirement and finalize a valid receipt in under 2 minutes without assistance.
- **SC-002**: In the acceptance scenario of 100 kg received, 4 kg rejected, and 1 kg
  subsequently damaged, the system exposes exactly 95 kg as available for allocation,
  shows rejected and damaged quantities separately, and reports zero unexplained
  variance.
- **SC-003**: For 100% of tested receipts and every accepted operation sequence,
  available plus allocated plus damaged plus dispatched quantity equals accepted
  quantity, and accepted plus rejected quantity equals received quantity.
- **SC-004**: Across concurrent, stale, and repeated allocation tests, allocated quantity
  never exceeds available stock, one group is allocated at most once, and no included
  order advances more than once for the same operation.
- **SC-005**: An authorized operator can allocate and prepare a fully covered order group
  in under 3 minutes, and its fulfillment summary reconciles required, allocated, and
  prepared quantities with zero variance while leaving dispatch for the later workflow.
- **SC-006**: For 100% of rejected validations, unauthorized attempts, incompatible
  allocations, insufficient-stock attempts, and invalid lifecycle operations, no
  quantity bucket or related order state is partially changed.
- **SC-007**: Every receipt with undispatched accepted produce at or beyond 24 hours is
  flagged within 1 minute of an authorized staff view, while zero fully dispatched or
  fully lost receipts are falsely flagged as awaiting turnaround.
- **SC-008**: An authorized operator can reconcile received, rejected, available,
  allocated, damaged, and dispatched kilograms for up to 500 active receipts in under 2
  minutes using the hub views.
- **SC-009**: A privacy review of public responses, page metadata, logs, analytics
  payloads, and unauthorized failure paths finds zero supplier details, staff identities,
  free-text operational notes, authentication material, or internal tokens.

## Assumptions

- Although the roadmap labels micro-hub fulfillment as Phase 004, this specification is
  numbered 003 because sequential feature numbering is configured and only feature
  directories 001 and 002 currently exist.
- Midnight consolidation is a prerequisite: it supplies immutable procurement
  requirements, order groups, compatible product and service scope, and grouped orders.
  This feature does not recreate or manually substitute those records.
- One finalized receipt can satisfy one or more order groups, but each group is supplied
  completely by exactly one receipt. Combining multiple receipts for one group and
  splitting one group across receipts are deferred to keep the hackathon flow
  deterministic.
- Allocation is for the full order-group quantity. A shortage remains visibly
  unallocated for operational resolution; partial fulfillment, substitution, and buyer
  communication are outside this feature.
- Quality grades and rejection or damage reasons come from small operator-approved
  lists. The exact labels are a planning decision; accepted produce receives one grade
  per receipt in the MVP, and that grade does not restrict allocation.
- Quantities are measured in kilograms with up to two decimal places. MAD pricing is not
  recalculated by this feature; rejected and damaged weights are operational loss
  quantities for the later transparency report.
- The target receipt-to-dispatch turnaround is 24 hours and is measured from the actual
  receipt time using Casablanca operating time.
- Preparation is an internal readiness confirmation. Physical handoff, the
  allocated-to-dispatched stock transition, order dispatch status, carrier manifests,
  external dispatch requests, provider references, route planning, and delivery
  execution belong to the later delivery-orchestration feature.
- Procurement overage and shortage remain visible for staff reconciliation. Overage is
  automatically rejected and cannot be allocated; neither variance modifies
  consolidated demand or automatically creates new order groups.
