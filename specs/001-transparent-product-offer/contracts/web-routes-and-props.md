# Web Routes and Inertia Props Contract

This feature exposes Inertia web routes only. Do not add a JSON API.

## Route groups

Create `routes/offers.php` and require it from `routes/web.php`.

### Public

Apply named middleware `throttle:public-offers`.

| Method | Path | Name | Controller | Result |
|---|---|---|---|---|
| GET | `/offers/{productOffer:public_id}` | `offers.show` | `PublicProductOfferController@show` | Inertia `offers/show` or 404 |

The controller passes the bound public ID to `ShowPublicProductOfferAction`. The Action
must independently enforce visibility and return not-found for drafts, withdrawals, and
superseded offers older than 30 days.

### Operator

Prefix `/operator/offers`, name prefix `operator.offers.`, middleware `auth`, `verified`,
and `throttle:operator-offers`. Every endpoint also authorizes through
`ProductOfferPolicy` or its Form Request.

| Method | Path | Name | Request | Controller method |
|---|---|---|---|---|
| GET | `/operator/offers` | `operator.offers.index` | none | `ProductOfferController@index` |
| GET | `/operator/offers/create` | `operator.offers.create` | none | `ProductOfferController@create` |
| POST | `/operator/offers` | `operator.offers.store` | `StoreProductOfferRequest` | `ProductOfferController@store` |
| GET | `/operator/offers/{productOffer}/edit` | `operator.offers.edit` | none | `ProductOfferController@edit` |
| PATCH | `/operator/offers/{productOffer}` | `operator.offers.update` | `UpdateProductOfferRequest` | `ProductOfferController@update` |
| POST | `/operator/offers/{productOffer}/replacement` | `operator.offers.replacements.store` | `CreateProductOfferReplacementRequest` | `ProductOfferReplacementController@store` |
| POST | `/operator/offers/{productOffer}/publish` | `operator.offers.publications.store` | `PublishProductOfferRequest` | `ProductOfferPublicationController@store` |
| POST | `/operator/offers/{productOffer}/withdraw` | `operator.offers.withdrawals.store` | `WithdrawProductOfferRequest` | `ProductOfferWithdrawalController@store` |
| POST | `/operator/offers/{productOffer}/benchmarks` | `operator.offers.benchmarks.store` | `StoreBenchmarkComparisonRequest` | `BenchmarkComparisonController@store` |
| POST | `/operator/offers/{productOffer}/benchmarks/{benchmarkComparison}/publish` | `operator.offers.benchmarks.publications.store` | `PublishBenchmarkComparisonRequest` | `BenchmarkComparisonPublicationController@store` |

Use scoped bindings for the nested benchmark route. Do not accept a comparison belonging
to another offer.

Redirects:

- create/update/record/publish benchmark/publish offer -> `operator.offers.edit`
- replacement -> edit the new replacement draft
- withdrawal -> `operator.offers.index`
- all successes set the existing Inertia success toast format

## Mutation payloads

### Create/update product offer

```text
crop: string, max 120
origin: string, max 255
available_quantity_kg: decimal string, > 0, max 2 fractional digits
availability_starts_at: valid local datetime
availability_ends_at: valid local datetime, after start
farmer_payment_per_kg: non-negative decimal string, max 2 fractional digits
platform_margin_per_kg: positive decimal string, max 2 fractional digits
standard_costs[collection]: non-negative decimal string
standard_costs[quality_control]: non-negative decimal string
standard_costs[hub_handling_storage]: non-negative decimal string
standard_costs[delivery_allocation]: non-negative decimal string
custom_costs: array, max 10
custom_costs[*][name]: required string, max 120, normalized unique, not a standard label/code
custom_costs[*][amount_per_kg]: non-negative decimal string, max 2 fractional digits
```

Use Form input names matching the nested keys. The Request normalizes whitespace and
local Casablanca datetimes; the Action converts validated monetary strings to centimes.

### Record benchmark

```text
benchmark_price_per_kg: positive decimal string, max 2 fractional digits
market_name: string, max 160
source_type: one of url, document, field_observation
source_reference: string, max 500; valid URL when source_type=url
observed_at: valid datetime, not future
is_demo: required boolean
```

### Publish offer

```text
benchmark_comparison_id: required integer; recorded comparison belonging to this draft
```

The comparison must be fresh at the Action's transaction time. Other publication,
replacement, and withdrawal Requests have no editable body fields.

## Operator Inertia props

### `operator/offers/index`

```ts
type OperatorOfferSummary = {
    id: number;
    publicId: string;
    crop: string;
    origin: string;
    status: 'draft' | 'published' | 'superseded' | 'withdrawn';
    finalPrice: string;
    publishedAt: string | null;
    canEdit: boolean;
    canPublish: boolean;
    canReplace: boolean;
    publicUrlAvailable: boolean;
};

type Props = { offers: OperatorOfferSummary[] }; // latest 50 only
```

### `operator/offers/manage`

One page handles create and edit through `mode`.

```ts
type Props = {
    mode: 'create' | 'edit';
    offer: OperatorOfferEditor | null;
    benchmarkComparisons: OperatorBenchmarkComparison[]; // newest 30 retained rows
    standardCostLabels: Record<StandardCostCode, string>;
    can: {
        update: boolean;
        publish: boolean;
        replace: boolean;
        withdraw: boolean;
        recordBenchmark: boolean;
    };
};
```

The editor includes the exact saved inputs plus server-calculated `finalPrice` and
`farmerSharePercentage`. Published records render read-only. Never compute preview values
in React.

## Public `offers/show` props

This is the complete allowlist. Do not add actor data or internal IDs.

```ts
type PublicMoney = {
    minor: number;
    formatted: string; // e.g. "5.50 MAD/kg"
};

type PublicCost = {
    code: string | null;
    name: string;
    amount: PublicMoney;
};

type PublicComparison = {
    marketName: string;
    sourceType: 'url' | 'document' | 'field_observation';
    sourceReference: string;
    observedAt: string; // ISO-8601 with Casablanca display handled by page
    isDemo: boolean;
    benchmarkPrice: PublicMoney;
    saving: PublicMoney; // may be negative
    savingPercentage: string; // signed, exactly two decimals plus "%"
    publishedAt: string;
    supersededAt: string | null;
    isSuperseded: boolean;
};

type Props = {
    offer: {
        publicId: string;
        crop: string;
        origin: string;
        availableQuantityKg: string;
        availabilityStartsAt: string;
        availabilityEndsAt: string;
        farmerPayment: PublicMoney;
        costs: PublicCost[];
        platformMargin: PublicMoney;
        finalPrice: PublicMoney;
        farmerSharePercentage: string;
        publishedAt: string;
        supersededAt: string | null;
        isSuperseded: boolean;
        replacesPublicId: string | null;
        replacementPublicId: string | null;
    };
    currentComparison: PublicComparison | null;
    freshComparisonUnavailable: boolean;
    comparisonHistory: PublicComparison[]; // superseded in last 30 days, max 30
};
```

Public privacy assertions must prove these keys are absent everywhere:

```text
id
created_by_user_id
recorded_by_user_id
published_by_user_id
creator
recorder
publisher
user
email
phone
exact_address
normalized_name
```

## UI behavior contract

- Operator pages use existing AppLayout, Heading, Button, Card, Input, Label, Select,
  InputError, Badge, and toast patterns.
- Add one Wayfinder link named “Product offers” to the existing app navigation.
- The manage page disables publication until a saved draft has four standard costs and a
  fresh recorded benchmark; server validation remains authoritative.
- The public page uses semantic HTML, visible units, source/time/demo labels, a `<dl>` or
  accessible table for costs, and responsive single-column/mobile presentation.
- If `currentComparison` is null, show “Fresh benchmark unavailable” and render no
  benchmark price, source, saving, or saving percentage.
- A negative saving is labeled “Difference above benchmark,” never “saving” or “discount.”
- Historical comparisons and superseded offers show a prominent “Superseded” badge and
  are never presented as current market claims.
- Public pages must render from server props only and contain no client-side pricing math.
