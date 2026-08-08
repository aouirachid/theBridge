# Web Routes, Payloads, and Inertia Props Contract

Phase 2 uses Inertia web pages plus same-origin JSON POST endpoints called by Inertia v3
`useHttp`. Do not add `routes/api.php`, API resources, or a public order lookup route.

## Route groups

Create `routes/orders.php` and require it from `routes/web.php` after Phase 1's
`routes/offers.php`.

### Public order endpoints

Both routes use standard web CSRF middleware and public ProductOffer binding by
`public_id`. Controllers return explicit JSON arrays, never models.

| Method | Path | Name | Middleware | Request | Controller |
|---|---|---|---|---|---|
| POST | `/offers/{productOffer:public_id}/orders/review` | `offers.orders.review` | `throttle:public-order-previews` | `ReviewOrderRequest` | `PublicOrderReviewController@store` |
| POST | `/offers/{productOffer:public_id}/orders` | `offers.orders.store` | `throttle:public-order-confirmations` | `StoreOrderRequest` | `PublicOrderController@store` |

Named limiter values:

```text
public-order-previews: 30 requests/minute/IP
public-order-confirmations: 10 requests/minute/IP
```

### Operator endpoints

Prefix `/operator/orders`, name prefix `operator.orders.`, middleware `auth`, `verified`,
and `throttle:operator-orders` at 60 requests/minute by user ID plus IP. Every route also
authorizes with `OrderPolicy` or its Form Request.

| Method | Path | Name | Request | Controller |
|---|---|---|---|---|
| GET | `/operator/orders` | `operator.orders.index` | `ListOrdersRequest` | `Operator\OrderController@index` |
| GET | `/operator/orders/{order:public_id}` | `operator.orders.show` | none | `Operator\OrderController@show` |
| POST | `/operator/orders/{order:public_id}/cancel` | `operator.orders.cancellations.store` | `CancelOrderRequest` | `Operator\OrderCancellationController@store` |

Cancellation redirects to `operator.orders.show` with the existing success-toast flash.
Invalid state returns safely to the same page with an order-level conflict error.

## Offer draft slot payload extension

Extend both Phase 1 create/update payloads:

```text
delivery_slots: required array, min 1, max 14
delivery_slots[*][starts_at]: required Casablanca local datetime
delivery_slots[*][ends_at]: required Casablanca local datetime, after starts_at
```

Request normalization converts datetimes to UTC. Every slot must be within the offer's
availability window, and exact `(starts_at, ends_at)` duplicates are invalid. The Phase 1
create/update Actions replace the complete draft slot collection transactionally.
`PublishProductOfferAction` rejects an offer without at least one future slot.

## Public review payload

`ReviewOrderRequest` accepts exactly:

```text
channel: required, b2c or b2b
quantity_kg: required decimal string, > 0, max 2 fractional digits
delivery_slot_public_id: required UUID belonging to route offer
```

No contact or price fields are needed for review. Unknown price, status, snapshot,
service-date, or eligibility fields are ignored by `validated()` and never reach the
Action.

### Review success: HTTP 200

```ts
type OrderReview = {
    channel: 'b2c' | 'b2b';
    crop: string;
    quantityKg: string;              // normalized, exactly two decimals
    deliverySlot: {
        publicId: string;
        startsAt: string;            // ISO-8601 UTC
        endsAt: string;
        serviceDate: string;         // YYYY-MM-DD in Casablanca
    };
    unitPrice: {
        minor: number;
        formatted: string;           // "5.50 MAD/kg"
    };
    total: {
        minor: number;
        formatted: string;           // "27.50 MAD"
    };
};
```

Review does not return remaining quantity, internal IDs, PII, or a reservation promise.

## Public confirmation payload

`StoreOrderRequest` accepts exactly:

```text
submission_token: required UUID supplied by current offer page
channel: required, b2c or b2b
quantity_kg: required decimal string, > 0, max 2 fractional digits
delivery_slot_public_id: required UUID belonging to route offer
delivery_zone: required DeliveryZone value
customer_name: required string, trimmed, max 120
phone: required string, normalized, max 32
email: nullable valid email, max 255

business_name: required_if channel=b2b, prohibited_if channel=b2c, max 160
delivery_address: nullable/prohibited_if channel=b2b, max 500
delivery_note: nullable/prohibited_if channel=b2b, max 500
```

The server ignores or rejects client-supplied price, total, crop, status, service date,
eligibility, actor, or internal-ID fields.

### Confirmation success or idempotent retry: HTTP 200

```ts
type OrderConfirmation = {
    reference: string;               // order public UUID; not a lookup URL
    channel: 'b2c' | 'b2b';
    crop: string;
    quantityKg: string;
    deliveryZone: {
        code: DeliveryZoneCode;
        label: string;
    };
    deliverySlot: {
        startsAt: string;
        endsAt: string;
        serviceDate: string;
    };
    unitPrice: {
        minor: number;
        formatted: string;
    };
    total: {
        minor: number;
        formatted: string;
    };
    currency: 'MAD';
    status: 'confirmed';
    confirmedAt: string;
    maskedPhone: string;              // e.g. "******34"
    eligibleForNextCycle: true;
};
```

This is the complete confirmation allowlist. Do not include name, full phone, email,
business name, address, note, token/hash, internal ID/FK, actor, or Eloquent metadata.

## Failure contract

| Condition | Outcome |
|---|---|
| Request field invalid | HTTP 422 JSON field errors from Form Request |
| Offer missing/private/withdrawn/superseded | HTTP 404 with generic message |
| Offer changed or no longer orderable | HTTP 409 `offer_unavailable` |
| Slot missing, past, or no longer belongs to offer | HTTP 409 `slot_unavailable` |
| Quantity exceeds current remainder | HTTP 409 `quantity_unavailable` |
| Existing token reused with different defining payload | HTTP 409 `submission_mismatch` |
| Public limiter exceeded | HTTP 429 with retry headers and generic message |
| Operator unauthenticated | authentication redirect |
| Operator authenticated without permission | HTTP 403 |
| Cancellation from non-confirmed state | conflict error, no write |

`OrderConflictException` exposes only one of the safe codes above and a fixed user-facing
message. It must not include submitted values, PII, raw tokens, SQL, or model dumps.

## Public offer page prop additions

Extend the Phase 1 `offers/show` props with these fields only when the current offer is
orderable. Historical/superseded offers render their transparency content but no order
form.

```ts
type DeliveryZoneCode =
    | 'casablanca_centre'
    | 'casablanca_east'
    | 'casablanca_west';

type OrderProps = {
    canOrder: boolean;
    deliveryZones: Array<{
        code: DeliveryZoneCode;
        label: string;
    }>;
    deliverySlots: Array<{
        publicId: string;
        startsAt: string;
        endsAt: string;
        serviceDate: string;
        label: string;                // preformatted Casablanca display label
    }>;
    submissionToken: string | null;   // raw value never stored/logged
};
```

Rules:

- `canOrder` is true only for the current public offer with at least one future slot and
  positive derived remaining quantity.
- `deliverySlots` contains at most 14 future slots sorted by start time.
- `submissionToken` is present only when `canOrder` is true.
- Do not expose exact remaining quantity; confirmation remains authoritative.

## Operator index query

`ListOrdersRequest` accepts optional:

```text
channel: b2c or b2b
status: any OrderStatus value
service_date: YYYY-MM-DD
offer: ProductOffer public UUID
delivery_zone: DeliveryZone value
page: positive integer
```

Unknown filters are ignored by `validated()`. Results are newest first, 25 per page,
and query parameters remain on pagination links.

### `operator/orders/index` props

```ts
type OperatorOrderSummary = {
    reference: string;
    channel: 'b2c' | 'b2b';
    crop: string;
    quantityKg: string;
    unitPrice: string;
    total: string;
    deliveryZone: { code: DeliveryZoneCode; label: string };
    serviceDate: string;
    status: OrderStatusValue;
    eligibleForNextCycle: boolean;
    confirmedAt: string;
};

type Props = {
    orders: LaravelPaginator<OperatorOrderSummary>; // 25/page
    filters: {
        channel: string | null;
        status: string | null;
        serviceDate: string | null;
        offer: string | null;
        deliveryZone: string | null;
    };
    filterOptions: {
        channels: Array<{ value: string; label: string }>;
        statuses: Array<{ value: string; label: string }>;
        deliveryZones: Array<{ value: string; label: string }>;
        offers: Array<{ publicId: string; crop: string }>;// latest 50 only
    };
};
```

The index never decrypts or returns names, phone, email, business, address, or notes.

### `operator/orders/show` props

```ts
type Props = {
    order: {
        reference: string;
        channel: 'b2c' | 'b2b';
        status: OrderStatusValue;
        crop: string;
        quantityKg: string;
        currency: 'MAD';
        unitPrice: string;
        total: string;
        deliveryZone: { code: DeliveryZoneCode; label: string };
        deliverySlot: { startsAt: string; endsAt: string; serviceDate: string };
        confirmedAt: string;
        eligibleForNextCycle: boolean;
        contact: {
            customerName: string;
            businessName: string | null;
            phone: string;
            email: string | null;
            deliveryAddress: string | null;
            deliveryNote: string | null;
        };
        transitions: Array<{
            from: OrderStatusValue | null;
            to: OrderStatusValue;
            occurredAt: string;
            actorLabel: 'Guest' | 'Operations staff';
        }>;
    };
    can: { cancel: boolean };
};
```

Do not return actor IDs/names. `actorLabel` is deliberately generalized.

## UI behavior contract

### Public offer order form

- Render only for `canOrder`; otherwise show a concise unavailable state.
- Mobile-first fields: channel toggle, quantity, delivery slot, zone, name, phone,
  optional email, conditional B2B business name, conditional B2C address/note.
- Review button calls Wayfinder review `.url()` through `useHttp`.
- Show validation and conflict errors beside the relevant section.
- After review success, render the exact server crop, quantity, slot, unit price, and
  total in a confirmation Card.
- Disable confirmation until review succeeds. Any field change clears the prior review.
- Confirmation calls Wayfinder store `.url()` through `useHttp` with the same normalized
  fields and submission token.
- Disable both buttons while processing. Do not use optimistic confirmation.
- On success, replace the form with the allowlisted confirmation and tell the buyer to
  save the reference. Do not create a tracking link.

### Operator pages

- Add one `Orders` Wayfinder link to existing app navigation for operations users.
- Index uses responsive filter controls and a horizontally scrollable semantic table on
  narrow screens; include pagination, empty state, and status badges.
- Detail groups price snapshot, delivery, private contact, and transition history into
  existing Cards. Contact data appears only here after authorization.
- Show cancel only when policy allows it and status is confirmed. Cancellation uses a
  confirmation dialog and server error handling; no optimistic status update.
- Reuse existing semantic colors/dark mode and visible focus states. Add no custom CSS.
