import { Head, Link, useForm } from '@inertiajs/react';
import { ExternalLink, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { show as showPublicOffer } from '@/routes/offers';
import { index, store, update } from '@/routes/operator/offers';
import { store as storeBenchmark } from '@/routes/operator/offers/benchmarks';
import { store as storeBenchmarkPublication } from '@/routes/operator/offers/benchmarks/publications';
import { store as storePublication } from '@/routes/operator/offers/publications';
import { store as storeReplacement } from '@/routes/operator/offers/replacements';
import type {
    OperatorBenchmarkComparison,
    OperatorOfferCapabilities,
    OperatorOfferEditor,
    StandardCostCode,
} from '@/types';

const STANDARD_COST_CODES: StandardCostCode[] = [
    'collection',
    'quality_control',
    'hub_handling_storage',
    'delivery_allocation',
];

type CustomCostInput = {
    name: string;
    amount_per_kg: string;
};

type OfferFormData = {
    crop: string;
    origin: string;
    available_quantity_kg: string;
    availability_starts_at: string;
    availability_ends_at: string;
    farmer_payment_per_kg: string;
    platform_margin_per_kg: string;
    standard_costs: Record<StandardCostCode, string>;
    custom_costs: CustomCostInput[];
};

type BenchmarkFormData = {
    benchmark_price_per_kg: string;
    market_name: string;
    source_type: 'url' | 'document' | 'field_observation';
    source_reference: string;
    observed_at: string;
    is_demo: boolean;
};

function pad(value: number): string {
    return value.toString().padStart(2, '0');
}

function toLocalDateTime(iso: string): string {
    const date = new Date(iso);

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function currentLocalDateTime(): string {
    return toLocalDateTime(new Date().toISOString());
}

function initialOfferForm(offer: OperatorOfferEditor | null): OfferFormData {
    return {
        crop: offer?.crop ?? '',
        origin: offer?.origin ?? '',
        available_quantity_kg: offer?.availableQuantity ?? '',
        availability_starts_at: offer
            ? toLocalDateTime(offer.availabilityStartsAt)
            : currentLocalDateTime(),
        availability_ends_at: offer
            ? toLocalDateTime(offer.availabilityEndsAt)
            : '',
        farmer_payment_per_kg: offer?.farmerPayment ?? '',
        platform_margin_per_kg: offer?.platformMargin ?? '',
        standard_costs: {
            collection: offer?.standardCosts.collection ?? '',
            quality_control: offer?.standardCosts.quality_control ?? '',
            hub_handling_storage:
                offer?.standardCosts.hub_handling_storage ?? '',
            delivery_allocation: offer?.standardCosts.delivery_allocation ?? '',
        },
        custom_costs:
            offer?.customCosts.map((cost) => ({
                name: cost.name,
                amount_per_kg: cost.amount,
            })) ?? [],
    };
}

export default function OperatorOffersManage({
    mode,
    offer,
    benchmarkComparisons,
    standardCostLabels,
    can,
}: {
    mode: 'create' | 'edit';
    offer: OperatorOfferEditor | null;
    benchmarkComparisons: OperatorBenchmarkComparison[];
    standardCostLabels: Record<StandardCostCode, string>;
    can: OperatorOfferCapabilities;
}) {
    const editable =
        mode === 'create' || (offer !== null && offer.status === 'draft');

    const offerForm = useForm<OfferFormData>(initialOfferForm(offer));

    const benchmarkForm = useForm<BenchmarkFormData>({
        benchmark_price_per_kg: '',
        market_name: '',
        source_type: 'field_observation',
        source_reference: '',
        observed_at: currentLocalDateTime(),
        is_demo: false,
    });

    const publishForm = useForm<{ benchmark_comparison_id: string }>({
        benchmark_comparison_id: '',
    });

    const replacementForm = useForm({});

    const benchmarkPublicationForm = useForm({});

    const setStandardCost = (code: StandardCostCode, value: string) => {
        offerForm.setData('standard_costs', {
            ...offerForm.data.standard_costs,
            [code]: value,
        });
    };

    const setCustomCost = (
        index: number,
        field: keyof CustomCostInput,
        value: string,
    ) => {
        offerForm.setData(
            'custom_costs',
            offerForm.data.custom_costs.map((row, i) =>
                i === index ? { ...row, [field]: value } : row,
            ),
        );
    };

    const addCustomCost = () => {
        if (offerForm.data.custom_costs.length >= 10) {
            return;
        }

        offerForm.setData('custom_costs', [
            ...offerForm.data.custom_costs,
            { name: '', amount_per_kg: '' },
        ]);
    };

    const removeCustomCost = (index: number) => {
        offerForm.setData(
            'custom_costs',
            offerForm.data.custom_costs.filter((_, i) => i !== index),
        );
    };

    const saveOffer = () => {
        if (mode === 'create') {
            offerForm.post(store.url());

            return;
        }

        if (offer !== null) {
            offerForm.patch(update.url({ productOffer: offer.id }));
        }
    };

    const recordBenchmark = () => {
        if (offer !== null) {
            benchmarkForm.post(storeBenchmark.url({ productOffer: offer.id }));
        }
    };

    const publishOffer = () => {
        if (offer !== null) {
            publishForm.post(storePublication.url({ productOffer: offer.id }));
        }
    };

    const createReplacement = () => {
        if (offer !== null) {
            replacementForm.post(
                storeReplacement.url({ productOffer: offer.id }),
            );
        }
    };

    const publishBenchmarkRefresh = (comparisonId: number) => {
        if (offer !== null) {
            benchmarkPublicationForm.post(
                storeBenchmarkPublication.url({
                    productOffer: offer.id,
                    benchmarkComparison: comparisonId,
                }),
            );
        }
    };

    return (
        <>
            <Head
                title={
                    mode === 'create'
                        ? 'New product offer'
                        : `Edit ${offer?.crop ?? 'offer'}`
                }
            />

            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title={
                            mode === 'create'
                                ? 'New product offer'
                                : `Edit ${offer?.crop ?? 'offer'}`
                        }
                        description="Complete the transparent cost breakdown and publish it"
                    />
                    {offer !== null && (
                        <Badge
                            variant={
                                offer.status === 'draft'
                                    ? 'secondary'
                                    : offer.status === 'superseded'
                                      ? 'destructive'
                                      : 'default'
                            }
                        >
                            {offer.status}
                        </Badge>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Offer details</CardTitle>
                        <CardDescription>
                            Pricing and availability are stored as whole
                            centimes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="crop">Crop</Label>
                            <Input
                                id="crop"
                                value={offerForm.data.crop}
                                onChange={(event) =>
                                    offerForm.setData(
                                        'crop',
                                        event.target.value,
                                    )
                                }
                                disabled={!editable}
                                maxLength={120}
                                placeholder="Tomatoes"
                            />
                            <InputError message={offerForm.errors.crop} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="origin">Origin</Label>
                            <Input
                                id="origin"
                                value={offerForm.data.origin}
                                onChange={(event) =>
                                    offerForm.setData(
                                        'origin',
                                        event.target.value,
                                    )
                                }
                                disabled={!editable}
                                maxLength={255}
                                placeholder="Souss-Massa"
                            />
                            <InputError message={offerForm.errors.origin} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="available_quantity_kg">
                                Available quantity (kg)
                            </Label>
                            <Input
                                id="available_quantity_kg"
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={offerForm.data.available_quantity_kg}
                                onChange={(event) =>
                                    offerForm.setData(
                                        'available_quantity_kg',
                                        event.target.value,
                                    )
                                }
                                disabled={!editable}
                                placeholder="100.00"
                            />
                            <InputError
                                message={offerForm.errors.available_quantity_kg}
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="availability_starts_at">
                                    Available from
                                </Label>
                                <Input
                                    id="availability_starts_at"
                                    type="datetime-local"
                                    value={
                                        offerForm.data.availability_starts_at
                                    }
                                    onChange={(event) =>
                                        offerForm.setData(
                                            'availability_starts_at',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!editable}
                                />
                                <InputError
                                    message={
                                        offerForm.errors.availability_starts_at
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="availability_ends_at">
                                    Available until
                                </Label>
                                <Input
                                    id="availability_ends_at"
                                    type="datetime-local"
                                    value={offerForm.data.availability_ends_at}
                                    onChange={(event) =>
                                        offerForm.setData(
                                            'availability_ends_at',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!editable}
                                />
                                <InputError
                                    message={
                                        offerForm.errors.availability_ends_at
                                    }
                                />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="farmer_payment_per_kg">
                                    Farmer payment (MAD/kg)
                                </Label>
                                <Input
                                    id="farmer_payment_per_kg"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={offerForm.data.farmer_payment_per_kg}
                                    onChange={(event) =>
                                        offerForm.setData(
                                            'farmer_payment_per_kg',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!editable}
                                    placeholder="2.80"
                                />
                                <InputError
                                    message={
                                        offerForm.errors.farmer_payment_per_kg
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="platform_margin_per_kg">
                                    Platform margin (MAD/kg)
                                </Label>
                                <Input
                                    id="platform_margin_per_kg"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={
                                        offerForm.data.platform_margin_per_kg
                                    }
                                    onChange={(event) =>
                                        offerForm.setData(
                                            'platform_margin_per_kg',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!editable}
                                    placeholder="1.00"
                                />
                                <InputError
                                    message={
                                        offerForm.errors.platform_margin_per_kg
                                    }
                                />
                            </div>
                        </div>

                        <div className="grid gap-3">
                            <p className="text-sm font-medium">
                                Standard operating costs (MAD/kg)
                            </p>
                            {STANDARD_COST_CODES.map((code) => (
                                <div
                                    key={code}
                                    className="grid gap-2 sm:grid-cols-[1fr_200px] sm:items-center"
                                >
                                    <Label htmlFor={`standard_costs_${code}`}>
                                        {standardCostLabels[code]}
                                    </Label>
                                    <div>
                                        <Input
                                            id={`standard_costs_${code}`}
                                            name={`standard_costs[${code}]`}
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={
                                                offerForm.data.standard_costs[
                                                    code
                                                ]
                                            }
                                            onChange={(event) =>
                                                setStandardCost(
                                                    code,
                                                    event.target.value,
                                                )
                                            }
                                            disabled={!editable}
                                            placeholder="0.00"
                                        />
                                        <InputError
                                            message={
                                                offerForm.errors[
                                                    `standard_costs.${code}`
                                                ]
                                            }
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="grid gap-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="text-sm font-medium">
                                    Custom operating costs (MAD/kg)
                                </p>
                                {editable && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addCustomCost}
                                        disabled={
                                            offerForm.data.custom_costs
                                                .length >= 10
                                        }
                                    >
                                        <Plus />
                                        Add custom cost
                                    </Button>
                                )}
                            </div>

                            {offerForm.data.custom_costs.map((row, index) => (
                                <div
                                    key={index}
                                    className="grid gap-2 sm:grid-cols-[1fr_180px_auto] sm:items-center"
                                >
                                    <div>
                                        <Input
                                            name={`custom_costs[${index}][name]`}
                                            value={row.name}
                                            onChange={(event) =>
                                                setCustomCost(
                                                    index,
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            disabled={!editable}
                                            placeholder="Cost name"
                                            maxLength={120}
                                        />
                                        <InputError
                                            message={
                                                offerForm.errors[
                                                    `custom_costs.${index}.name`
                                                ]
                                            }
                                        />
                                    </div>
                                    <div>
                                        <Input
                                            name={`custom_costs[${index}][amount_per_kg]`}
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={row.amount_per_kg}
                                            onChange={(event) =>
                                                setCustomCost(
                                                    index,
                                                    'amount_per_kg',
                                                    event.target.value,
                                                )
                                            }
                                            disabled={!editable}
                                            placeholder="0.00"
                                        />
                                        <InputError
                                            message={
                                                offerForm.errors[
                                                    `custom_costs.${index}.amount_per_kg`
                                                ]
                                            }
                                        />
                                    </div>
                                    {editable && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() =>
                                                removeCustomCost(index)
                                            }
                                            aria-label={`Remove ${row.name || `custom cost ${index + 1}`}`}
                                        >
                                            <Trash2 />
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>

                        {editable && (
                            <Button
                                onClick={saveOffer}
                                disabled={offerForm.processing}
                            >
                                {mode === 'create'
                                    ? 'Create draft'
                                    : 'Save draft'}
                            </Button>
                        )}
                    </CardContent>
                </Card>

                {offer !== null && offer.status !== 'draft' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Saved breakdown</CardTitle>
                            <CardDescription>
                                These values were calculated and frozen by the
                                server.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm">
                            <div className="flex justify-between">
                                <span>Farmer payment</span>
                                <span>{offer.farmerPayment} MAD/kg</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Final price</span>
                                <span>{offer.finalPrice} MAD/kg</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Farmer share</span>
                                <span>{offer.farmerSharePercentage}</span>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {offer !== null && offer.status === 'draft' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Server preview</CardTitle>
                            <CardDescription>
                                Refreshed from the last saved draft. No
                                client-side math.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm">
                            <div className="flex justify-between">
                                <span>Farmer payment</span>
                                <span>
                                    {offer.preview.farmerPayment} MAD/kg
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span>Operating costs</span>
                                <span>
                                    {offer.preview.operatingCost} MAD/kg
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span>Platform margin</span>
                                <span>
                                    {offer.preview.platformMargin} MAD/kg
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span>Final price</span>
                                <span>{offer.preview.finalPrice} MAD/kg</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Farmer share</span>
                                <span>{offer.preview.farmerShare}</span>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {offer !== null && offer.status === 'published' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Published offer</CardTitle>
                            <CardDescription>
                                Correct the offer without rewriting its
                                published basis.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap items-center gap-3">
                            {can.replace && (
                                <Button
                                    onClick={createReplacement}
                                    disabled={replacementForm.processing}
                                >
                                    Create replacement
                                </Button>
                            )}
                            <Button asChild variant="outline">
                                <Link
                                    href={showPublicOffer(offer.publicId)}
                                    target="_blank"
                                >
                                    <ExternalLink />
                                    View public offer
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {can.recordBenchmark && benchmarkComparisons.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Benchmark refresh</CardTitle>
                            <CardDescription>
                                Review recorded observations and publish a fresh
                                one without changing the offer.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {benchmarkComparisons.map((comparison) => (
                                <div
                                    key={comparison.id}
                                    className="flex flex-wrap items-center justify-between gap-3 border-t pt-3 text-sm first:border-t-0 first:pt-0"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium">
                                                {comparison.marketName}
                                            </p>
                                            {comparison.isSuperseded && (
                                                <Badge variant="outline">
                                                    Superseded
                                                </Badge>
                                            )}
                                            {comparison.isPublished &&
                                                !comparison.isSuperseded && (
                                                    <Badge variant="default">
                                                        Published
                                                    </Badge>
                                                )}
                                            {!comparison.isPublished &&
                                                !comparison.isSuperseded && (
                                                    <Badge variant="secondary">
                                                        Recorded
                                                    </Badge>
                                                )}
                                            {comparison.isDemo && (
                                                <Badge variant="secondary">
                                                    Demo data
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-muted-foreground">
                                            {comparison.benchmarkPrice} MAD/kg
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        {comparison.isPublished && (
                                            <p className="text-xs text-muted-foreground">
                                                {comparison.isSuperseded
                                                    ? 'Superseded by a newer refresh'
                                                    : 'Current public claim'}
                                            </p>
                                        )}
                                        {comparison.isRecorded &&
                                            offer !== null &&
                                            offer.status === 'published' && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        publishBenchmarkRefresh(
                                                            comparison.id,
                                                        )
                                                    }
                                                    disabled={
                                                        benchmarkPublicationForm.processing
                                                    }
                                                >
                                                    Publish refresh
                                                </Button>
                                            )}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {can.recordBenchmark && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Record a benchmark</CardTitle>
                            <CardDescription>
                                An observed market price used to compare against
                                this offer.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="benchmark_price_per_kg">
                                        Benchmark price (MAD/kg)
                                    </Label>
                                    <Input
                                        id="benchmark_price_per_kg"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={
                                            benchmarkForm.data
                                                .benchmark_price_per_kg
                                        }
                                        onChange={(event) =>
                                            benchmarkForm.setData(
                                                'benchmark_price_per_kg',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="8.00"
                                    />
                                    <InputError
                                        message={
                                            benchmarkForm.errors
                                                .benchmark_price_per_kg
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="market_name">
                                        Market name
                                    </Label>
                                    <Input
                                        id="market_name"
                                        value={benchmarkForm.data.market_name}
                                        onChange={(event) =>
                                            benchmarkForm.setData(
                                                'market_name',
                                                event.target.value,
                                            )
                                        }
                                        maxLength={160}
                                        placeholder="Casablanca traditional market"
                                    />
                                    <InputError
                                        message={
                                            benchmarkForm.errors.market_name
                                        }
                                    />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="source_type">
                                        Source type
                                    </Label>
                                    <Select
                                        value={benchmarkForm.data.source_type}
                                        onValueChange={(value) =>
                                            benchmarkForm.setData(
                                                'source_type',
                                                value as BenchmarkFormData['source_type'],
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="source_type"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select a source type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="field_observation">
                                                Field observation
                                            </SelectItem>
                                            <SelectItem value="url">
                                                Published source URL
                                            </SelectItem>
                                            <SelectItem value="document">
                                                Document
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={
                                            benchmarkForm.errors.source_type
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="observed_at">
                                        Observed at
                                    </Label>
                                    <Input
                                        id="observed_at"
                                        type="datetime-local"
                                        value={benchmarkForm.data.observed_at}
                                        onChange={(event) =>
                                            benchmarkForm.setData(
                                                'observed_at',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            benchmarkForm.errors.observed_at
                                        }
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="source_reference">
                                    Source reference
                                </Label>
                                <Input
                                    id="source_reference"
                                    value={benchmarkForm.data.source_reference}
                                    onChange={(event) =>
                                        benchmarkForm.setData(
                                            'source_reference',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={500}
                                    placeholder={
                                        benchmarkForm.data.source_type === 'url'
                                            ? 'https://…'
                                            : 'Reference or document identifier'
                                    }
                                />
                                <InputError
                                    message={
                                        benchmarkForm.errors.source_reference
                                    }
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_demo"
                                    checked={benchmarkForm.data.is_demo}
                                    onCheckedChange={(checked) =>
                                        benchmarkForm.setData(
                                            'is_demo',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="is_demo">
                                    Demo observation
                                </Label>
                                <InputError
                                    message={benchmarkForm.errors.is_demo}
                                />
                            </div>

                            <Button
                                onClick={recordBenchmark}
                                disabled={benchmarkForm.processing}
                            >
                                Record benchmark
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {can.publish && offer !== null && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Publish offer</CardTitle>
                            <CardDescription>
                                Choose a fresh recorded benchmark to publish
                                against.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="benchmark_comparison_id">
                                    Benchmark comparison
                                </Label>
                                <Select
                                    value={
                                        publishForm.data.benchmark_comparison_id
                                    }
                                    onValueChange={(value) =>
                                        publishForm.setData(
                                            'benchmark_comparison_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="benchmark_comparison_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select a recorded benchmark" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {benchmarkComparisons
                                            .filter(
                                                (comparison) =>
                                                    comparison.isRecorded,
                                            )
                                            .map((comparison) => (
                                                <SelectItem
                                                    key={comparison.id}
                                                    value={comparison.id.toString()}
                                                >
                                                    {comparison.marketName} ·{' '}
                                                    {comparison.benchmarkPrice}{' '}
                                                    MAD/kg
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={
                                        publishForm.errors
                                            .benchmark_comparison_id
                                    }
                                />
                            </div>

                            <Button
                                onClick={publishOffer}
                                disabled={
                                    publishForm.processing ||
                                    publishForm.data.benchmark_comparison_id ===
                                        ''
                                }
                            >
                                Publish offer
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

OperatorOffersManage.layout = {
    breadcrumbs: [
        {
            title: 'Product offers',
            href: index(),
        },
        {
            title: 'New offer',
            href: index(),
        },
    ],
};
