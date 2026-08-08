# Radical Transparency Agri-Tech Platform

## Executive Summary and Spec Kit Implementation Roadmap

**Document purpose:** Define the hackathon MVP as a sequence of independently specified, testable implementation phases using GitHub Spec Kit.

**Target:** A working Sunday demo that proves a more transparent and efficient path from Moroccan farmers to B2B and B2C customers in Casablanca.

---

## 1. Executive Summary

The platform is a transparent agricultural distribution operator connecting farmers directly with professional buyers and consumers in Casablanca. It replaces several undocumented supply-chain steps with one accountable operation: direct sourcing from farmers, short-term consolidation in a central micro-hub, quality control, order preparation, and coordinated distribution.

The business serves two channels:

- **B2B:** greengrocers, cafés, restaurants, and small retailers place orders during the day. At midnight, orders are grouped by product and delivery zone. The platform creates consolidated procurement quantities and optimized delivery manifests for the following day.
- **B2C:** consumers order from a public storefront. Last-mile delivery is delegated to an external provider such as Glovo or Yassir. For the hackathon, partner dispatch is represented by a controlled mock; the product must not claim a live integration that does not exist.

Produce moves through a central micro-hub for less than 24 hours whenever possible. The hub provides receiving, grading, quality control, temporary storage, and preparation. This makes the model operationally credible, but it must be presented as infrastructure-light rather than fully asset-light.

The main customer experience is radical price transparency. Each offer compares a timestamped, sourced traditional-market benchmark with the platform price and explains every component: farmer payment, sourcing and handling, storage, delivery, and platform margin. Historical financial events are recorded in an append-only, tamper-evident ledger. QR scanning is not required for the MVP; the same digital receipt can later be connected to a QR or short batch code.

### Core value proposition

> We replace several opaque handovers with one accountable distributor. Farmers receive a larger share, B2B and B2C customers pay a fairer price, and every dirham between the farm gate and the final sale is explained.

### Illustrative demo economics

| Component                             |            MAD/kg |
| ------------------------------------- | ----------------: |
| Farmer payment                        |              2.80 |
| Collection and quality control        |              0.50 |
| Micro-hub handling and storage        |              0.30 |
| Consolidated delivery allocation      |              0.90 |
| Platform margin                       |              1.00 |
| **Platform price**                    |          **5.50** |
| Observed traditional-market benchmark |              8.00 |
| **Customer saving**                   | **2.50 (31.25%)** |

These numbers are demonstration assumptions until supported by field evidence. The UI must clearly label simulated values as demo data and show the source and observation time for real benchmarks.

### Hackathon success measures

- Farmer payment and farmer share of the final price are visible.
- Customer savings versus a sourced or explicitly simulated benchmark are visible.
- B2B and B2C orders can be created without manual database changes.
- Orders can be consolidated by product and zone using a deterministic midnight process.
- A micro-hub operator can receive, grade, allocate, and dispatch stock.
- The B2B flow produces a consolidated delivery manifest.
- The B2C flow produces a mock external-partner dispatch request.
- Every published price is reproducible from recorded cost components.
- Historical financial records cannot be edited through normal application flows.
- The demo completes end to end without relying on an unavailable third-party API.

---

## 2. Spec Kit Delivery Model

This roadmap uses Spec Kit's **spec of specs** approach: each phase is a self-contained feature with its own `spec.md`, `plan.md`, `tasks.md`, acceptance criteria, and implementation cycle. Code is the output of the approved specifications, not the starting point.

### Project initialization

Initialize Spec Kit once at the repository root using the integration required by the team. For Codex CLI skills mode, the official project currently documents:

```bash
specify init --here --integration codex --integration-options="--skills"
```

Use the command form installed for the team's coding agent. Most integrations expose `/speckit.*` commands; Codex skills mode may expose equivalent `$speckit-*` skills.

### One-time constitution

Run `/speckit.constitution` before the first feature spec. The constitution must make these rules non-negotiable:

1. **Hackathon-first scope:** ship the smallest complete B2B and B2C demonstration; postpone nonessential integrations.
2. **Transparent calculations:** money uses fixed-precision decimals and all derived prices are calculated server-side.
3. **Honest data:** market observations include source and timestamp; simulations are visibly labeled.
4. **Append-only auditability:** financial history is appended, never edited or deleted through application APIs.
5. **Security by default:** no hardcoded secrets, no public PII, strict input validation, authorization on staff operations, and rate limiting on public or abuse-prone endpoints.
6. **Testable slices:** every phase has automated acceptance coverage for its critical business rules.
7. **Operational resilience:** unavailable delivery partners cannot block the demo; a mock adapter must implement the same internal contract.
8. **Simple architecture:** no blockchain, live payments, fleet optimization, or speculative AI in the hackathon MVP.
9. **Performance awareness:** grouping and reporting queries must be bounded by date, product, and zone and must avoid N+1 access patterns.

Suggested command:

```text
/speckit.constitution Establish project principles for a hackathon-first agricultural distribution platform: honest sourced data, fixed-precision server-side price calculations, append-only financial auditability, secure handling of personal data, role-based staff operations, rate limiting, independently testable vertical slices, external-provider adapters with demo mocks, minimal dependencies, and no blockchain or live payment scope for the MVP.
```

### Required workflow for every phase

Run the following cycle independently for every numbered phase:

1. `/speckit.specify` — define what users need and why; exclude implementation details.
2. `/speckit.clarify` — resolve material ambiguity before technical planning.
3. `/speckit.plan` — select architecture, data model, contracts, testing, and technology.
4. `/speckit.checklist` — validate requirement completeness and acceptance quality.
5. `/speckit.tasks` — generate dependency-ordered, file-specific tasks.
6. `/speckit.analyze` — detect gaps and conflicts across spec, plan, and tasks.
7. `/speckit.implement` — implement only after analysis passes.
8. `/speckit.converge` — compare the code with the artifacts and add any missing work.

Each phase is complete only when its acceptance criteria pass and it leaves the application in a demonstrable state.

---

## 3. Phase Overview

| Order | Spec feature                             | Outcome                                                                            | Hackathon priority            |
| ----: | ---------------------------------------- | ---------------------------------------------------------------------------------- | ----------------------------- |
|   001 | Transparent product offer                | Publish a product with farmer price, platform costs, margin, and market comparison | Must have                     |
|   002 | B2B and B2C order capture                | Capture demand from both channels                                                  | Must have                     |
|   003 | Midnight order consolidation             | Group demand and generate procurement requirements                                 | Must have                     |
|   004 | Micro-hub fulfillment                    | Receive, grade, allocate, and prepare produce                                      | Must have, simplified         |
|   005 | Delivery orchestration                   | Create B2B manifests and mock B2C partner dispatches                               | Must have, mocked integration |
|   006 | Transparency ledger and impact dashboard | Prove price history, savings, farmer share, and consolidation impact               | Must have                     |

Phases 001–003 form the minimum commercial proof. Phases 004–006 complete the operational and transparency story expected by the jury.

---

## 4. Phase 001 — Transparent Product Offer

### Objective

Allow an authorized sourcing operator to publish a produce offer whose final price is calculated from explicit cost components and compared with a traceable market benchmark.

### Primary users

- Platform sourcing operator
- Public B2B or B2C visitor

### Scope

- Create a produce offer with crop, origin, available quantity, farmer price, and availability window.
- Record collection, quality-control, hub, delivery-allocation, and platform-margin components.
- Calculate the final platform price server-side.
- Record a market benchmark with market name, source type, source reference, observation time, and demo-data flag.
- Display platform price, benchmark, saving, saving percentage, farmer share, and cost breakdown.
- Keep farmer contact details and internal actor identifiers out of the public response.

### Acceptance gate

Given a tomato offer with a 2.80 MAD/kg farmer payment and cost components totaling 2.70 MAD/kg, the system publishes a 5.50 MAD/kg price. Given an 8.00 MAD/kg benchmark, it displays a 2.50 MAD/kg saving, a 31.25% saving rate, and a 50.91% farmer share. The benchmark source, timestamp, and demo status are visible.

### Out of scope

- Automated scraping
- Dynamic pricing
- QR generation
- Farmer self-service onboarding

### `/speckit.specify` seed

```text
/speckit.specify Create a transparent agricultural product offer. An authorized sourcing operator records the crop, origin, available quantity, farmer payment per kilogram, named operating-cost components, platform margin, and an observed market benchmark with source, timestamp, and demo-data status. The system calculates and publishes the final price, customer saving, saving percentage, farmer share, and a public cost breakdown without exposing personal farmer or staff information. Published calculations must be reproducible and unambiguous.
```

---

## 5. Phase 002 — B2B and B2C Order Capture

### Objective

Capture demand from professional buyers and consumers while preserving the different data and fulfillment needs of each channel.

### Primary users

- B2C consumer
- B2B greengrocer, café, restaurant, or retailer
- Platform operator

### Scope

- Create a B2C order with offer, quantity, customer contact, delivery address or zone, and preferred fulfillment window.
- Create a B2B order with business name, contact, offer, quantity, delivery zone, and requested delivery window.
- Show the unit price and total before confirmation.
- Store an immutable price snapshot on each order so later offer changes cannot rewrite historical totals.
- Track lifecycle statuses: `pending`, `confirmed`, `grouped`, `allocated`, `dispatched`, `delivered`, and `cancelled`.
- Apply server-side validation, authorization where required, and request throttling.

### Acceptance gate

A consumer can order 5 kg and a greengrocer can order 40 kg from the same active tomato offer. Both orders preserve their original unit price, appear in the operator view, and remain eligible for the next consolidation cycle.

### Out of scope

- Live card payments
- Refund processing
- Customer accounts or loyalty programs
- Real-time driver tracking

### `/speckit.specify` seed

```text
/speckit.specify Add B2C and B2B agricultural order capture. Consumers place small orders using contact and delivery-zone information without requiring an account. Professional buyers place larger orders with business identity and delivery-window information. Every order shows and preserves its confirmed unit price and total, follows a defined lifecycle, validates quantity against an active offer, protects personal data, and becomes eligible for the next daily consolidation cycle.
```

---

## 6. Phase 003 — Midnight Order Consolidation

### Objective

Convert individual orders into consolidated demand, procurement requirements, and delivery-zone groups at the daily cutoff.

### Primary user

- Platform operations manager

### Scope

- Close the ordering window at a configurable daily cutoff, initially midnight Casablanca time.
- Group eligible orders by service date, crop or offer, channel, and delivery zone.
- Calculate total requested kilograms, order count, and estimated delivery allocation per kilogram.
- Create a consolidated farmer procurement requirement.
- Mark included orders as grouped without modifying their price snapshots.
- Make consolidation idempotent: retrying the same cycle must not duplicate groups or quantities.
- Support an operator-only “Run consolidation now” action for the hackathon demo.
- Define the outcome when a zone does not reach its minimum quantity: postpone, manually approve, or cancel. The selected rule must be resolved during `/speckit.clarify`.

### Acceptance gate

Given 20 eligible tomato orders totaling 100 kg across three zones, one consolidation run produces exactly one procurement requirement for 100 kg and three delivery groups. Re-running the same cutoff produces no duplicates. The operator can see every included order and reconcile group totals with the source orders.

### Out of scope

- Route optimization algorithms
- Automated farmer bidding
- Automated purchasing or payment
- Forecasting and AI demand prediction

### `/speckit.specify` seed

```text
/speckit.specify Consolidate confirmed agricultural orders at a configurable midnight cutoff in Casablanca time. Group orders deterministically by service date, product, channel, and delivery zone; calculate quantities and order counts; create farmer procurement requirements and delivery groups; preserve source-order price snapshots; provide full reconciliation; and make repeated execution safe without duplicates. Authorized operators need a manual run control for demonstrations and recovery.
```

---

## 7. Phase 004 — Micro-Hub Fulfillment

### Objective

Track the short operational journey through the platform's micro-hub from receiving to dispatch, including quality and loss visibility.

### Primary users

- Hub receiver
- Fulfillment operator

### Scope

- Receive a procured quantity against a consolidation group.
- Record accepted and rejected quantities, grade, received time, and rejection reason.
- Track available, allocated, damaged, and dispatched quantities.
- Allocate accepted stock to B2B and B2C order groups without exceeding available stock.
- Record handling or storage loss explicitly rather than hiding it in margin.
- Prepare a fulfillment summary for dispatch.
- Target less than 24 hours between receipt and dispatch and flag overdue stock.

### Acceptance gate

Given 100 kg received, with 4 kg rejected and 1 kg damaged, the system exposes 95 kg as fulfillable and prevents allocations beyond that quantity. Losses appear separately in the transparent operational record. Prepared orders can advance to dispatch.

### Out of scope

- IoT temperature sensors
- Warehouse slot optimization
- Multi-warehouse transfers
- Complex batch splitting and merging beyond the demo path

### `/speckit.specify` seed

```text
/speckit.specify Add micro-hub fulfillment for consolidated produce. Authorized staff receive procured quantities, record accepted and rejected weight with quality grade and reasons, track available, allocated, damaged, and dispatched quantities, allocate stock without overselling, expose handling losses explicitly, prepare orders for dispatch, and flag produce held longer than the target turnaround window.
```

---

## 8. Phase 005 — Delivery Orchestration

### Objective

Produce an executable handoff for both distribution channels without making the MVP depend on live delivery APIs.

### Primary users

- Dispatch operator
- External delivery provider adapter

### Scope

- Generate B2B delivery manifests grouped by zone, including stops, quantities, and contact information visible only to authorized operations staff.
- Generate B2C dispatch requests through a provider-neutral delivery contract.
- Provide a mock delivery adapter that returns deterministic reference IDs and statuses.
- Record quoted or flat-rate delivery cost and its allocation across relevant kilograms or orders.
- Track `ready`, `submitted`, `accepted`, `picked_up`, `delivered`, and `failed` states.
- Make dispatch retries idempotent to prevent duplicate provider requests.
- Never log complete addresses, phone numbers, credentials, or provider tokens in plaintext application logs.

### Acceptance gate

The demo produces one consolidated B2B manifest per zone and one mock external-provider request for each eligible B2C delivery. Every dispatch has a reference and status, repeated submission creates no duplicate request, and delivery cost flows into the relevant price report.

### Out of scope

- Live Glovo or Yassir integration
- Driver application
- Real-time GPS tracking
- Automated multi-stop route optimization

### `/speckit.specify` seed

```text
/speckit.specify Orchestrate delivery for consolidated agricultural orders. Operations staff generate zone-based B2B manifests and provider-neutral B2C dispatch requests. A deterministic mock provider must support the complete demo without external credentials. Dispatch has traceable statuses, idempotent retries, explicit delivery-cost allocation, restricted personal data, and logs that never reveal sensitive customer or provider information.
```

---

## 9. Phase 006 — Transparency Ledger and Impact Dashboard

### Objective

Make the commercial and operational claims auditable through an append-only event history and a clear public impact dashboard.

### Primary users

- Public customer
- Platform operator
- Hackathon jury

### Scope

- Append financial and operational events for offer publication, order confirmation, consolidation, receiving, loss, allocation, and dispatch.
- Link entries with `previous_hash` and `entry_hash` to make retroactive changes detectable.
- Prohibit update and delete operations for ledger entries through the application service account.
- Verify a ledger chain on demand and expose a simple valid or invalid indicator.
- Display market benchmark, platform price, farmer payment, operating costs, delivery, platform margin, customer saving, and farmer share.
- Display consolidation measures: orders grouped, kilograms consolidated, and delivery groups created.
- Describe trip reduction as an estimate unless measured data exists; do not fabricate CO2 values.
- Provide a seeded end-to-end tomato scenario for the final demonstration.

### Acceptance gate

The jury can open one page and see how a 2.80 MAD/kg farmer payment becomes a 5.50 MAD/kg platform price, compare it with an 8.00 MAD/kg benchmark, verify the ledger indicator, and view the order-consolidation outcome. Modifying a historical ledger record in a controlled test causes verification to fail.

### Out of scope

- Public blockchain
- Cryptocurrency or tokens
- Legal certification of records
- Unsupported environmental claims
- QR scanning

### `/speckit.specify` seed

```text
/speckit.specify Create an auditable transparency and impact experience for the agricultural platform. Append price and operational events in a tamper-evident chain, prohibit normal application updates and deletions, verify chain integrity, and publish a privacy-safe dashboard showing the sourced market benchmark, farmer payment, every cost component, platform margin, final price, customer saving, farmer share, grouped orders, consolidated kilograms, and delivery groups. Environmental indicators must be labeled as estimates unless backed by measured data.
```

---

## 10. Cross-Phase Data Concepts

These are conceptual entities for consistency across specifications. Their technical representation belongs in each `/speckit.plan` artifact.

- **Farmer:** private supplier identity and contact information.
- **Product offer:** crop, origin, availability, quantity, price components, and current status.
- **Market benchmark:** observed price, market, source, timestamp, and demo-data flag.
- **Order:** channel-specific demand with an immutable price snapshot.
- **Consolidation cycle:** cutoff, service date, scope, status, and idempotency identity.
- **Order group:** product, zone, channel, included orders, and total quantity.
- **Procurement requirement:** quantity the platform needs from farmers after consolidation.
- **Hub receipt:** received, accepted, rejected, damaged, allocated, and dispatched quantities.
- **Dispatch:** channel, provider or route manifest, cost, reference, and lifecycle status.
- **Ledger entry:** event type, canonical payload, previous hash, entry hash, actor, and timestamp.

### Invariants

- Monetary calculations use decimal arithmetic and MAD currency.
- Public responses contain no phone numbers, addresses, or private actor IDs.
- `accepted quantity + rejected quantity = received quantity`.
- `allocated quantity` never exceeds `available quantity`.
- The sum of grouped order quantities equals the corresponding source-order quantities.
- A consolidation cycle and dispatch request are idempotent.
- The published price equals the sum of its published components.
- A historical order price does not change when an offer changes.
- Ledger entries are append-only and hash-chain verification is deterministic.

---

## 11. Recommended Hackathon Execution Order

### Saturday evening

1. Initialize Spec Kit and approve the constitution.
2. Complete Phase 001 through implementation and convergence.
3. Complete Phase 002 through implementation and convergence.
4. Specify and plan Phase 003 before stopping.

### Sunday morning

1. Implement and converge Phase 003.
2. Implement the smallest complete Phase 004.
3. Implement Phase 005 using only the mock provider.
4. Implement the public dashboard and ledger verification from Phase 006.

### Before submission

- Run the relevant automated tests and one full manual demonstration.
- Run `/speckit.converge` for every implemented phase.
- Verify mobile layout and empty, invalid, and failure states.
- Confirm every displayed benchmark is sourced or labeled as demo data.
- Confirm external delivery is presented as mocked.
- Freeze the demo dataset.
- Rehearse the five-minute flow with a backup local recording or screenshots.

### Final demo path

1. Publish tomatoes with transparent pricing and an 8.00 MAD/kg benchmark.
2. Place one B2C order and multiple B2B orders.
3. Run the midnight consolidation control.
4. Show the consolidated procurement quantity and zone groups.
5. Receive and grade the produce in the micro-hub.
6. Generate B2B manifests and mock B2C delivery references.
7. Open the public dashboard and show savings, farmer share, all costs, grouping impact, and a valid ledger.

---

## 12. Explicit Post-Hackathon Backlog

Do not allow these items to enter the MVP unless all six phases are complete:

- Real delivery-provider API integrations
- Payment collection and farmer payouts
- Automated market-data ingestion or scraping
- QR-linked batch receipts
- Farmer self-service registration
- Advanced forecasting
- IoT temperature monitoring
- Multi-hub allocation
- Route optimization
- Carbon accounting based on measured distance and vehicle data
- Returns, refunds, disputes, and customer loyalty

---

## 13. Reference

This roadmap follows GitHub Spec Kit's official workflow and its recommendation to decompose a large product into independently specified features:

- [GitHub Spec Kit](https://github.com/github/spec-kit)
- [Spec Kit quickstart](https://github.com/github/spec-kit/blob/main/docs/quickstart.md)
- [Spec of Specs](https://github.github.com/spec-kit/concepts/spec-of-specs.html)
