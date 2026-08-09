# Quickstart Validation: Transparent Product Offer

## Purpose

Use this guide after implementation to prove Phase 1 end to end. It is a validation guide,
not implementation code.

## Prerequisites

1. Copy/configure `.env` for the existing application.
2. Install existing dependencies only: `composer install` and `npm.cmd install`.
3. Run `php artisan migrate --no-interaction`.
4. Start the application with `composer run dev`.
5. Register or select a local test user, then grant that local user sourcing-operator
   status using the project's trusted local administration method. For a throwaway demo
   database, the operator may run:

   ```powershell
   php artisan tinker --execute 'App\Models\User::where("email", "operator@example.test")->update(["is_sourcing_operator" => true]);'
   ```

   Replace the email with the local account. Do not use this as a production role-management
   workflow.

## Automated checks

Run the narrow feature checks first:

```powershell
php artisan test --compact tests/Unit/Support/Pricing/OfferPriceCalculatorTest.php
php artisan test --compact tests/Unit/Actions/ProductOffers
php artisan test --compact tests/Feature/ProductOffers
```

Then run required project checks:

```powershell
vendor/bin/pint --dirty --format agent
composer run types:check
npm.cmd run format:check
npm.cmd run lint:check
npm.cmd run types:check
npm.cmd run build
php artisan test --compact
```

Expected: every command exits successfully. `npm.cmd` is used on Windows because the
PowerShell npm shim may be blocked by execution policy.

## Manual scenario 1: Exact tomato publication

1. Sign in as the sourcing operator and open the Product offers navigation item.
2. Create a draft with:

   | Input | Value |
   |---|---:|
   | Crop | Tomatoes |
   | Origin | Souss-Massa |
   | Available quantity | 100.00 kg |
   | Farmer payment | 2.80 MAD/kg |
   | Collection | 0.30 MAD/kg |
   | Quality control | 0.20 MAD/kg |
   | Hub handling and storage | 0.30 MAD/kg |
   | Delivery allocation | 0.90 MAD/kg |
   | Platform margin | 1.00 MAD/kg |

3. Save the draft. Confirm the server review shows final price `5.50 MAD/kg` and farmer
   share `50.91%`.
4. Record a benchmark observed within the previous 24 hours:

   | Input | Value |
   |---|---|
   | Benchmark | 8.00 MAD/kg |
   | Market | Casablanca traditional market |
   | Source type | Field observation |
   | Source reference | Phase 1 demo observation |
   | Demo data | Yes |

5. Publish the offer using that benchmark. Confirm the operator page links to the public
   offer and repeating publication creates no duplicate.
6. Open the public offer signed out. Confirm:

   - final price `5.50 MAD/kg`;
   - saving `2.50 MAD/kg`;
   - saving percentage `31.25%`;
   - farmer share `50.91%`;
   - all four standard costs appear separately;
   - benchmark source, observation time, and Demo Data label appear;
   - no farmer/staff name, email, internal ID, or exact address appears.

## Manual scenario 2: Freshness boundary

Use automated frozen-time tests for exact boundaries. Manually verify the visible state by
opening an offer whose current benchmark is older than 24 hours:

- offer, costs, final price, and farmer share remain visible;
- “Fresh benchmark unavailable” appears;
- benchmark price/source, saving, and saving percentage are absent.

Record and publish a new fresh benchmark. Confirm the public comparison changes only
after publication and the offer's `5.50 MAD/kg` price does not change.

## Manual scenario 3: Replacement and history

1. From the published offer, create a replacement draft.
2. Change one offer input, save, review, record a fresh benchmark, and publish.
3. Confirm the original offer is marked Superseded and links to the replacement.
4. Confirm the original offer and superseded comparisons remain public during the
   30-day window and are clearly historical.
5. Rely on frozen-time tests to confirm that immediately after the 30-day boundary the
   old public URL returns 404 while an authorized operator can still view the record.

## Manual scenario 4: Security and invalid input

- Signed-out mutation request redirects to login.
- Authenticated non-operator receives forbidden response.
- Missing required standard cost, duplicate custom name, future benchmark, stale
  publication benchmark, zero benchmark, zero final price, and end-before-start all fail
  with field-specific errors and no partial data.
- A source URL/note containing disallowed private contact/address content is rejected or
  corrected before publication.
- Repeated publish, replacement, and benchmark-publish requests do not create duplicates.
- Sustained requests eventually receive the standard 429 retry-later response.

## Expected completion evidence

- Exact tomato calculations match the acceptance gate.
- All automated checks pass.
- Public Inertia props contain only the documented allowlist.
- No published economics or published comparison inputs can be edited through normal
  routes.
- No new dependency, external service, queue worker requirement, or manual database edit
  is needed for the product-offer workflow itself.
