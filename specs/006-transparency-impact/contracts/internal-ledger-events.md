# Internal Contract: Ledger Events

## Purpose

This is the exhaustive boundary between Phase 001-005 source Actions and
`AppendLedgerEntryAction`. An implementation agent must not infer payloads from models.
Each source Action calls the matching named `LedgerEventData` constructor with the exact
keys below while its existing transaction is still open.

General rules:

- Keys appear exactly as listed. Constructors reject missing or extra keys.
- Values are integers, booleans, nulls, safe strings, or ordered lists of these values.
- UUID values are random upstream public IDs, never database IDs.
- Times are UTC `YYYY-MM-DDTHH:MM:SSZ` strings.
- Ordered component/group/allocation lists use the stated order before hashing.
- `actor` is passed separately as `?User`; it never appears in payload JSON.
- The idempotency input is private, hashed immediately, and never logged or returned.
- The same idempotency input with different content throws `event_mismatch`.

## 1. OfferPublished

Source: `PublishProductOfferAction`, after price snapshot and costs are final.

```text
idempotency: offer_published|{product_offers.id}|{published_at}
subject_type: product_offer
subject_public_id: product_offers.public_id
occurred_at: product_offers.published_at
payload:
  crop: string
  origin: generalized public-safe string
  available_quantity_hundredths: integer converted exactly from upstream quantity
  farmer_payment_minor: integer
  cost_components: ordered by position then id
    - code: standard_code or "custom"
      name: public label
      amount_minor: integer
      position: integer
  platform_margin_minor: integer
  final_price_minor: integer
  farmer_share_bps: integer
  replaces_offer_public_id: UUID|null
  is_demo: boolean
```

If the initial benchmark publishes in the same Action, append OfferPublished first and
BenchmarkPublished second before commit.

## 2. BenchmarkPublished

Sources: `PublishProductOfferAction` for the initial comparison or
`PublishBenchmarkComparisonAction` for a refresh.

```text
idempotency: benchmark_published|{benchmark_comparisons.id}|{published_at}
subject_type: product_offer
subject_public_id: parent product offer public_id
occurred_at: benchmark_comparisons.published_at
payload:
  offer_public_id: UUID
  benchmark_price_minor: integer
  market_name: safe string
  source_type: url|document|field_observation
  source_reference: reviewed public-safe string
  observed_at: UTC string
  published_at: UTC string
  is_demo: boolean
  saving_minor: signed integer
  saving_percentage_bps: signed integer
```

The unique ledger event `public_id` identifies this publication event. The private
idempotency hash distinguishes comparison rows without exposing an internal ID.

## 3. OrderConfirmed

Source: `CreateOrderAction`, after confirmation and transitions exist.

```text
idempotency: order_confirmed|{orders.id}|{confirmed_at}
subject_type: order
subject_public_id: orders.public_id
occurred_at: orders.confirmed_at
payload:
  offer_public_id: offer_public_id_snapshot
  crop: crop_snapshot
  channel: b2c|b2b
  quantity_hundredths: integer
  currency: MAD
  unit_price_minor: integer
  total_minor: integer
  service_date: YYYY-MM-DD
  delivery_zone: safe enum value
```

Explicitly forbidden: customer/business/contact names, phone, email, exact address,
delivery note, slot internal ID, submission token/hash.

## 4. OrderCancelled

Source: `CancelOrderAction`, after accepted status transition.

```text
idempotency: order_cancelled|{order_status_transitions.id}
subject_type: order
subject_public_id: orders.public_id
occurred_at: transition.created_at
payload:
  from_status: confirmed
  to_status: cancelled
  released_quantity_hundredths: integer
  service_date: YYYY-MM-DD
  delivery_zone: safe enum value
```

## 5. ConsolidationCompleted

Source: `RunConsolidationCycleAction`, after all memberships, groups, procurement totals,
and order transitions reconcile.

```text
idempotency: consolidation_completed|{consolidation_cycles.id}|{completed_at}
subject_type: consolidation_cycle
subject_public_id: consolidation_cycles.public_id
occurred_at: consolidation_cycles.completed_at
payload:
  cutoff_at: UTC string
  service_date: YYYY-MM-DD
  order_count: integer
  quantity_hundredths: integer
  procurement_quantity_hundredths: integer
  groups: ordered by product offer public UUID, channel, delivery zone, public UUID
    - group_public_id: UUID
      offer_public_id: UUID
      channel: b2c|b2b
      delivery_zone: safe enum value
      order_count: integer
      quantity_hundredths: integer
```

Rejected/cancelled/deferred candidates are not in totals. Their aggregate decision counts
may be added only if Phase 003 already exposes a safe fixed-category summary and the
artifacts are updated first.

## 6. HubReceiptFinalized

Source: `FinalizeHubReceiptAction`.

```text
idempotency: hub_receipt_finalized|{hub_receipts.id}|{finalized_at}
subject_type: hub_receipt
subject_public_id: hub_receipts.public_id
occurred_at: finalized_at
payload:
  consolidation_group_public_id: UUID
  received_quantity_hundredths: integer
  accepted_quantity_hundredths: integer
  rejected_quantity_hundredths: integer
  quality_grade: safe enum value
  rejection_reason_code: safe fixed code|null
```

No free-text rejection note is allowed in the ledger.

## 7. HubReceiptCorrected

Source: `CorrectHubReceiptAction`.

```text
idempotency: hub_receipt_corrected|{hub_receipt_corrections.id}
subject_type: hub_receipt
subject_public_id: hub_receipts.public_id
occurred_at: correction.created_at
payload:
  prior_received_quantity_hundredths: integer
  corrected_received_quantity_hundredths: integer
  prior_accepted_quantity_hundredths: integer
  corrected_accepted_quantity_hundredths: integer
  prior_rejected_quantity_hundredths: integer
  corrected_rejected_quantity_hundredths: integer
  reason_code: safe fixed code
```

The correction's internal ID participates only in the private idempotency input. Never
place it or its encrypted free-text note in the payload.

## 8. HandlingLossRecorded

Source: `RecordHandlingLossAction`.

```text
idempotency: handling_loss_recorded|{handling_losses.id}
subject_type: hub_receipt
subject_public_id: hub_receipts.public_id
occurred_at: handling_losses.occurred_at
payload:
  hub_receipt_public_id: UUID
  quantity_hundredths: integer
  reason_code: safe fixed code
  available_after_hundredths: integer
  damaged_after_hundredths: integer
```

No free-text note is allowed.

## 9. StockAllocated

Source: `AllocateOrderGroupAction`.

```text
idempotency: stock_allocated|{stock_allocations.id}|{created_at}
subject_type: stock_allocation
subject_public_id: stock_allocations.public_id
occurred_at: stock_allocations.created_at
payload:
  hub_receipt_public_id: UUID
  order_group_public_id: UUID
  quantity_hundredths: integer
  available_after_hundredths: integer
  allocated_after_hundredths: integer
```

## 10. StockAllocationReleased

Source: `ReleaseStockAllocationAction`.

```text
idempotency: stock_allocation_released|{stock_allocations.id}|{released_at}
subject_type: stock_allocation
subject_public_id: stock_allocations.public_id
occurred_at: release.created_at
payload:
  released_quantity_hundredths: integer
  reason_code: safe fixed code
  available_after_hundredths: integer
  allocated_after_hundredths: integer
```

The allocation UUID is the safe subject. The private idempotency hash distinguishes its
one release without exposing the allocation's internal ID or raw release token.

## 11. DispatchSubmitted

Source: `SubmitDispatchAction`, after membership and cost allocation are frozen.

```text
idempotency: dispatch_submitted|{dispatch_attempts.id}
subject_type: dispatch
subject_public_id: dispatches.public_id
occurred_at: dispatch_attempts.completed_at or started_at for incomplete outcome
payload:
  channel: b2c|b2b
  service_date: YYYY-MM-DD
  delivery_zone: safe enum value
  provider_kind: internal_manifest|mock
  status: submitted|accepted|failed
  order_count: integer
  quantity_hundredths: integer
  delivery_cost_minor: integer|null
  delivery_cost_source: flat_rate|provider_quote|null
  provider_reference: safe mock reference|null
  allocations: ordered by order public UUID
    - order_public_id: UUID
      quantity_hundredths: integer
      allocated_delivery_cost_minor: integer|null
  failure_category: fixed safe code|null
```

No provider request/response, address, contact, note, token/hash, or private provider
value is allowed.

## 12. DispatchStatusChanged

Source: `AdvanceDispatchAction`, after source Order/stock changes reconcile.

```text
idempotency: dispatch_status_changed|{dispatch_status_transitions.id}
subject_type: dispatch
subject_public_id: dispatches.public_id
occurred_at: transition.occurred_at
payload:
  from_status: accepted|picked_up|submitted|failed
  to_status: picked_up|delivered|failed|submitted
  order_count: integer
  quantity_hundredths: integer
  delivery_cost_minor: integer
  failure_category: fixed safe code|null
```

## 13. ImpactIndicatorPublished

Source: `PublishImpactIndicatorAction`, after the new version exists.

```text
idempotency: impact_indicator_published|{impact_indicators.id}|{published_at}
subject_type: impact_indicator
subject_public_id: impact_indicators.public_id
occurred_at: impact_indicators.published_at
payload:
  consolidation_cycle_public_id: UUID
  code: trip_reduction
  classification: estimate|measured
  baseline_trip_count: integer
  actual_trip_count: integer
  value: signed integer
  unit: trips
  method: safe public string
  source_reference: safe public string|null
  evidence_reference: safe public string|null
  assumptions: safe public string|null
  limitation: safe public string
  reporting_starts_on: YYYY-MM-DD
  reporting_ends_on: YYYY-MM-DD
  is_demo: boolean
  supersedes_indicator_public_id: UUID|null
```

## 14. CorrectionRecorded

Use only when a source correction does not already have a more specific event above.
There is no public generic correction endpoint in Phase 006.

```text
idempotency: correction_recorded|{safe source kind}|{source history internal id}
subject_type: same safe subject type as original event
subject_public_id: same safe subject public UUID
occurred_at: correction accepted time
payload:
  corrected_event_public_id: UUID
  correction_type: reversal|replacement|late_fact
  reason_code: safe fixed code
  replacement_event_public_id: UUID|null
```

Free-text reasons are forbidden. Original entries remain unchanged.

## Integration Test Rule

For every source Action integration:

1. Direct Action success creates the source change and exactly one matching entry.
2. Replaying the source Action creates no second entry.
3. Forced append failure rolls back the source change and event together.
4. Stored payload equals the exact allowlist and contains no canary PII.
5. The existing source feature suite remains green.
