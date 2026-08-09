# Feature Specification: B2C and B2B Order Capture

**Feature Branch**: `N/A (no branch hook configured)`

**Created**: 2026-08-08

**Status**: Draft

**Input**: User description: "Add B2C and B2B agricultural order capture. Consumers place small orders using contact and delivery-zone information without requiring an account. Professional buyers place larger orders with business identity and delivery-window information. Every order shows and preserves its confirmed unit price and total, follows a defined lifecycle, validates quantity against an active offer, protects personal data, and becomes eligible for the next daily consolidation cycle."

## Clarifications

### Session 2026-08-08

- Q: How should buyers choose the order's service date and delivery window? → A: Select an available date-and-time delivery slot defined by the offer.
- Q: What delivery-location information is required for B2C confirmation? → A: Delivery zone is required; exact address is optional.
- Q: Should Phase 2 enforce different quantity limits for B2C and B2B orders? → A: No; both channels require a positive quantity that does not exceed available offer stock.
- Q: Who may cancel a confirmed guest order before grouping? → A: Only an authorized staff actor.
- Q: Which contact method is required for guest order confirmation? → A: Phone number is required; email is optional.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consumer Confirms an Order (Priority: P1)

A consumer opens an active public offer, chooses a quantity, provides contact and
delivery-zone information, reviews the authoritative unit price and total, and confirms
the order without creating an account. The consumer receives a non-sequential reference
and a clear summary of what was confirmed.

**Why this priority**: Account-free consumer ordering is the smallest complete demand
capture flow and proves that a published offer can generate consolidation-ready demand.

**Independent Test**: A guest can confirm a 5 kg tomato order from an active offer and
receive a confirmation showing the same unit price and total that were displayed at
review.

**Acceptance Scenarios**:

1. **Given** an active tomato offer has at least 5 kg remaining, **When** a consumer
   enters valid contact and delivery-zone information, reviews a 5 kg order, and
   confirms it, **Then** one confirmed B2C order is created with the displayed unit
   price, quantity, total, service date, and a non-sequential reference.
2. **Given** a consumer has reviewed an order, **When** the offer becomes inactive or
   insufficient before confirmation, **Then** confirmation is rejected, no order or
   quantity reservation is created, and the consumer is asked to review current
   availability and price.
3. **Given** a consumer does not have an account, **When** they complete valid review and
   confirmation steps, **Then** authentication is not required and no customer account
   is created implicitly.

---

### User Story 2 - Professional Buyer Confirms an Order (Priority: P2)

A professional buyer selects an active offer, enters business identity and contact
information, chooses a quantity, delivery zone, and an available offer-defined delivery
slot, then
reviews and confirms the authoritative price and total without requiring an account.

**Why this priority**: B2B demand is commercially important and needs additional
identity and delivery-window information while sharing the same pricing and availability
invariants as B2C ordering.

**Independent Test**: A greengrocer can confirm a 40 kg tomato order against the same
active offer used by a consumer, with business and delivery-window details preserved for
operations.

**Acceptance Scenarios**:

1. **Given** the active tomato offer has at least 40 kg remaining, **When** a
   greengrocer submits valid business identity, contact, delivery zone, delivery slot,
   and 40 kg quantity and confirms the review, **Then** one confirmed B2B order is
   created with its business details, selected delivery slot, displayed unit price, and
   total.
2. **Given** a professional buyer omits the business name, contact details, delivery
   zone, or delivery slot, **When** they try to review or confirm the order,
   **Then** the submission is rejected with field-specific guidance and no order is
   confirmed.
3. **Given** a previously displayed delivery slot is no longer offered at confirmation,
   **When** a B2B buyer attempts confirmation, **Then** the request is rejected and no
   quantity is reserved.

---

### User Story 3 - Operator Monitors and Advances Orders (Priority: P3)

An authorized platform operator sees confirmed B2C and B2B orders in one operational
view, filters them by channel, status, service date, offer, or delivery zone, and can
cancel an order before grouping. Confirmed eligible orders are clearly ready for the
next daily consolidation cycle, while the lifecycle rules protect them from invalid
transitions as later operational phases advance them.

**Why this priority**: Captured demand has operational value only if staff can find it,
protect its customer information, and hand it safely to consolidation and fulfillment.

**Independent Test**: After one 5 kg B2C order and one 40 kg B2B order are confirmed, an
authorized operator can find both, verify a total of 45 kg of eligible demand, and make
only valid status changes without altering either price snapshot.

**Acceptance Scenarios**:

1. **Given** confirmed B2C and B2B orders exist for the next service date, **When** an
   authorized operator opens the order view, **Then** both appear with channel, public
   reference, offer, quantity, confirmed unit price, total, delivery zone, service date,
   status, and consolidation eligibility.
2. **Given** a confirmed order, **When** an authorized operator cancels it before
   grouping, **Then** its status becomes cancelled, its reserved quantity is released,
   it is no longer consolidation-eligible, and its confirmed price snapshot remains
   unchanged.
3. **Given** an order in any lifecycle state, **When** an actor attempts an invalid state
   transition or tries to change its confirmed quantity or pricing, **Then** the change
   is rejected and the order remains unchanged.
4. **Given** an unauthenticated person or an authenticated person without operations
   permission, **When** they request the operator order view or a lifecycle change,
   **Then** private order data is not disclosed and no order changes.

### Edge Cases

- Quantity must be a positive kilogram value with no more than two decimal places and
  must not exceed the offer's remaining orderable quantity at confirmation. A total that
  results in a fraction of a centime is rounded half-up once to the nearest centime.
- Review never reserves stock. Confirmation rechecks the offer's publication state,
  availability window, service date, price, and remaining quantity against current data.
- Concurrent confirmations that together exceed remaining quantity must not overbook the
  offer; only confirmations covered by available quantity succeed.
- Repeated confirmation caused by a retry or double submission must produce at most one
  order for the same confirmation attempt and must not reserve quantity twice.
- Client-supplied unit prices, totals, statuses, service dates, offer descriptions, or
  eligibility flags are ignored or rejected; authoritative values come from the active
  offer and ordering rules.
- A selected delivery slot must still belong to the offer, have an end later than its
  start, fall within the offer's available service period, and use the platform's
  Casablanca service time.
- B2C delivery requires a supported delivery zone. A free-text address or delivery note
  may supplement the zone but cannot replace it with an unsupported zone.
- Whitespace-only, malformed, overlong, unexpected, or hostile contact, business,
  address, and delivery-note input is rejected safely without persisting a partial order.
- Phone numbers must be valid for operational contact; an optional email address must be
  valid when supplied.
- An order that is cancelled before grouping releases its reserved quantity exactly
  once. Cancellation after grouping or later fulfillment states is rejected in this
  phase because downstream reversal workflows are out of scope.
- Missing or non-public offers, stale order references, and references belonging to a
  different order return safe not-found or conflict outcomes without revealing private
  records.
- A successfully confirmed order remains historically accurate if its offer is later
  superseded, withdrawn, depleted, or repriced through a replacement publication.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Public visitors MUST be able to start B2C or B2B ordering only from a
  currently public, active offer whose service period permits ordering.
- **FR-002**: A B2C order MUST collect customer name, valid phone number, delivery zone,
  quantity in kilograms, and one available offer-defined delivery slot. Email address,
  delivery address, and delivery note are optional.
- **FR-003**: A B2B order MUST collect business name, buyer contact name, valid phone
  number, delivery zone, quantity in kilograms, and one available offer-defined delivery
  slot. Email address is optional.
- **FR-004**: Neither B2C nor B2B order confirmation MUST require or automatically create
  a customer account.
- **FR-005**: Before confirmation, the system MUST show channel, crop, quantity, delivery
  zone, selected delivery slot, authoritative unit price in MAD/kg, and total in MAD.
- **FR-006**: The order total MUST equal the authoritative offer unit price multiplied by
  the confirmed quantity using decimal arithmetic. If multiplication produces a
  fraction of a centime, the final total MUST be rounded half-up once to the nearest
  centime. MAD amounts MUST be displayed to two decimal places, with no hidden price
  component added during confirmation.
- **FR-007**: Confirmation MUST revalidate that the selected offer is public and active,
  that the selected delivery slot still belongs to the offer and remains available, and
  that the requested quantity is positive and does not exceed its remaining orderable
  quantity. Phase 2 MUST NOT impose channel-specific quantity thresholds.
- **FR-008**: A successful confirmation MUST atomically reserve the order quantity so
  concurrent orders cannot make confirmed demand exceed the offer's orderable quantity.
- **FR-009**: Every confirmed order MUST preserve an immutable snapshot of its offer
  reference, crop description, channel, quantity, MAD currency, unit price, total,
  service date, and confirmation time.
- **FR-010**: Later changes to offer availability, public status, or replacement price
  MUST NOT change any confirmed order snapshot or historical total.
- **FR-011**: Each confirmation attempt MUST have replay protection so repeating the same
  attempt creates at most one order and reserves quantity at most once.
- **FR-012**: A successful order MUST receive a non-sequential public reference that does
  not expose internal identifiers or allow practical enumeration of other orders.
- **FR-013**: The lifecycle MUST support `pending`, `confirmed`, `grouped`, `allocated`,
  `dispatched`, `delivered`, and `cancelled` states and MUST record when each accepted
  transition occurred.
- **FR-014**: This feature MUST allow only these lifecycle paths: `pending` to
  `confirmed` or `cancelled`; `confirmed` to `grouped` or `cancelled`; `grouped` to
  `allocated`; `allocated` to `dispatched`; and `dispatched` to `delivered`.
  `delivered` and `cancelled` are terminal states.
- **FR-015**: Only confirmed orders that are not cancelled, not already grouped, assigned
  to the applicable next service date, and still associated with their recorded offer
  MUST be marked eligible for the next daily consolidation cycle.
- **FR-016**: Cancelling a confirmed but ungrouped order MUST release its reserved
  quantity exactly once while preserving its order record and confirmed price snapshot.
- **FR-017**: An authorized operations actor MUST be able to list and inspect orders with
  channel, reference, offer, quantity, price snapshot, service date, delivery zone,
  lifecycle state, transition history, and consolidation eligibility.
- **FR-018**: The operator view MUST support bounded filtering by channel, lifecycle
  state, service date, offer, and delivery zone so staff can reconcile the next cycle's
  demand without exposing the list publicly.
- **FR-019**: Only authorized operational workflows MAY advance orders after
  confirmation or cancel them before grouping; order submitters MUST NOT be able to set
  status, cancel an order, set consolidation eligibility, service date, unit price,
  total, or internal actor attribution.
- **FR-020**: Invalid, inactive, unavailable, depleted, conflicting, duplicate, or
  unauthorized submissions MUST fail without creating a partial order or incorrect
  reservation.
- **FR-021**: Live payments, refunds, customer accounts, loyalty, public order tracking,
  route optimization, driver tracking, consolidation execution, allocation, dispatch,
  and delivery execution are outside this feature. Later phases may perform the defined
  post-confirmation transitions without rewriting the order snapshot.

### Security and Data Requirements *(mandatory)*

- **SR-001**: Public order review and confirmation MUST accept only explicitly allowed
  fields and MUST enforce server-side validation for presence, type, format, precision,
  range, supported zone, chronological validity, and safe length as applicable.
- **SR-002**: Public order review and confirmation MUST use abuse controls that permit
  normal guest ordering while throttling sustained automated submissions and returning a
  clear retry-later outcome.
- **SR-003**: Customer names, business contact names, phone numbers, email addresses,
  delivery addresses, and delivery notes are personal or commercially sensitive data.
  They MUST be encrypted in transit and protected at rest, available only to authorized
  operational actors for fulfillment and support, and excluded from public responses,
  public page metadata, analytics payloads, logs, and error messages.
- **SR-004**: The post-confirmation public response MUST disclose only the order's public
  reference, channel, crop, quantity, delivery zone, selected delivery slot, price snapshot,
  service date, and status, plus no more contact information than a masked confirmation
  destination.
- **SR-005**: Operator listing, inspection, cancellation, and lifecycle transitions MUST
  require authenticated operations permission independently of interface visibility.
- **SR-006**: Unauthenticated protected requests MUST receive an
  authentication-required outcome; authenticated but unauthorized requests MUST receive
  a forbidden outcome; malformed input MUST receive field-specific validation outcomes;
  depleted or changed offers and invalid state transitions MUST receive conflict
  outcomes; missing records MUST receive not-found outcomes without exposing private
  record existence.
- **SR-007**: Public order references, confirmation tokens, internal order identifiers,
  and operator identifiers MUST NOT permit order enumeration or access to another
  customer's personal data.
- **SR-008**: Diagnostics MUST retain enough non-sensitive context to investigate a
  failed confirmation or transition without recording complete submitted payloads,
  contact details, addresses, notes, tokens, or other personal data.
- **SR-009**: Personal data MUST be retained only under the platform's approved
  fulfillment, support, and legal-retention policy; retention or removal MUST never alter
  the non-personal confirmed price snapshot and consolidation history required for
  operational reconciliation.

### Key Entities *(include if feature involves data)*

- **Order**: Channel-specific demand linked to one published offer, with public
  reference, service date, delivery zone, lifecycle state, consolidation eligibility,
  timestamps, and an immutable confirmed price snapshot.
- **Order Price Snapshot**: The immutable crop description, quantity, MAD currency, unit
  price, total, offer reference, and confirmation time that preserve the commercial
  terms accepted by the buyer.
- **B2C Order Details**: Private consumer name, required phone number, optional email,
  selected offer-defined delivery slot, optional address or delivery note, and
  relationship to the common order.
- **B2B Order Details**: Private business name, buyer contact name, required phone number,
  optional email, selected offer-defined delivery slot, and relationship to the common
  order.
- **Order Status Transition**: An append-only record of an accepted lifecycle change,
  its time, previous and resulting state, and private actor attribution when an operator
  initiated it.
- **Product Offer**: The published crop, public availability, orderable quantity,
  service period, and authoritative unit price against which review and confirmation are
  validated.
- **Operations Actor**: An authenticated staff actor permitted to view private order
  details and perform the lifecycle actions belonging to this or later phases.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: At least 90% of first-time test participants can confirm a valid guest B2C
  order in under 3 minutes without assistance or account creation.
- **SC-002**: At least 90% of first-time professional-buyer test participants can confirm
  a valid B2B order in under 4 minutes and provide all required business and
  delivery-window information without staff correction.
- **SC-003**: In the acceptance dataset, a 5 kg B2C order and a 40 kg B2B order from the
  same active tomato offer both confirm successfully, appear in the authorized operator
  view, preserve their original unit prices and totals, and contribute exactly 45 kg of
  eligible demand to the next cycle.
- **SC-004**: For 100% of confirmed orders, the stored total equals confirmed quantity
  multiplied by confirmed unit price to the currency's supported precision, and remains
  unchanged after offer replacement, withdrawal, or availability changes.
- **SC-005**: Across concurrent and repeated-submission tests, confirmed quantities never
  exceed offer orderable quantity, and one confirmation attempt creates no more than one
  order and one reservation.
- **SC-006**: For 100% of invalid, depleted, inactive, conflicting, duplicate, and
  unauthorized attempts, no partial order, excess reservation, lifecycle change, or
  incorrect consolidation eligibility is created.
- **SC-007**: For 100% of lifecycle tests, only the defined transitions succeed,
  terminal states cannot advance, and no transition changes a confirmed price snapshot.
- **SC-008**: A privacy review of public responses, page metadata, logs, analytics
  payloads, and unauthorized failure paths finds zero unmasked names, contact details,
  addresses, delivery notes, private actor identifiers, or confirmation tokens.
- **SC-009**: An authorized operator can locate all orders for a chosen next-cycle
  service date and reconcile eligible order count and kilograms by channel, offer, and
  delivery zone in under 2 minutes for a dataset of 500 orders.

## Assumptions

- Phase 1 supplies published offers with authoritative MAD/kg prices, service periods,
  public identifiers, and remaining orderable quantities.
- MAD is the only order currency in this phase, and product quantities are measured in
  kilograms with up to two decimal places.
- Delivery zone is mandatory for both channels. A detailed B2C address or delivery note
  is optional during capture and may be completed operationally before dispatch.
- "Small" B2C and "larger" B2B describe the expected use cases but do not impose
  channel-specific minimums or maximums in this phase; both channels are constrained by
  positive quantity and remaining offer availability.
- Each offer defines its selectable delivery slots as date-and-time windows in Casablanca
  service time. Both B2C and B2B buyers must choose one currently available slot; the
  selected slot determines the order's service date and is preserved for later
  fulfillment planning.
- The next daily consolidation cycle uses the order's service date and Casablanca
  operating time. This phase marks eligible orders but does not execute consolidation.
- Pending is a short-lived pre-confirmation processing state. A normal successful public
  submission finishes as confirmed; failed confirmation does not leave an actionable
  pending order.
- Customers receive confirmation on screen. Sending email or SMS notifications is not
  required in this phase.
- Only authorized staff may cancel an order in this MVP. Customer self-service
  cancellation and public order tracking are intentionally excluded because there are no
  customer accounts and secure recovery has not been specified.
- The project will apply its approved personal-data retention policy; defining legal
  retention periods or automated erasure is outside this feature.
