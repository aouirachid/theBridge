# Data Model: B2C and B2B Order Capture

## Modeling rules

- Phase 1 `ProductOffer` is required and remains the source of crop, availability, and
  unit price.
- Internal relationships use bigint IDs. Publicly submitted/returned identities use
  random UUIDs.
- Store MAD as integer centimes and quantity as integer hundredths of a kilogram.
- Store timestamps in UTC; derive/display service dates in `Africa/Casablanca`.
- Store current order status plus append-only transition rows. Never delete orders or
  transition history through the application.
- Store encrypted attributes in `TEXT`; never query or index them.
- Published offer slots and confirmed commercial snapshots are immutable.

## User extension

Add one field to the existing `users` table:

| Field | Type | Rules |
|---|---|---|
| `is_operations_operator` | boolean | default `false`, indexed, not fillable |

Add a boolean cast and `UserFactory::operationsOperator()` state. Do not expose the flag
in profile input or public props. Keep Phase 1's `is_sourcing_operator` separate.

## Backed enums

### DeliveryZone

| PHP case | Stored value | Public label |
|---|---|---|
| `CasablancaCentre` | `casablanca_centre` | Casablanca Centre |
| `CasablancaEast` | `casablanca_east` | Casablanca East |
| `CasablancaWest` | `casablanca_west` | Casablanca West |

This is the complete Phase 2 service map. Expanding it is a later business decision.

### OrderChannel

| PHP case | Stored value |
|---|---|
| `B2c` | `b2c` |
| `B2b` | `b2b` |

### OrderStatus

| PHP case | Stored value |
|---|---|
| `Pending` | `pending` |
| `Confirmed` | `confirmed` |
| `Grouped` | `grouped` |
| `Allocated` | `allocated` |
| `Dispatched` | `dispatched` |
| `Delivered` | `delivered` |
| `Cancelled` | `cancelled` |

Allowed lifecycle graph:

```text
pending -> confirmed -> grouped -> allocated -> dispatched -> delivered
    |          |
    +----------+----------------------------------------------> cancelled
```

The diagram's cancellation path is limited in Phase 2: only `pending -> cancelled` and
`confirmed -> cancelled` are allowed. Phase 2 normally creates and confirms inside one
transaction, so no committed actionable pending row remains. Later phases own their own
forward transition Actions. `Delivered` and `Cancelled` are terminal.

## OfferDeliverySlot

Table: `offer_delivery_slots`

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | internal primary key |
| `public_id` | UUID string | random, unique, generated server-side |
| `product_offer_id` | bigint FK | parent Phase 1 offer; cascade only for draft maintenance |
| `starts_at` | timestamp | UTC; at or after offer start |
| `ends_at` | timestamp | UTC; after slot start and at or before offer end |
| `created_at`, `updated_at` | timestamps | Laravel timestamps |

Constraints/indexes:

- unique `public_id`
- unique `(product_offer_id, starts_at, ends_at)`
- index `(product_offer_id, starts_at)`
- a draft has 1–14 distinct slots before publication
- exact duplicate slots are rejected; overlapping non-identical slots are allowed
- draft create/update replaces the complete slot collection in the offer transaction
- published slots cannot be added, changed, or deleted

Relationships:

- belongs to `ProductOffer`
- has many `Order`

Public eligibility at captured UTC `now`:

```text
parent offer is the current public, unsuperseded, unwithdrawn publication
starts_at > now
starts_at and ends_at remain inside the parent's availability window
```

An already selected slot is rechecked during confirmation. Slot rows are never used as
the confirmed historical display source; the order stores its own time snapshot.

## Order

Table: `orders`

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | internal primary key |
| `public_id` | UUID string | random, unique operator/public confirmation reference |
| `submission_hash` | char(64) | SHA-256 of raw token; unique; raw token never stored |
| `product_offer_id` | bigint FK | source publication; restrict deletion |
| `offer_delivery_slot_id` | bigint FK | selected slot; restrict deletion |
| `channel` | varchar(8) | `OrderChannel` value |
| `status` | varchar(16) | `OrderStatus`; default `pending` |
| `quantity_hundredths` | unsigned bigint | positive; `500` means `5.00 kg` |
| `currency` | char(3) | always `MAD` |
| `unit_price_minor` | unsigned bigint | immutable confirmed MAD/kg centimes |
| `total_minor` | unsigned bigint | immutable one-time half-up total |
| `offer_public_id_snapshot` | UUID string | immutable source public reference |
| `crop_snapshot` | varchar(120) | immutable source crop label |
| `service_date` | date | Casablanca calendar date of selected slot start |
| `slot_starts_at` | timestamp | immutable UTC slot snapshot |
| `slot_ends_at` | timestamp | immutable UTC slot snapshot |
| `delivery_zone` | varchar(40) | `DeliveryZone` value; safe coded filter field |
| `customer_name` | text | encrypted; B2C consumer or B2B buyer contact name |
| `business_name` | nullable text | encrypted; required for B2B, null for B2C |
| `phone` | text | encrypted; required for both channels |
| `email` | nullable text | encrypted; valid if supplied |
| `delivery_address` | nullable text | encrypted; optional B2C; null for B2B in Phase 2 |
| `delivery_note` | nullable text | encrypted; optional B2C; null for B2B in Phase 2 |
| `confirmed_at` | timestamp | set in same transaction as confirmation history |
| `created_at`, `updated_at` | timestamps | Laravel timestamps |

Indexes:

- unique `public_id`
- unique `submission_hash`
- index `(status, service_date)` for next-cycle eligibility
- index `(product_offer_id, status)` for reserved-quantity aggregate
- index `(channel, service_date)` for operator filtering
- index `(delivery_zone, service_date)` for operator filtering
- index `(created_at, id)` for stable newest-first pagination

Model configuration:

- cast `channel`, `status`, and `delivery_zone` to backed enums
- cast `service_date` to date and timestamps to datetime
- encrypted casts: `customer_name`, `business_name`, `phone`, `email`,
  `delivery_address`, `delivery_note`
- hide all encrypted fields, `submission_hash`, internal IDs/FKs, and actor data by default
- allowlist fillable server-owned fields; never use `$guarded = []`

Channel validation:

```text
B2C: customer_name, phone, zone, quantity, slot required;
     email, delivery_address, delivery_note optional;
     business_name must be absent.

B2B: business_name, customer_name (buyer contact), phone, zone, quantity, slot required;
     email optional;
     delivery_address and delivery_note must be absent in Phase 2.
```

Commercial immutability:

- After confirmation, `product_offer_id`, `offer_delivery_slot_id`, channel, quantity,
  all snapshot fields, currency, unit price, total, service date, and confirmation time
  never change.
- Phase 2 may change only current `status` from `confirmed` to `cancelled`, append its
  transition, and update `updated_at`.
- PII retention/redaction is governed outside Phase 2 and must not alter commercial or
  lifecycle history.

Relationships:

- belongs to `ProductOffer`
- belongs to `OfferDeliverySlot`
- has many `OrderStatusTransition`, oldest first

## OrderStatusTransition

Table: `order_status_transitions`

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | primary key |
| `order_id` | bigint FK | parent order; restrict deletion |
| `actor_user_id` | nullable bigint FK | null for guest confirmation; null on user deletion |
| `from_status` | nullable varchar(16) | null only for initial pending record |
| `to_status` | varchar(16) | resulting `OrderStatus` |
| `created_at` | timestamp | accepted transition time; no `updated_at` |

Indexes:

- index `(order_id, created_at, id)` for ordered history
- index `(actor_user_id, created_at)` for private accountability

Mutation rules:

- append only; no application update/delete path
- confirmation appends `null -> pending` and `pending -> confirmed` in the same order
  transaction
- cancellation appends `confirmed -> cancelled` with the authorized operator ID
- later phases append only their allowed transitions

## Order total contract

Implement only in `App\Support\Pricing\OrderTotalCalculator`.

Inputs:

```text
unit_price_minor: positive integer centimes per kilogram
quantity_hundredths: positive integer hundredths of a kilogram
```

Calculation:

```text
numerator = unit_price_minor * quantity_hundredths
total_minor = intdiv(numerator, 100)
if numerator % 100 >= 50: total_minor += 1
```

Reject non-positive inputs and multiplication overflow. Do not use floats. Examples:

```text
550 * 500 / 100 = 2750 minor = 27.50 MAD
550 * 4000 / 100 = 22000 minor = 220.00 MAD
550 * 525 / 100 = 2887.5 -> 2888 minor = 28.88 MAD
```

Quantity parsing accepts only digits plus an optional decimal point and one or two
fractional digits, then converts exactly as Phase 1 parses money:

```text
"5" -> 500
"5.2" -> 520
"5.25" -> 525
```

## Review and confirmation algorithm

### ReviewOrderAction (no writes)

1. Capture one UTC `now`.
2. Load the offer by public ID with the selected slot.
3. Require the offer to be the current public publication and its slot to be eligible.
4. Parse quantity to hundredths.
5. Sum non-cancelled order quantity for the offer and reject quantity above the current
   remainder. This is advisory only and reserves nothing.
6. Calculate and return the allowlisted review shape.

### CreateOrderAction (one transaction)

1. Normalize the validated payload and compute `submission_hash`.
2. If the hash exists, compare the existing offer, slot, channel, quantity, zone, name,
   business name, phone, email, address, and note with the normalized submitted values.
   Return the same safe confirmation when all match; throw `submission_mismatch` when
   any differ.
3. Begin a database transaction and lock the current offer row.
4. Reload/validate the selected slot and public offer state using one captured `now`.
5. Parse quantity; sum all non-cancelled order quantities for the locked offer.
6. Reject if requested quantity exceeds the exact remainder.
7. Recalculate unit price and total; create the pending Order and initial transition.
8. Change it to confirmed, set `confirmed_at`, and append pending -> confirmed.
9. Commit and return an explicit PII-free confirmation array.

A unique-hash race is resolved by re-reading the matching order and applying step 2.
No failed confirmation leaves a row or reservation.

## Availability and cancellation

```text
reserved_quantity = SUM(quantity_hundredths WHERE status != cancelled)
remaining_quantity = offer.available_quantity_hundredths - reserved_quantity
```

`CancelOrderAction` locks the order and parent offer in a transaction. It accepts only
`confirmed`, sets `cancelled`, appends the operator transition, and commits. Repeating
cancellation returns the already-cancelled order without another transition. All other
states produce `invalid_state`. Because availability is derived, the quantity is released
once without updating a counter.

## Consolidation eligibility

An order is eligible for the next cycle when all are true:

```text
status = confirmed
service_date is the selected cycle's service date
no grouping relationship exists yet (introduced by Phase 3)
```

Phase 2 exposes this as `status === confirmed` for the selected service date. Phase 3
adds grouping identity and tightens the query without changing price snapshots.
