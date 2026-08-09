# Feature Specification: Transparency Ledger and Impact Dashboard

**Feature Branch**: `N/A (no branch hook configured)`

**Created**: 2026-08-09

**Status**: Draft

**Input**: User description: "Create an auditable transparency and impact experience for the agricultural platform. Append price and operational events in a tamper-evident chain, prohibit normal application updates and deletions, verify chain integrity, and publish a privacy-safe dashboard showing the sourced market benchmark, farmer payment, every cost component, platform margin, final price, customer saving, farmer share, grouped orders, consolidated kilograms, and delivery groups. Environmental indicators must be labeled as estimates unless backed by measured data."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Understand the Complete Price (Priority: P1)

A public visitor or hackathon juror opens one transparency page and follows each dirham
from the farmer payment through every operating cost and platform margin to the final
price. The visitor can compare that price with an attributed market benchmark and see
the customer saving and farmer share without seeing personal farmer, customer, or staff
information.

**Why this priority**: The platform's central promise is that its price and benefit
claims are understandable, sourced, and reproducible by an independent visitor.

**Independent Test**: Publish the demonstration tomato scenario and verify that a guest
can identify the benchmark source and time, farmer payment, every named cost including
zero-value components, platform margin, final price, saving, saving percentage, farmer
share, data status, and integrity status from one page.

**Acceptance Scenarios**:

1. **Given** a 2.80 MAD/kg farmer payment, named operating costs totaling 1.70 MAD/kg, a
   1.00 MAD/kg platform margin, and an eligible 8.00 MAD/kg benchmark, **When** a visitor
   opens the tomato transparency page, **Then** the page shows a 5.50 MAD/kg final price,
   2.50 MAD/kg and 31.25% customer saving, 50.91% farmer share, every component, and the
   benchmark's source, observation time, and real-or-demo status.
2. **Given** the latest benchmark is stale or unavailable, **When** the page is opened,
   **Then** the platform price and its full breakdown remain visible, saving claims are
   withheld, and the page explains that no eligible benchmark is available.
3. **Given** the platform price equals or exceeds the benchmark, **When** the comparison
   is displayed, **Then** the result is mathematically accurate and is not described as
   a saving or customer benefit when it is zero or negative.
4. **Given** private farmer, customer, business, and staff records support the scenario,
   **When** any public dashboard response, metadata, or failure state is inspected,
   **Then** none of those identities, contacts, exact addresses, notes, or internal
   identifiers are disclosed.

---

### User Story 2 - Verify Historical Integrity (Priority: P1)

An authorized platform operator verifies that the financial and operational event
history remains complete and unchanged. A public visitor sees a simple current integrity
indicator, the time and scope of the latest completed verification, and an honest warning
when integrity is invalid, incomplete, or has never been verified.

**Why this priority**: Visible calculations are not auditable if historical records can
be silently rewritten or if an integrity claim is shown without a completed check.

**Independent Test**: Create a representative event chain, verify it successfully,
attempt normal updates and deletions, and introduce one controlled historical alteration
in an isolated test. Normal mutations are rejected, and the controlled alteration makes
the next verification invalid without changing or concealing the affected history.

**Acceptance Scenarios**:

1. **Given** valid offer, order, consolidation, hub, loss, allocation, and dispatch
   events, **When** an authorized operator requests verification, **Then** every entry is
   checked in deterministic order and the completed result is valid with its checked
   scope, entry count, and completion time.
2. **Given** a historical ledger entry, **When** any normal application flow attempts to
   update or delete it, **Then** the operation is rejected and the entry and chain remain
   unchanged.
3. **Given** an earlier entry's protected content, predecessor link, or integrity proof
   is altered through a controlled test mechanism, **When** verification runs, **Then**
   the result is invalid and identifies a safe failure location to authorized staff.
4. **Given** a valid result exists and a newer event is appended, **When** the public
   page is opened before another verification completes, **Then** it does not describe
   the unverified expanded chain as currently valid.

---

### User Story 3 - See Consolidation Impact (Priority: P2)

A visitor sees how individual demand was combined into an operationally meaningful
delivery scope: the number of orders grouped, total kilograms consolidated, and number
of delivery groups created. An operator can reconcile the public totals to safe source
records without exposing individual buyers.

**Why this priority**: Consolidation is the operational evidence behind the platform's
efficiency claim and completes the story beyond price alone.

**Independent Test**: Run a completed demonstration cycle containing B2B and B2C orders
across multiple zones, then confirm that public totals count each eligible source once,
sum quantities exactly, show their date/product/channel scope, and reveal no order-level
personal data.

**Acceptance Scenarios**:

1. **Given** a completed cycle with grouped orders across multiple delivery groups,
   **When** the dashboard is opened for that cycle, **Then** it shows exact distinct
   order count, consolidated kilograms, delivery-group count, and the reporting scope.
2. **Given** a replayed consolidation, allocation, or dispatch operation, **When** impact
   totals are refreshed, **Then** no order, kilogram, group, loss, or event is counted
   twice.
3. **Given** a cycle is incomplete or its source totals do not reconcile, **When** the
   public dashboard is viewed, **Then** affected metrics are marked incomplete or
   unavailable rather than presented as final impact.

---

### User Story 4 - Distinguish Estimated and Measured Impact (Priority: P2)

A visitor can tell immediately whether an environmental or transport-efficiency
indicator is an estimate or a measurement, understand its unit and reporting period,
and inspect the stated method or evidence supporting it.

**Why this priority**: Honest qualification prevents illustrative operational benefits
from becoming unsupported environmental claims.

**Independent Test**: Review one estimated trip-reduction indicator, one measured
indicator with evidence, and one unsupported carbon claim. The estimate is prominently
labeled with its method, the measured value links to its evidence and period, and the
unsupported claim is not published.

**Acceptance Scenarios**:

1. **Given** trip reduction is derived from order and delivery-group counts rather than
   observed trips, **When** it appears publicly, **Then** it is labeled `Estimate` next
   to the value and shows its calculation method, inputs, unit, period, and update time.
2. **Given** an authorized operator has recorded measured data with a source, method,
   unit, reporting period, and evidence reference, **When** the indicator is published,
   **Then** it is labeled `Measured` and exposes that non-sensitive provenance.
3. **Given** no defensible measurement or documented estimation method exists for CO2
   or another environmental claim, **When** the dashboard is built, **Then** no numeric
   claim is displayed and the absence is stated honestly where relevant.

### Edge Cases

- The first ledger entry has no predecessor and must be verifiable as the declared
  beginning of exactly one chain; a second beginning, missing entry, duplicate position,
  broken predecessor, or out-of-order entry makes verification invalid.
- Financial and quantity event content must use the exact immutable upstream snapshots,
  units, precision, and occurrence times. Later offer, benchmark, order, cost, or status
  changes must not rewrite earlier ledger content.
- A business correction must append a new correction or reversal event that references
  the affected safe event; it must not replace, edit, or delete history.
- Repeated or concurrent handling of the same business event must append it at most once
  and must not fork the chain. Distinct concurrent events must receive one deterministic
  order before either is treated as committed.
- A failed source operation must not create a success ledger event. If the source
  operation and its audit event cannot both complete, the business operation must not be
  represented as successfully auditable.
- Unknown event types, missing source records, malformed values, negative amounts where
  prohibited, invalid precision, future occurrence times, unexpected fields, or
  cross-scoped references are rejected without a partial entry.
- Verification interrupted by timeout, resource limits, or concurrent appends must
  produce an incomplete result, never a valid result. A prior result remains historical
  and is visibly scoped to the chain state it actually checked.
- An invalid or incomplete chain must remain inspectable by authorized operators, while
  the public page must show a safe warning and must not imply that affected claims have
  passed integrity verification.
- Dashboard totals must distinguish zero from unavailable, preserve MAD/kg versus total
  MAD and kilograms, and state the numerator and denominator of each percentage.
- Group counts use distinct finalized groups, order counts use distinct included orders,
  and consolidated kilograms use the recorded grouped quantities. Cancelled, rejected,
  damaged, late, or otherwise excluded quantities must not silently inflate impact.
- Empty reporting scopes show a clear no-data state rather than zero impact. Superseded,
  demo, stale, incomplete, and invalid records retain their respective labels.
- Estimated indicators must not use visual treatment or wording that could reasonably be
  mistaken for measurements. A mixture of measured and estimated inputs is classified
  as estimated.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST append a financial or operational ledger event exactly
  once for each successful offer publication, benchmark comparison publication, order
  confirmation, consolidation completion, hub receipt, recorded rejection or loss,
  stock allocation, dispatch handoff, actual delivery-cost allocation, and accepted
  lifecycle change relevant to the transparency story.
- **FR-002**: Each ledger entry MUST preserve a unique safe event identity, event type,
  safe subject reference, canonical allowlisted event facts, source occurrence time,
  append time, private actor attribution when applicable, its predecessor's integrity
  proof, and its own integrity proof.
- **FR-003**: The first entry MUST be explicitly identifiable as the chain beginning;
  every later entry MUST be linked to exactly one immediately preceding entry, creating
  one deterministic history with no missing positions, duplicate positions, or forks.
- **FR-004**: An event's integrity proof MUST cover its immutable identity, type, safe
  subject, canonical facts, source time, append time, chain position, private actor
  attribution, and predecessor proof so alteration of any protected fact is detectable.
- **FR-005**: The same logical source event MUST NOT create more than one ledger entry,
  including after retries, repeated delivery, or concurrent processing.
- **FR-006**: Concurrent distinct events MUST be serialized into one unambiguous chain
  order, and a failed or conflicting append MUST leave no partial entry, gap, or fork.
- **FR-007**: Ledger entries MUST be append-only through every normal application path.
  No public, staff, administrative, bulk, maintenance, or application service-account
  operation may update or delete a committed entry.
- **FR-008**: Corrections, reversals, and late facts MUST be represented by new events
  referencing the affected safe event and explaining the correction category; they MUST
  NOT replace or obscure the original event.
- **FR-009**: A successful auditable source operation MUST not be exposed as complete
  unless its required ledger event is also durably appended; a failed source operation
  MUST NOT append a success event.
- **FR-010**: An authorized platform operator MUST be able to request deterministic
  verification of a bounded full chain state and inspect its result.
- **FR-011**: Verification MUST check the declared chain beginning, continuous and unique
  ordering, predecessor linkage, recomputed integrity proof for every entry, expected
  entry count, and the exact chain endpoint included in the verification.
- **FR-012**: Each completed verification result MUST state valid, invalid, or incomplete;
  identify the checked chain endpoint and entry count; record start and completion times;
  and provide an authorized, non-sensitive failure category and safe first-failure
  location when not valid.
- **FR-013**: A verification result MUST apply only to the exact endpoint it checked.
  Appending a newer event MUST make clear that the expanded chain has not yet received a
  completed current verification.
- **FR-014**: The public dashboard MUST show a simple valid, invalid, incomplete, stale,
  or not-yet-verified integrity indicator with the last completed verification time and
  checked scope, without exposing private actor data or internal diagnostics.
- **FR-015**: The public transparency experience MUST present one coherent view of the
  latest eligible published offer snapshot, its eligible published benchmark comparison,
  the related completed consolidation scope, and its latest applicable integrity result.
- **FR-016**: The price view MUST display farmer payment, every standard and additional
  named operating-cost component including recorded zero amounts, delivery allocation,
  platform margin, final platform price, MAD currency, and per-kilogram basis.
- **FR-017**: The benchmark view MUST display benchmark price, market name, source type,
  privacy-safe source reference, observation time, publication time, demo-data status,
  and freshness status whenever it is eligible for comparison.
- **FR-018**: The dashboard MUST derive and display customer saving, saving percentage,
  and farmer share from the immutable published values using the same formulas and
  rounding rules as the source offer. It MUST NOT recalculate historical claims from
  later mutable values.
- **FR-019**: The dashboard MUST withhold saving values when no eligible fresh benchmark
  exists and MUST display zero or negative comparison values truthfully without benefit
  language.
- **FR-020**: The dashboard MUST show distinct grouped-order count, consolidated
  kilograms, and distinct delivery-group count for a clearly identified completed
  service-date, product, channel, and zone scope, with combined totals only where the
  constituent scopes are visible and reconcilable.
- **FR-021**: Impact totals MUST count each eligible source order, quantity, and group
  exactly once, exclude cancelled or ineligible demand, distinguish rejected and damaged
  quantities from consolidated kilograms, and expose incomplete or unreconciled scopes
  rather than presenting them as final.
- **FR-022**: An authorized operator MUST be able to reconcile each public monetary and
  consolidation figure to privacy-safe source references and the corresponding ledger
  events without changing those records.
- **FR-023**: Every published environmental or transport-efficiency indicator MUST be
  classified as `Estimate` or `Measured` and display its name, value, unit, reporting
  period, method, source or evidence reference, and last update time.
- **FR-024**: An environmental indicator MAY be classified as `Measured` only when all
  inputs are observations from the stated reporting period and the supporting evidence
  and measurement method are recorded. Any modeled, assumed, extrapolated, or mixed
  input requires the `Estimate` classification.
- **FR-025**: Estimated indicators MUST show the `Estimate` label immediately beside
  the value and disclose their formula, input scope, assumptions, and known limitation
  in plain language.
- **FR-026**: The system MUST NOT publish a numeric CO2, emissions, trip-reduction, waste,
  or other environmental claim without either measured evidence or a documented,
  reproducible estimation method. Missing support MUST result in no numeric claim.
- **FR-027**: The feature MUST provide one visibly identified demonstration tomato
  scenario spanning offer publication through delivery impact, using 2.80 MAD/kg farmer
  payment, 5.50 MAD/kg final price, and 8.00 MAD/kg benchmark, with deterministic
  consolidation data and no claim that simulated inputs are real observations.
- **FR-028**: Public blockchain, cryptocurrency, tokens, legal certification, QR
  scanning, live market scraping, carbon-credit accounting, and unsupported
  environmental claims MUST remain outside this feature.

### Security and Data Requirements *(mandatory)*

- **SR-001**: Ledger appends MUST be initiated only by trusted successful business
  workflows. Verification requests, private failure details, reconciliation, and
  environmental-indicator management MUST require an authenticated user with the
  appropriate operational permission; interface visibility alone MUST NOT grant access.
- **SR-002**: No public or staff input may supply a predecessor proof, integrity proof,
  chain position, private actor attribution, calculated price, or aggregate total.
  User-controlled filters, periods, safe references, environmental inputs, methods, and
  evidence references MUST be allowlisted and validated for type, precision, range,
  chronology, ownership, lifecycle, and safe length as applicable.
- **SR-003**: Ledger payloads MUST contain only the minimum allowlisted non-personal facts
  required to prove the business event. Farmer, customer, business-contact, and staff
  names; phones; emails; exact addresses; delivery notes; credentials; tokens; session
  data; raw requests or responses; and private provider data MUST never enter the ledger.
- **SR-004**: Private actor attribution and detailed integrity failures MUST be protected
  in transit and at rest and disclosed only to authorized staff. Public responses,
  metadata, analytics, exports, logs, and exception messages MUST use approved safe
  references, aggregate values, and coarse integrity outcomes only.
- **SR-005**: Ledger entries and verification records MUST NOT be written to diagnostic
  logs as complete payloads. Diagnostics may include only a safe event reference, safe
  chain position, operation, timing, and allowlisted failure category.
- **SR-006**: Public dashboard access and protected verification, reconciliation, and
  indicator-management operations MUST use abuse controls appropriate to their cost.
  Verification MUST be bounded so repeated requests cannot exhaust resources or create
  overlapping uncontrolled checks.
- **SR-007**: Unauthenticated protected requests MUST receive an
  authentication-required outcome; authenticated but unauthorized requests MUST receive
  a forbidden outcome; malformed input MUST receive field-specific validation outcomes;
  duplicate, stale, conflicting, or concurrent operations MUST receive safe conflict
  outcomes; missing or cross-scoped records MUST receive safe not-found outcomes.
- **SR-008**: Public references MUST be non-sequential and must not permit enumeration of
  orders, people, staff actors, provider records, private evidence, or internal ledger
  subjects. Public source and evidence references MUST be reviewed as safe for disclosure.
- **SR-009**: Append-only audit records MUST use non-personal business snapshots so the
  approved removal of personal data does not erase price, quantity, cost, consolidation,
  dispatch, or integrity history. Retention of source evidence remains governed by the
  platform's approved business and legal-retention policy.

### Key Entities *(include if feature involves data)*

- **Ledger Entry**: One immutable financial or operational event with a unique safe
  identity, type, safe subject, canonical facts, occurrence and append times, chain
  position, predecessor proof, own integrity proof, and private actor attribution where
  applicable.
- **Ledger Verification**: The result of checking one exact chain endpoint, including
  status, entry count, start and completion times, and an authorized safe failure
  category and location when verification is not valid.
- **Correction Event**: A new ledger entry that references an earlier safe event and
  records a correction, reversal, or late fact without changing the earlier event.
- **Transparency View**: The privacy-safe association of one published offer and
  benchmark comparison with completed operational impact and its applicable integrity
  result.
- **Price Breakdown**: The immutable farmer payment, named operating-cost components,
  delivery allocation, platform margin, final price, benchmark, saving, saving
  percentage, and farmer share shown in MAD per kilogram.
- **Consolidation Impact Summary**: Exact distinct grouped orders, consolidated
  kilograms, delivery groups, rejected or damaged quantities, and completion status for
  one clearly identified reporting scope.
- **Impact Indicator**: A measured or estimated environmental or transport-efficiency
  value with unit, period, method, provenance, assumptions, evidence status, and update
  time.
- **Transparency Operator**: An authenticated staff member allowed to run verification,
  inspect safe reconciliation detail, and manage indicator evidence; their identity is
  never public.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: For 100% of published scenarios, an independent reviewer can reproduce
  final price, customer saving, saving percentage, and farmer share from the visible
  inputs and formulas, matching every displayed result to two decimal places.
- **SC-002**: The demonstration tomato scenario consistently shows 2.80 MAD/kg farmer
  payment, 5.50 MAD/kg final price, 8.00 MAD/kg benchmark, 2.50 MAD/kg and 31.25% saving,
  and 50.91% farmer share, with every cost and data-status label visible.
- **SC-003**: Across repeated and concurrent processing of at least 10,000 representative
  source events, every logical event appears exactly once in one unbroken order, with
  zero duplicate positions, gaps, forks, or partial entries.
- **SC-004**: Verification of an unchanged representative 10,000-entry history completes
  within 30 seconds and reports valid; each controlled alteration of protected content,
  order, or predecessor linkage is detected and reports invalid in 100% of test cases.
- **SC-005**: For 100% of attempted normal updates and deletions, no committed ledger
  entry changes or disappears; every correction is represented only by an additional
  traceable event.
- **SC-006**: At least 4 of 5 first-time visitors can identify the farmer payment,
  platform margin, benchmark source and status, final price, saving, farmer share,
  grouped orders, consolidated kilograms, delivery groups, and integrity status within
  90 seconds of opening the page.
- **SC-007**: For every completed demonstration cycle, public grouped-order, kilogram,
  delivery-group, rejected, and damaged totals reconcile with their eligible source
  records with zero duplicate counting and zero unexplained variance.
- **SC-008**: Every environmental or transport-efficiency value in a publication review
  has a visible estimate-or-measured label, unit, period, method, and provenance; 100% of
  unsupported numeric claims are withheld.
- **SC-009**: A privacy review of ledger content, public responses, page metadata,
  analytics, exports, logs, exceptions, and integrity failures finds zero personal names,
  phones, emails, exact addresses, delivery notes, credentials, tokens, session data,
  raw payloads, private provider data, or private actor identifiers.
- **SC-010**: For 100% of stale, incomplete, invalid, or never-verified scopes, the
  public dashboard communicates that status and never labels affected claims as having a
  current valid integrity check.
- **SC-011**: The public transparency page presents the full seeded scenario within 2
  seconds for at least 95% of local demonstration views, and an authorized operator can
  reconcile any displayed claim to safe source events in under 3 minutes.

## Assumptions

- This is roadmap Phase 006 and uses sequential feature directory
  `006-transparency-impact`; no branch-creation hook is configured.
- Phases 001 through 005 provide immutable published offer and benchmark snapshots,
  confirmed order price snapshots, completed consolidation groups, hub receipt and loss
  records, stock allocations, dispatch status history, and actual delivery-cost
  allocations. Phase 006 does not recreate or silently repair missing upstream facts.
- One platform-wide ordered chain is sufficient for the hackathon volume. The plan may
  define bounded verification execution, but every valid result covers an explicitly
  identified chain beginning and endpoint rather than a sampled subset.
- MAD is the only currency; monetary dashboard values retain their recorded total or
  per-kilogram basis and use two decimal places. Quantities retain the upstream maximum
  of two decimal places in kilograms. Derived percentages use unrounded recorded values
  and display round-half-up to two decimal places.
- The upstream benchmark freshness, demo-data, supersession, and 30-day public-history
  rules remain authoritative. Phase 006 adds audit evidence and a unified view without
  making stale or simulated data appear observed.
- Grouped-order and consolidated-kilogram metrics come from completed consolidation
  membership. Delivery-group counts use distinct finalized operational delivery groups
  for the same stated scope, not provider retry attempts or status-history rows.
- Trip reduction may be estimated from a documented comparison between individual-order
  trips and actual finalized delivery groups. It remains an estimate unless actual trip
  observations for both sides of the comparison exist. No CO2 value is assumed for the
  seeded demonstration.
- The seeded tomato scenario is visibly demonstration data wherever its market,
  operational, or environmental inputs are simulated. It may coexist with real records
  without being included in real-impact totals.
- The feature relies on existing staff authentication and operations permissions and
  introduces no new customer account, payment, blockchain, certification, or live
  external-data integration.
