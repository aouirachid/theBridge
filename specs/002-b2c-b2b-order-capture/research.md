# Research: B2C and B2B Order Capture

## Decision 1: Treat Phase 1 as a hard prerequisite

**Decision**: Implement `specs/001-transparent-product-offer/tasks.md` before Phase 2.
Phase 2 extends the Phase 1 `ProductOffer`, public offer page, policies, Actions, and
integer pricing conventions.

**Rationale**: The current database and application contain no Phase 1 offer code.
Duplicating a temporary offer model would misdirect implementation and create a costly
merge later.

**Alternatives considered**: Build a standalone Phase 2 offer stub (rejected: duplicate
domain); include all Phase 1 work in this feature (rejected: destroys phase boundaries).

## Decision 2: Store offer-defined delivery slots as rows

**Decision**: Add `offer_delivery_slots` owned by `ProductOffer`. Draft offer create/update
replaces the complete bounded slot collection in the same transaction as other draft
fields. Published offers and their slots are immutable. Require 1–14 valid distinct
slots before publication.

**Rationale**: Rows provide stable random public IDs and simple membership/immutability
checks. Extending the existing draft workflow is smaller than separate slot CRUD.

**Alternatives considered**: JSON on the offer (rejected: weak identity/relations);
global free-form windows (rejected: contradicts clarification); separate slot management
screens (rejected: unnecessary use cases).

## Decision 3: Use one order table with conditional channel fields

**Decision**: Store common commercial, lifecycle, delivery, and encrypted contact fields
on `orders`. B2C leaves business fields null; B2B requires them. Keep status history in
`order_status_transitions`.

**Rationale**: Both channels share nearly every invariant. One table avoids two detail
models, joins, polymorphism, and duplicated Actions while preserving channel-specific
validation.

**Alternatives considered**: Separate B2C/B2B tables (rejected: duplicated lifecycle and
queries); JSON details (rejected: weaker schema and casting clarity).

## Decision 4: Use integer money and quantity units

**Decision**: Reuse Phase 1 integer centimes for unit price. Parse kilograms into integer
hundredths (`5.25 kg` -> `525`). Calculate `total_minor` as
`round_half_up(unit_price_minor * quantity_hundredths / 100)` with overflow guards.

**Rationale**: This exactly implements the specification's one-time centime rounding and
avoids database/PHP/JavaScript float disagreements.

**Alternatives considered**: Database decimals throughout (rejected: conversion and
rounding ambiguity); money package (rejected: new dependency); browser math (rejected:
non-authoritative and easy to diverge).

## Decision 5: Use server review and confirmation endpoints

**Decision**: The public offer page uses Inertia v3 `useHttp` for a no-write review POST
and a write confirmation POST. Review returns the authoritative unit price and total.
Confirmation revalidates everything and returns a PII-free confirmation on the same page.

**Rationale**: `useHttp` supports standalone JSON, processing state, and 422 validation
without adding Axios. Keeping both steps on the existing offer page is the smallest UX
that proves review-before-confirmation and avoids a public tracking route.

**Alternatives considered**: Client-side price calculation (rejected: can disagree);
pending-order review rows (rejected: abandoned PII and cleanup); separate confirmation
page/token (rejected: becomes public order tracking).

## Decision 6: Derive reserved quantity under an offer lock

**Decision**: During confirmation, lock the current `ProductOffer`, sum
`quantity_hundredths` for all its non-cancelled orders, compare the new quantity with the
offer's available quantity, then insert inside the same transaction. Cancellation changes
status under lock; the aggregate therefore releases quantity exactly once.

**Rationale**: Derived reservation has one source of truth and avoids a mutable counter
that could drift. MySQL/PostgreSQL honor the row lock; SQLite serializes conflicting
writes and safely fails one writer rather than overbooking.

**Alternatives considered**: Mutable reserved counter (rejected: extra invariant);
cache lock (rejected: second consistency system); no lock (rejected: race condition).

## Decision 7: Hash the idempotency token

**Decision**: The offer page receives one random submission token. Confirmation hashes
it with SHA-256 and stores only the unique hash. A repeated token with the same normalized
payload returns the original safe confirmation; reuse with changed order-defining fields
returns `submission_mismatch`.

**Rationale**: It prevents double reservation and keeps the raw token out of storage.
The token grants no public read capability and is never used in a URL.

**Alternatives considered**: Public reference only (rejected: generated after write);
raw token (rejected: avoidable exposure); session-only idempotency (rejected: harder to
test and less durable across retries).

## Decision 8: Use explicit enums and append-only transitions

**Decision**: Native backed enums define three zones (`casablanca_centre`,
`casablanca_east`, `casablanca_west`), two channels, and seven statuses. `Order` stores
the current status; every accepted initial confirmation or cancellation also appends an
`OrderStatusTransition` row. Later phases add their own transition Actions.

**Rationale**: A tiny explicit vocabulary prevents string drift and gives later phases a
stable lifecycle without adding a state-machine package.

**Alternatives considered**: Free strings (rejected: typo-prone); generic state-machine
library (rejected: dependency/abstraction); manual advancement through future states in
Phase 2 (rejected: scope violation).

## Decision 9: Separate public and operator controls

**Decision**: Add non-fillable `users.is_operations_operator`, `OrderPolicy`, authenticated
verified operator routes, and named user/IP throttling. Public preview and confirmation
use separate IP throttles. Encrypt PII with Laravel encrypted casts on `TEXT` fields and
never return models directly.

**Rationale**: Sourcing and operations are distinct permissions. Laravel's encrypted
casts are version-supported; encrypted values are intentionally not queryable, so the
index filters only non-sensitive coded fields.

**Alternatives considered**: Reuse sourcing permission (rejected: excessive access);
plaintext PII (rejected: specification/security violation); searchable encryption
(rejected: unnecessary MVP complexity).

## Decision 10: Keep verification focused and dependency-free

**Decision**: Use direct Pest tests for all five Actions plus calculator tests and two
HTTP feature files. Update only affected Phase 1 tests. Use existing static/frontend
checks and manual responsive validation; do not install browser tooling.

**Rationale**: This satisfies the constitution's separate Action/HTTP layers with the
fewest test files and no dependency approval.

**Alternatives considered**: Browser package (rejected: new dependency); one large
end-to-end test only (rejected: poor failure localization and missing Action coverage).
