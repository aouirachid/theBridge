# Web Routes, Command, Payloads, and Inertia Props Contract

Phase 003 uses authenticated Inertia web routes plus one Artisan command. Do not add an
API route, queue endpoint, public cycle page, or customer lookup.

## Route file and middleware

Create `routes/consolidations.php` and require it from `routes/web.php` after Phase 002's
`routes/orders.php`.

All routes use prefix `/operator/consolidations`, name prefix
`operator.consolidations.`, middleware `auth`, `verified`, and policy/Form Request
authorization. Apply `throttle:operator-consolidations` at 60 requests/minute by user ID
plus IP to the group. Apply the stricter `throttle:consolidation-mutations` at 30
requests/minute by user ID plus IP to PUT/POST routes.

All listed routes require operations-operator permission. The cutoff update route
additionally requires operations-manager permission. The index Action returns cutoff
configuration only to managers.

| Method | Path | Name | Request | Controller |
|---|---|---|---|---|
| GET | `/operator/consolidations` | `operator.consolidations.index` | `ListConsolidationCyclesRequest` | `Operator\ConsolidationCycleController@index` |
| POST | `/operator/consolidations` | `operator.consolidations.store` | `StartManualConsolidationRequest` | `Operator\ManualConsolidationController@store` |
| PUT | `/operator/consolidations/cutoff` | `operator.consolidations.cutoff.update` | `UpdateConsolidationCutoffRequest` | `Operator\ConsolidationCutoffController@update` |
| GET | `/operator/consolidations/{consolidationCycle:public_id}` | `operator.consolidations.show` | `ShowConsolidationCycleRequest` | `Operator\ConsolidationCycleController@show` |
| POST | `/operator/consolidations/{consolidationCycle:public_id}/runs` | `operator.consolidations.runs.store` | `RetryConsolidationCycleRequest` | `Operator\ConsolidationCycleRunController@store` |
| POST | `/operator/consolidations/{consolidationCycle:public_id}/candidates/{consolidationCandidate:public_id}/decision` | `operator.consolidations.candidates.decisions.store` | `DecideUnderMinimumCandidateRequest` | `Operator\UnderMinimumDecisionController@store` |

Use `scopeBindings()` for the nested candidate route. Missing, unrelated, or private
UUIDs return the same safe not-found response.

Mutation success redirects to the cycle show page (cutoff update redirects to index)
with the existing Inertia success-toast flash. Conflict redirects back with one safe
operation-level error; validation uses ordinary field errors. Controllers do nothing
else.

## Request strictness

Each Request accepts only the fields documented below. In `after()`, compare submitted
business keys with the documented allowlist (ignoring framework `_token` / `_method`)
and add a generic request error for any extra key. Controllers pass only `validated()`
or `safe()->only(...)` to Actions.

## Index filters

`ListConsolidationCyclesRequest` accepts optional:

```text
status: any ConsolidationCycleStatus value
trigger: automatic or manual
operating_date: YYYY-MM-DD
service_date: YYYY-MM-DD
product_offer: ProductOffer public UUID represented by a candidate/requirement
channel: any OrderChannel value
delivery_zone: any DeliveryZone value
page: positive integer
```

Product/channel/zone filters use `whereHas`/`whereExists` against candidates or completed
outputs and must not duplicate cycle rows. Cycles sort by service date descending then ID
descending, 25/page, retaining filters.

### `operator/consolidations/index` props

```ts
type CycleStatus =
    | 'open'
    | 'processing'
    | 'awaiting_decision'
    | 'completed'
    | 'failed';

type CycleSummary = {
    reference: string;
    serviceDate: string;
    status: CycleStatus;
    trigger: 'automatic' | 'manual' | null;
    effectiveCutoff: string | null;       // ISO-8601 with Casablanca display label
    sourceOrderCount: number;
    sourceQuantityKg: string;
    includedOrderCount: number;
    includedQuantityKg: string;
    excludedOrderCount: number;
    excludedQuantityKg: string;
    procurementRequirementCount: number;
    deliveryGroupCount: number;
    completedAt: string | null;
    safeFailureLabel: string | null;
};

type IndexProps = {
    cycles: LaravelPaginator<CycleSummary>; // 25/page
    filters: {
        status: string | null;
        trigger: string | null;
        operatingDate: string | null;
        serviceDate: string | null;
        productOffer: string | null;
        channel: string | null;
        deliveryZone: string | null;
    };
    filterOptions: {
        statuses: Array<{ value: CycleStatus; label: string }>;
        triggers: Array<{ value: 'automatic' | 'manual'; label: string }>;
        products: Array<{ value: string; label: string }>;// public UUID and safe crop
        channels: Array<{ value: 'b2c' | 'b2b'; label: string }>;
        deliveryZones: Array<{ value: string; label: string }>;
    };
    cutoff: {
        currentTime: string;              // HH:MM
        currentEffectiveOn: string | null;
        pendingTime: string | null;
        pendingEffectiveOn: string | null;
        timezone: 'Africa/Casablanca';
        nextAutomaticServiceDate: string;
        nextAutomaticCutoffAt: string;
    } | null;                              // null for non-manager operators
    manualRun: {
        availableServiceDates: Array<{ value: string; label: string }>;// max 14
    };
    can: {
        run: boolean;
        updateCutoff: boolean;
    };
};
```

Index props contain no customer data, internal IDs, hashes, raw exception messages, or
staff identity.

## Manual start payload

`StartManualConsolidationRequest` accepts exactly:

```text
service_date: required YYYY-MM-DD from available open service dates
confirm: required accepted boolean
```

The client does not submit cutoff, trigger, order IDs, products, quantities, totals,
minimums, status, or actor. The Action resolves authoritative current time and scheduled
cutoff. If now is before the scheduled cutoff, this is an early closure at now. If now
is at/after it, the cycle preserves the scheduled cutoff and excludes later orders.

## Cutoff update payload

`UpdateConsolidationCutoffRequest` accepts exactly:

```text
cutoff_time: required 24-hour HH:MM
confirm: required accepted boolean
```

Effective date is server-derived as the next Casablanca operating date. Submitting the
same latest pending value is idempotent. The client cannot submit effective date, actor,
previous value, timezone, or cycle fields.

## Cycle show query

`ShowConsolidationCycleRequest` accepts optional:

```text
candidate_page: positive integer
group_page: positive integer
group: OrderGroup public UUID belonging to cycle
order_page: positive integer; used only with group
```

### `operator/consolidations/show` props

```ts
type CandidateSummary = {
    reference: string;
    crop: string;
    channel: 'b2c' | 'b2b';
    deliveryZone: { code: string; label: string };
    quantityKg: string;
    orderCount: number;
    commercialTotal: string;             // formatted MAD
    estimatedDeliveryAllocation: string; // formatted MAD/kg, labelled estimate
    minimumQuantityKg: string;
    underMinimum: boolean;
    decision: 'approved' | 'excluded' | null;
    decisionLabel: string | null;
    decidedAt: string | null;
    canDecide: boolean;
};

type RequirementSummary = {
    reference: string;
    crop: string;
    serviceDate: string;
    requiredQuantityKg: string;
    orderCount: number;
    commercialTotal: string;
    estimatedDeliveryAllocation: string;
    status: 'outstanding' | 'received';
};

type GroupSummary = {
    reference: string;
    requirementReference: string;
    crop: string;
    channel: 'b2c' | 'b2b';
    deliveryZone: { code: string; label: string };
    serviceDate: string;
    quantityKg: string;
    orderCount: number;
    commercialTotal: string;
    estimatedDeliveryAllocation: string;
};

type SourceOrderSummary = {
    reference: string;
    crop: string;
    channel: 'b2c' | 'b2b';
    deliveryZone: { code: string; label: string };
    serviceDate: string;
    quantityKg: string;
    currency: 'MAD';
    unitPrice: string;
    total: string;
    confirmedAt: string;
    status: string;
    orderUrl: string; // Wayfinder-generated server URL for authorized order detail
};

type ShowProps = {
    cycle: CycleSummary & {
        cutoffTimezone: 'Africa/Casablanca' | null;
        scheduledCutoff: string | null;
        startedAt: string | null;
        variance: {
            orderCount: number;
            quantityKg: string;
            commercialTotal: string;
            reconciled: boolean;
        };
    };
    candidates: LaravelPaginator<CandidateSummary>; // current generation, 50/page
    requirements: Array<RequirementSummary>;        // max 50; one/product
    groups: LaravelPaginator<GroupSummary>;         // 50/page
    selectedGroup: (GroupSummary & {
        orders: LaravelPaginator<SourceOrderSummary>; // 50/page
    }) | null;
    excluded: {
        orderCount: number;
        quantityKg: string;
        candidateCount: number;
        reasonCounts: {
            confirmedAfterCutoff: number;
            notConfirmed: number;
            alreadyGrouped: number;
            operatorExcludedBelowMinimum: number;
        };
        followUpMessage: string | null;
    };
    can: {
        run: boolean;
        decide: boolean;
    };
};
```

Never include names, phone, email, business name, address, note, raw actor fields,
internal IDs/FKs, fingerprints, cache keys, SQL, or Eloquent metadata. Actor display is
only `Operations staff` where accountability must be visible.

## Retry / continue payload

`RetryConsolidationCycleRequest` accepts exactly:

```text
confirm: required accepted boolean
```

Allowed when cycle is awaiting decision with no unresolved candidate, or failed and
recoverable. Completed returns its existing result. Open cycles use the manual-start
route so service date/early closure stays explicit. Processing or irrecoverable state is
a conflict.

## Candidate decision payload

`DecideUnderMinimumCandidateRequest` accepts exactly:

```text
decision: required approved or excluded
confirm: required accepted boolean
```

The nested candidate supplies the server-side product/channel/zone, generation,
fingerprint, count, quantity, and minimum. The candidate must be current, under minimum,
undecided, and belong to an awaiting-decision cycle. Same-decision replay succeeds
without another mutation; a different decision or stale generation conflicts. Exclusion
stores the fixed safe reason `operator_excluded_below_minimum`.

## Artisan command and schedule

Generate one command:

```text
php artisan orders:consolidate
```

It accepts no business arguments and returns success for not-due/already-complete no-op,
awaiting-decision, or successful completion. It returns failure only for a safe failed
attempt and prints the safe cycle reference/category without payloads or PII.

Register in `routes/console.php`:

```text
orders:consolidate
every minute
named midnight-order-consolidation
without overlapping for 10 minutes
on one server
```

The command invokes only `StartScheduledConsolidationAction`; it contains no date,
grouping, query, transaction, or retry business logic.

## Failure contract

| Condition | HTTP/command outcome | Data outcome |
|---|---|---|
| Guest | authentication redirect | no change |
| Unverified/ordinary user | forbidden | no change |
| Invalid/extra input | field/request validation error | no change |
| Missing/unrelated public reference | not found | no private disclosure |
| Ordering window already closed during new order confirmation | conflict `ordering_window_closed` | no Order created |
| Cycle processing/lock busy | conflict `cycle_busy` / later retry | no duplicate output |
| Candidate stale | conflict `candidate_stale`; refresh required | old decision does not carry forward |
| Candidate different-decision replay | conflict `decision_conflict` | first decision remains |
| Under-minimum decisions unresolved | safe awaiting state | no completed outputs/order transitions |
| More than 500 eligible Orders | safe `cycle_too_large` failure | no partial output |
| Conditional Order claim mismatch | safe `source_orders_changed` failure/re-preview | final transaction rolls back |
| Final invariant mismatch | safe `reconciliation_failed` failure | final transaction rolls back |
| Upstream Order lacks a required grouping dimension | safe `invalid_source_order` failure | no fallback grouping or partial output |
| Mutation limiter exceeded | HTTP 429 with retry headers | no change |

`ConsolidationConflictException` exposes only fixed safe codes/messages and is rendered
consistently with existing Inertia conflict behavior. It must never include submitted
values, PII, fingerprints, raw cache tokens, SQL, internal IDs, or model dumps.

## UI behavior

### Index page

- Use AppLayout and one policy-aware Consolidations sidebar Wayfinder Link.
- Show current/pending cutoff and timezone in one Card. Manager-only update uses native
  `type=time`, explicit confirmation Dialog, `<Form {...Controller.update.form()}>`,
  processing disablement, InputError, and flash toast.
- Show manual run in one Card with a server-provided service-date Select and explicit
  warning that early execution closes that date. Use no caller cutoff field.
- Show filter controls and 25-row responsive semantic cycle table with status Badges,
  empty state, and Wayfinder pagination Links.
- No polling, optimistic status, client date math, client totals, or custom CSS.

### Show page

- Show effective/scheduled cutoff, source/included/excluded totals, status, and variance
  in Cards before any actions.
- Awaiting-decision candidates use existing Dialog plus approve/exclude Forms. Exclude
  warning states Orders remain confirmed for follow-up. Disable decisions while any form
  processes; refresh on conflict.
- Show requirements and groups in responsive semantic tables. Selecting a group reloads
  the same show page with `group` query and displays its 50/page source-order table.
- Link source references to the existing authorized Order detail using Wayfinder URLs.
- Render clear loading/processing, empty, failed, awaiting, completed, and unauthorized
  outcomes; preserve visible focus and existing dark-mode semantic tokens.

Generated imports use named Wayfinder controller-action or route imports. Never hardcode
application URLs and never hand-edit `resources/js/actions` or `resources/js/routes`.
