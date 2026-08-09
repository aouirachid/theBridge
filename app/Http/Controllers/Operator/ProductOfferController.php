<?php

namespace App\Http\Controllers\Operator;

use App\Actions\ProductOffers\CreateProductOfferDraftAction;
use App\Actions\ProductOffers\ListProductOffersAction;
use App\Actions\ProductOffers\ShowProductOfferDraftAction;
use App\Actions\ProductOffers\UpdateProductOfferDraftAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StoreProductOfferRequest;
use App\Http\Requests\Operator\UpdateProductOfferRequest;
use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductOfferController extends Controller
{
    /**
     * List the latest operator offers.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProductOffer::class);

        return Inertia::render('operator/offers/index', [
            'offers' => app(ListProductOffersAction::class)->execute(),
        ]);
    }

    /**
     * Show the create review form.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', ProductOffer::class);

        return Inertia::render('operator/offers/manage', [
            'mode' => 'create',
            'offer' => null,
            'benchmarkComparisons' => [],
            'standardCostLabels' => OfferCostComponent::STANDARD_LABELS,
            'can' => [
                'update' => false,
                'publish' => false,
                'replace' => false,
                'withdraw' => false,
                'recordBenchmark' => true,
            ],
        ]);
    }

    /**
     * Store a new offer draft.
     */
    public function store(StoreProductOfferRequest $request): RedirectResponse
    {
        $offer = app(CreateProductOfferDraftAction::class)->execute($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Offer draft saved.')]);

        return to_route('operator.offers.edit', $offer);
    }

    /**
     * Show the edit review form for one offer.
     */
    public function edit(Request $request, ProductOffer $productOffer): Response
    {
        $this->authorize('view', $productOffer);

        $data = app(ShowProductOfferDraftAction::class)->execute($request->user(), $productOffer);

        return Inertia::render('operator/offers/manage', [
            'mode' => 'edit',
            'offer' => $this->editor($data),
            'benchmarkComparisons' => $data['benchmarks']['newest30'],
            'standardCostLabels' => OfferCostComponent::STANDARD_LABELS,
            'can' => [
                'update' => $data['capabilities']['canUpdate'],
                'publish' => $data['capabilities']['canPublish'],
                'replace' => $data['capabilities']['canReplace'],
                'withdraw' => $data['capabilities']['canWithdraw'],
                'recordBenchmark' => $data['capabilities']['canRecordBenchmark'],
            ],
        ]);
    }

    /**
     * Update an unpublished draft.
     */
    public function update(UpdateProductOfferRequest $request, ProductOffer $productOffer): RedirectResponse
    {
        app(UpdateProductOfferDraftAction::class)->execute($request->user(), $productOffer, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Offer draft updated.')]);

        return to_route('operator.offers.edit', $productOffer);
    }

    /**
     * Map the review Action output to the operator editor props.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function editor(array $data): array
    {
        $standardCosts = [];
        foreach ($data['costs']['standard'] as $cost) {
            $standardCosts[$cost['code']] = $cost['amount'];
        }

        return [
            'id' => $data['offer']['id'],
            'publicId' => $data['offer']['publicId'],
            'crop' => $data['offer']['crop'],
            'origin' => $data['offer']['origin'],
            'availableQuantity' => $data['offer']['availableQuantity'],
            'availabilityStartsAt' => $data['offer']['availabilityStartsAt'],
            'availabilityEndsAt' => $data['offer']['availabilityEndsAt'],
            'farmerPayment' => $data['offer']['farmerPayment'],
            'platformMargin' => $data['offer']['platformMargin'],
            'finalPrice' => $data['offer']['finalPrice'],
            'farmerSharePercentage' => $data['offer']['farmerSharePercentage'],
            'status' => $data['offer']['status'],
            'standardCosts' => $standardCosts,
            'customCosts' => array_map(fn (array $cost): array => [
                'id' => $cost['id'],
                'name' => $cost['name'],
                'normalizedName' => $cost['normalized_name'],
                'amount' => $cost['amount'],
            ], $data['costs']['custom']),
            'preview' => $data['preview'],
        ];
    }
}
