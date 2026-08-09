# Web Routes, Payloads, and Inertia Props Contract

This feature uses staff-only Inertia pages and ordinary form redirects. Do not add API
routes, JSON resources, webhooks, provider callbacks, or public dispatch endpoints.

## Route Group

Create `routes/dispatches.php` and require it from `routes/web.php`. Group all routes
under `/operator/dispatches`, name prefix `operator.dispatches.`, and middleware `auth`,
`verified`, and `throttle:dispatch-operations`. The limiter allows 60 protected requests
per minute keyed by authenticated user ID plus IP. Policy/Form Request authorization is
still required independently.

| Method | Path | Name | Request | Controller |
|---|---|---|---|---|
| GET | `/operator/dispatches` | `operator.dispatches.index` | `ListDispatchesRequest` | `Operator\DispatchController@index` |
| POST | `/operator/dispatches/generate` | `operator.dispatches.generate` | `GenerateDispatchesRequest` | `Operator\DispatchGenerationController@store` |
| GET | `/operator/dispatches/{dispatch:public_id}` | `operator.dispatches.show` | none | `Operator\DispatchController@show` |
| POST | `/operator/dispatches/{dispatch:public_id}/submit` | `operator.dispatches.submit` | `SubmitDispatchRequest` | `Operator\DispatchSubmissionController@store` |
| POST | `/operator/dispatches/{dispatch:public_id}/statuses` | `operator.dispatches.statuses.store` | `AdvanceDispatchRequest` | `Operator\DispatchStatusController@store` |

Define `/generate` before the bound `/{dispatch}` route. Successful generation redirects
to index with preserved service date. Other successful mutations redirect to show. Use
the existing `Inertia::flash('toast', ['type' => 'success', 'message' => ...])` shape.

## Shared Types

```ts
type SelectOption = { value: string; label: string };

type DispatchStatus =
    | 'ready'
    | 'submitted'
    | 'accepted'
    | 'picked_up'
    | 'delivered'
    | 'failed';

type DispatchChannel = 'b2b' | 'b2c';

type LaravelPaginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};
```

Every quantity string has exactly two decimals and `kg`. Every money string has exactly
two decimals and `MAD`. Timestamps are ISO-8601 with timezone. React never parses or
calculates money, quantity, status, eligibility, retryability, or permissions.

## Index Request

`ListDispatchesRequest` accepts only optional:

```text
service_date: YYYY-MM-DD
channel: b2b or b2c
delivery_zone: existing DeliveryZone value
status: existing DispatchStatus value
page: positive integer
```

Reject unexpected filters. Default service date is the current Casablanca date. Sort
Dispatches by service date descending, then updated time descending, then ID descending.
Paginate 25 and preserve filters.

## Index Props

Page component: `operator/dispatches/index`

```ts
type DispatchIndexProps = {
    filters: {
        serviceDate: string;
        channel: DispatchChannel | null;
        deliveryZone: string | null;
        status: DispatchStatus | null;
    };
    options: {
        channels: SelectOption[];
        deliveryZones: SelectOption[];
        statuses: SelectOption[];
    };
    summary: {
        total: number;
        ready: number;
        inProgress: number;
        delivered: number;
        failed: number;
        totalQuantityKg: string;
        totalDeliveryCostMad: string;
    };
    generation: {
        eligibleOrderCount: number;
        eligibleQuantityKg: string;
        b2bZoneCount: number;
        b2cOrderCount: number;
        lateB2bOrderCount: number;
        lateB2bQuantityKg: string;
        exceedsLimit: boolean;
        limit: number;
    };
    dispatches: LaravelPaginator<{
        reference: string;
        channel: { code: DispatchChannel; label: string };
        provider: { code: string; label: string; simulated: boolean };
        serviceDate: string;
        deliveryZone: { code: string; label: string };
        status: { code: DispatchStatus; label: string };
        orderCount: number;
        quantityKg: string;
        deliveryCostMad: string | null;
        providerReference: string | null;
        lifecycleVersion: number;
        updatedAt: string;
        canView: boolean;
    }>;
    can: { generate: boolean };
};
```

Index props contain no customer/business/staff names, contacts, addresses, notes,
internal IDs, hashes, operation tokens, attempt payloads, or provider-private values.

## Generate Payload

```text
service_date: required YYYY-MM-DD
```

The client must not submit channel, zone, Order IDs, OrderGroup IDs, quantity, status,
cost, provider, sequence, membership, actor, or limit. The Action derives all eligible
work. Repeated/concurrent generation is safe without a client token because deterministic
scope and membership unique indexes are the correctness boundary.

If more than 500 eligible Orders exist, return a safe `generation_limit_exceeded`
conflict and create/refresh nothing.

## Show Route and Props

`DispatchController@show` uses UUID route binding, policy authorization, and
`ShowDispatchAction`. It accepts no query/business fields. Component:
`operator/dispatches/show`.

```ts
type DispatchShowProps = {
    dispatch: {
        reference: string;
        channel: { code: DispatchChannel; label: string };
        provider: { code: string; label: string; simulated: boolean };
        providerReference: string | null;
        serviceDate: string;
        deliveryZone: { code: string; label: string };
        status: { code: DispatchStatus; label: string };
        failureFromStatus: { code: DispatchStatus; label: string } | null;
        deliveryCostMad: string | null;
        deliveryCostSource: { code: string; label: string } | null;
        orderCount: number;
        quantityKg: string;
        lifecycleVersion: number;
        isFrozen: boolean;
        frozenAt: string | null;
        submittedAt: string | null;
        acceptedAt: string | null;
        pickedUpAt: string | null;
        deliveredAt: string | null;
        failedAt: string | null;
        isTerminal: boolean;
        retryable: boolean;
        postPickupManualFollowUp: boolean;
    };
    orders: LaravelPaginator<{
        reference: string;
        sequence: number;
        crop: string;
        quantityKg: string;
        allocatedDeliveryCostMad: string | null;
        serviceWindow: { startsAt: string; endsAt: string };
        deliveryZone: { code: string; label: string };
        customerName: string;
        businessName: string | null;
        phone: string;
        email: string | null;
        deliveryAddress: string | null;
        deliveryNote: string | null;
        orderStatus: { code: string; label: string };
    }>;
    attempts: LaravelPaginator<{
        number: number;
        outcome: { code: string; label: string };
        providerReference: string | null;
        providerCostMad: string | null;
        failureCategory: string | null;
        initiatedBy: string;
        startedAt: string;
        completedAt: string | null;
    }>;
    transitions: LaravelPaginator<{
        from: { code: DispatchStatus; label: string };
        to: { code: DispatchStatus; label: string };
        source: { code: string; label: string };
        actor: string;
        failureCategory: string | null;
        occurredAt: string;
    }>;
    operationTokens: {
        submit: string | null;
        advance: string | null;
    };
    allowedTargets: SelectOption[];
    can: {
        submit: boolean;
        advance: boolean;
        viewPrivateHandoff: boolean;
    };
};
```

The order contact fields are the only customer PII in this feature's props. They appear
only on this authorized detail page for this Dispatch's Orders. Never include another
Order, raw encrypted value, internal ID, staff email, operation hash/token, request
payload, provider-private value, or model serialization. Actor labels are authorized
accountability labels; public/aggregate responses never include them.

Orders paginate at 50 in stable `sequence,id` order. Attempts and transitions each
paginate at 50 newest-first. Use distinct page query names so paginators do not collide.

## Submit / Retry Payload

```text
operation_token: required UUID
expected_version: required positive integer
flat_cost_mad:
    required decimal string on first B2B submission
    prohibited for B2C
    prohibited after cost is frozen
```

Do not accept provider kind/reference, provider quote, cost source, status, failure,
membership, Order IDs, quantity, actor, timestamp, or personal handoff fields. The
Action derives them.

Behavior:

- Ready B2B: freeze current membership/cost; advance to submitted.
- Ready B2C: transiently call mock; freeze quote; append submitted and accepted.
- Failed before pickup: reuse membership/cost/provider identity; submit same Dispatch.
- Submitted B2C with latest uncertain attempt: reconcile using the same Dispatch and
  provider identity before accepting; never create a new logical provider request.
- Failed after pickup or delivered: terminal safe conflict.
- Same token/same canonical payload: idempotent success redirect.
- Same token/different payload: `operation_mismatch` conflict.

## Advance Payload

```text
operation_token: required UUID
expected_version: required positive integer
target_status: required DispatchStatus present in server-provided allowedTargets
```

Do not accept failure category/note, provider data, Order/group/allocation/receipt IDs,
quantity, stock counters, cost, actor, or event time.

Server-derived allowed targets:

| Channel/current | Targets |
|---|---|
| B2B submitted | accepted, failed |
| B2B accepted | picked_up, failed |
| B2B picked_up | delivered, failed |
| B2C accepted | picked_up, failed |
| B2C picked_up | delivered, failed |
| ready | none; use submit |
| failed before pickup | none; use submit/retry |
| delivered or failed after pickup | none |

For B2C, the Action asks the in-process mock to return the requested simulated outcome;
the operator cannot bypass that provider boundary. Failure category is fixed as
`operator_reported_failure` for B2B or `mock_reported_failure` for B2C. No free-text
failure note exists in the MVP.

## Failure Contract

| Condition | Outcome |
|---|---|
| Invalid field/UUID/date/money/enum/version | HTTP 422 field errors |
| Unauthenticated protected route | Authentication redirect |
| Authenticated without operations permission | HTTP 403 |
| Missing/cross-scoped public reference | HTTP 404 without confirming protected data |
| Stale lifecycle version or invalid state/target | Redirect back with safe `dispatch_state_conflict` |
| Same token with changed payload | Redirect back with safe `operation_mismatch` |
| Same token and payload | Idempotent success redirect; no duplicate effect |
| Generation has 501st eligible Order | Redirect back with `generation_limit_exceeded`; no writes |
| Membership/quantity/cost/stock mismatch | Redirect back with fixed reconciliation conflict; full rollback |
| Ambiguous provider outcome | Keep same Dispatch/attempt/provider identity as submitted; show safe retry guidance |
| Rate limiter exceeded | HTTP 429 with retry guidance |

`DispatchConflictException` contains a fixed allowlisted code and message only. Render it
consistently from the project's existing exception boundary. Never include PII, submitted
values, payloads, raw tokens, hashes, provider-private values, SQL, internal IDs, or
models. Unexpected exceptions remain reportable with safe context only.

## UI Contract

### Index

- Use AppLayout and add one operations-only sidebar item using generated Wayfinder route.
- First row: summary Cards. Second: filter form and generation Card for selected date.
- Generation Card shows eligible B2B zones, B2C Orders, 500 limit, and late B2B count;
  no contacts or buyer identities.
- Confirm generation in Dialog. Disable only its submitted form; show InputError and
  AlertError. Explain that ready manifests refresh and submitted manifests stay frozen.
- Show dispatches in a semantic responsive table inside `overflow-x-auto`, with channel,
  simulated badge, zone, status, count, kilograms, cost, and detail Link.
- Empty states: no eligible work, no dispatches for filters, only late B2B work, and over
  generation limit.

### Show

- Header shows safe reference, channel/provider badge, simulated warning for B2C,
  service date/zone, status, quantity, cost, version, and terminal/manual-follow-up state.
- Show submit/retry Card only when `can.submit`. B2B first submission includes one MAD
  flat-cost input; B2C explains the deterministic 25.00 MAD mock quote. Retry never shows
  a cost input.
- Show one confirmation Dialog for each allowed status action. B2C labels controls
  `Simulate pickup`, `Simulate delivery`, or `Simulate failure`; B2B labels them as staff
  handoff outcomes.
- Stops/orders table contains the authorized minimum PII. Do not copy it into hidden
  fields, DOM data attributes, toast messages, client logs, or analytics.
- Attempts and status history show only safe categories/references and authorized actor
  labels. Empty states: no attempts/history/cost/address/note.
- Use one `<Form>` per mutation, generated Wayfinder `.form()` props, processing-disabled
  controls, server errors, existing focus styles, Tailwind v4 utilities, dark mode, and
  no custom CSS, polling, or optimistic updates.

## Wayfinder Contract

After routes/controllers exist, run:

```text
php artisan wayfinder:generate --with-form --no-interaction
```

Use named imports from generated controller actions/routes. Do not hardcode application
URLs and never edit generated files.
