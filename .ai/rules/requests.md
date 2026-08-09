---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Public-display inputs must use PublicDisplayText
Any operator-supplied text that renders publicly (crop, origin, market_name, source_reference, custom cost names) must add the `App\Support\Validation\PublicDisplayText` rule to block emails, phones, exact street addresses, and credential-bearing URLs. Do not bypass it for source_reference even for url source type.

## State-transition routes reject any body
Withdraw, replacement, and benchmark-publication endpoints take no input; apply the `App\Concerns\RejectsNonEmptyBody` trait in their Form Request so a stray body fails validation instead of being silently ignored.
