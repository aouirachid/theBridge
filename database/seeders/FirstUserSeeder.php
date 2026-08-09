<?php

namespace Database\Seeders;

use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\CreateOrderAction;
use App\Actions\ProductOffers\CreateProductOfferDraftAction;
use App\Actions\ProductOffers\CreateReplacementOfferDraftAction;
use App\Actions\ProductOffers\PublishProductOfferAction;
use App\Actions\ProductOffers\RecordBenchmarkComparisonAction;
use App\Actions\ProductOffers\WithdrawProductOfferAction;
use App\Models\OfferDeliverySlot;
use App\Models\Order;
use App\Models\ProductOffer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Seed realistic demo data for local demos.
 *
 * Run it explicitly and on demand:
 *
 *     php artisan db:seed --class=DemoDataSeeder
 *
 * The primary demo account (hichamaltit@gmail.com) is given both operator
 * flags, two dedicated demo operators are created, and every offer/order row
 * is built through the real domain Actions so the data obeys the same rules
 * as live submissions. Re-running is safe: once offers exist the action
 * seeding is skipped.
 */
class FirstUserSeeder extends Seeder
{
    /**
     * The four standard cost components shared by every demo offer (MAD/kg).
     *
     * @var array<string, string>
     */
    private const STANDARD_COSTS = [
        'collection' => '0.30',
        'quality_control' => '0.20',
        'hub_handling_storage' => '0.30',
        'delivery_allocation' => '0.90',
    ];

    /**
     * @return array{
     *     crop: string,
     *     origin: string,
     *     quantity: string,
     *     farmer: string,
     *     margin: string,
     *     benchmark: string,
     *     market: string,
     *     custom_costs?: list<array{name: string, amount_per_kg: string}>,
     * }
     */
    private function offerSpecs(): array
    {
        return [
            'tomatoes' => [
                'crop' => 'Cherry tomatoes',
                'origin' => 'Souss-Massa',
                'quantity' => '5000.00',
                'farmer' => '2.50',
                'margin' => '0.80',
                'benchmark' => '6.20',
                'market' => 'Souk Central Casablanca',
                'custom_costs' => [['name' => 'Packaging', 'amount_per_kg' => '0.20']],
            ],
            'avocado' => [
                'crop' => 'Avocado (Fuerte)',
                'origin' => 'Ait Melloul, Souss',
                'quantity' => '3000.00',
                'farmer' => '3.50',
                'margin' => '1.00',
                'benchmark' => '8.00',
                'market' => 'Derb Omar wholesale district',
                'custom_costs' => [['name' => 'Cold chain', 'amount_per_kg' => '0.25']],
            ],
            'clementines' => [
                'crop' => 'Clementines',
                'origin' => 'Gharb, Kenitra',
                'quantity' => '8000.00',
                'farmer' => '1.80',
                'margin' => '0.60',
                'benchmark' => '5.00',
                'market' => 'Souk Central Casablanca',
            ],
            'potatoes' => [
                'crop' => 'Potatoes (Spunta)',
                'origin' => 'Saïss, Meknès',
                'quantity' => '12000.00',
                'farmer' => '1.50',
                'margin' => '0.50',
                'benchmark' => '4.50',
                'market' => 'Marché de gros Casablanca',
            ],
            'peppers' => [
                'crop' => 'Sweet peppers',
                'origin' => 'Souss-Massa',
                'quantity' => '4000.00',
                'farmer' => '2.20',
                'margin' => '0.70',
                'benchmark' => '5.50',
                'market' => 'Derb Omar wholesale district',
                'custom_costs' => [['name' => 'Grading', 'amount_per_kg' => '0.15']],
            ],
            'strawberries' => [
                'crop' => 'Strawberries',
                'origin' => 'Loukkos, Larache',
                'quantity' => '2000.00',
                'farmer' => '4.00',
                'margin' => '1.20',
                'benchmark' => '8.50',
                'market' => 'Marché de gros Casablanca',
                'custom_costs' => [['name' => 'Cold chain', 'amount_per_kg' => '0.25']],
            ],
            'onions' => [
                'crop' => 'Yellow onions',
                'origin' => 'Souss-Massa',
                'quantity' => '6000.00',
                'farmer' => '1.20',
                'margin' => '0.40',
                'benchmark' => '4.00',
                'market' => 'Souk Central Casablanca',
            ],
        ];
    }

    /**
     * Seed the application's database with demo data.
     */
    public function run(): void
    {
        $sourcing = $this->sourcingOperator();
        $operations = $this->operationsOperator();

        if (ProductOffer::query()->exists()) {
            $this->command?->info('Demo data already present — skipping offer and order seeding.');

            return;
        }

        $now = CarbonImmutable::now();

        $tomatoes = $this->seedPublishedOffer($sourcing, 'tomatoes', $now);
        $avocado = $this->seedPublishedOffer($sourcing, 'avocado', $now);
        $clementines = $this->seedPublishedOffer($sourcing, 'clementines', $now);
        $potatoes = $this->seedPublishedOffer($sourcing, 'potatoes', $now);
        $peppers = $this->seedPublishedOffer($sourcing, 'peppers', $now);

        // Replace the peppers offer with a slightly cheaper draft, then publish
        // the replacement so the original becomes superseded.
        $replacement = (new CreateReplacementOfferDraftAction)->execute($sourcing, $peppers);
        $this->recordBenchmark($sourcing, $replacement, '5.50', $now);
        $this->publishOffer($sourcing, $replacement);

        // Strawberries are published and then withdrawn one-way.
        $strawberries = $this->seedPublishedOffer($sourcing, 'strawberries', $now);
        (new WithdrawProductOfferAction)->execute($sourcing, $strawberries);

        // Onions stay as a draft with a recorded (unpublished) benchmark.
        $onions = $this->seedDraftOffer($sourcing, 'onions', $now);
        $this->recordBenchmark($sourcing, $onions, '4.00', $now);

        $cancelled = $this->seedOrders([
            [$tomatoes, 'b2c', '5.00', 'casablanca_centre', 'Yasmine Benali', '0661234567', 'yasmine.benali@example.com', null, 'Appartement 4, Bd Zerktouni', 'Slot 0'],
            [$tomatoes, 'b2b', '200.00', 'casablanca_west', 'Riad Grill Casablanca', '0522012345', null, 'Riad Grill Casablanca', 'Zone industrielle Ain Sebaa', 'Slot 1'],
            [$avocado, 'b2c', '3.00', 'casablanca_centre', 'Karim El Fassi', '0662345678', 'karim.elfassi@example.com', null, '12 Rue de Foucauld', 'Slot 0'],
            [$clementines, 'b2b', '500.00', 'casablanca_east', 'Casa Market SA', '0522345678', null, 'Casa Market SA', 'Marché Sidi Othmane', 'Slot 1'],
            [$potatoes, 'b2c', '25.00', 'casablanca_west', 'Fatima Zahra Alaoui', '0663456789', 'fatima.alaoui@example.com', null, 'Villa 8, Ain Diab', 'Slot 2'],
            [$replacement, 'b2c', '2.00', 'casablanca_centre', 'Omar Bennani', '0664567890', 'omar.bennani@example.com', null, '5 Rue d\'Agadir', 'Slot 0'],
        ]);

        $order = Order::query()->where('public_id', $cancelled)->firstOrFail();
        (new CancelOrderAction)->execute($order, $operations->id);

        $this->command?->info('Demo data seeded.');
    }

    private function sourcingOperator(): User
    {
        $this->ensurePrimaryDemoUser();

        return User::factory()->operator()->create([
            'name' => 'Salma El Idrissi',
            'email' => 'sourcing@thebridge.demo',
        ]);
    }

    private function operationsOperator(): User
    {
        return User::factory()->operationsOperator()->create([
            'name' => 'Omar Tazi',
            'email' => 'operations@thebridge.demo',
        ]);
    }

    /**
     * Ensure the primary demo login owns both operator flags.
     */
    private function ensurePrimaryDemoUser(): void
    {
        $user = User::query()->where('email', 'hichamaltit@gmail.com')->first();

        if ($user === null) {
            $user = User::factory()->create([
                'name' => 'Hicham El Altit',
                'email' => 'hichamaltit@gmail.com',
            ]);
        }

        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
            'is_sourcing_operator' => true,
            'is_operations_operator' => true,
        ])->save();
    }

    /**
     * Create, benchmark, and publish one offer through the real Actions.
     */
    private function seedPublishedOffer(User $operator, string $key, CarbonImmutable $now): ProductOffer
    {
        $offer = $this->seedDraftOffer($operator, $key, $now);
        $this->recordBenchmark($operator, $offer, $this->offerSpecs()[$key]['benchmark'], $now);

        return $this->publishOffer($operator, $offer);
    }

    /**
     * Create a draft offer whose availability window and slots lie in the future.
     */
    private function seedDraftOffer(User $operator, string $key, CarbonImmutable $now): ProductOffer
    {
        $spec = $this->offerSpecs()[$key];

        return (new CreateProductOfferDraftAction)->execute($operator, [
            'crop' => $spec['crop'],
            'origin' => $spec['origin'],
            'available_quantity_kg' => $spec['quantity'],
            'availability_starts_at' => $now->addDays(1)->setTime(8, 0),
            'availability_ends_at' => $now->addDays(7)->setTime(18, 0),
            'farmer_payment_per_kg' => $spec['farmer'],
            'platform_margin_per_kg' => $spec['margin'],
            'standard_costs' => self::STANDARD_COSTS,
            'custom_costs' => $spec['custom_costs'] ?? [],
            'delivery_slots' => [
                ['starts_at' => $now->addDays(2)->setTime(8, 0), 'ends_at' => $now->addDays(2)->setTime(12, 0)],
                ['starts_at' => $now->addDays(4)->setTime(8, 0), 'ends_at' => $now->addDays(4)->setTime(12, 0)],
                ['starts_at' => $now->addDays(6)->setTime(14, 0), 'ends_at' => $now->addDays(6)->setTime(18, 0)],
            ],
        ]);
    }

    private function recordBenchmark(User $operator, ProductOffer $offer, string $price, CarbonImmutable $now): void
    {
        $spec = $this->offerSpecs()[$this->specKeyForOffer($offer)] ?? ['market' => 'Souk Central Casablanca'];

        (new RecordBenchmarkComparisonAction)->execute($operator, $offer, [
            'benchmark_price_per_kg' => $price,
            'market_name' => $spec['market'],
            'source_type' => 'field_observation',
            'source_reference' => 'Weekly street market survey, Casablanca wholesale district',
            'observed_at' => $now,
            'is_demo' => true,
        ]);
    }

    private function publishOffer(User $operator, ProductOffer $offer): ProductOffer
    {
        $comparison = $offer->benchmarkComparisons()->whereNull('published_at')->latest('id')->firstOrFail();

        return (new PublishProductOfferAction)->execute($operator, $offer, $comparison);
    }

    /**
     * @param  list<array{0: ProductOffer, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string|null, 7: string|null, 8: string|null, 9: string}>  $rows
     */
    private function seedOrders(array $rows): string
    {
        $cancelledReference = '';

        foreach ($rows as $index => [$offer, $channel, $quantity, $zone, $customer, $phone, $email, $business, $address, $slotKey]) {
            $slot = $this->slotFor($offer, $slotKey);

            $result = (new CreateOrderAction)->execute($offer->public_id, [
                'submission_token' => "demo-order-{$index}-" . now()->format('YmdHis'),
                'channel' => $channel,
                'quantity_kg' => $quantity,
                'delivery_slot_public_id' => $slot->public_id,
                'delivery_zone' => $zone,
                'customer_name' => $customer,
                'phone' => $phone,
                'email' => $email,
                'business_name' => $business,
                'delivery_address' => $address,
                'delivery_note' => null,
            ]);

            if ($index === count($rows) - 1) {
                $cancelledReference = $result['reference'];
            }
        }

        return $cancelledReference;
    }

    private function slotFor(ProductOffer $offer, string $slotKey): OfferDeliverySlot
    {
        $index = (int) substr($slotKey, -1);

        return $offer->deliverySlots()->orderBy('starts_at')->get()[$index];
    }

    private function specKeyForOffer(ProductOffer $offer): string
    {
        foreach ($this->offerSpecs() as $key => $spec) {
            if ($spec['crop'] === $offer->crop && $offer->origin === $spec['origin']) {
                return $key;
            }
        }

        return '';
    }
}
