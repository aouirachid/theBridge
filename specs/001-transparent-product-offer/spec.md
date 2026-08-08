# Feature Specification: Transparent Product Offer

**Feature Branch**: `N/A (no branch hook configured)`

**Created**: 2026-08-08

**Status**: Draft

**Input**: User description: "Create a transparent agricultural product offer. An authorized sourcing operator records the crop, origin, available quantity, farmer payment per kilogram, named operating-cost components, platform margin, and an observed market benchmark with source, timestamp, and demo-data status. The system calculates and publishes the final price, customer saving, saving percentage, farmer share, and a public cost breakdown without exposing personal farmer or staff information. Published calculations must be reproducible and unambiguous."

## Clarifications

### Session 2026-08-08

- Q: How fresh must a public market benchmark be, and how long must benchmark data be kept? → A: Show a benchmark from the last 24 hours and retain benchmark observations for at least 30 days.
- Q: What should visitors see when no benchmark from the last 24 hours is available? → A: Keep the offer and platform price visible, hide benchmark-derived savings, and show that a fresh benchmark is unavailable.
- Q: How should a new benchmark become the public comparison for an unchanged offer? → A: An authorized operator reviews and publishes a comparison update without replacing the offer.
- Q: Which operating-cost categories must an offer contain? → A: Require collection, quality control, hub handling and storage, and delivery allocation, while allowing additional named components.
- Q: How long should superseded offers and comparisons remain publicly accessible? → A: Keep them public and marked as superseded for 30 days, then restrict them to authorized staff.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Publish a Reproducible Offer (Priority: P1)

An authorized sourcing operator records the produce, its public origin, availability,
farmer payment, itemized operating costs, platform margin, and market benchmark. Before
publication, the operator reviews the calculated price, savings, and farmer share. The
operator can publish only when all required information is complete and internally
consistent.

**Why this priority**: Publishing one trustworthy offer is the core commercial proof for
Phase 1 and supplies all information needed by the public experience.

**Independent Test**: An authorized operator can enter the illustrative tomato offer,
publish it, and reproduce every displayed result from the recorded inputs alone.

**Acceptance Scenarios**:

1. **Given** an authorized sourcing operator records tomatoes with a farmer payment of
   2.80 MAD/kg, 0.30 MAD/kg collection, 0.20 MAD/kg quality control, 0.30 MAD/kg hub
   handling and storage, 0.90 MAD/kg delivery allocation, a platform margin of
   1.00 MAD/kg, and an 8.00 MAD/kg benchmark, **When** the operator publishes the offer,
   **Then** the published snapshot shows a 5.50 MAD/kg final price, 2.50 MAD/kg customer
   saving, 31.25% saving percentage, and 50.91% farmer share.
2. **Given** an offer is missing a benchmark source, observation timestamp, demo-data
   status, required availability information, or a valid price component, **When** the
   operator attempts publication, **Then** publication is rejected with field-specific
   guidance and no incomplete public offer is created.
3. **Given** a user is unauthenticated or lacks sourcing permission, **When** they attempt
   to create, revise, or publish an offer, **Then** the protected operation is denied and
   no offer data changes.

---

### User Story 2 - Inspect the Public Price Breakdown (Priority: P2)

A public B2B or B2C visitor opens a published offer and sees what the produce is, where
it comes from at a privacy-safe level, when and how much is available, how its final
price is composed, how it compares with the observed market benchmark, and whether the
benchmark is demonstration data.

**Why this priority**: Radical transparency is the customer-facing value proposition;
the visitor must be able to verify the platform's claims without an account.

**Independent Test**: A visitor can use only the public offer to add its listed price
components and independently confirm the final price, savings, saving percentage, and
farmer share, while finding no personal farmer or staff information.

**Acceptance Scenarios**:

1. **Given** a published offer, **When** a public visitor views it, **Then** the visitor
   sees the crop, public origin, available quantity, availability window, farmer payment,
   every named operating cost, platform margin, final price, benchmark details, customer
   saving, saving percentage, and farmer share with units and calculation meanings.
2. **Given** a benchmark marked as demo data, **When** a visitor views the offer, **Then**
   the benchmark and comparison results are visibly identified as based on demo data.
3. **Given** a published offer, **When** any visitor inspects its public information,
   **Then** no farmer or staff name, contact information, exact private address, private
   actor identifier, or other personal information is disclosed.
4. **Given** a published offer has no benchmark observed within the previous 24 hours,
   **When** a public visitor views it, **Then** the offer and platform price remain
   visible, benchmark-derived savings are not shown, and the visitor sees that a fresh
   benchmark is unavailable.

---

### User Story 3 - Correct an Offer Without Rewriting Its Basis (Priority: P3)

An authorized sourcing operator can correct offer information by preparing a replacement
publication, or refresh only its benchmark through a reviewed comparison update, while
every previously published set of inputs and results remains unchanged and reproducible.

**Why this priority**: Corrections are operationally necessary, but silent changes would
undermine the trust established by the published calculation.

**Independent Test**: After publishing an offer, an operator creates a corrected
replacement and both the original snapshot and replacement calculations can be
reproduced from their own recorded inputs.

**Acceptance Scenarios**:

1. **Given** an offer has been published, **When** an authorized operator needs to change
   its crop, origin, availability, price input, or margin, **Then** the original published
   snapshot is not overwritten and the correction is published as a separately
   identifiable replacement.
2. **Given** a replacement is published, **When** a visitor opens the current offer,
   **Then** the visitor sees the current publication and can identify that it replaces an
   earlier publication without receiving private operator information.
3. **Given** a new benchmark observation exists for an otherwise unchanged offer,
   **When** an authorized operator reviews and publishes it, **Then** the public comparison
   uses the new observation without replacing or changing the offer and the earlier
   comparison remains reproducible.
4. **Given** an offer or comparison has been superseded, **When** a visitor opens it
   during the 30 days following supersession, **Then** it remains publicly viewable and
   is clearly marked as superseded; **When** that public window ends, **Then** only an
   authorized staff actor can view it.

### Edge Cases

- Available quantity must be greater than zero and expressed in kilograms; an
  availability window must have an end later than its start.
- Farmer payment and every operating-cost component must be non-negative MAD/kg values
  with no more than two decimal places; platform margin must be a positive MAD/kg value.
- Collection, quality control, hub handling and storage, and delivery allocation must
  each be present as separate standard components, even when a component's value is zero.
- Each operating-cost component must have a non-blank, publicly safe name, and component
  names within one snapshot must be distinct after whitespace and case normalization;
  additional components cannot reuse or imitate a standard category name.
- The benchmark must be greater than zero MAD/kg, its observation time cannot be in the
  future or more than 24 hours before publication, and its source reference must be
  present and safe for public display.
- If the platform price equals the benchmark, saving and saving percentage are zero. If
  it exceeds the benchmark, both are negative so the system never describes a surcharge
  as a saving.
- A zero final price is rejected because farmer share would be undefined.
- Repeated publication attempts for the same draft must produce at most one published
  snapshot and must not duplicate replacement relationships.
- Concurrent changes during publication must not mix inputs from different revisions;
  all outputs belong to one complete set of recorded inputs.
- Malformed, unexpected, duplicate, or hostile input is rejected safely without exposing
  internal details or persisting a partial publication.
- Missing, withdrawn, or unpublished offers return a clear public not-found outcome and
  do not reveal whether private drafts exist.
- When the displayed benchmark becomes older than 24 hours, its price, saving, and saving
  percentage are removed from public display without hiding the offer or its platform
  price; its retained observation remains available only to authorized staff.
- A superseded publication remains public through the first 30 days after supersession
  and becomes staff-only immediately after that window, without changing its recorded
  inputs or results.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST allow only an authenticated actor with sourcing-operator
  permission to create, revise, and publish an offer.
- **FR-002**: An offer draft MUST record crop, privacy-safe origin, available quantity in
  kilograms, availability-window start and end, and its publication state.
- **FR-003**: An offer draft MUST record farmer payment per kilogram, the required
  operating-cost components per kilogram, any additional uniquely named operating-cost
  components, and platform margin per kilogram, all in MAD with no more than two decimal
  places. Its required operating costs MUST be the separate standard categories
  collection, quality control, hub handling and storage, and delivery allocation.
- **FR-004**: Operating-cost components MUST remain separately named and visible; they
  MUST NOT be combined with one another, farmer payment, or platform margin in the public
  breakdown. Operators MAY add uniquely named components beyond the four required
  categories.
- **FR-005**: The benchmark MUST record price per kilogram, market name, source type,
  public source reference, observation timestamp, and an explicit demo-data status.
- **FR-006**: The system MUST calculate final price per kilogram as farmer payment plus
  all operating-cost components plus platform margin.
- **FR-007**: The system MUST calculate customer saving per kilogram as benchmark price
  minus final price.
- **FR-008**: The system MUST calculate saving percentage as customer saving divided by
  benchmark price, multiplied by 100.
- **FR-009**: The system MUST calculate farmer share as farmer payment divided by final
  price, multiplied by 100.
- **FR-010**: Percentage results MUST be calculated from the recorded monetary values,
  without using previously rounded intermediate percentages, and displayed using
  round-half-up to two decimal places.
- **FR-011**: Every displayed monetary value MUST identify MAD and its per-kilogram basis;
  every quantity MUST identify kilograms; every percentage MUST identify its numerator
  and denominator in plain language.
- **FR-012**: Before publication, the system MUST present the operator with the exact
  recorded inputs and calculated outputs that will form the public snapshot.
- **FR-013**: Publication MUST be rejected unless all required fields satisfy the stated
  validation rules and all derived values can be calculated unambiguously.
- **FR-014**: A successful offer publication MUST preserve one complete snapshot
  containing every offer calculation input, derived offer result, initial benchmark
  comparison, and publication time needed to reproduce its public claims.
- **FR-015**: A published snapshot MUST NOT be silently overwritten. Corrections MUST
  create a separately identifiable replacement linked to the publication it replaces,
  except that a benchmark-only refresh follows the comparison-update requirements.
- **FR-016**: Publication MUST be all-or-nothing: visitors MUST never receive a snapshot
  containing only part of its inputs, outputs, or public breakdown.
- **FR-017**: Repeating the same publication request for an unchanged draft MUST NOT
  create duplicate published snapshots.
- **FR-018**: Public visitors MUST be able to view published offers without an account.
- **FR-019**: The public offer MUST display crop, privacy-safe origin, quantity and
  availability, the complete cost breakdown, final price, and farmer share. When an
  eligible fresh benchmark exists, it MUST also display benchmark attribution,
  observation time, demo-data status, saving, and saving percentage.
- **FR-020**: Demo-data status MUST be visibly associated with the benchmark and its
  derived comparison claims, not disclosed only in hidden metadata or fine print.
- **FR-021**: The system MUST show zero or negative comparison values truthfully and MUST
  NOT label a negative saving as a discount or customer benefit.
- **FR-022**: Automated market-data collection, dynamic pricing, QR generation, farmer
  self-service onboarding, ordering, payment, and tamper-evident ledger capabilities are
  outside this feature.
- **FR-023**: A benchmark MUST be eligible for public comparison only when its recorded
  observation time falls within the 24 hours immediately preceding publication.
- **FR-024**: Every recorded benchmark observation MUST be retained for at least 30 days
  from its observation time, whether it represents real or demo data.
- **FR-025**: When no benchmark observed within the previous 24 hours is available, the
  public offer and platform price MUST remain visible, benchmark price and derived saving
  values MUST be hidden, and a clear fresh-benchmark-unavailable notice MUST be shown.
- **FR-026**: An authorized sourcing operator MUST review and explicitly publish a new
  benchmark comparison before it replaces the comparison shown for an unchanged offer;
  merely recording an observation MUST NOT change public information.
- **FR-027**: Publishing a benchmark comparison update MUST preserve a separately
  identifiable snapshot of its benchmark input, saving outputs, publication time, and
  relationship to the unchanged offer without changing the offer's inputs or final price.
- **FR-028**: All four standard operating-cost categories MUST appear in the operator
  review and public breakdown, including categories whose recorded amount is zero.
- **FR-029**: A superseded offer or benchmark comparison MUST remain publicly accessible
  and visibly marked as superseded for 30 days after its supersession time; after that
  window it MUST be accessible only to authorized staff and MUST remain reproducible from
  its unchanged recorded inputs.

### Security and Data Requirements *(mandatory)*

- **SR-001**: Create, revise, preview, publish, and replace operations MUST enforce
  sourcing-operator authorization independently of any controls shown in the interface.
- **SR-002**: Every user-controlled field MUST be checked for required presence, type,
  length, allowed precision, range, chronological validity, and public-display safety as
  applicable; invalid submissions MUST return field-specific guidance without persisting
  a partial publication.
- **SR-003**: Farmer names, farmer contact details, exact private farm addresses, staff
  names, staff contact details, private actor identifiers, credentials, and session data
  are sensitive and MUST NOT appear in public offer responses, public source references,
  page metadata, logs, or error messages. Only a deliberately generalized origin may be
  published.
- **SR-004**: Private actor attribution used for internal accountability MUST remain
  access-controlled and must not be inferable from public replacement or publication
  identifiers.
- **SR-005**: Public offer access and protected offer mutations MUST use the project's
  abuse controls. Limits MUST permit ordinary browsing and operator work while rejecting
  sustained automated request floods with a clear retry-later outcome.
- **SR-006**: Unauthenticated protected requests MUST receive an authentication-required
  outcome; authenticated actors without permission MUST receive a forbidden outcome;
  invalid input MUST receive a validation outcome; conflicting or repeated publication
  MUST resolve safely without duplication; missing public offers MUST receive a
  not-found outcome without exposing drafts.
- **SR-007**: Public information MUST be limited to fields explicitly approved for
  disclosure so adding private internal information cannot expose it by default.
- **SR-008**: Diagnostic records MUST identify failures without recording complete
  submitted payloads or any personal farmer or staff information.

### Key Entities *(include if feature involves data)*

- **Product Offer Draft**: The operator-managed working record containing crop, public
  origin, quantity, availability window, monetary inputs, benchmark, and readiness for
  publication.
- **Operating-Cost Component**: A uniquely named per-kilogram cost belonging to one offer
  snapshot, distinct from farmer payment and platform margin. Each offer has the four
  required standard categories and may have additional uniquely named components.
- **Market Benchmark**: A price observation with market name, source type, public source
  reference, observation timestamp, explicit demo-data status, 24-hour public-display
  eligibility, and a minimum 30-day retention period.
- **Published Offer Snapshot**: An immutable, publicly viewable set of offer inputs,
  calculated offer outputs, initial comparison, publication time, and optional
  relationship to the offer snapshot it replaces. Once superseded, it remains public for
  30 days before becoming staff-only.
- **Published Benchmark Comparison**: An immutable, operator-reviewed benchmark and its
  derived saving values associated with an unchanged published offer; a newer published
  comparison may supersede it without changing the offer. A superseded comparison remains
  public for 30 days before becoming staff-only.
- **Sourcing Operator**: An authenticated staff actor authorized to manage offer drafts
  and publications; their private identity is excluded from public offer data.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An authorized operator can enter, review, and publish a complete offer in
  under 5 minutes without manual data changes outside the normal workflow.
- **SC-002**: For 100% of published offers, an independent reviewer can reproduce the
  displayed final price, saving, saving percentage, and farmer share from the visible
  recorded inputs and stated formulas, matching displayed values to two decimal places.
- **SC-003**: The illustrative tomato scenario always publishes 5.50 MAD/kg final price,
  2.50 MAD/kg saving, 31.25% saving percentage, and 50.91% farmer share from the stated
  2.80 MAD/kg farmer payment, 2.70 MAD/kg combined costs and margin, and 8.00 MAD/kg
  benchmark.
- **SC-004**: In privacy review of every public offer field and failure state, zero farmer
  or staff names, contact details, exact private addresses, private actor identifiers,
  credentials, or session data are exposed.
- **SC-005**: 100% of incomplete, invalid, unauthorized, and duplicate publication
  attempts are rejected or resolved safely without a partial or duplicate publication.
- **SC-006**: In a moderated usability check, at least 4 of 5 first-time visitors can
  identify the farmer payment, platform margin, benchmark source and time, demo status,
  customer saving, and farmer share within 60 seconds of opening an offer.
- **SC-007**: For 100% of published corrections, the replacement is identifiable and the
  earlier published calculation remains unchanged and reproducible.
- **SC-008**: Under a review set containing below-benchmark, equal-to-benchmark, and
  above-benchmark offers, 100% show mathematically correct comparison values and none
  describe a negative saving as a customer benefit.
- **SC-009**: For 100% of newly published offers, the displayed benchmark was observed
  within the preceding 24 hours, and every benchmark observation remains available to
  authorized staff throughout the 30 days following its observation.
- **SC-010**: For 100% of public offers without a benchmark from the previous 24 hours,
  visitors can still see the offer and platform price, see no stale comparison values,
  and receive a clear fresh-benchmark-unavailable notice.
- **SC-011**: For 100% of benchmark refreshes, public comparison values change only after
  authorized review and publication, the offer's recorded inputs and final price remain
  unchanged, and both the previous and new comparison can be reproduced.
- **SC-012**: For 100% of published offers, reviewers can identify separate collection,
  quality-control, hub handling-and-storage, and delivery-allocation amounts, including
  any zero amount, plus every additional named cost without ambiguity or duplication.
- **SC-013**: For 100% of superseded offers and comparisons, public access remains
  available with a superseded label throughout the 30 days after supersession, public
  access ends after that window, and authorized staff can still reproduce the record.

## Assumptions

- MAD is the only currency in Phase 1, and all price inputs are per kilogram.
- The four standard operating-cost categories are fixed labels for consistent reporting;
  optional additional categories describe costs not already represented by them.
- Monetary inputs are recorded to at most two decimal places; displayed monetary values
  therefore do not require additional rounding after summation.
- A privacy-safe origin is a region, province, municipality, or similarly generalized
  locality approved for public display, never an exact farm or home address.
- A source reference may be a public URL, document reference, or human-readable field
  observation note that contains no personal information.
- Demo-data status is required for every benchmark: `true` means simulated or illustrative
  data, while `false` means a real observation backed by its displayed source and time.
- The feature relies on the project's existing staff authentication, role/permission,
  session protection, and abuse-control mechanisms; it does not define a new login flow.
- One draft produces at most one published snapshot. Corrections to offer inputs are
  prepared as a new draft and published as a replacement; a benchmark-only refresh is
  reviewed and published as a comparison update on the unchanged offer.
- Phase 1 provides current and replacement offer viewing but not a cryptographic or
  append-only transparency ledger; tamper-evident historical auditing remains Phase 6.
- Automated scraping, live market feeds, dynamic pricing, order capture, payment, QR
  generation, and farmer self-service are excluded.
