# Data Model: Transparent Product Offer

## Modeling rules

- Use ordinary bigint primary keys for internal relations, matching the repository.
- Use a random UUID `product_offers.public_id` only for public route binding.
- Store MAD/kg as integer centimes (`550` means `5.50 MAD/kg`).
- Store percentages as signed integer basis points (`5091` means `50.91%`).
- Store quantity as `decimal(10,2)` kilograms and cast it to a string.
- Store all timestamps in UTC. Display them in `Africa/Casablanca`.
- Never soft-delete or hard-delete domain rows in Phase 1.
- Derive lifecycle labels from timestamps; do not add a redundant `status` column.

## User extension

Add one column to the existing `users` table:

| Field | Type | Rules |
|---|---|---|
| `is_sourcing_operator` | boolean | default `false`, indexed |

Update `User::casts()` with boolean casting. Do **not** add this field to `#[Fillable]`;
profile input must never grant the role. Add `UserFactory::operator()` for tests.

## ProductOffer

Table: `product_offers`

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | primary key |
| `public_id` | UUID string | random, unique, generated server-side, public route key |
| `created_by_user_id` | nullable bigint FK | private actor; `users.id`, null on user deletion |
| `replaces_product_offer_id` | nullable bigint self-FK | unique; published offer being corrected; restrict deletion |
| `crop` | varchar(120) | required, trimmed, public-safe |
| `origin` | varchar(255) | required generalized locality, never exact private address |
| `available_quantity_kg` | decimal(10,2) | greater than `0.00` |
| `availability_starts_at` | timestamp | UTC |
| `availability_ends_at` | timestamp | UTC; later than start |
| `farmer_payment_minor` | unsigned bigint | non-negative centimes/kg |
| `platform_margin_minor` | unsigned bigint | positive centimes/kg |
| `final_price_minor` | unsigned bigint | exact stored sum of farmer, costs, margin; positive |
| `farmer_share_bps` | unsigned integer | stored half-up percentage hundredths |
| `published_at` | nullable timestamp | null = draft; set once |
| `superseded_at` | nullable timestamp | set once when replacement publishes |
| `withdrawn_at` | nullable timestamp | set once by operator; immediately non-public |
| `created_at`, `updated_at` | timestamps | Laravel timestamps |

Indexes:

- unique `public_id`
- unique `replaces_product_offer_id`
- index `(published_at, superseded_at, withdrawn_at)` for public visibility
- index `(created_by_user_id, created_at)` for the operator list

Relationships:

- belongs to `User` as `creator`
- belongs to optional `ProductOffer` as `replaces`
- has one optional `ProductOffer` as `replacement`
- has many `OfferCostComponent` ordered by `position`, then `id`
- has many `BenchmarkComparison` ordered newest first

Computed lifecycle:

```text
draft       published_at is null
published   published_at set; superseded_at and withdrawn_at null
superseded  superseded_at set; withdrawn_at null
withdrawn   withdrawn_at set
```

Mutation rules:

- Draft fields and costs may be updated.
- Published fields, costs, and derived offer values are immutable.
- Only `superseded_at` and `withdrawn_at` may be set after publication.
- Publishing a replacement and superseding its predecessor happen in one transaction.
- Repeating publication/withdrawal returns the already transitioned record without
  creating duplicates.

## OfferCostComponent

Table: `offer_cost_components`

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | primary key |
| `product_offer_id` | bigint FK | cascade on offer deletion at DB maintenance level; no app delete route |
| `standard_code` | nullable varchar(40) | one of four fixed codes, null for custom |
| `name` | varchar(120) | public label |
| `normalized_name` | varchar(120) | trimmed, whitespace-collapsed, Unicode lowercase comparison key |
| `amount_minor` | unsigned bigint | non-negative centimes/kg |
| `position` | unsigned small integer | stable public display order |
| `created_at`, `updated_at` | timestamps | Laravel timestamps |

Required standard rows and fixed labels:

| `standard_code` | Public `name` | Position |
|---|---|---:|
| `collection` | Collection | 10 |
| `quality_control` | Quality control | 20 |
| `hub_handling_storage` | Hub handling and storage | 30 |
| `delivery_allocation` | Delivery allocation | 40 |

Constraints/indexes:

- unique `(product_offer_id, standard_code)`
- unique `(product_offer_id, normalized_name)`
- maximum 10 custom components enforced by Request and Action
- custom `position` starts at 100 and follows submitted order
- standard rows are always present and shown, including zero values
- custom normalized names cannot match a standard code or fixed label

Draft updates replace that draft's complete cost collection inside the same transaction.
Published cost rows are never updated.

## BenchmarkComparison

Table: `benchmark_comparisons`

This single row represents a recorded observation. Publishing the row adds its reviewed
calculation fields and publication timestamp; recording alone never changes public data.

| Field | Type | Rules / meaning |
|---|---|---|
| `id` | bigint | primary key |
| `product_offer_id` | bigint FK | offer whose unchanged price is compared |
| `recorded_by_user_id` | nullable bigint FK | private recorder; null on user deletion |
| `published_by_user_id` | nullable bigint FK | private reviewer; null until published; null on user deletion |
| `supersedes_comparison_id` | nullable bigint self-FK | unique; prior comparison replaced by this one |
| `benchmark_price_minor` | unsigned bigint | positive centimes/kg |
| `market_name` | varchar(160) | required public attribution |
| `source_type` | varchar(32) | `url`, `document`, or `field_observation` |
| `source_reference` | varchar(500) | public-safe URL/reference/note; URL required when type is `url` |
| `observed_at` | timestamp | UTC; not future |
| `is_demo` | boolean | explicit, always public when comparison is shown |
| `saving_minor` | nullable signed bigint | benchmark minus final price; set at publication |
| `saving_percentage_bps` | nullable signed integer | stored half-up result; set at publication |
| `published_at` | nullable timestamp | null = recorded only; set after operator review |
| `superseded_at` | nullable timestamp | set once when newer comparison publishes |
| `created_at`, `updated_at` | timestamps | Laravel timestamps |

Indexes:

- unique `supersedes_comparison_id`
- index `(product_offer_id, published_at, superseded_at)`
- index `(product_offer_id, observed_at)` for 24-hour freshness and 30-day staff history

State transitions:

```text
recorded    published_at is null
published   published_at set; superseded_at null
superseded  superseded_at set
```

Publication rules:

- The parent offer must already be published, except the selected initial comparison is
  published atomically by `PublishProductOfferAction`.
- `observed_at` must be at or after `publication_time - 24 hours` and not after
  `publication_time`.
- The Action locks the offer, candidate comparison, and current published comparison.
- It stores saving values, reviewer, and publication time, then supersedes the previous
  current comparison. Repeating publication of the same row is idempotent.
- A recorded but unpublished observation is always staff-only.
- Rows are retained indefinitely, satisfying the minimum 30-day retention.

## Exact calculation contract

Implement only in `App\Support\Pricing\OfferPriceCalculator`.

Inputs are integer centimes:

```text
operating_cost_minor = sum(all component amount_minor)
final_price_minor = farmer_payment_minor
                  + operating_cost_minor
                  + platform_margin_minor
saving_minor = benchmark_price_minor - final_price_minor
farmer_share_bps = round_half_up(
    farmer_payment_minor * 10000 / final_price_minor
)
saving_percentage_bps = round_half_up(
    saving_minor * 10000 / benchmark_price_minor
)
```

`10000` converts a ratio to percentage hundredths: `5091` renders as `50.91%`.

Signed integer half-up algorithm (never cast to float):

1. Record the sign of the numerator.
2. Divide the absolute numerator with `intdiv(absNumerator, denominator)`.
3. Compute the remainder with `%`.
4. Increment the quotient when `remainder * 2 >= denominator`.
5. Restore the sign.

Reject a zero denominator. Inputs are bounded by validation so multiplication by `10000`
fits PHP's 64-bit integer.

Decimal-string parsing algorithm:

1. Accept only digits with an optional decimal point and one or two fractional digits.
2. Split on the decimal point; right-pad the fractional part to two digits.
3. Return `whole * 100 + fraction` as an integer.
4. Format public money as `intdiv(minor, 100).'.'.twoDigitRemainder` plus `MAD/kg`.

The illustrative scenario must produce:

```text
farmer                         280
collection                     30
quality control                20
hub handling/storage           30
delivery allocation            90
platform margin               100
final price                   550  => 5.50 MAD/kg
benchmark                     800
saving                        250  => 2.50 MAD/kg
saving percentage            3125  => 31.25%
farmer share                 5091  => 50.91%
```

## Visibility rules

Evaluate with one captured UTC `now` per Action call.

### Current public offer

An offer is public when:

- `published_at` is set;
- `withdrawn_at` is null; and
- `superseded_at` is null, or `superseded_at >= now - 30 days`.

At exactly 30 days after supersession it is still public. Immediately afterward it is
staff-only. Missing, draft, withdrawn, and older superseded public IDs all return the same
404 response.

### Current comparison on an unsuperseded offer

Show the unsuperseded published comparison only when:

- `observed_at <= now`; and
- `observed_at >= now - 24 hours`.

At exactly 24 hours old it is still fresh. Immediately afterward hide benchmark price,
source, saving, and saving percentage and set `freshComparisonUnavailable = true`. Keep
the offer, costs, final price, and farmer share visible.

### Historical comparisons

Return at most 30 superseded comparisons with `superseded_at >= now - 30 days`, newest
first. These are explicitly labeled historical/superseded and never used as the current
comparison, so their observation may be older than 24 hours. After 30 days they are
staff-only.

## Public data allowlist

The public Action constructs arrays containing only the fields defined in
`contracts/web-routes-and-props.md`. It must not call `toArray()` on `ProductOffer`,
`BenchmarkComparison`, `User`, or loaded relationships. Never expose internal primary
keys, actor foreign keys, actor relationships, normalized names, or raw lifecycle fields
not listed in the contract.
