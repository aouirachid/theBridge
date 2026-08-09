# Data Model: Transparency Ledger and Impact Dashboard

## Modeling Rules

- Internal relations use bigint IDs. Public route/response identities use random UUIDs.
- The application has exactly one chain with key `platform`.
- Store hashes as lowercase 64-character hexadecimal SHA-256 strings.
- Store ledger payloads as canonical JSON text. Do not use a JSON cast that can change
  serialized key order before verification.
- Store money, percentages, and quantities exactly as upstream integer minor units,
  basis points, and hundredths. Never put floats in a ledger payload.
- Store timestamps in UTC to whole seconds; format public times in `Africa/Casablanca`.
- `ledger_entries` has `created_at` only and no `updated_at` or soft deletes.
- No model uses `$guarded = []`. Public output is always a hand-built allowlist.

## ProductOffer Extension

Add one field to the existing `product_offers` table:

| Field | Type | Rules |
|---|---|---|
| `is_demo` | boolean | Default `false`; server-owned; immutable after publication |

Add the boolean cast and a factory state. Ordinary offer Requests MUST reject/ignore this
field and ordinary create/update Actions default it to false. Only
`SeedTransparencyDemoAction` may pass true through `CreateProductOfferDraftAction`.
Public offer and transparency allowlists display `Demo data` when true. This flag labels
simulated offer economics independently of the benchmark's existing `is_demo` flag.

## Enums

### LedgerEventType

Use these exact TitleCase cases and stored values. Do not add a generic custom case.

| PHP case | Stored value | Source Action |
|---|---|---|
| `OfferPublished` | `offer_published` | `PublishProductOfferAction` |
| `BenchmarkPublished` | `benchmark_published` | `PublishProductOfferAction` or `PublishBenchmarkComparisonAction` |
| `OrderConfirmed` | `order_confirmed` | `CreateOrderAction` |
| `OrderCancelled` | `order_cancelled` | `CancelOrderAction` |
| `ConsolidationCompleted` | `consolidation_completed` | `RunConsolidationCycleAction` |
| `HubReceiptFinalized` | `hub_receipt_finalized` | `FinalizeHubReceiptAction` |
| `HubReceiptCorrected` | `hub_receipt_corrected` | `CorrectHubReceiptAction` |
| `HandlingLossRecorded` | `handling_loss_recorded` | `RecordHandlingLossAction` |
| `StockAllocated` | `stock_allocated` | `AllocateOrderGroupAction` |
| `StockAllocationReleased` | `stock_allocation_released` | `ReleaseStockAllocationAction` |
| `DispatchSubmitted` | `dispatch_submitted` | `SubmitDispatchAction` |
| `DispatchStatusChanged` | `dispatch_status_changed` | `AdvanceDispatchAction` |
| `ImpactIndicatorPublished` | `impact_indicator_published` | `PublishImpactIndicatorAction` |
| `CorrectionRecorded` | `correction_recorded` | approved correction path only |

### LedgerVerificationStatus

| PHP case | Stored value | Meaning |
|---|---|---|
| `Valid` | `valid` | Every expected entry through the captured endpoint passed |
| `Invalid` | `invalid` | A deterministic integrity mismatch was found |
| `Incomplete` | `incomplete` | The bounded check did not reach the captured endpoint |

`stale` and `not_yet_verified` are public derived labels, not stored statuses.

### LedgerVerificationFailure

| PHP case | Stored value |
|---|---|
| `MissingGenesis` | `missing_genesis` |
| `UnexpectedGenesis` | `unexpected_genesis` |
| `PositionGap` | `position_gap` |
| `PreviousHashMismatch` | `previous_hash_mismatch` |
| `EntryHashMismatch` | `entry_hash_mismatch` |
| `EntryCountMismatch` | `entry_count_mismatch` |
| `EndpointMismatch` | `endpoint_mismatch` |
| `LimitExceeded` | `limit_exceeded` |
| `TimeLimitExceeded` | `time_limit_exceeded` |
| `ReadFailure` | `read_failure` |

Never store raw exceptions, SQL, payloads, or model dumps in this field.

### ImpactIndicatorClassification

| PHP case | Stored value |
|---|---|
| `Estimate` | `estimate` |
| `Measured` | `measured` |

### ImpactIndicatorCode

| PHP case | Stored value | Public label | Unit |
|---|---|---|---|
| `TripReduction` | `trip_reduction` | Estimated trips avoided | `trips` |

This is the only allowed indicator in the MVP. Do not add CO2/emissions cases.

## LedgerChain

Table: `ledger_chains`

One row is the mutable serialization head for the single immutable entry chain.

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | Internal primary key |
| `key` | varchar(32) | Unique; exactly `platform` |
| `last_position` | unsigned bigint | Default `0`; equals committed entry count |
| `last_entry_hash` | nullable char(64) | Null only when `last_position = 0` |
| `created_at`, `updated_at` | timestamps | Ordinary Laravel timestamps |

Constraints:

- unique `key`
- check in Action: position zero iff hash null; positive position iff hash is valid hex
- insert the row in a separate data migration after table creation
- only `AppendLedgerEntryAction` may update the head

Relationships: has many `LedgerEntry` and `LedgerVerification`.

## LedgerEntry

Table: `ledger_entries`

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | Internal primary key |
| `public_id` | UUID string | Random, unique safe event reference |
| `ledger_chain_id` | bigint FK | `LedgerChain`; restrict deletion |
| `position` | unsigned bigint | 1-based contiguous position |
| `event_key_hash` | char(64) | Private SHA-256 idempotency key; globally unique |
| `event_type` | varchar(40) | `LedgerEventType` |
| `subject_type` | varchar(40) | Exact safe subject token from event contract |
| `subject_public_id` | UUID string | Random public ID of source subject |
| `payload_json` | text | Exact canonical safe payload JSON |
| `occurred_at` | timestamp | Authoritative source event time in UTC |
| `recorded_at` | timestamp | Append time in UTC |
| `actor_user_id` | nullable bigint FK | Private relation; null on user deletion |
| `actor_reference` | nullable UUID string | Random immutable private event attribution snapshot |
| `previous_hash` | nullable char(64) | Null only at position 1 |
| `entry_hash` | char(64) | SHA-256 of exact canonical envelope |
| `created_at` | timestamp | Same instant as `recorded_at`; no `updated_at` |

Indexes and constraints:

- unique `public_id`
- unique `event_key_hash`
- unique `(ledger_chain_id, position)`
- index `(event_type, occurred_at, id)`
- index `(subject_type, subject_public_id, position)`
- index `(ledger_chain_id, created_at, id)`
- actor relation uses `nullOnDelete`; `actor_reference`, which is inside the hash, never
  changes when the user relation is removed
- the model rejects update/delete/soft-delete attempts
- SQLite triggers named `ledger_entries_no_update` and `ledger_entries_no_delete` abort
  UPDATE and DELETE through the configured application connection

The table contains no source internal IDs, names, contacts, addresses, notes, raw tokens,
credentials, provider payloads, or arbitrary metadata.

## Exact Canonical Hash Contract

`LedgerHasher` accepts the scalar values below and a canonical payload array. It must:

1. Reject floats, resources, objects, callables, invalid UTF-8, non-string object keys,
   non-finite values, and strings outside documented lengths.
2. Distinguish a list from an object. Preserve list order. Recursively sort object keys
   in binary ascending order.
3. Encode JSON with exceptions, unescaped Unicode, and unescaped slashes.
4. Encode all timestamps as UTC `YYYY-MM-DDTHH:MM:SSZ` strings.
5. Hash the UTF-8 bytes with SHA-256 and return lowercase hexadecimal.

Canonical envelope key order after recursive sorting:

```text
actor_reference: string|null
chain_key: "platform"
event_key_hash: 64-char hex
event_type: LedgerEventType value
occurred_at: UTC string
payload: canonical object
position: positive integer
previous_hash: 64-char hex|null
public_id: UUID string
recorded_at: UTC string
subject_public_id: UUID string
subject_type: safe contract token
version: 1
```

`entry_hash = sha256(canonical_json(envelope))`.

The event idempotency input is an explicit ASCII string defined per event in
`contracts/internal-ledger-events.md`; store only
`event_key_hash = sha256(idempotency_input)`.

## Append Algorithm

`AppendLedgerEntryAction::handle(LedgerEventData $event): LedgerEntry`:

1. Hash the event's exact idempotency input and read an existing entry by
   `event_key_hash`. If found, compare event type, safe subject, canonical payload,
   occurrence time, and actor relation; return it only when all match, otherwise throw
   `LedgerConflictException('event_mismatch')`.
2. Inside the caller's existing transaction, lock the `platform` `LedgerChain` row.
3. Re-read `event_key_hash` under the lock for concurrent replay.
4. Set position to `last_position + 1`; previous hash is the head hash or null at 1.
5. Capture one UTC `recorded_at`, generate random `public_id`, generate random private
   `actor_reference` only when an actor exists, and compute the entry hash.
6. Insert one entry and update chain `last_position` and `last_entry_hash`.
7. Assert the head matches the inserted position/hash and return the entry.

Do not open a transaction in this Action when the caller already owns one. The upstream
Action remains responsible for wrapping source writes and the append in one transaction.
For direct tests/seeder-only calls, the caller explicitly provides the transaction.

## LedgerVerification

Table: `ledger_verifications`

Every row is immutable evidence of one completed or safely terminated check.

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | Internal primary key |
| `public_id` | UUID string | Random unique operator reference |
| `ledger_chain_id` | bigint FK | Checked chain; restrict deletion |
| `requested_by_user_id` | nullable bigint FK | Private operator; null on user deletion |
| `status` | varchar(16) | `LedgerVerificationStatus` |
| `endpoint_position` | unsigned bigint | Captured chain head position |
| `endpoint_hash` | nullable char(64) | Captured head hash; null for empty chain |
| `expected_entry_count` | unsigned bigint | Equals captured endpoint position |
| `checked_entry_count` | unsigned bigint | Entries processed before result |
| `failure_position` | nullable unsigned bigint | First safe failing position |
| `failure` | nullable varchar(40) | `LedgerVerificationFailure` |
| `started_at` | timestamp | UTC |
| `completed_at` | timestamp | UTC; at or after start |
| `created_at` | timestamp | Same as completed time; no `updated_at` |

Indexes:

- unique `public_id`
- index `(ledger_chain_id, completed_at, id)`
- index `(status, completed_at, id)`
- index `(requested_by_user_id, completed_at, id)`

### Verification Algorithm

`VerifyLedgerAction::handle(LedgerChain $chain, User $actor): LedgerVerification`:

1. Authorize in the caller, capture start time and one immutable snapshot of chain
   `last_position` and `last_entry_hash`.
2. If endpoint exceeds `max_entries_per_verification` (10,000), record `Incomplete` with
   `LimitExceeded`; do not scan.
3. Select only hash-envelope columns for positions `<= endpoint_position`, ordered by
   position, and iterate with a cursor.
4. Starting at expected position 1 and previous hash null, require exact position,
   genesis rule, previous hash, recomputed entry hash, and count.
5. After the cursor, require checked count equals endpoint position and the final entry
   hash equals captured endpoint hash. The empty chain is valid only at position 0 with
   null endpoint hash.
6. Record `Valid` when all checks pass, `Invalid` on the first deterministic mismatch,
   or `Incomplete` on limit/time/read interruption. Store only an allowlisted failure and
   safe position. Unexpected exceptions are reported without payload and rethrown after
   best-effort incomplete-result recording.

Public derived integrity label:

```text
not_yet_verified: no completed result
stale: newest result endpoint position/hash != current head position/hash
valid: current-endpoint result status valid
invalid: current-endpoint result status invalid
incomplete: current-endpoint result status incomplete
```

Never use an older valid result when a newer current-endpoint invalid/incomplete result
exists.

## ImpactIndicator

Table: `impact_indicators`

One row is one published version of trip-reduction evidence for one completed
consolidation cycle.

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | Internal primary key |
| `public_id` | UUID string | Random unique public reference |
| `consolidation_cycle_id` | bigint FK | Completed source cycle; restrict deletion |
| `supersedes_impact_indicator_id` | nullable self FK | Unique prior version; restrict deletion |
| `published_by_user_id` | nullable bigint FK | Private actor; null on user deletion |
| `code` | varchar(40) | Only `trip_reduction` |
| `classification` | varchar(16) | `estimate` or `measured` |
| `baseline_trip_count` | unsigned bigint | Baseline trips for the reporting period |
| `actual_trip_count` | unsigned bigint | Actual/represented delivery trips |
| `value` | signed bigint | `baseline_trip_count - actual_trip_count` |
| `unit` | varchar(16) | Exactly `trips` |
| `method` | varchar(500) | Public-safe method statement |
| `source_reference` | nullable varchar(500) | Required for measured; public-safe |
| `evidence_reference` | nullable varchar(500) | Required for measured; public-safe |
| `assumptions` | nullable varchar(500) | Required for estimate |
| `limitation` | varchar(500) | Required for both classifications |
| `reporting_starts_on` | date | Cycle reporting period start |
| `reporting_ends_on` | date | At or after start |
| `is_demo` | boolean | Explicitly public |
| `published_at` | timestamp | UTC, set at insert |
| `superseded_at` | nullable timestamp | Set once when next version publishes |
| `created_at`, `updated_at` | timestamps | Ordinary timestamps |

Indexes/constraints:

- unique `public_id`
- unique `supersedes_impact_indicator_id`
- index `(consolidation_cycle_id, code, published_at, superseded_at)`
- index `(classification, published_at)`

Publication rules:

- Lock the completed cycle and current unsuperseded indicator for its scope.
- For `Estimate`, derive baseline from distinct grouped orders and actual from distinct
  finalized delivery groups. The Request cannot submit counts. Use the fixed method
  `Grouped orders minus finalized delivery groups`; require public assumptions and
  limitation.
- For `Measured`, require operator-supplied non-negative integer baseline/actual counts,
  public-safe method, source reference, evidence reference, and limitation. Assumptions
  must be absent. This MVP does not fetch or upload evidence.
- Compute signed `value`; a negative result is displayed as additional trips, never as a
  benefit.
- Insert the new version and set the old version's `superseded_at` in one transaction.
- Append `ImpactIndicatorPublished` in the same transaction.
- Identical replay returns the current row; different repeated input creates a new
  version rather than editing history.

## Dashboard Read Model (Derived, Not Stored)

For one public `ProductOffer`, `ShowPublicTransparencyAction` returns:

1. The immutable published price snapshot and all ordered cost components.
2. The eligible current published benchmark under Phase 001 freshness rules.
3. The newest completed consolidation cycle for that offer, or a clear no-data state.
4. Distinct included order count, sum of grouped `quantity_hundredths`, distinct finalized
   delivery-group count, and separately labeled rejected/damaged quantities.
5. Current unsuperseded impact indicator for that cycle.
6. Current derived integrity label from the chain head and newest verification.

Aggregate rules:

- All queries are bounded by one offer and one completed cycle.
- Count source order membership once, not transition or dispatch-attempt rows.
- Sum immutable group membership quantities, not receipt quantities.
- Count finalized operational delivery groups once, not provider attempts/status rows.
- Use existing upstream formatters. Do not recalculate prices, percentages, or quantities
  in React or create Phase 006 replacements.

## Tomato Demo Contract

`SeedTransparencyDemoAction` uses the production Phase 001-005 Actions to create or
return one deterministic visibly-demo scenario:

```text
crop: Tomatoes
farmer_payment_minor: 280
costs: collection 30, quality_control 20,
       hub_handling_storage 30, delivery_allocation 90
platform_margin_minor: 100
final_price_minor: 550
benchmark_price_minor: 800, is_demo: true
saving_minor: 250
saving_percentage_bps: 3125
farmer_share_bps: 5091
orders: 20
consolidated_quantity_hundredths: 10000
delivery_groups: 3
estimated_trip_reduction: 17
```

The demo uses safe fictional contact data only where upstream order Actions require it;
none may enter ledger payloads or public props. The seeder is replay-safe and never
inserts source or ledger rows directly. It finds/creates one private demo operator using
the fixed non-real email `transparency-demo@thebridge.test` and a generated random
password. A completed scenario is found by that actor plus `ProductOffer.is_demo`, exact
crop, and exact immutable price; rerun returns it. If a partial matching scenario exists,
the Action stops with `demo_scenario_incomplete` instead of guessing or duplicating it.
