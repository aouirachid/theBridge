# Data Model: Delivery Orchestration

## Modeling Rules

- Phases 001–004 are prerequisites. Reuse their real models and fields; never duplicate
  Order, OrderGroup, StockAllocation, HubReceipt, or their enums.
- Internal relationships use bigint IDs. Staff routes use random UUIDs. Private hashes
  are hidden and never exposed or logged.
- Quantities are unsigned integer hundredths of a kilogram. Money is unsigned integer
  MAD centimes. Calculations use integers only.
- Timestamps are stored in UTC and displayed in `Africa/Casablanca`.
- Dispatch rows contain no customer or business names, phone numbers, emails, addresses,
  or notes. Those values remain encrypted on Order and are read only when authorized.
- Dispatch, attempt, membership/cost, transition, and order-transition rows have no
  application delete route. Submitted membership and cost allocations are immutable.
- Raw operation tokens, submitted payloads, provider payloads, and PII hashes are never
  persisted or logged.

## Migration Order

Generate migrations with Artisan and apply this exact order:

1. Create `dispatches`.
2. Create `dispatch_orders`.
3. Create `dispatch_attempts`.
4. Create `dispatch_status_transitions`.
5. Add dispatched progress to `stock_allocations`.
6. Add nullable dispatch attribution to `order_status_transitions`.

Reverse in the opposite order. Restrict domain FK deletion except private actor FKs,
which become null on user deletion. Do not mix data backfills into these migrations.

## Required Upstream Contracts

### Order

Required fields and relationships:

```text
id, public_id, order_group_id, channel, status, quantity_hundredths,
crop_snapshot, service_date, slot_starts_at, slot_ends_at, delivery_zone,
customer_name, business_name, phone, email, delivery_address, delivery_note,
orderGroup, statusTransitions
```

PII fields use the upstream encrypted casts and remain hidden by default. Phase 005 adds
only `dispatchOrders()` / `dispatch()`-related relationships; it does not add PII or
rewrite an immutable snapshot.

### OrderGroup

Required fields and relationships:

```text
id, public_id, channel, delivery_zone, service_date,
total_quantity_hundredths, fulfillment_status,
current_stock_allocation_id, orders, currentStockAllocation
```

`OrderGroupFulfillmentStatus::ReadyForDispatch` means the allocation is prepared but
the included Orders remain `OrderStatus::Allocated`. `Dispatched` means every included
Order has been picked up.

### StockAllocation

Required upstream fields:

```text
id, public_id, hub_receipt_id, order_group_id, quantity_hundredths,
prepared_quantity_hundredths, prepared_at, released_at, dispatched_at,
lifecycle_version, hubReceipt, orderGroup
```

Phase 005 adds `dispatched_quantity_hundredths` and dispatch relationships/derived
remaining quantity.

### HubReceipt

Required counters:

```text
accepted_quantity_hundredths, available_quantity_hundredths,
allocated_quantity_hundredths, damaged_quantity_hundredths,
dispatched_quantity_hundredths, inventory_version
```

The accepted-stock equation remains authoritative:

```text
available + allocated + damaged + dispatched = accepted
```

### OrderStatusTransition

Reuse the append-only upstream model. Phase 005 adds nullable `dispatch_id` so each
`Allocated -> Dispatched` and `Dispatched -> Delivered` order transition can be traced to
the Dispatch. The existing Phase 004 `stock_allocation_id` remains populated on pickup
transitions and may remain populated on delivery transitions for direct provenance.

## Enums

### DispatchStatus

| PHP case | Stored value | Meaning |
|---|---|---|
| `Ready` | `ready` | Generated and refreshable, not submitted |
| `Submitted` | `submitted` | Frozen handoff submitted or awaiting outcome |
| `Accepted` | `accepted` | Handoff accepted; stock still allocated |
| `PickedUp` | `picked_up` | Physical handoff complete; stock/Orders dispatched |
| `Delivered` | `delivered` | Customer delivery complete; terminal |
| `Failed` | `failed` | Failure recorded; retry depends on `failure_from_status` |

Valid transitions:

```text
ready -> submitted
submitted -> accepted | failed
accepted -> picked_up | failed
picked_up -> delivered | failed
failed(before pickup) -> submitted
```

`delivered` and `failed` from `picked_up` are terminal. No other transition is valid.

### DispatchProviderKind

| PHP case | Stored value | Meaning |
|---|---|---|
| `InternalManifest` | `internal_manifest` | B2B staff-managed zone manifest |
| `Mock` | `mock` | Credential-free simulated B2C provider |

Mapping is fixed: B2B uses `InternalManifest`; B2C uses `Mock`.

### DeliveryCostSource

| PHP case | Stored value |
|---|---|
| `FlatRate` | `flat_rate` |
| `ProviderQuote` | `provider_quote` |

B2B uses operator-approved `FlatRate`; B2C mock results use `ProviderQuote`.

### DispatchAttemptOutcome

| PHP case | Stored value | Meaning |
|---|---|---|
| `Submitted` | `submitted` | B2B handoff submitted, awaiting staff outcome |
| `Accepted` | `accepted` | Mock submission returned accepted |
| `Failed` | `failed` | Safe provider/submission failure |
| `Uncertain` | `uncertain` | Submission result unknown; preserve identity for reconciliation |

The production mock normally returns `Accepted`; Action tests may bind a deterministic
test provider to exercise fixed safe failure/uncertain results without network activity.

### DispatchTransitionSource

| PHP case | Stored value |
|---|---|
| `Operator` | `operator` |
| `MockProvider` | `mock_provider` |

## Dispatch

Table: `dispatches`

One row represents one B2B manifest or one B2C delivery request.

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | Internal primary key |
| `public_id` | UUID string | Random, unique staff route key |
| `scope_key_hash` | char(64) | Private deterministic SHA-256 identity; unique |
| `created_by_user_id` | nullable bigint FK | Private actor; null on user deletion |
| `channel` | varchar(8) | Existing `OrderChannel`; B2B or B2C |
| `provider_kind` | varchar(24) | `DispatchProviderKind`; derived from channel |
| `service_date` | date | Casablanca service date from source Orders |
| `delivery_zone` | varchar(40) | Existing coded `DeliveryZone` |
| `status` | varchar(16) | `DispatchStatus`; default ready |
| `failure_from_status` | nullable varchar(16) | Prior status for current failure; controls retry |
| `delivery_cost_minor` | nullable unsigned bigint | Frozen actual/quoted MAD centimes |
| `delivery_cost_source` | nullable varchar(24) | Null until cost exists |
| `provider_reference` | nullable varchar(64) | Mock safe reference; null for B2B |
| `lifecycle_version` | unsigned bigint | Starts 1; increments once per successful Action that changes Dispatch/membership state |
| `frozen_at` | nullable timestamp | First submission; membership/cost immutable afterward |
| `submitted_at` | nullable timestamp | Latest successful submit/retry time |
| `accepted_at` | nullable timestamp | Latest accepted time |
| `picked_up_at` | nullable timestamp | Physical handoff time; set once |
| `delivered_at` | nullable timestamp | Delivery time; set once |
| `failed_at` | nullable timestamp | Current failure time; cleared only by allowed pre-pickup retry |
| `created_at`, `updated_at` | timestamps | Laravel timestamps |

Indexes/constraints:

- unique `public_id`, `scope_key_hash`, nullable `provider_reference`
- index `(service_date, channel, delivery_zone, status)`
- index `(status, updated_at, id)` for work queue ordering
- index `(created_by_user_id, created_at)` for private accountability

Scope key input, then SHA-256:

```text
B2B: b2b|service_date|delivery_zone
B2C: b2c|order_id
```

The hash is never exposed or logged. Relationships: belongs to creator; has many
DispatchOrders, DispatchAttempts, and DispatchStatusTransitions; has many Orders through
DispatchOrders.

## DispatchOrder

Table: `dispatch_orders`

One row is one immutable submitted manifest stop/request membership and its exact cost
allocation. Ready B2B rows may be rebuilt until the parent Dispatch is submitted.

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | Primary key |
| `dispatch_id` | bigint FK | Parent Dispatch; restrict deletion |
| `order_id` | bigint FK | Source Order; restrict deletion; globally unique |
| `sequence` | unsigned integer | Stable 1-based sequence within Dispatch |
| `quantity_hundredths` | unsigned bigint | Immutable source Order quantity snapshot |
| `allocated_delivery_cost_minor` | nullable unsigned bigint | Set/frozen at first submission |
| `created_at`, `updated_at` | timestamps | Updates allowed only while parent ready |

Indexes/constraints:

- unique `order_id` (one Order belongs to at most one Dispatch)
- unique `(dispatch_id, order_id)`
- unique `(dispatch_id, sequence)`
- index `(dispatch_id, sequence, id)`

No PII is stored. Product/window/contact values are read from the related Order only for
authorized detail output or transient provider submission.

## DispatchAttempt

Table: `dispatch_attempts`

One append-only row records one first submission or pre-pickup retry.

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | Primary key |
| `dispatch_id` | bigint FK | Parent Dispatch; restrict deletion |
| `initiated_by_user_id` | nullable bigint FK | Private actor; null on user deletion |
| `attempt_number` | unsigned integer | Starts 1 per Dispatch |
| `operation_token_hash` | char(64) | SHA-256 of raw UUID; globally unique |
| `operation_payload_hash` | char(64) | Canonical safe defining payload hash |
| `outcome` | varchar(16) | `DispatchAttemptOutcome` |
| `provider_reference` | nullable varchar(64) | Safe mock reference |
| `provider_cost_minor` | nullable unsigned bigint | Safe quote, never raw response |
| `failure_category` | nullable varchar(48) | Fixed safe allowlisted category |
| `started_at` | timestamp | UTC |
| `completed_at` | nullable timestamp | Null only while/if outcome unresolved |
| `created_at` | timestamp | No `updated_at`; append-only |

Indexes/constraints:

- unique `operation_token_hash`
- unique `(dispatch_id, attempt_number)`
- index `(dispatch_id, created_at, id)`
- index `(outcome, created_at)`

No request/response body, PII, raw token, signature, credential, or exception text is
stored.

## DispatchStatusTransition

Table: `dispatch_status_transitions`

Append-only accepted lifecycle event.

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | Primary key |
| `dispatch_id` | bigint FK | Parent Dispatch; restrict deletion |
| `dispatch_attempt_id` | nullable bigint FK | Submission/retry origin; restrict deletion |
| `actor_user_id` | nullable bigint FK | Private operator; null on user deletion |
| `source` | varchar(24) | `DispatchTransitionSource` |
| `from_status` | varchar(16) | Previous `DispatchStatus` |
| `to_status` | varchar(16) | Resulting `DispatchStatus` |
| `operation_token_hash` | nullable char(64) | Operator/simulation control token; unique when set |
| `operation_payload_hash` | nullable char(64) | Canonical safe target/version hash |
| `failure_category` | nullable varchar(48) | Required only when `to_status=failed` |
| `occurred_at` | timestamp | Authoritative UTC event time |
| `created_at` | timestamp | Recorded time; no `updated_at` |

Indexes/constraints:

- unique nullable `operation_token_hash`
- unique `(dispatch_attempt_id, to_status)` for attempt-origin transitions
- index `(dispatch_id, occurred_at, id)`
- index `(to_status, occurred_at)`
- index `(actor_user_id, occurred_at)`

For database engines whose nullable unique semantics differ, keep the real unique token
index and enforce attempt/to-status uniqueness with a deterministic non-null event hash.
The implementation must preserve the invariant portably rather than relying on nullable
behavior alone.

## StockAllocation Extension

Add to `stock_allocations`:

| Field | Type | Rules / meaning |
|---|---|---|
| `dispatched_quantity_hundredths` | unsigned bigint | Default 0; `<= quantity_hundredths` |

Add index `(prepared_at, released_at, dispatched_at)` if not already present. Derived:

```text
remaining_dispatch_quantity = quantity_hundredths - dispatched_quantity_hundredths
fully_dispatched = dispatched_quantity_hundredths = quantity_hundredths
```

On first partial pickup, keep `dispatched_at` null and group status
`ReadyForDispatch`. On full pickup, set `dispatched_at` once and group status
`Dispatched`. `lifecycle_version` increments on every partial/full pickup mutation.

## OrderStatusTransition Extension

Add to `order_status_transitions`:

| Field | Type | Rules / meaning |
|---|---|---|
| `dispatch_id` | nullable bigint FK | Dispatch that caused pickup/delivery; restrict deletion |

Add index `(dispatch_id, created_at, id)`. Existing rows remain null. The existing
unique/order-state constraints remain authoritative; do not replace them.

## Provider Boundary Contract

`App\Support\Dispatches\DeliveryProvider` is an interface with exactly two methods. Use
PHPDoc array shapes and scalar/enum return types; do not add generic DTO/base-provider
layers.

`submit(array $request): array` receives this transient B2C-only shape:

```text
dispatch_reference: safe Dispatch UUID
order_reference: safe Order UUID
crop: immutable safe Order label
quantity_hundredths: positive integer
slot_starts_at: UTC ISO-8601
slot_ends_at: UTC ISO-8601
delivery_zone: DeliveryZone value
customer_name: decrypted string
phone: decrypted string
email: decrypted nullable string
delivery_address: decrypted nullable string
delivery_note: decrypted nullable string
```

It returns only:

```text
provider_reference: safe string
status: accepted | failed
quote_minor: non-negative integer or null on failure
failure_category: fixed safe nullable string
```

`simulate(string $providerReference, DispatchStatus $target): array` accepts only
`PickedUp`, `Delivered`, or `Failed` and returns:

```text
provider_reference: same safe string
status: exact requested allowed target
failure_category: mock_reported_failure only when failed, otherwise null
```

`MockDeliveryProvider` validates the safe Dispatch reference, derives
`MOCK-` plus the first 12 uppercase hexadecimal SHA-256 characters of that reference,
returns `config('dispatch.mock_quote_minor')`, and ignores every personal field when
deriving output. It stores nothing, logs nothing, reads no environment value, and makes
no HTTP/network call. `SubmitDispatchAction` is solely responsible for assembling and
discarding the transient array.

## Configuration

File: `config/dispatch.php`

```text
timezone = Africa/Casablanca
max_orders_per_generation = 500
dispatch_page_size = 25
detail_order_page_size = 50
attempt_page_size = 50
transition_page_size = 50
mutation_rate_per_minute = 60
mock_quote_minor = 2500
max_flat_cost_minor = 100000000
transaction_attempts = 3
```

The mock quote is visibly demo data. Config contains no provider URL, credential, token,
or secret. Application code uses `config()`, never `env()`.

## Operation Fingerprint Contract

`DispatchOperationFingerprint` hashes a raw validated UUID with SHA-256. It builds the
payload hash from an explicit ordered scalar array, JSON-encodes with exceptions, and
hashes that JSON. Never pass a Request/model or PII.

Submit canonical payload:

```text
submit_dispatch, dispatch_id, expected_lifecycle_version,
channel, existing_or_parsed_cost_minor
```

Advance canonical payload:

```text
advance_dispatch, dispatch_id, expected_lifecycle_version, target_status
```

Same token + same payload returns the already recorded result. Same token + different
payload throws `operation_mismatch`. Hashes are hidden and excluded from logs/errors.

## MAD Contract

`MadMoney` accepts only digits plus optional `.` and one or two fractional digits:

```text
"0" -> 0
"25" -> 2500
"25.5" -> 2550
"25.50" -> 2550
```

Reject signs, commas, exponent notation, whitespace-only values, more than two decimals,
values above `max_flat_cost_minor`, and multiplication overflow. Format `2550` as
`25.50 MAD`. Do not duplicate parsing in a Request, Action, model, or React page.

## Largest-Remainder Cost Allocation

Input: positive Dispatch order quantities and non-negative total cost.

For each DispatchOrder in safe Order public-UUID order:

```text
numerator = total_cost_minor * quantity_hundredths
base = intdiv(numerator, total_quantity_hundredths)
remainder = numerator % total_quantity_hundredths
```

1. Reject empty orders, zero quantity, zero total quantity, or multiplication overflow.
2. Sum bases; `remaining = total_cost_minor - sum(base)`.
3. Sort by remainder descending, then Order public UUID ascending.
4. Add one centime to the first `remaining` rows.
5. Require every allocation non-negative and their sum equals total cost exactly.

For cost 0, every allocation is 0. B2C with one Order receives the full cost. The
allocator returns a map keyed by Order internal ID; public UUID is tie-break input only.

## Generation Algorithm

`GenerateDispatchesAction(serviceDate, actor)`:

1. Authorize through the caller/policy and capture one UTC `now`.
2. In one three-attempt transaction, query at most 501 Orders in stable
   `(channel, delivery_zone, order_group_id, id)` order where:
   - service date matches;
   - Order status is `Allocated`;
   - OrderGroup status is `ReadyForDispatch`;
   - current StockAllocation is active, prepared in full, and has remaining quantity;
   - Order has no DispatchOrder, or belongs to the same ready B2B manifest being rebuilt.
3. Reject more than 500 without creating or refreshing anything.
4. For each B2B zone, resolve the private scope hash and lock/first-create one Dispatch.
   If ready, rebuild ordered DispatchOrders from all current eligible source Orders. If
   membership changes, increment lifecycle version once. If frozen/non-ready, never
   change it; unassigned later work becomes a derived late-work flag in list/show output.
5. For each B2C Order without membership, resolve its private scope hash, first-create
   one ready mock Dispatch, and create its one DispatchOrder.
6. Assert unique membership, quantity/count reconciliation, channel/date/zone agreement,
   and no cost on ready rows; commit and return safe counts/references.

A unique-key race is handled by re-reading the winning Dispatch/membership and verifying
the same scope. Never catch-and-ignore an arbitrary database exception.

## Submission / Retry Algorithm

`SubmitDispatchAction(dispatch, validatedInput, actor)`:

1. Resolve an existing attempt by token hash before new work; compare payload hash and
   return same result or throw `operation_mismatch`.
2. Begin a three-attempt transaction. Lock Dispatch, DispatchOrders, and source Orders in
   stable ID order. Require expected version and either ready, failed-before-pickup, or
   submitted with the latest attempt marked uncertain.
3. Require at least one membership and reconcile its quantity/channel/date/zone. On first
   submit set `frozen_at`; never modify membership afterward.
4. B2B first submit: parse required operator flat cost. Retry: reuse frozen cost. Create
   attempt, allocate/freeze cost, append ready/failed -> submitted, and stop at submitted.
5. B2C: prohibit operator cost. Build one transient minimum provider array from its one
   protected Order; call the in-process mock. Validate safe reference, quote, and allowed
   outcome. Persist no request/response payload or PII. Allocate full quote to its Order.
   Append ready/failed -> submitted and submitted -> accepted in the same transaction for
   an accepted result. An uncertain result keeps current status submitted; a later call
   reuses the same Dispatch/provider identity and reconciles before acceptance.
6. Set safe provider/cost/attempt facts, increment lifecycle version once for the
   successful Action, assert allocations sum exactly, and commit.

The mock performs no I/O; do not generalize this transaction design to a future live
provider.

## Advance Algorithm

`AdvanceDispatchAction(dispatch, targetStatus, expectedVersion, token, actor)`:

1. Resolve same-token replay/mismatch.
2. In a three-attempt transaction lock in exact order: Dispatch, DispatchOrders, Orders,
   OrderGroups, StockAllocations, HubReceipts.
3. Derive allowed targets from current status/channel. For B2C accepted/picked-up states,
   call mock `simulate` and require returned reference/target match. For B2B use the
   authorized operator target directly.
4. For `PickedUp`, require every included Order still Allocated and each allocation
   active/prepared. Bulk-update exact Orders to Dispatched and append one attributed
   OrderStatusTransition each. Per allocation, sum just-picked quantities, conditionally
   increment dispatched progress, decrement HubReceipt allocated, increment dispatched,
   and increment both versions. When progress reaches allocation quantity, set
   `dispatched_at` and group `Dispatched`; otherwise group remains ReadyForDispatch.
5. For `Delivered`, require every included Order Dispatched, update exact Orders to
   Delivered, and append transitions. Do not change stock counters.
6. For `Failed`, record fixed safe category and `failure_from_status`. Do not change
   source Orders or stock. Failure from PickedUp is terminal; earlier failure may retry.
7. Append one DispatchStatusTransition, update Dispatch timestamps/status/version, assert
   affected counts and all stock/cost invariants, then commit.

Any mismatch rolls back every Dispatch, Order, group, allocation, receipt, and history
change. A safe conflict contains only fixed code/message and safe route reference.

## Core Invariants

After every success or idempotent replay:

```text
one B2B Dispatch per service_date + delivery_zone
one B2C Dispatch per Order
one Order belongs to zero or one Dispatch
ready B2B membership may refresh; frozen membership never changes
each DispatchOrder quantity equals its immutable Order quantity
sum DispatchOrder costs = Dispatch cost after first submission
B2C Dispatch has exactly one DispatchOrder
0 <= StockAllocation dispatched progress <= allocation quantity
HubReceipt available + allocated + damaged + dispatched = accepted
pickup moves each included Order and quantity exactly once
delivery changes no hub quantity
post-pickup failure changes no Order or hub quantity and is terminal
confirmed Order price snapshot and estimated delivery allocation never change
dispatch tables contain zero copied PII
```
