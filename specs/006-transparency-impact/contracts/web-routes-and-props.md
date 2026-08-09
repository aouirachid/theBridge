# Web Contract: Transparency Routes and Inertia Props

## Route Table

All frontend navigation/forms use generated Wayfinder controller actions. Do not hardcode
these paths in React.

| Method | Path | Name | Middleware | Controller | Request |
|---|---|---|---|---|---|
| GET | `/transparency/{productOffer}` | `transparency.show` | `web`, public read limiter | `PublicTransparencyController@show` | `ShowPublicTransparencyRequest` |
| GET | `/operator/transparency` | `operator.transparency.index` | `web`, `auth`, `verified` | `Operator\Transparency\TransparencyController@index` | `ShowTransparencyOperationsRequest` |
| POST | `/operator/transparency/verify` | `operator.transparency.verify` | prior + `throttle:transparency-mutations` | `Operator\Transparency\LedgerVerificationController@store` | `VerifyLedgerRequest` |
| POST | `/operator/transparency/indicators` | `operator.transparency.indicators.store` | prior + `throttle:transparency-mutations` | `Operator\Transparency\ImpactIndicatorController@store` | `PublishImpactIndicatorRequest` |

Route binding:

- `{productOffer}` uses the random `ProductOffer.public_id` route key.
- Draft, withdrawn, expired-public-history, missing, and malformed offer references return
  the same safe 404.
- No route binds `LedgerEntry` or exposes entry update/delete methods.

Rate limits:

- public read: 120/minute per IP, safe 429
- operator mutations: 10/minute per authenticated user ID plus IP

## Request Contracts

### ShowPublicTransparencyRequest

No query/body fields. Reject unexpected query fields to keep one unambiguous view.

### ShowTransparencyOperationsRequest

```text
page: optional integer 1..10000
status: optional valid|invalid|incomplete
```

Unexpected fields are rejected. Default order is newest verification first.

### VerifyLedgerRequest

No business fields. Authorization requires operations permission. Chain key and endpoint
are server-derived; the client cannot choose or submit hashes/positions.

### PublishImpactIndicatorRequest

```text
consolidation_cycle: required random public UUID
classification: required estimate|measured
baseline_trip_count: prohibited for estimate; required integer 0..100000 for measured
actual_trip_count: prohibited for estimate; required integer 0..100000 for measured
method: prohibited for estimate; required safe string 10..500 for measured
source_reference: prohibited for estimate; required safe string 3..500 for measured
evidence_reference: prohibited for estimate; required safe string 3..500 for measured
assumptions: required safe string 10..500 for estimate; prohibited for measured
limitation: required safe string 10..500
reporting_starts_on: required date
reporting_ends_on: required date on/after reporting_starts_on
is_demo: required boolean
```

The Action derives `code`, `unit`, estimate counts/method, signed value, publication time,
actor, prior version, and public ID. References are display text/URLs only; the app does
not fetch or upload evidence.

## Public Page Props

Component: `resources/js/pages/transparency/show.tsx`

```text
offer:
  publicId: string
  crop: string
  origin: string
  availableQuantity: string
  publishedAt: string (Casablanca display)
  isDemo: boolean

price:
  currency: "MAD"
  basis: "per kilogram"
  farmerPayment: string
  costs:
    - code: string
      label: string
      amount: string
  platformMargin: string
  finalPrice: string
  farmerShare:
    formatted: string
    numeratorLabel: "Farmer payment"
    denominatorLabel: "Final price"

benchmark: null | {
  price: string
  marketName: string
  sourceType: string
  sourceReference: string
  observedAt: string
  publishedAt: string
  isDemo: boolean
  freshness: "fresh"
  saving: string
  savingPercentage: string
  comparisonKind: "positive" | "zero" | "negative"
}
benchmarkUnavailable: boolean

consolidation: null | {
  cyclePublicId: string
  serviceDate: string
  completedAt: string
  orderCount: number
  consolidatedKilograms: string
  deliveryGroupCount: number
  rejectedKilograms: string
  damagedKilograms: string
  complete: boolean
  groups: [
    {
      publicId: string
      channel: string
      deliveryZone: string
      orderCount: number
      kilograms: string
    }
  ]
}

indicator: null | {
  publicId: string
  code: "trip_reduction"
  label: string
  classification: "estimate" | "measured"
  classificationLabel: "Estimate" | "Measured"
  value: number
  displayValue: string
  unit: "trips"
  baselineTripCount: number
  actualTripCount: number
  method: string
  sourceReference: string | null
  evidenceReference: string | null
  assumptions: string | null
  limitation: string
  reportingPeriod: string
  updatedAt: string
  isDemo: boolean
}

integrity:
  status: "valid" | "invalid" | "incomplete" | "stale" | "not_yet_verified"
  label: string
  checkedEntries: number | null
  verifiedAt: string | null
  explanation: string
```

Public omissions:

- no internal IDs/FKs, actor references, hashes, positions, failure locations, event list,
  user objects, customer/business identity, contact, exact address, delivery note,
  credentials, tokens, provider-private data, raw model attributes, or raw JSON payload
- no benchmark price/saving fields when the current comparison is unavailable/stale
- no numeric environmental field when no supported indicator is published

Page behavior:

- Show price cards first, then ordered breakdown, benchmark, consolidation, impact, and
  integrity explanation.
- Display `Demo data` beside every simulated offer/benchmark/indicator claim.
- Display `Estimate` immediately beside estimated value; do not rely on tooltip/fine print.
- Negative saving/value uses neutral warning language, never green benefit language.
- Use one-column mobile layout, two/three-column responsive grids at larger breakpoints,
  semantic lists/tables, visible focus, existing dark mode, and no chart.
- Empty/stale/incomplete states are explicit Cards/Alerts, not zeros.

## Operator Page Props

Component: `resources/js/pages/operator/transparency/index.tsx`

```text
chain:
  entryCount: number
  endpointShortHash: string | null
  currentIntegrityStatus: string
  currentIntegrityLabel: string

verifications:
  data: [
    {
      publicId: string
      status: "valid" | "invalid" | "incomplete"
      statusLabel: string
      endpointPosition: number
      checkedEntryCount: number
      failure: string | null
      failurePosition: number | null
      startedAt: string
      completedAt: string
      requestedBy: string | null
    }
  ]
  links: standard safe pagination links
  meta: standard pagination metadata

cycles: [
  {
    publicId: string
    label: string
    serviceDate: string
    orderCount: number
    deliveryGroupCount: number
    hasCurrentIndicator: boolean
  }
]

indicatorDefaults:
  classification: "estimate"
  reportingStartsOn: string
  reportingEndsOn: string
  isDemo: boolean

can:
  verify: boolean
  publishIndicator: boolean
```

`requestedBy` is a private staff display label allowed only on this protected page; it is
never included in public props or logs.

Operator behavior:

- `<Form action={store()}>`/Wayfinder submits verification with no hidden hash/position.
- The indicator `<Form>` shows measured-only fields only for `measured` and
  assumptions-only for `estimate`, while the server remains authoritative.
- Disable submit while processing; render `InputError` and `AlertError`; use toast/flash
  only after server-confirmed success.
- Do not optimistically change integrity, indicator, or chain state.

## Redirect and Failure Outcomes

- Successful verification redirects to operator index with a safe status flash.
- Successful indicator publication redirects to operator index with a safe status flash.
- Guest protected request redirects to login; ordinary authenticated user receives 403.
- Invalid fields return normal field errors; stale/missing cycles return safe 404 or 409.
- Current verification already running/conflicting append returns safe 409.
- Mutation throttle returns 429 with retry-later message.
- Public missing/private offer always returns the same 404.
- Exceptions/logs contain only operation, safe route reference, safe verification/event
  reference, position, failure code, and timing.
