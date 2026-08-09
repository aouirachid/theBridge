# Frontend Product Requirements Document

## Radical Transparency Agri-Tech Platform

| Document field | Value |
| --- | --- |
| Status | Product baseline for design and frontend delivery |
| Version | 1.0 |
| Date | 2026-08-09 |
| Product stage | Hackathon MVP with post-hackathon direction |
| Primary market | Casablanca, Morocco |
| Currency | Moroccan dirham (MAD) |
| Timezone | Africa/Casablanca |
| Frontend stack | React 19, Inertia.js 3, TypeScript, Tailwind CSS 4, existing UI primitives, Wayfinder |
| Source of truth | `contextRoadmap.md` and feature specifications 001-006 |

## 1. Product Summary

The platform is a transparent agricultural distribution service connecting Moroccan farmers with Casablanca professional buyers and consumers. It replaces multiple opaque supply-chain handovers with one accountable operation: direct sourcing, short-term micro-hub consolidation, quality control, preparation, and coordinated delivery.

The frontend must make two propositions immediately understandable:

1. Every dirham between the farm gate and the final price is explained.
2. Customer demand is consolidated into an operational flow that improves farmer share and buyer value without pretending that simulated benchmarks, estimated impact, or mocked delivery integrations are real.

The product supports two commercial channels:

- **B2C:** consumers place small guest orders from a public offer page. Delivery is represented by a clearly labeled mock external-provider flow in the MVP.
- **B2B:** greengrocers, cafes, restaurants, and small retailers place larger guest orders. Operations staff consolidate and fulfill them through zone-based delivery manifests.

The full frontend spans a public trust-and-ordering experience and an authenticated staff operations workspace. It does not include customer accounts, live payments, live driver tracking, or a live Glovo/Yassir integration.

## 2. Product Goals

### 2.1 Primary goals

- Let a first-time visitor understand the platform price, farmer payment, named operating costs, platform margin, market benchmark, saving, and farmer share without an account.
- Let B2C and B2B buyers review and confirm an order from the same public offer while collecting only channel-appropriate information.
- Let authorized staff move work from offer publication through ordering, consolidation, hub receiving, allocation, preparation, and dispatch without direct database intervention.
- Show the integrity and operational impact of the completed journey in a privacy-safe public transparency view.
- Make demo, stale, superseded, estimated, incomplete, failed, and mocked data impossible to mistake for current measured reality.
- Support a reliable end-to-end jury demo on desktop and mobile without unavailable third-party services.

### 2.2 Success measures

- At least four of five first-time visitors can find the farmer payment, final price, platform margin, benchmark source/status, saving, farmer share, grouped-order count, consolidated kilograms, delivery-group count, and integrity status within 90 seconds.
- An operator can create, review, and publish a complete offer in under five minutes.
- A buyer can review and confirm an order without assistance and receives a safe reference immediately.
- Every public monetary claim can be reproduced from visible inputs to two decimal places.
- Staff can complete the seeded tomato scenario from publication to delivered/mock-delivered status without editing the database.
- The main public transparency experience renders within two seconds for at least 95% of local demo views.
- Privacy review finds no farmer, buyer, or staff PII in public props, metadata, errors, logs, or analytics.

## 3. Non-Goals

The MVP must not present or imply support for:

- Customer registration, login, order history, loyalty, or public order tracking.
- Live card payment, refunds, invoices, or financial settlement.
- Live Glovo, Yassir, GPS, driver, route-optimization, or provider-webhook integration.
- Farmer self-service onboarding or farmer-facing dashboards.
- Automated market scraping, dynamic pricing, forecasting, or speculative AI.
- Blockchain, cryptocurrency, tokens, legal certification, or carbon credits.
- QR scanning as a required journey. The transparency receipt may later be linked to a QR or short batch code.
- Unsupported environmental claims. No numeric claim appears without a measured source or documented estimation method.
- Multi-hub inventory, complex batch splitting/merging, returns, disputes, notifications, or live operational polling.

## 4. Users and Permissions

| Persona | Need | Frontend access |
| --- | --- | --- |
| Public visitor / juror | Understand the model and verify its claims | Landing, current/superseded public offer, price breakdown, impact and integrity dashboard |
| B2C buyer | Buy a small quantity for home delivery | Public offer, B2C order review and confirmation |
| B2B buyer | Buy a larger quantity for a business | Public offer, B2B order review and confirmation |
| Sourcing operator | Create, correct, benchmark, publish, replace, and withdraw offers | Product Offers workspace |
| Operations operator | Inspect orders, run consolidation, reconcile groups, and coordinate dispatch | Orders, Consolidations, Dispatches, permitted hub/transparency views |
| Operations manager | Manage daily cutoff in addition to ordinary operations | Consolidation cutoff controls |
| Hub receiver / fulfillment operator | Receive, grade, record loss, allocate, release, and prepare stock | Hub Fulfillment workspace |
| Transparency operator | Verify ledger integrity, reconcile claims, and manage supported impact indicators | Transparency Operations workspace |

The interface may hide unauthorized navigation and controls, but every protected action remains server-authorized. Public buyers do not need an account. Staff routes require authentication, verified email, and the relevant permission.

## 5. Experience Principles

1. **Truth before persuasion.** Demo, stale, superseded, estimated, incomplete, and simulated states use prominent adjacent labels, not footnotes.
2. **Show the arithmetic.** Money always includes MAD and its basis (`MAD/kg` or total `MAD`). Percentages explain their numerator and denominator.
3. **Server authority.** React never calculates or infers prices, totals, stock balances, eligibility, permissions, transitions, freshness, or impact metrics.
4. **Progressive disclosure.** Public pages lead with the value proposition and key figures, then expose full sources, methodology, and history.
5. **Operational confidence.** Each staff page answers: what is the current state, what needs attention, what action is allowed, and what will happen next?
6. **No silent mutation.** Published and historical facts are replaced or corrected with visible history; UI language never suggests editing immutable records in place.
7. **Privacy by presentation.** Public and list views show safe references and aggregates. PII appears only on authorized detail pages where operationally necessary.
8. **Mobile is a primary context.** Public ordering and hub workflows must remain usable on a phone; wide operational tables may scroll horizontally without losing semantic structure.

## 6. Information Architecture

### 6.1 Public navigation

- Home
- Available produce / featured offer
- How pricing works
- Transparency and impact
- Staff login

For the hackathon, “Available produce” may lead directly to the current seeded offer rather than requiring a separate multi-product catalog. A public catalog becomes necessary only when multiple simultaneously orderable offers are introduced.

### 6.2 Staff navigation

- Dashboard
- Product offers
- Orders
- Consolidations
- Hub fulfillment
- Dispatches
- Transparency
- Account settings

Navigation items are permission-aware. The sidebar collapses to icons on wide layouts and becomes the existing mobile sheet/drawer on narrow layouts. Generated Wayfinder links are used for application navigation.

### 6.3 Lifecycle shown across modules

`Offer published -> Order confirmed -> Consolidation grouped -> Hub received -> Stock allocated -> Ready for dispatch -> Dispatch submitted/accepted -> Picked up -> Delivered -> Impact verified`

The interface should reuse consistent labels, colors, and safe references so staff can follow one scenario across modules without confusing an order, consolidation cycle, procurement requirement, receipt, allocation, or dispatch.

## 7. Screen Inventory

| ID | Screen | Audience | Priority | Source status |
| --- | --- | --- | --- | --- |
| PUB-01 | Home / product story | Public | P1 | Product-level requirement; replaces starter welcome page |
| PUB-02 | Public offer, transparency, and ordering | Public, B2C, B2B | P1 | Defined by Phases 001-002 |
| PUB-03 | Public impact and integrity dashboard | Public, juror | P1 | Defined by Phase 006; route/layout finalized during its plan |
| AUTH-01 | Staff authentication and account security | Staff | P1 | Existing application capability |
| OPS-01 | Staff dashboard | Staff | P2 | Product-level navigation/attention summary |
| OFF-01 | Offer list | Sourcing operator | P1 | Defined by Phase 001 |
| OFF-02 | Offer create/edit/review workspace | Sourcing operator | P1 | Defined by Phase 001 |
| ORD-01 | Order list | Operations operator | P1 | Defined by Phase 002 |
| ORD-02 | Order detail | Operations operator | P1 | Defined by Phase 002 |
| CON-01 | Consolidation list and controls | Operations operator/manager | P1 | Defined by Phase 003 |
| CON-02 | Consolidation cycle detail | Operations operator | P1 | Defined by Phase 003 |
| HUB-01 | Hub work queue | Hub/operations staff | P1 | Defined by Phase 004 |
| HUB-02 | Receive produce | Hub receiver | P1 | Defined by Phase 004 |
| HUB-03 | Receipt workspace | Hub/fulfillment operator | P1 | Defined by Phase 004 |
| DSP-01 | Dispatch list and generation | Dispatch operator | P1 | Defined by Phase 005 |
| DSP-02 | Dispatch detail and status actions | Dispatch operator | P1 | Defined by Phase 005 |
| TRN-01 | Ledger verification and reconciliation | Transparency operator | P1 | Defined by Phase 006; route/layout finalized during its plan |
| TRN-02 | Impact indicator management | Transparency operator | P2 | Defined by Phase 006; route/layout finalized during its plan |

## 8. Public Experience Requirements

### 8.1 PUB-01 — Home / product story

**Purpose:** Establish credibility and direct users to the current offer or transparency demo.

**Required content:**

- Headline based on the core promise: fewer opaque handovers, a fairer farmer share, and every dirham explained.
- Short explanation of the two channels: B2B consolidated distribution and B2C partner delivery.
- Primary CTA: `View today's produce` or `View transparent offer`.
- Secondary CTA: `See how the price is built` leading to PUB-03 or the breakdown anchor on PUB-02.
- A three-step operating model: direct sourcing, micro-hub quality/fulfillment, coordinated delivery.
- Trust strip explaining timestamped benchmarks, immutable price snapshots, and tamper-evident history in plain language.
- Explicit demo notice when featured operational or benchmark data is simulated.
- Staff login link that does not dominate the public experience.

The page must not claim a live marketplace breadth, live delivery partnership, blockchain proof, or measured environmental benefit that the system cannot demonstrate.

### 8.2 PUB-02 — Public offer, transparency, and ordering

**Purpose:** Combine the storefront, price explanation, benchmark evidence, and guest checkout for one published offer.

**Above the fold:**

- Crop, privacy-safe origin, availability window, and available quantity.
- Final platform price in `MAD/kg`.
- Farmer payment and farmer-share percentage.
- Fresh benchmark comparison when eligible, including saving or truthful difference above benchmark.
- Current state badges: `Demo data`, `Fresh benchmark unavailable`, or `Superseded` as applicable.
- Order CTA only when the offer is current and orderable.

**Price breakdown:**

- Accessible definition list or table containing farmer payment, all four standard costs, every additional named cost including zero values, platform margin, and final price.
- Required standard costs: collection, quality control, hub handling and storage, and delivery allocation.
- A plain-language formula and units. No client-side arithmetic.
- Benchmark market, source type, safe source reference, observation time, publication time, freshness, and demo status.
- Saving per kilogram, saving percentage, and farmer share when eligible.
- If price equals the benchmark, state `No difference from benchmark`.
- If price exceeds the benchmark, use `Difference above benchmark`; never use `saving`, `discount`, or benefit styling.
- If the benchmark is stale/unavailable, retain the offer and platform price, hide benchmark price and derived saving values, and explain why.

**History:**

- Current/replacement relationship between published offers.
- Up to 30 recent superseded comparisons.
- Prominent `Superseded` treatment on history; historical records must never look current.

**Guest order form:**

- Render only when `canOrder` is true; otherwise show a concise reason-neutral unavailable state.
- Mobile-first field order: channel, quantity, delivery slot, zone, customer name, phone, optional email, then channel-specific fields.
- B2B requires business name and does not collect home address or delivery note.
- B2C may collect delivery address and note and must not show business name.
- Step 1, `Review order`: server returns normalized crop, quantity, slot, unit price, and total.
- Step 2, `Confirm order`: enabled only after successful review. Any defining field change clears the prior review.
- Both actions show processing states and disable their own submit controls.
- Validation appears beside fields; offer/slot/quantity conflicts appear beside the review summary with a clear instruction to refresh or revise.
- Confirmation replaces the form with safe order reference, channel, crop, quantity, zone, slot, unit price, total, status, masked phone, confirmation time, and next-cycle eligibility.
- Tell the buyer to save the reference. Do not provide a tracking link or imply a reservation before confirmation.

### 8.3 PUB-03 — Public impact and integrity dashboard

**Purpose:** Present the complete commercial and operational proof in one privacy-safe view.

**Required sections:**

1. **Scenario header:** crop, public origin, service/reporting scope, data status, and safe reference.
2. **Price story:** farmer payment, named costs, delivery allocation, platform margin, final price, benchmark, saving/difference, saving percentage, and farmer share.
3. **Consolidation story:** exact distinct grouped orders, consolidated kilograms, delivery groups, rejected quantity, damaged quantity, and completion/reconciliation status for an identified date/product/channel/zone scope.
4. **Integrity status:** `Valid`, `Invalid`, `Incomplete`, `Stale`, or `Not yet verified`, plus checked endpoint/scope, entry count, and last completed verification time. Public failure language remains coarse and safe.
5. **Impact indicators:** value, unit, reporting period, update time, method, source/evidence reference, and an immediately adjacent `Estimate` or `Measured` badge.
6. **Methodology:** plain-language definitions for farmer share, saving percentage, consolidated kilograms, delivery-group count, and integrity verification.

If a scope is incomplete or unreconciled, affected metrics are unavailable or explicitly incomplete rather than zero. Unsupported CO2, waste, or trip claims are withheld. The seeded tomato scenario must be visibly marked as demonstration data wherever its inputs are simulated.

## 9. Staff Experience Requirements

### 9.1 AUTH-01 — Authentication and account security

- Reuse the existing login, email verification, password reset, password confirmation, passkey, two-factor authentication, profile, security, and appearance experiences.
- Public registration should not be positioned as customer signup; if enabled by the environment, it is staff provisioning only until a separate onboarding policy exists.
- After login, route staff to the dashboard or first authorized operations module.

### 9.2 OPS-01 — Staff dashboard

**Purpose:** Provide a permission-aware navigation and attention summary, not a second analytics system.

- Show links to authorized modules with current attention counts: drafts awaiting publication, confirmed orders, cycles awaiting decision, outstanding procurement, overdue receipts, ready/failed dispatches, and stale/not-yet-verified integrity.
- Values are server-provided and link to already filtered module pages.
- Do not expose buyer PII, compute cross-module totals in React, or add live polling for the MVP.
- If cross-module props are not available in the current phase, render module navigation cards without invented counts.

### 9.3 OFF-01 — Offer list

- Show up to 50 newest offers with crop, origin, status, final price, publication time, and permitted actions.
- Use badges for draft, published, superseded, and withdrawn.
- Primary action is `Create offer`; row actions lead to manage, replace, or public view only when allowed.
- Provide meaningful empty state and hide unauthorized actions.

### 9.4 OFF-02 — Offer create/edit/review workspace

- One page supports create and edit modes.
- Group fields into Produce and availability, Delivery slots, Price components, Benchmark, and Publication review.
- Support 1-14 future delivery slots within the offer availability window.
- Show all four standard cost fields even when zero and allow up to ten uniquely named custom costs.
- Display server-calculated final price and farmer share; never calculate a preview in React.
- Let operators record benchmarks with market, source type/reference, observed time, and required demo-data switch.
- Disable publish in the interface until a saved draft has required costs, a future delivery slot, and a fresh recorded benchmark; server validation is final.
- Before publication, show the exact saved inputs and derived outputs that become immutable.
- Published offers are read-only. Corrections create a replacement; benchmark refreshes create a separately published comparison.
- Withdrawal, replacement, and publication use confirmation dialogs and show success toasts or safe operation errors.

### 9.5 ORD-01 — Order list

- Filters: channel, status, service date, product offer, delivery zone.
- Newest-first, 25 rows per page, filters retained across pagination.
- Columns: safe reference, channel, crop, quantity, unit price, total, zone, service date, status, next-cycle eligibility, confirmation time.
- No names, phone, email, business name, address, or notes on the list.
- Provide status badges, clear/reset filters, empty state, and a detail link.

### 9.6 ORD-02 — Order detail

- Cards for immutable price snapshot, delivery scope, authorized private contact, and status-transition history.
- Contact card may show customer/business name, phone, email, B2C address, and note only after authorization.
- Transition actors are generalized as `Guest` or `Operations staff`.
- `Cancel order` appears only for confirmed orders when allowed, requires confirmation, and never updates optimistically.

### 9.7 CON-01 — Consolidation list and controls

- Summary table filters: status, trigger, operating date, service date, product, channel, and zone; 25 rows per page.
- Each row shows service date, status, trigger, cutoff, source/included/excluded counts and kilograms, requirement/group counts, completion, and safe failure label.
- Manager-only cutoff card shows current and pending cutoff, effective date, timezone, and next scheduled run. Updating requires a native time input and confirmation dialog.
- Manual-run card uses a server-provided service date and warns that an early run closes ordering for that date.
- No polling, client date math, or client totals.

### 9.8 CON-02 — Consolidation cycle detail

- Lead with status, effective/scheduled cutoff, source/included/excluded totals, variance, and reconciliation status.
- When awaiting decisions, show each under-minimum candidate with crop, channel, zone, quantity, order count, minimum, commercial total, and estimated delivery allocation.
- Approve/exclude actions require confirmation. Exclusion warns that orders remain confirmed for follow-up.
- Show procurement requirements and delivery groups in responsive tables.
- Selecting a group reloads the same page and shows its paginated source orders with links to authorized order detail.
- Handle open, processing, awaiting decision, completed, failed, empty, and stale-conflict states explicitly.

### 9.9 HUB-01 — Hub work queue

- Six stock summary cards: received, rejected, available, allocated, damaged, and dispatched kilograms, plus overdue receipt count.
- Filters: product, receipt state, allocation state, service date, overdue.
- Outstanding procurement requirements are oldest-first and provide a `Receive produce` action when permitted.
- Receipt table shows safe references, product, service scope/date, grade, received time, stock quantities, and turnaround.
- Overdue uses destructive treatment; stock still within target uses neutral remaining-time text.

### 9.10 HUB-02 — Receive produce

- Show immutable procurement reference, product, scope, service date, and required quantity before the form.
- Fields: received time, received quantity, accepted quantity, quality-rejected quantity, conditional grade, conditional rejection reason, and optional note.
- Explain that the server validates the balance and classifies overage automatically.
- Do not calculate overage, rejected total, or accepted balance in React.

### 9.11 HUB-03 — Receipt workspace

- Stock-reconciliation card appears first with received, accepted, rejected, available, allocated, damaged, and dispatched quantities plus turnaround.
- Separate forms for authorized correction, handling loss, group allocation, allocation release, and preparation.
- Only the submitted form is disabled; each form owns its field errors and operation conflict alert.
- Show compatible groups, allocations, correction history, and loss history with bounded empty states.
- Prepared allocations say `Ready for dispatch` while remaining allocated. This page must not offer a Dispatch action or imply stock moved before pickup.

### 9.12 DSP-01 — Dispatch list and generation

- First row: total, ready, in-progress, delivered, failed, total kilograms, and total delivery cost summary cards.
- Second row: service-date/channel/zone/status filters and generation card.
- Generation card shows eligible orders/quantity, B2B zones, B2C orders, late B2B work, and 500-order limit.
- Generation requires a confirmation dialog and explains that ready manifests refresh while submitted manifests remain frozen.
- Table columns: safe reference, channel, provider, simulated badge, date, zone, status, order count, kilograms, cost, provider reference, and detail link.
- Distinct empty states: no eligible work, no filtered dispatches, only late B2B work, and generation limit exceeded.

### 9.13 DSP-02 — Dispatch detail and status actions

- Header shows safe reference, channel/provider, prominent simulated B2C warning, date, zone, status, quantity, cost/source, lifecycle version, and terminal/manual-follow-up state.
- Submit/retry card appears only when allowed. First B2B submission collects one flat MAD cost; B2C explains the deterministic 25.00 MAD mock quote; retries never ask for cost again.
- Each allowed transition has its own confirmation dialog. B2C actions say `Simulate pickup`, `Simulate delivery`, or `Simulate failure`; B2B actions describe staff handoff outcomes.
- Authorized stops/orders table shows only required PII. PII must not be copied to hidden fields, data attributes, toast text, client logs, or analytics.
- Attempts and status history show safe categories, references, times, and authorized actor labels.
- No polling or optimistic status changes.

### 9.14 TRN-01 — Ledger verification and reconciliation

- Show current chain endpoint, entry count, last verification status/time/scope, and whether newer events make that result stale.
- `Run verification` is available only to authorized operators, requires confirmation, and communicates that verification is bounded and may finish valid, invalid, or incomplete.
- Verification history lists status, checked endpoint, entry count, start/completion times, and safe first-failure category/location for staff.
- Reconciliation lets an operator trace each public monetary and consolidation figure to privacy-safe source references and ledger events.
- Ledger entries are view-only. There is no edit or delete control; corrections are new events.
- Detailed failure information remains authorized and must exclude raw payloads, PII, hashes, tokens, SQL, or internal model data.

### 9.15 TRN-02 — Impact indicator management

- Authorized operators can draft and publish indicators with name, value, unit, reporting period, method, source/evidence reference, update time, and classification.
- `Measured` is available only when all inputs are observed within the period and evidence/method are recorded.
- Any modeled, assumed, extrapolated, mixed, or incomplete input is `Estimate`.
- Publication review shows exactly how the value and qualification appear publicly.
- Unsupported indicators remain unpublished and produce no numeric public claim.

## 10. Cross-Product Interaction Requirements

### 10.1 Forms

- Use the existing Inertia `<Form>` for standard visits and `useHttp` only for public order review/confirmation JSON requests defined by Phase 002.
- Inputs have persistent labels, descriptions where needed, and field-level `InputError` output.
- Processing controls are disabled and show a spinner or action-specific progress label.
- Destructive or irreversible actions require a Dialog summarizing the effect.
- A server conflict is distinct from validation: show an `AlertError` with safe recovery guidance.
- Preserve user-entered non-sensitive values after validation errors. Clear stale review results when their defining inputs change.
- Do not use optimistic updates for orders, consolidation, inventory, dispatch, or integrity.

### 10.2 Loading, empty, and error states

Every page must specify and test:

- Initial loading/progress behavior during Inertia navigation.
- Processing state for each independent mutation.
- Empty state with explanation and next permitted action.
- Field validation, operation conflict, rate-limit, unauthorized, forbidden, not-found, and unexpected-error outcomes.
- Deferred content skeleton and retry state if Phase 006 planning chooses deferred props.

Errors must be actionable but must not reveal whether private records exist, submitted PII, internal IDs, SQL, tokens, hashes, or raw provider payloads.

### 10.3 Status language

- Use server-provided labels; React must not recreate state machines.
- Neutral: draft, open, ready, submitted, accepted, allocated.
- Positive: published, completed, prepared/ready for dispatch, picked up, delivered, valid, measured.
- Warning: demo, simulated, stale, superseded, awaiting decision, estimate, incomplete, overdue.
- Destructive: withdrawn, cancelled, excluded, failed, invalid, damaged/rejected where attention is required.
- Color is supplementary; every status includes visible text and, when helpful, an icon.

### 10.4 Dates and numbers

- Store/provide authoritative timestamps from the server and display them in Africa/Casablanca time.
- Show `MAD/kg` versus total `MAD` explicitly.
- Quantities show kilograms and at most the precision supplied by the server.
- Percentages show two decimals and plain-language definitions.
- Do not use JavaScript floats for business calculations.

## 11. Visual and Design-System Direction

### 11.1 Brand character

The visual language should feel agricultural, credible, modern, and operational rather than rustic or speculative. Transparency should be expressed through structure, labels, source attribution, and readable arithmetic—not decorative charts that obscure exact values.

### 11.2 Existing system constraints

- Reuse `AppLayout`, sidebar, breadcrumbs, Heading, Card, Button, Badge, Input, Label, Select, Dialog, Alert, Skeleton, Spinner, Tooltip, and Sonner toast patterns.
- Continue the existing CSS-variable semantic color system and Instrument Sans typography.
- Use Tailwind CSS 4 utilities and CSS-first theme tokens; add no page-specific custom CSS unless separately approved.
- Support the existing light, dark, and system appearance modes.
- Use Lucide icons consistently; icons never replace labels for unfamiliar operations.

### 11.3 Recommended semantic extension

When branding is implemented, introduce agricultural brand colors through shared semantic theme variables rather than hardcoded page colors. Maintain WCAG contrast in both themes. Reserve green for positive/current states, amber for estimation/staleness/attention, and red for destructive/invalid states; never use green merely to make a simulated saving look more persuasive.

### 11.4 Data presentation

- Prefer exact metric cards, definition lists, reconciliation rows, and semantic tables.
- Use charts only where they improve comparison; always retain exact accessible values.
- Price breakdown may use a stacked visual only as a supplement to the itemized table.
- Do not represent tamper evidence as blockchain imagery or use a shield/checkmark without the actual verification status and time.

## 12. Responsive Requirements

### Mobile: below 640 px

- Public content and forms use one column and comfortable tap targets.
- Primary CTA remains visible without obscuring content; no mandatory sticky action.
- Summary cards stack; long references wrap or truncate with accessible copy affordance where appropriate.
- Operational tables live in labeled `overflow-x-auto` regions and preserve table semantics.
- Dialogs become near-full-width and keep confirm/cancel actions reachable.
- Sidebar uses the existing mobile drawer.

### Tablet: 640-1023 px

- Use two-column metric grids where readable.
- Forms may group closely related short fields while preserving natural keyboard order.
- Detail pages keep actions near their associated card instead of in a distant global toolbar.

### Desktop: 1024 px and above

- Use the existing inset sidebar layout.
- Public pages use a constrained reading width with selective wide sections for price/impact comparison.
- Operational summary cards may use four-to-seven columns based on count; tables use available width without shrinking critical labels into ambiguity.

Responsive acceptance widths: 320, 375, 768, 1024, and 1440 px. No horizontal page overflow is allowed; only explicitly scrollable data regions may overflow.

## 13. Accessibility Requirements

Target WCAG 2.2 AA for all new frontend work.

- One clear `h1` per page and logical heading hierarchy.
- Landmark structure (`header`, `nav`, `main`, `aside`, `footer`) and a keyboard-visible skip link.
- All controls reachable and operable by keyboard with visible focus.
- Minimum 44x44 CSS-pixel pointer targets for primary touch controls where practical.
- Every input has an associated label; required and error states are conveyed in text and programmatically.
- Error summary or focus management directs users to failed fields after submission.
- Dialog focus is trapped, titled, described, and returned to the triggering control on close.
- Tables use captions or nearby headings, correct header cells, and accessible scroll-region labels.
- Badges and metrics do not rely on color alone.
- Toasts supplement persistent page feedback and use an appropriate live region.
- Motion respects `prefers-reduced-motion`; no essential information depends on animation.
- Dates, abbreviations, currency, percentages, and estimates have understandable text alternatives.
- Source links have descriptive labels and indicate external destinations where relevant.

## 14. Content and Localization

### 14.1 Tone

Use concise, factual, non-technical language. Prefer `How this price is built` over `Cost allocation schema`, `Checked history` over `hash chain` in public content, and `Simulated delivery provider` over wording that suggests a live partnership.

### 14.2 Required disclosure patterns

- `Demo data — this benchmark is illustrative, not a live market observation.`
- `Fresh benchmark unavailable — the platform price remains current, but no saving comparison is shown.`
- `Estimate — calculated from the method and assumptions below; not directly measured.`
- `Simulated provider — no request was sent to Glovo, Yassir, or another live delivery service.`
- `Superseded — retained for transparency; this is not the current offer/comparison.`
- `Verification stale — newer events were added after the last completed check.`

### 14.3 Language scope

The MVP may launch in one product language, but layouts and component APIs must not block later French and Arabic localization. Avoid concatenated sentence fragments, fixed-width text containers, and direction-dependent icons. Arabic support requires a separate RTL QA pass before it is claimed as supported.

## 15. Privacy, Security, and Trust Requirements

- Public pages use explicit allowlists and never expose farmer/staff names, buyer PII, exact private addresses, internal actor IDs, tokens, or provider-private data.
- Order list and consolidation views remain aggregate/reference-based. Buyer PII appears only on authorized Order and Dispatch detail pages and only when required.
- Never place PII in query strings, DOM data attributes, hidden fields unrelated to a submission, analytics, toasts, client logs, or error telemetry.
- Public references are non-sequential and not presented as lookup endpoints unless a route explicitly exists.
- Staff controls are permission-aware, but hidden controls are not a security boundary.
- Rate-limit outcomes show retry guidance without exposing limiter keys or internal state.
- Any link to evidence/source material must be reviewed as safe for public disclosure.
- Append-only ledger history has no edit/delete interface. Corrections append new events.
- The frontend must not claim data is `verified` unless the server supplies a completed verification for the exact current endpoint.

## 16. Performance Requirements

- Public offer and transparency pages: useful content within two seconds on the local demo environment for at least 95% of views.
- Staff list/detail pages: server response within one second locally for at least 95% of requests under stated MVP bounds.
- Paginate staff lists at the specified limits: generally 25 rows; bounded detail collections at 50.
- Prefetch likely staff detail navigation where it does not expose unauthorized data or create excessive requests.
- Keep page props explicit and bounded; do not serialize models or send PII to pages that do not display it.
- Use skeletons only for genuinely deferred content and preserve layout stability.
- No polling in consolidation, hub, or dispatch MVP pages. Refresh is explicit after conflicts or when operators need new state.

## 17. Analytics and Product Instrumentation

Analytics must be optional, privacy-safe, and aggregate. Do not record contact fields, addresses, notes, raw references, operation tokens, source URLs containing private data, or staff identities.

Recommended events:

- `public_offer_viewed` with crop-safe slug/category and current/demo/stale flags.
- `price_breakdown_opened` and `benchmark_source_opened`.
- `order_review_succeeded`, `order_confirmation_succeeded`, and safe failure category by channel.
- `transparency_viewed` and `methodology_opened`.
- Staff module page viewed and mutation outcome using coarse module/action/status only.

Core funnel: public offer view -> order form start -> review success -> confirmation success. Trust funnel: public offer view -> price breakdown -> source/methodology -> transparency dashboard.

## 18. Frontend Acceptance and Quality Gates

### 18.1 Functional

- All screens and actions render only from server-provided props and allowed transitions.
- Seeded tomato scenario displays 2.80 MAD/kg farmer payment, 5.50 MAD/kg final price, 8.00 MAD/kg benchmark, 2.50 MAD/kg and 31.25% saving, and 50.91% farmer share.
- B2C 5 kg and B2B 40 kg flows review and confirm from the same active offer.
- Operator can follow the complete scenario across offer, order, consolidation, hub, dispatch, and transparency screens using safe references.
- Every empty, processing, validation, conflict, unauthorized, stale, superseded, demo, simulated, estimate/measured, incomplete, and failure state defined in this PRD has deliberate copy and presentation.

### 18.2 Technical

- TypeScript check, ESLint check, Prettier check, and production build pass.
- Frontend uses existing components, Inertia navigation/forms, and Wayfinder routes; no hardcoded application URLs.
- Light and dark themes pass visual review.
- No custom pricing, quantity, duration, state, or permission calculation exists in React.
- No new frontend dependency is introduced without approval.

### 18.3 Accessibility and responsive

- Keyboard-only completion of public ordering and every staff mutation flow.
- Automated accessibility scan has no critical or serious violations on all primary screens.
- Manual screen-reader review covers public offer/order confirmation, operator dialogs, table navigation, and integrity status.
- Screens pass at 320, 375, 768, 1024, and 1440 px without unintended page overflow.
- Text/status remains understandable at 200% zoom and with reduced motion.

### 18.4 Privacy

- Public prop, HTML, metadata, analytics, error, and log inspection finds no prohibited PII or private identifiers.
- Staff list pages do not receive private contact fields.
- Dispatch/order detail PII is absent from hidden DOM state, toast messages, client logs, and analytics.

## 19. Delivery Plan and Priorities

### Release 1 — Commercial proof

- PUB-01, PUB-02
- OFF-01, OFF-02
- ORD-01, ORD-02
- Staff authentication/navigation

Exit outcome: a transparent offer can be published and both B2C and B2B orders can be confirmed.

### Release 2 — Operational proof

- CON-01, CON-02
- HUB-01, HUB-02, HUB-03
- DSP-01, DSP-02
- OPS-01 navigation/attention summary as available

Exit outcome: confirmed demand can be consolidated, received, graded, allocated, prepared, and dispatched through the mock-safe workflow.

### Release 3 — Audit and impact proof

- PUB-03
- TRN-01, TRN-02
- Cross-module reconciliation links and final demo polish

Exit outcome: public visitors can verify the complete price and consolidation story, and staff can verify/reconcile its tamper-evident history.

## 20. End-to-End Demo Script

1. Open the home page and state the value proposition.
2. Open the tomato offer and show the 2.80 MAD/kg farmer payment, complete cost breakdown, 5.50 MAD/kg platform price, sourced/demo-labeled 8.00 MAD/kg benchmark, saving, and farmer share.
3. Confirm one 5 kg B2C order and one 40 kg B2B order; save their safe references.
4. In staff operations, show both immutable price snapshots in the order list/details.
5. Run consolidation for the service date, resolve any under-minimum candidate, and reconcile included quantities.
6. Receive produce, record 4 kg quality rejection and 1 kg handling loss, and show 95 kg fulfillable.
7. Allocate and prepare the relevant groups.
8. Generate one B2B manifest per zone and one mock B2C dispatch per order; submit and advance them without implying a live provider call.
9. Open the transparency dashboard and show the price story, grouped orders, kilograms, delivery groups, losses, demo/estimate labels, and integrity status.
10. Run or show a completed ledger verification and explain that newer events make an older verification stale until checked again.

## 21. Dependencies and Open Product Decisions

### Dependencies

- Phases 001-005 must supply their documented immutable snapshots, statuses, permissions, props, and safe references before dependent screens are enabled.
- Phase 006 technical planning must define its exact routes, Inertia props, pagination, and whether public impact is a dedicated page or a clearly linked extension of the public offer.
- The seeded demo dataset must be internally consistent across all modules and visibly labeled wherever simulated.
- Brand name, final logo, production copy language, and public legal/privacy text require product-owner approval.

### Decisions fixed by this PRD

- Public ordering remains guest-only in the MVP.
- There is no public order-tracking screen.
- The public offer is the core storefront; a separate catalog is deferred until simultaneous offer breadth requires it.
- Public transparency and staff operations use the existing design system, dark mode, and responsive patterns.
- Mocked delivery, demo benchmarks, and estimated impact receive prominent adjacent disclosure.

### Decisions to finalize during Phase 006 planning

- Exact PUB-03 and TRN-01/TRN-02 route names and page split.
- Whether the public impact view defaults to the latest completed scenario or requires a safe scenario reference.
- Whether verification runs synchronously within the bounded MVP or uses an existing supported deferred execution mechanism.
- Which environmental/transport indicator, if any, has defensible evidence for the hackathon beyond an explicitly estimated trip-reduction example.

## 22. Post-Hackathon Frontend Backlog

- Multi-offer public catalog with search/filtering.
- Optional customer accounts and consent-based order history.
- Public reference lookup only after privacy, authentication, and abuse rules are designed.
- Farmer portal and onboarding.
- Live payment and settlement experience.
- Live delivery-provider integration, tracking, and notification surfaces.
- QR/short-code access to the same transparency receipt.
- Multi-hub inventory and route-planning interfaces.
- Production-grade French and Arabic localization including RTL validation.
- Evidence-backed environmental reporting beyond the MVP’s qualified estimates.

