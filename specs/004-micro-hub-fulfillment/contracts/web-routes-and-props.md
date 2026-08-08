# Web Routes, Payloads, and Inertia Props Contract

This feature uses staff-only Inertia pages and ordinary form redirects. Do not add an API
route, JSON resource layer, or public hub endpoint.

## Route Group

Create `routes/hub.php` and require it from `routes/web.php`. Group all routes under
`/operator/hub`, name prefix `operator.hub.`, middleware `auth`, `verified`, and
`throttle:hub-operations`. The limiter allows 60 requests/minute keyed by authenticated
user ID plus IP. Policies/Form Requests authorize every route independently.

| Method | Path | Name | Request | Controller |
|---|---|---|---|---|
| GET | `/operator/hub` | `operator.hub.index` | `ListHubWorkQueueRequest` | `Operator\HubDashboardController@index` |
| GET | `/operator/hub/procurement-requirements/{procurementRequirement:public_id}/receipts/create` | `operator.hub.receipts.create` | none | `Operator\HubReceiptController@create` |
| POST | `/operator/hub/procurement-requirements/{procurementRequirement:public_id}/receipts` | `operator.hub.receipts.store` | `ReceiveProduceRequest` | `Operator\HubReceiptController@store` |
| GET | `/operator/hub/receipts/{hubReceipt:public_id}` | `operator.hub.receipts.show` | none | `Operator\HubReceiptController@show` |
| POST | `/operator/hub/receipts/{hubReceipt:public_id}/corrections` | `operator.hub.receipt-corrections.store` | `CorrectHubReceiptRequest` | `Operator\HubReceiptCorrectionController@store` |
| POST | `/operator/hub/receipts/{hubReceipt:public_id}/handling-losses` | `operator.hub.handling-losses.store` | `RecordHandlingLossRequest` | `Operator\HubHandlingLossController@store` |
| POST | `/operator/hub/receipts/{hubReceipt:public_id}/allocations` | `operator.hub.allocations.store` | `AllocateStockRequest` | `Operator\HubAllocationController@store` |
| POST | `/operator/hub/allocations/{stockAllocation:public_id}/release` | `operator.hub.allocation-releases.store` | `ReleaseStockAllocationRequest` | `Operator\HubAllocationReleaseController@store` |
| POST | `/operator/hub/allocations/{stockAllocation:public_id}/prepare` | `operator.hub.preparations.store` | `PrepareStockAllocationRequest` | `Operator\HubPreparationController@store` |

Use POST for release and prepare because each appends an audited transition; neither
deletes or generically updates a resource. Every successful mutation redirects to the
receipt show route and sets the existing
`Inertia::flash('toast', ['type' => 'success', 'message' => ...])` shape.

## Shared Types

```ts
type StockQuantities = {
    receivedKg: string;
    acceptedKg: string;
    qualityRejectedKg: string;
    procurementOverageKg: string;
    rejectedKg: string;
    availableKg: string;
    allocatedKg: string;
    damagedKg: string;
    dispatchedKg: string;
};

type Turnaround = {
    isOverdue: boolean;
    heldHours: number;
    undispatchedKg: string;
    label: string;
};

type SelectOption = { value: string; label: string };
```

All quantity strings contain exactly two decimal places. All timestamp strings are
ISO-8601 with timezone. Labels are server-provided; React does not derive quantities,
durations, compatibility, states, or permissions.

## Work Queue Request

`ListHubWorkQueueRequest` accepts optional:

```text
product: ProductOffer public UUID
receipt_state: active or depleted
allocation_state: unallocated, allocated, or ready_for_dispatch
service_date: YYYY-MM-DD
overdue: 0 or 1
page: positive integer
```

Unknown filters never reach the Action. Receipt rows are newest first and 25/page with
query parameters preserved. Outstanding requirements are oldest service date first and
capped at 50.

### `operator/hub/index` Props

```ts
type HubIndexProps = {
    totals: {
        receivedKg: string;
        rejectedKg: string;
        availableKg: string;
        allocatedKg: string;
        damagedKg: string;
        dispatchedKg: string;
        overdueReceiptCount: number;
    };
    filters: {
        product: string | null;
        receiptState: 'active' | 'depleted' | null;
        allocationState: 'unallocated' | 'allocated' | 'ready_for_dispatch' | null;
        serviceDate: string | null;
        overdue: boolean | null;
    };
    filterOptions: {
        products: Array<{ reference: string; label: string }>;
        receiptStates: SelectOption[];
        allocationStates: SelectOption[];
    };
    outstandingRequirements: Array<{
        reference: string;
        product: string;
        serviceScope: string;
        serviceDate: string;
        requiredQuantityKg: string;
    }>;
    receipts: LaravelPaginator<{
        reference: string;
        procurementReference: string;
        product: string;
        serviceScope: string;
        qualityGrade: string | null;
        receivedAt: string;
        quantities: StockQuantities;
        turnaround: Turnaround;
    }>;
    can: { receive: boolean };
};
```

Return references, not prebuilt application URLs. React uses Wayfinder controller/route
functions with those references.

## Receive Page

### `operator/hub/receipts/create` Props

```ts
type ReceiveHubReceiptProps = {
    requirement: {
        reference: string;
        product: string;
        serviceScope: string;
        serviceDate: string;
        requiredQuantityKg: string;
    };
    qualityGrades: SelectOption[];
    rejectionReasons: SelectOption[]; // excludes procurement_overage
    operationToken: string;
};
```

### Receive Payload

```text
operation_token: required UUID
received_at: required Casablanca-local datetime, not future
received_quantity_kg: required positive decimal string, max 2 fractional digits
accepted_quantity_kg: required non-negative decimal string, max 2 fractional digits
quality_rejected_quantity_kg: required non-negative decimal string, max 2 fractional digits
quality_grade: required when accepted > 0, HubQualityGrade value
rejection_reason: required when quality rejected > 0, selectable ReceiptRejectionReason
rejection_note: nullable string, trimmed, max 500
```

The client MUST NOT submit total rejected, overage rejected, available, allocated,
damaged, dispatched, requirement quantity, status, actor, or internal IDs. The server
derives overage and total rejected and verifies the exact balance.

## Receipt Workspace

### `operator/hub/receipts/show` Props

```ts
type HubReceiptShowProps = {
    receipt: {
        reference: string;
        procurementReference: string;
        product: string;
        serviceScope: string;
        serviceDate: string;
        qualityGrade: string | null;
        rejectionReason: string | null;
        rejectionNote: string | null;
        receivedAt: string;
        quantities: StockQuantities;
        turnaround: Turnaround;
        can: {
            correct: boolean;
            recordDamage: boolean;
            allocate: boolean;
        };
    };
    corrections: Array<{
        previous: Pick<StockQuantities, 'receivedKg' | 'acceptedKg' | 'qualityRejectedKg' | 'procurementOverageKg' | 'rejectedKg'>;
        resulting: Pick<StockQuantities, 'receivedKg' | 'acceptedKg' | 'qualityRejectedKg' | 'procurementOverageKg' | 'rejectedKg'>;
        reason: string;
        note: string | null;
        occurredAt: string;
        actorName: string;
    }>;
    handlingLosses: Array<{
        quantityKg: string;
        reason: string;
        note: string | null;
        occurredAt: string;
        recordedAt: string;
        actorName: string;
    }>;
    compatibleGroups: Array<{
        reference: string;
        channel: 'b2b' | 'b2c';
        deliveryZone: string;
        serviceDate: string;
        requiredQuantityKg: string;
    }>;
    allocations: Array<{
        reference: string;
        groupReference: string;
        channel: 'b2b' | 'b2c';
        deliveryZone: string;
        serviceDate: string;
        requiredQuantityKg: string;
        allocatedQuantityKg: string;
        preparedQuantityKg: string | null;
        state: 'allocated' | 'ready_for_dispatch' | 'released';
        allocatedAt: string;
        preparedAt: string | null;
        releasedAt: string | null;
        canRelease: boolean;
        canPrepare: boolean;
        releaseToken: string | null;
        preparationToken: string | null;
    }>;
    options: {
        qualityGrades: SelectOption[];
        rejectionReasons: SelectOption[];
        correctionReasons: SelectOption[];
        handlingLossReasons: SelectOption[];
        allocationReleaseReasons: SelectOption[];
    };
    operationTokens: {
        correction: string | null;
        damage: string | null;
        allocation: string | null;
    };
};
```

Corrections, losses, compatible groups, and allocations are each bounded to 50 rows for
the MVP. Prepared allocation rows already contain the fulfillment summary; do not create
a separate page or table.

## Mutation Payloads

### Correct Receipt

```text
operation_token: required UUID
received_quantity_kg: required positive decimal string
accepted_quantity_kg: required non-negative decimal string
quality_rejected_quantity_kg: required non-negative decimal string
rejection_reason: required when quality rejected > 0
rejection_note: nullable string, max 500
correction_reason: required ReceiptCorrectionReason
correction_note: nullable string, max 500
```

Do not accept a new requirement, receipt time, quality grade, actor, stock bucket, or
downstream state.

### Record Handling Loss

```text
operation_token: required UUID
quantity_kg: required positive decimal string, max 2 fractional digits
reason: required HandlingLossReason
occurred_at: required Casablanca-local datetime, between receipt time and now
note: nullable string, max 500
```

### Allocate Order Group

```text
operation_token: required UUID
order_group_reference: required UUID
```

The server derives exact allocation quantity from the group and requires the group to
belong to the receipt's procurement requirement. Never accept quantity, grade, product,
zone, service date, status, or order IDs.

### Release Allocation

```text
operation_token: required UUID
reason: required AllocationReleaseReason
note: nullable string, max 500
```

### Prepare Allocation

```text
operation_token: required UUID
prepared_quantity_kg: required positive decimal string, max 2 fractional digits
```

Prepared quantity must equal allocation quantity. Preparation leaves stock and Orders
allocated.

## Failure Contract

| Condition | Outcome |
|---|---|
| Invalid field/precision/enum/time | HTTP 422 field errors from Form Request |
| Unauthenticated staff route | Authentication redirect |
| Authenticated without ability | HTTP 403 |
| Missing safe public reference | HTTP 404 without disclosing unrelated records |
| Stale receipt/group/allocation version | Redirect back with safe `operation` conflict error |
| Insufficient stock/incompatible group/invalid lifecycle | Redirect back with safe `operation` conflict error |
| Same operation token with changed payload | Redirect back with `operation_mismatch` error |
| Same operation token and same payload | Idempotent success redirect; no duplicate row/transition |
| Rate limiter exceeded | HTTP 429 with retry guidance |

`HubConflictException` contains a fixed safe code and message only. Never include raw
values, payloads, tokens, notes, PII, SQL, internal IDs, or model dumps.

## UI Contract

### Work Queue

- Add one `Hub fulfillment` sidebar link visible only when shared authorization props
  permit hub access.
- Show six quantity summary Cards plus overdue count.
- Show a bounded outstanding-requirements section and a filtered/paginated receipt table.
- Use a destructive Badge only for overdue; show neutral remaining-time text below 24h.
- Render clear empty states for no requirements and no matching receipts.

### Receive Form

- Show immutable procurement/product/scope/date/required quantity above the form.
- Use one `<Form {...HubReceiptController.store.form(...)}` with field errors and a
  processing-disabled submit button.
- Do not calculate overage or accepted balance in React. Explain that the server will
  reject an unbalanced receipt and will classify overage automatically.

### Receipt Workspace

- Show a stock reconciliation Card first, then correction/loss forms, compatible groups,
  allocations, and audit histories.
- Hide mutation forms using server `can` props; policies remain authoritative.
- Use a separate `<Form>` per mutation. Disable only the submitted form.
- Show `InputError` beside fields and `AlertError` for operation conflicts.
- Empty states: no corrections, losses, compatible groups, or allocations.
- Prepared rows say `Ready for dispatch` but remain allocated. Never show a Dispatch
  button or optimistic bucket change.
- Use responsive `overflow-x-auto` semantic tables, existing focus/dark-mode primitives,
  and no custom CSS.
