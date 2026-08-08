# Data Model: Midnight Order Consolidation

## Upstream models reused

Phase 003 MUST use these Phase 001/002 records and names. Except for the explicit Order
relationship and User permission below, it must not duplicate or rename them.

### ProductOffer and OfferCostComponent

`ProductOffer.id` is the MVP product scope. A required immutable cost component with
`standard_code = delivery_allocation` supplies `amount_minor` in MAD centimes/kg. The
offer's `crop` is the safe display label; grouping never uses the label as identity.

### Order

Required existing fields:

```text
id, public_id, product_offer_id, channel, status,
quantity_hundredths, currency, unit_price_minor, total_minor,
offer_public_id_snapshot, crop_snapshot, service_date, delivery_zone,
confirmed_at, updated_at
```

Required existing enums:

```text
OrderChannel: B2c=b2c, B2b=b2b
OrderStatus: Confirmed, Grouped, Allocated, Dispatched, Delivered, Cancelled
DeliveryZone: existing three Casablanca values
```

Phase 003 adds:

| Field | Type | Rules / meaning |
|---|---|---|
| `order_group_id` | nullable bigint FK | restrict delete; null until included; indexed |

Relationships:

- belongs to optional `OrderGroup`
- existing ProductOffer and transition relationships remain unchanged

The single nullable FK is the membership fact. Do not add a membership pivot. A grouped
Order keeps the FK even if Phase 004 temporarily returns its status from allocated to
grouped after releasing stock.

### OrderStatusTransition

Reuse the append-only Phase 002 transition model. Final consolidation appends exactly
one `confirmed -> grouped` row for each successfully claimed included Order. Actor is the
manual operator for an early/manual cycle and null for an automatic system cycle.

### User

Add non-fillable boolean `is_operations_manager`, default false. Add an
`operationsManager()` factory state that also sets `is_operations_operator = true`.
Ordinary operators can run and inspect cycles; only managers receive cutoff props or may
update the cutoff.

## Enums

### ConsolidationCycleStatus

| Case | Value | Meaning |
|---|---|---|
| `Open` | `open` | service-date window accepts confirmations |
| `Processing` | `processing` | short evaluation/finalization attempt is active |
| `AwaitingDecision` | `awaiting_decision` | current preview has unresolved under-minimum candidates |
| `Completed` | `completed` | reconciled outputs committed |
| `Failed` | `failed` | safe failure recorded; operator retry allowed |

Transitions:

```text
first order confirmation -> open row (or reuse existing open row)
open -> processing -> completed
open -> processing -> awaiting_decision
awaiting_decision -> processing -> completed
processing -> failed
failed -> processing -> awaiting_decision|completed|failed
completed -> completed (idempotent read-only replay)
```

No transition may return a non-open cycle to `open`.

### ConsolidationTrigger

| Case | Value |
|---|---|
| `Automatic` | `automatic` |
| `Manual` | `manual` |

### ConsolidationCandidateDecision

| Case | Value | Meaning |
|---|---|---|
| `Approved` | `approved` | include this under-minimum group |
| `Excluded` | `excluded` | leave its Orders confirmed/ungrouped for follow-up |

Only under-minimum candidates receive a decision. Above-minimum candidates have null
decision and are included automatically.

### ProcurementRequirementStatus

| Case | Value | Meaning |
|---|---|---|
| `Outstanding` | `outstanding` | awaits hub receipt |
| `Received` | `received` | Phase 004 has finalized its receipt |

Phase 003 creates only `Outstanding`; Phase 004 owns the transition to `Received`.

## ConsolidationCutoffChange

Table: `consolidation_cutoff_changes`

Append-only history. No application update/delete path.

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | primary key; breaks ties for same effective date |
| `previous_cutoff_minutes` | unsigned small integer | 0–1439; effective value before this decision |
| `new_cutoff_minutes` | unsigned small integer | 0–1439; requested HH:MM as minute of day |
| `effective_on` | date | next Casablanca operating date after decision |
| `actor_user_id` | nullable bigint FK | operations user; null on user deletion |
| `created_at` | timestamp | UTC decision time; no `updated_at` |

Indexes:

- index `(effective_on, id)` for latest applicable rule
- index `(actor_user_id, created_at)` for private accountability

Rules:

- no rows means the default cutoff is minute 0 (`00:00`)
- latest row ordered by `effective_on DESC, id DESC` where `effective_on <= operating
  date` is effective
- repeating the latest pending value returns that row without another insert
- response props use a generalized `Operations staff` actor label, never user ID/name

## ConsolidationCycle

Table: `consolidation_cycles`

One row is both the service-date ordering window and its consolidation cycle.

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | primary key |
| `public_id` | UUID string | random, unique route key |
| `service_date` | date | exactly one Casablanca service date; unique |
| `operating_date` | date | `service_date - 1` Casablanca calendar day; immutable snapshot |
| `status` | varchar(24) | `ConsolidationCycleStatus`; default `open` |
| `trigger` | nullable varchar(16) | set when window closes |
| `cutoff_change_id` | nullable bigint FK | scheduled setting snapshotted at cycle creation; null for default midnight |
| `scheduled_cutoff_minutes` | unsigned small integer | 0â€“1439; immutable snapshot made at cycle creation |
| `scheduled_cutoff_at` | timestamp | immutable scheduled UTC instant made at cycle creation |
| `effective_cutoff_at` | nullable timestamp | authoritative UTC eligibility boundary |
| `effective_cutoff_local_date` | nullable date | preserved Casablanca cutoff date |
| `effective_cutoff_minutes` | nullable unsigned small integer | actual local minute; early manual may differ from scheduled |
| `cutoff_timezone` | varchar(64) | immutable `Africa/Casablanca` snapshot made at creation |
| `triggered_by_user_id` | nullable bigint FK | manual actor; null for automatic; null on user deletion |
| `current_generation_hash` | nullable char(64) | current deterministic preview SHA-256 |
| `source_order_count` | unsigned integer | all current eligible orders before decisions; default 0 |
| `source_quantity_hundredths` | unsigned bigint | all current eligible quantity; default 0 |
| `included_order_count` | unsigned integer | completed included orders; default 0 |
| `included_quantity_hundredths` | unsigned bigint | completed included quantity; default 0 |
| `excluded_order_count` | unsigned integer | current/final operator-excluded orders; default 0 |
| `excluded_quantity_hundredths` | unsigned bigint | current/final excluded quantity; default 0 |
| `confirmed_after_cutoff_count` | unsigned integer | same-service-date aggregate exclusion; default 0 |
| `not_confirmed_count` | unsigned integer | same-service-date lifecycle exclusion; default 0 |
| `already_grouped_count` | unsigned integer | same-service-date membership exclusion; default 0 |
| `commercial_total_minor` | unsigned bigint | sum of included immutable Order totals; default 0 |
| `procurement_requirement_count` | unsigned integer | completed output count; default 0 |
| `delivery_group_count` | unsigned integer | completed output count; default 0 |
| `started_at` | nullable timestamp | latest attempt start in UTC |
| `completed_at` | nullable timestamp | final completion in UTC |
| `failed_at` | nullable timestamp | latest safe failure in UTC |
| `last_failure_code` | nullable varchar(64) | fixed non-sensitive category only |
| `created_at`, `updated_at` | timestamps | standard timestamps |

Indexes/constraints:

- unique `public_id`
- unique `service_date` — primary idempotency/window invariant
- index `(status, service_date)`
- index `(trigger, service_date)`
- index `(created_at, id)` for recent-cycle pagination

Relationships:

- belongs to optional cutoff change
- belongs to optional triggering User
- has many candidates, requirements, and groups

Model rules:

- random UUID route key; explicit casts for enums, date, datetimes
- default status mirrored in model attributes
- public/HTTP arrays never expose internal IDs, hashes, or actor identity

## ConsolidationCandidate

Table: `consolidation_candidates`

One safe group preview for one cycle generation. Old generations remain audit history
and are ignored once the cycle points to another generation.

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | primary key |
| `public_id` | UUID string | random, unique nested route key |
| `consolidation_cycle_id` | bigint FK | restrict delete |
| `generation_hash` | char(64) | whole-preview SHA-256; hidden |
| `group_key_hash` | char(64) | product/channel/zone SHA-256; hidden |
| `demand_fingerprint` | char(64) | exact source-set/minimum SHA-256; hidden |
| `product_offer_id` | bigint FK | stable MVP product scope; restrict delete |
| `crop_snapshot` | varchar(120) | safe display label copied from source Orders |
| `channel` | varchar(8) | existing OrderChannel |
| `delivery_zone` | varchar(40) | existing DeliveryZone |
| `quantity_hundredths` | unsigned bigint | source quantity sum |
| `order_count` | unsigned integer | source count |
| `commercial_total_minor` | unsigned bigint | sum of source Order totals |
| `delivery_allocation_minor_per_kg` | unsigned bigint | quantity-weighted estimate, one final half-up rounding |
| `minimum_quantity_hundredths` | unsigned bigint | effective config value preserved |
| `is_under_minimum` | boolean | quantity below effective minimum |
| `decision` | nullable varchar(16) | CandidateDecision; null until decided |
| `decision_reason` | nullable varchar(64) | fixed `operator_excluded_below_minimum` only for exclusion |
| `decided_by_user_id` | nullable bigint FK | private actor; null on user deletion |
| `decided_at` | nullable timestamp | UTC decision time |
| `created_at`, `updated_at` | timestamps | standard timestamps |

Indexes/constraints:

- unique `public_id`
- unique `(consolidation_cycle_id, generation_hash, group_key_hash)`
- index `(consolidation_cycle_id, generation_hash, is_under_minimum, decision)`
- index `(product_offer_id, channel, delivery_zone)`
- index `(decided_by_user_id, decided_at)`

Decision rules:

- above-minimum candidate: `is_under_minimum=false`, decision stays null
- under-minimum candidate: decision must be approved or excluded before completion
- first decision wins; same decision replay returns current row; different decision is a
  conflict
- decision route must scope candidate to cycle and require candidate generation equals
  `cycle.current_generation_hash`
- changed demand creates a new generation/candidate; old decision never carries forward

## ProcurementRequirement

Table: `procurement_requirements`

One included product scope for one completed cycle.

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | primary key |
| `public_id` | UUID string | random, unique route key |
| `consolidation_cycle_id` | bigint FK | restrict delete |
| `product_offer_id` | bigint FK | product scope; restrict delete |
| `crop_snapshot` | varchar(120) | safe immutable display label |
| `service_date` | date | same as parent cycle |
| `required_quantity_hundredths` | unsigned bigint | included group sum |
| `order_count` | unsigned integer | included order count |
| `commercial_total_minor` | unsigned bigint | included Order total sum |
| `delivery_allocation_minor_per_kg` | unsigned bigint | weighted aggregate estimate |
| `status` | varchar(16) | ProcurementRequirementStatus; default outstanding |
| `created_at`, `updated_at` | timestamps | standard timestamps |

Indexes/constraints:

- unique `public_id`
- unique `(consolidation_cycle_id, product_offer_id)`
- index `(status, service_date)` for Phase 004 work queue
- index `(product_offer_id, service_date)`

Relationships:

- belongs to cycle and ProductOffer
- has many OrderGroups

## OrderGroup

Table: `order_groups`

One completed deterministic product/channel/zone group.

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | primary key |
| `public_id` | UUID string | random, unique route key |
| `consolidation_cycle_id` | bigint FK | restrict delete |
| `procurement_requirement_id` | bigint FK | restrict delete |
| `channel` | varchar(8) | existing OrderChannel |
| `delivery_zone` | varchar(40) | existing DeliveryZone |
| `service_date` | date | same as parent cycle/requirement |
| `total_quantity_hundredths` | unsigned bigint | included Order quantity sum |
| `order_count` | unsigned integer | included Order count |
| `commercial_total_minor` | unsigned bigint | included immutable Order total sum |
| `delivery_allocation_minor_per_kg` | unsigned bigint | weighted aggregate estimate |
| `created_at`, `updated_at` | timestamps | standard timestamps |

Indexes/constraints:

- unique `public_id`
- unique `(procurement_requirement_id, channel, delivery_zone)`
- index `(consolidation_cycle_id, service_date)`
- index `(channel, delivery_zone, service_date)`

Relationships:

- belongs to cycle and procurement requirement
- has many Orders through `orders.order_group_id`

Do not add a Phase 003 group status. Phase 004 adds and owns fulfillment status.

## Configuration

File: `config/consolidation.php`

```text
timezone = Africa/Casablanca
default_cutoff_minutes = 0
max_orders_per_cycle = 500
cycle_page_size = 25
candidate_page_size = 50
group_page_size = 50
group_order_page_size = 50
cache_lock_seconds = 120
cache_lock_wait_seconds = 5
delivery_minimums[channel][zone] = 1000 hundredths for all six demo pairs
```

Use enum backed values as keys. Values must be non-negative integers. Never call `env()`
outside the config file; this configuration contains no secret.

`Support\Quantities\KilogramQuantity` is the shared parser/formatter for these integer
hundredths. Phase 004 must reuse it rather than create another hub-specific quantity
class.

## Cutoff resolution algorithm

For a new cycle with service date `D`:

```text
operating_date = D - 1 Casablanca calendar day
cutoff_change = latest row where effective_on <= operating_date,
                ordered effective_on DESC, id DESC
scheduled_minutes = cutoff_change.new_cutoff_minutes or 0
scheduled_local = operating_date at scheduled_minutes in Africa/Casablanca
scheduled_utc = unambiguous UTC instant of scheduled_local
```

Store `operating_date`, `cutoff_change_id`, `scheduled_cutoff_minutes`,
`scheduled_cutoff_at`, and `cutoff_timezone` when the cycle row is first created. Never
re-resolve these fields for an existing cycle. A later cutoff change therefore affects
only cycle rows created after that change.

Scheduled start runs only when current Casablanca operating date equals
`operating_date` and current minute is at/after `scheduled_minutes`; its effective cutoff
is `scheduled_utc` even if the scheduler starts late.

Manual start:

```text
if authoritative now < scheduled_utc:
    trigger = manual
    effective_cutoff = now (early closure)
else:
    trigger = manual
    effective_cutoff = scheduled_utc (due/recovery; do not absorb late orders)
```

The operator supplies only `service_date`; never a cutoff timestamp.

## Order confirmation window guard

Phase 002 `CreateOrderAction` must add these steps inside its existing confirmation
transaction, before creating the Order:

1. Resolve the proposed schedule snapshot for the selected slot's service date.
2. `firstOrCreate` the service-date ConsolidationCycle in open status with that snapshot;
   recover its unique-key race by re-reading the row.
3. Use only the persisted cycle snapshot. Reject with safe `ordering_window_closed` when
   authoritative now is after `scheduled_cutoff_at`.
4. Lock/reload the cycle where supported and require status remains open.
5. Continue Phase 002 confirmation only after the guard passes.

This is the only Phase 002 business change. Do not alter its price, availability,
customer, token, or lifecycle logic.

## Deterministic preview algorithm

Input query:

```text
status = confirmed
service_date = cycle.service_date
confirmed_at <= cycle.effective_cutoff_at
order_group_id IS NULL
ORDER BY product_offer_id, channel, delivery_zone, id
LIMIT max_orders_per_cycle + 1
```

Reject safely if more than 500 eligible Orders exist; do not partially consolidate.
Select only safe required columns and eager-load only the required ProductOffer delivery
allocation component.

For bounded reconciliation, also count same-service-date Orders with mutually exclusive
precedence: `already_grouped`, then `not_confirmed`, then `confirmed_after_cutoff`.
Persist these three aggregate counts. Upstream non-null constraints make missing grouping
dimensions impossible; a detected contract violation fails safely as
`invalid_source_order`.

For each exact `(product_offer_id, channel, delivery_zone)` group:

```text
quantity = SUM(order.quantity_hundredths)
order_count = COUNT(order)
commercial_total = SUM(order.total_minor)
delivery_numerator = SUM(delivery_component.amount_minor * order.quantity_hundredths)
delivery_estimate = round_half_up(delivery_numerator / quantity)
minimum = config[channel][delivery_zone]
under_minimum = quantity < minimum
group_key_hash = SHA256(product_offer_id|channel|delivery_zone)
demand_fingerprint = SHA256(
    group key + ordered [order id, quantity, unit price, total, confirmed_at] + minimum
)
```

Generation hash is SHA-256 of ordered group-key hashes and demand fingerprints. Hashes
are internal, hidden, and never logged.

## Cycle processing algorithm

### Start/close and evaluate

1. Acquire the service-date cache lock as an optimization.
2. In a short three-attempt transaction, first-create/reload and lock the cycle.
3. Completed returns existing reconciliation. Any non-open cycle keeps every persisted
   schedule/effective-cutoff field unchanged.
4. For an open cycle, derive the effective cutoff from its immutable schedule snapshot,
   record trigger, effective cutoff, actor, and started time, set `processing`, and
   commit. This committed close-boundary step permanently closes the ordering window.
5. In a second transaction, query/recompute the preview, persist current-generation
   candidates, and update source/exclusion preview totals.
6. If a current under-minimum candidate lacks a decision, set awaiting_decision and
   commit with no requirements, groups, order FK changes, or transitions.

### Finalize

When all current under-minimum candidates have decisions:

1. Start one three-attempt transaction and lock/reload cycle then eligible Orders in ID
   order; recompute generation and abort to a fresh awaiting-decision generation if it
   changed.
2. Remove excluded candidate groups from the included set without changing their Orders.
3. Create one requirement per included ProductOffer and one OrderGroup per included
   channel/zone using the unique keys.
4. For each group in stable order, conditionally update its exact Order IDs where status
   is confirmed and `order_group_id IS NULL`; set grouped and the group FK.
5. Require affected count equals expected count, then append exactly those transition
   rows.
6. Verify group sums, requirement sums, included/excluded sums, commercial totals, and
   unique membership in memory from the locked safe dataset.
7. Store final cycle totals/status/completion time and commit.

Any exception rolls back preview/final outputs. A separate small conditional write may
change `processing` to `failed` with a fixed safe category, but it must not update or
clear any schedule/effective-cutoff field. If the close-boundary transaction itself
rolls back, the cycle remains open and no failure row is written. Exception text and
payloads are never stored.

## Core invariants

```text
one cycle per service_date
one requirement per cycle + product_offer
one group per requirement + channel + delivery_zone
one Order belongs to zero or one OrderGroup
group quantity/count/total = sum/count of its included Orders
requirement quantity/count/total = sum of its groups
cycle source = included + excluded for quantity and count
cycle included = sum of requirements = sum of groups
completed cycle has zero unexplained variance
confirmed Order snapshot fields never change
excluded Order remains confirmed with order_group_id null
```
