# Data Model: Micro-Hub Fulfillment

## Modeling Rules

- Phase 1, Phase 2, and consolidation models are prerequisites; never duplicate them.
- Internal relationships use bigint IDs. Staff-facing route identities use random UUIDs.
- Store every quantity as integer hundredths of a kilogram.
- Store timestamps in UTC; interpret/display operator time in `Africa/Casablanca`.
- Do not delete hub domain rows through application routes.
- Optional free-text operational notes use encrypted casts and `TEXT` columns.
- Current counters are authoritative projections; correction, loss, allocation, release,
  preparation, and order-transition facts remain auditable.
- Every mutation stores a SHA-256 operation-token hash and canonical payload hash. Raw
  tokens and submitted payloads are never persisted or logged.

## Migration Order

Use this exact order: create receipts, corrections, handling losses, and allocations;
then add the allocation pointer/status to `order_groups`; finally add the allocation FK
to `order_status_transitions`. Reversible `down()` methods run the reverse order so the
circular group/allocation references are removed before either table is dropped.

## Replay Fingerprint Contract

`HubOperationFingerprint` hashes the raw UUID token with SHA-256. For the payload hash,
each Action first converts quantities to integers, timestamps to UTC ISO-8601 strings,
enums to stored values, nullable strings to normalized null/string values, and builds an
explicit ordered scalar array prefixed by the operation name. The helper JSON-encodes
that array with exceptions enabled and hashes it with SHA-256. Do not hash an unvalidated
Request object or store the encoded payload.

## Required Upstream Entities

### ProcurementRequirement

The consolidation phase must supply:

| Field/relationship | Required meaning |
|---|---|
| `id` | Internal bigint identity |
| `public_id` | Random UUID, unique, staff route key |
| `product_offer_id` or equivalent product scope | Stable compatibility identity |
| `service_date` | Casablanca service date |
| `required_quantity_hundredths` | Positive consolidated procurement quantity |
| current state / receipt relationship | Distinguishes outstanding from received |
| `orderGroups` | Groups supplied by this requirement |

One requirement has at most one `HubReceipt` in this MVP.

### OrderGroup

The consolidation phase must supply `public_id`, `procurement_requirement_id`, channel,
delivery zone, service date, `total_quantity_hundredths`, grouped state, and `orders`.
This feature adds:

| Field | Type | Rules |
|---|---|---|
| `fulfillment_status` | varchar(24) | `OrderGroupFulfillmentStatus`; default `grouped`, indexed |
| `current_stock_allocation_id` | nullable bigint FK | unique; null or the current non-released allocation |

The FK is added after `stock_allocations` exists. Restrict deletion. Model relationships:
`currentStockAllocation()` and `stockAllocations()`.

### OrderStatusTransition Extension

Add nullable `stock_allocation_id` restricted FK to `order_status_transitions` and a
unique index on `(order_id, stock_allocation_id, to_status)`. Existing non-hub rows keep
it null. Allocation appends `Grouped -> Allocated`; release appends
`Allocated -> Grouped`; later delivery appends `Allocated -> Dispatched` using the same
allocation identity.

## Backed Enums

### HubQualityGrade

| PHP case | Stored value | Label |
|---|---|---|
| `GradeA` | `grade_a` | Grade A |
| `GradeB` | `grade_b` | Grade B |
| `GradeC` | `grade_c` | Grade C |

All accepted grades are allocatable. Grade never participates in compatibility.

### ReceiptRejectionReason

| PHP case | Stored value | User selectable |
|---|---|---|
| `QualityDefect` | `quality_defect` | yes |
| `DamagedInTransit` | `damaged_in_transit` | yes |
| `Other` | `other` | yes |
| `ProcurementOverage` | `procurement_overage` | no; server-derived only |

### ReceiptCorrectionReason

| PHP case | Stored value |
|---|---|
| `WeighingError` | `weighing_error` |
| `DataEntryError` | `data_entry_error` |
| `Other` | `other` |

### HandlingLossReason

| PHP case | Stored value |
|---|---|
| `HandlingDamage` | `handling_damage` |
| `StorageDamage` | `storage_damage` |
| `Spoilage` | `spoilage` |
| `Other` | `other` |

### AllocationReleaseReason

| PHP case | Stored value |
|---|---|
| `AllocationError` | `allocation_error` |
| `StockIssue` | `stock_issue` |
| `Other` | `other` |

### OrderGroupFulfillmentStatus

| PHP case | Stored value | Meaning |
|---|---|---|
| `Grouped` | `grouped` | No active allocation |
| `Allocated` | `allocated` | Full group quantity reserved |
| `ReadyForDispatch` | `ready_for_dispatch` | Prepared; stock/orders still allocated |
| `Dispatched` | `dispatched` | Reserved for later delivery workflow |

## HubReceipt

Table: `hub_receipts`

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | internal primary key |
| `public_id` | UUID string | random, unique staff route key |
| `procurement_requirement_id` | bigint FK | unique; restrict deletion |
| `finalized_by_user_id` | nullable bigint FK | private actor; null on user deletion |
| `finalization_token_hash` | char(64) | SHA-256, unique |
| `finalization_payload_hash` | char(64) | SHA-256 canonical defining payload |
| `received_at` | timestamp | actual UTC receive time; not future |
| `quality_grade` | varchar(16) | `HubQualityGrade`; required when accepted > 0 |
| `initial_received_quantity_hundredths` | unsigned bigint | immutable original received quantity |
| `initial_accepted_quantity_hundredths` | unsigned bigint | immutable original accepted quantity |
| `initial_quality_rejected_quantity_hundredths` | unsigned bigint | immutable original quality rejection |
| `initial_overage_rejected_quantity_hundredths` | unsigned bigint | immutable server-derived overage |
| `initial_rejection_reason` | nullable varchar(32) | immutable original quality-rejection reason |
| `initial_rejection_note` | nullable text | immutable encrypted original note |
| `received_quantity_hundredths` | unsigned bigint | current corrected received quantity |
| `accepted_quantity_hundredths` | unsigned bigint | current corrected accepted quantity |
| `quality_rejected_quantity_hundredths` | unsigned bigint | current operator quality rejection |
| `overage_rejected_quantity_hundredths` | unsigned bigint | current server-derived overage |
| `available_quantity_hundredths` | unsigned bigint | current unreserved usable stock |
| `allocated_quantity_hundredths` | unsigned bigint | current active/prepared allocation stock |
| `damaged_quantity_hundredths` | unsigned bigint | cumulative handling/storage loss |
| `dispatched_quantity_hundredths` | unsigned bigint | later delivery workflow; zero in this phase |
| `rejection_reason` | nullable varchar(32) | user-selectable quality reason; required when quality rejected > 0 |
| `rejection_note` | nullable text | encrypted; max 500 chars |
| `inventory_version` | unsigned bigint | starts 1; increment on every counter/projection mutation |
| `downstream_started_at` | nullable timestamp | set once on first damage/allocation; blocks correction |
| `created_at`, `updated_at` | timestamps | Laravel timestamps |

Indexes:

- unique `public_id`, `procurement_requirement_id`, `finalization_token_hash`
- index `(received_at, id)` for overdue/newest reads
- index `(quality_grade, received_at)`
- index `(procurement_requirement_id, received_at)`

Computed values:

```text
rejected = quality_rejected + overage_rejected
undispatched = available + allocated
overdue = received_at <= now - 24 hours AND undispatched > 0
```

Relationships: belongs to procurement requirement and finalizing user; has many
corrections, handling losses, and stock allocations.

## HubReceiptCorrection

Table: `hub_receipt_corrections`

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | primary key |
| `hub_receipt_id` | bigint FK | restrict deletion |
| `corrected_by_user_id` | nullable bigint FK | private actor; null on user deletion |
| `operation_token_hash` | char(64) | SHA-256, unique |
| `operation_payload_hash` | char(64) | canonical payload SHA-256 |
| `reason` | varchar(32) | `ReceiptCorrectionReason` |
| `note` | nullable text | encrypted; max 500 chars |
| `before_*_quantity_hundredths` | unsigned bigint columns | received, accepted, quality rejected, overage rejected |
| `after_*_quantity_hundredths` | unsigned bigint columns | received, accepted, quality rejected, overage rejected |
| `before_rejection_reason` | nullable varchar(32) | preserved reason code |
| `after_rejection_reason` | nullable varchar(32) | resulting reason code |
| `before_rejection_note` | nullable text | encrypted preserved receipt note |
| `after_rejection_note` | nullable text | encrypted resulting receipt note |
| `created_at` | timestamp | correction time; no `updated_at` |

Correction is append-only. It may update only receipt quantities, rejection reason/note,
available quantity, and inventory version. It may not alter requirement, received time,
grade, actors, damage, allocation, or dispatch history.

## HandlingLoss

Table: `handling_losses`

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | primary key |
| `hub_receipt_id` | bigint FK | restrict deletion |
| `recorded_by_user_id` | nullable bigint FK | private actor; null on user deletion |
| `operation_token_hash` | char(64) | SHA-256, unique |
| `operation_payload_hash` | char(64) | canonical payload SHA-256 |
| `quantity_hundredths` | unsigned bigint | positive and <= available at write |
| `reason` | varchar(32) | `HandlingLossReason` |
| `note` | nullable text | encrypted; max 500 chars |
| `occurred_at` | timestamp | UTC; >= received_at and <= current time |
| `created_at` | timestamp | recorded time; no `updated_at` |

Handling loss rows are append-only. Each successful row decrements available and
increments damaged by exactly the same quantity.

## StockAllocation

Table: `stock_allocations`

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | primary key |
| `public_id` | UUID string | random, unique staff reference |
| `hub_receipt_id` | bigint FK | source receipt; restrict deletion |
| `order_group_id` | bigint FK | full target group; restrict deletion |
| `quantity_hundredths` | unsigned bigint | immutable exact group quantity |
| `allocated_by_user_id` | nullable bigint FK | private actor; null on user deletion |
| `allocated_at` | timestamp | UTC |
| `allocation_token_hash` | char(64) | SHA-256, unique |
| `allocation_payload_hash` | char(64) | canonical payload SHA-256 |
| `released_by_user_id` | nullable bigint FK | private actor |
| `released_at` | nullable timestamp | set once; null while active |
| `release_reason` | nullable varchar(32) | required on release |
| `release_note` | nullable text | encrypted; max 500 chars |
| `release_token_hash` | nullable char(64) | SHA-256, unique when set |
| `release_payload_hash` | nullable char(64) | canonical payload SHA-256 |
| `prepared_quantity_hundredths` | nullable unsigned bigint | must equal allocation |
| `prepared_by_user_id` | nullable bigint FK | private actor |
| `prepared_at` | nullable timestamp | set once; leaves allocation active |
| `preparation_token_hash` | nullable char(64) | SHA-256, unique when set |
| `preparation_payload_hash` | nullable char(64) | canonical payload SHA-256 |
| `dispatched_at` | nullable timestamp | reserved for later delivery feature |
| `lifecycle_version` | unsigned bigint | starts 1; increment on release/preparation/dispatch |
| `created_at`, `updated_at` | timestamps | Laravel timestamps |

Indexes:

- unique `public_id`, `allocation_token_hash`, nullable release/preparation token hashes
- index `(hub_receipt_id, released_at, allocated_at)`
- index `(order_group_id, released_at, allocated_at)`
- index `(prepared_at, released_at)` for ready handoff reads

Derived lifecycle:

```text
released            released_at is set
ready_for_dispatch  released_at null AND prepared_at set
allocated           released_at null AND prepared_at null
dispatched          dispatched_at set (later feature only)
```

## Quantity Contract

`App\Support\Hub\KilogramQuantity` accepts only digits plus an optional dot and one or
two fractional digits:

```text
"5" -> 500
"5.2" -> 520
"5.25" -> 525
```

It formats `525` as `5.25`. Reject signs, commas, exponent notation, whitespace-only,
more than two decimals, values outside validated bounds, and multiplication overflow.

## Receipt Finalization Algorithm

Inside one transaction with up to three deadlock retries:

1. Hash token and canonical validated payload. Resolve an existing replay first.
2. Lock/reload the procurement requirement and require it to be outstanding.
3. Parse `received`, `accepted`, and `quality_rejected` quantities.
4. Calculate `overage = max(received - required, 0)`.
5. Require `accepted + quality_rejected = min(received, required)`.
6. Require grade when accepted > 0 and quality rejection reason when quality rejected > 0.
7. Create the unique receipt with immutable initial/current values; set available to
   accepted and all other buckets to zero.
8. Mark the requirement received without changing its required quantity.
9. Commit and redirect to the receipt workspace.

The acceptance example `required=100`, `received=110`, `quality_rejected=0` produces
`accepted=100`, `overage_rejected=10`, and `available=100`.

## Counter Mutation Algorithms

Every mutation reloads inside a transaction, calculates new integers in PHP, asserts both
invariants, and updates `hub_receipts` with
`WHERE id = ? AND inventory_version = ?`. Zero updated rows means `stock_changed`.

### Correction

Require `downstream_started_at` null and available=accepted with all other buckets zero.
Recalculate overage and available, append before/after correction, update the current
receipt projection, and increment version. Initial fields never change.

### Damage

Require `0 < quantity <= available`. Append `HandlingLoss`; subtract from available, add
to damaged, set downstream start if null, and increment version.

### Allocation

1. Require receipt and group share the same procurement requirement.
2. Derive allocation quantity from the group; never accept it from the client.
3. Require group status grouped, every included Order grouped, and receipt available >=
   group quantity.
4. Create the allocation and conditionally set the group's current allocation/status
   only while the pointer is null and status is grouped.
5. Move receipt available to allocated with version compare-and-set.
6. Bulk-update exactly the group's orders grouped -> allocated and bulk-insert one
   append-only transition per order linked to the allocation.
7. Any count mismatch throws and rolls back every row.

### Release

Require active, unprepared, undispatched allocation. Conditionally clear only the
matching group pointer and return group to grouped. Move receipt allocated to available,
bulk-update exactly the included Orders allocated -> grouped, append transitions, and
set release facts once.

### Preparation

Require active allocation and submitted prepared quantity equal to immutable allocation
quantity. Set preparation facts and group status ready-for-dispatch using lifecycle
compare-and-set. Do not change receipt counters or Order statuses.

## Invariants

After every successful or replayed operation:

```text
accepted + quality_rejected + overage_rejected = received
available + allocated + damaged + dispatched = accepted
accepted <= procurement requirement
one procurement requirement -> at most one receipt
one order group -> at most one current stock allocation
one allocation -> exactly one receipt and one order group
prepared quantity is null or equals allocation quantity
released allocation is not prepared or dispatched
```
