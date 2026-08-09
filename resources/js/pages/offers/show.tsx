import { Head, Link } from '@inertiajs/react';
import OrderCheckout from '@/components/order-checkout';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { show as showOffer } from '@/routes/offers';
import type { OrderProps, PublicComparison, PublicOfferShow } from '@/types';

const SOURCE_LABELS: Record<PublicComparison['sourceType'], string> = {
    url: 'Published source',
    document: 'Document',
    field_observation: 'Field observation',
};

function formatCasablancaDateTime(iso: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Africa/Casablanca',
    }).format(new Date(iso));
}

export default function PublicOfferShow({
    offer,
    currentComparison,
    freshComparisonUnavailable,
    comparisonHistory,
    order,
}: {
    offer: PublicOfferShow;
    currentComparison: PublicComparison | null;
    freshComparisonUnavailable: boolean;
    comparisonHistory: PublicComparison[];
    order: OrderProps;
}) {
    return (
        <>
            <Head title={`${offer.crop} · ${offer.origin}`}>
                <meta
                    name="description"
                    content={`Transparent ${offer.crop} offer from ${offer.origin} with a public price breakdown.`}
                />
            </Head>

            <div className="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6">
                <article className="flex flex-col gap-6">
                    <header className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                The Bridge · transparent product offer
                            </p>
                            <h1 className="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                                {offer.crop}
                            </h1>
                            <p className="mt-1 text-muted-foreground">
                                {offer.origin}
                            </p>
                        </div>
                        {offer.isSuperseded && (
                            <Badge variant="destructive">Superseded</Badge>
                        )}
                    </header>

                    {offer.isSuperseded && (
                        <p className="text-sm text-muted-foreground">
                            This publication was superseded and is kept for
                            reference only. It no longer represents the current
                            offer.
                            {offer.replacementPublicId !== null && (
                                <>
                                    {' '}
                                    <Link
                                        href={showOffer(
                                            offer.replacementPublicId,
                                        )}
                                        className="font-medium text-primary underline underline-offset-4"
                                    >
                                        View the current offer
                                    </Link>
                                </>
                            )}
                        </p>
                    )}

                    {offer.replacesPublicId !== null && (
                        <p className="text-sm text-muted-foreground">
                            This publication corrects an earlier offer.{' '}
                            <Link
                                href={showOffer(offer.replacesPublicId)}
                                className="font-medium text-primary underline underline-offset-4"
                            >
                                View the previous publication
                            </Link>
                        </p>
                    )}

                    <section
                        aria-labelledby="availability-heading"
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle id="availability-heading">
                                    Availability
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-3 text-sm">
                                    <div className="flex items-baseline justify-between gap-4">
                                        <dt>Quantity</dt>
                                        <dd>{offer.availableQuantityKg} kg</dd>
                                    </div>
                                    <div className="flex items-baseline justify-between gap-4">
                                        <dt>Available from</dt>
                                        <dd>
                                            {formatCasablancaDateTime(
                                                offer.availabilityStartsAt,
                                            )}
                                        </dd>
                                    </div>
                                    <div className="flex items-baseline justify-between gap-4">
                                        <dt>Available until</dt>
                                        <dd>
                                            {formatCasablancaDateTime(
                                                offer.availabilityEndsAt,
                                            )}
                                        </dd>
                                    </div>
                                </dl>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Publication</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-3 text-sm">
                                    <div className="flex items-baseline justify-between gap-4">
                                        <dt>Published</dt>
                                        <dd>
                                            {formatCasablancaDateTime(
                                                offer.publishedAt,
                                            )}
                                        </dd>
                                    </div>
                                    <div className="flex items-baseline justify-between gap-4">
                                        <dt>Farmer share</dt>
                                        <dd>{offer.farmerSharePercentage}</dd>
                                    </div>
                                </dl>
                            </CardContent>
                        </Card>
                    </section>

                    {offer.isSuperseded ? null : order.canOrder ? (
                        <OrderCheckout
                            offerPublicId={offer.publicId}
                            order={order}
                        />
                    ) : (
                        <Card>
                            <CardHeader>
                                <CardTitle>Ordering unavailable</CardTitle>
                                <CardDescription>
                                    This offer cannot be ordered right now.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Where the price goes</CardTitle>
                            <CardDescription>
                                Per kilogram in Moroccan dirham (MAD), the exact
                                server-stored breakdown.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-3 text-sm">
                                <div className="flex items-baseline justify-between gap-4">
                                    <dt>Farmer payment</dt>
                                    <dd>{offer.farmerPayment.formatted}</dd>
                                </div>
                                {offer.costs.map((cost) => (
                                    <div
                                        key={cost.code ?? cost.name}
                                        className="flex items-baseline justify-between gap-4"
                                    >
                                        <dt>{cost.name}</dt>
                                        <dd>{cost.amount.formatted}</dd>
                                    </div>
                                ))}
                                <div className="flex items-baseline justify-between gap-4">
                                    <dt>Platform margin</dt>
                                    <dd>{offer.platformMargin.formatted}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-4 border-t pt-3 font-medium">
                                    <dt>Final price</dt>
                                    <dd>{offer.finalPrice.formatted}</dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-center gap-2">
                                <CardTitle>Compared with the market</CardTitle>
                                {currentComparison?.isDemo === true && (
                                    <Badge variant="secondary">Demo data</Badge>
                                )}
                            </div>
                            <CardDescription>
                                {currentComparison?.isDemo === true
                                    ? 'Illustrative or simulated observation, not a live market price.'
                                    : 'Observed market benchmark from the last 24 hours.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {freshComparisonUnavailable ||
                            currentComparison === null ? (
                                <p className="text-sm text-muted-foreground">
                                    Fresh benchmark unavailable
                                </p>
                            ) : (
                                <dl className="grid gap-3 text-sm">
                                    <div className="flex items-baseline justify-between gap-4">
                                        <dt>Benchmark price</dt>
                                        <dd>
                                            {
                                                currentComparison.benchmarkPrice
                                                    .formatted
                                            }
                                        </dd>
                                    </div>
                                    <div className="flex items-baseline justify-between gap-4">
                                        <dt>Market</dt>
                                        <dd>{currentComparison.marketName}</dd>
                                    </div>
                                    <div className="flex items-baseline justify-between gap-4">
                                        <dt>Source</dt>
                                        <dd>
                                            {
                                                SOURCE_LABELS[
                                                    currentComparison.sourceType
                                                ]
                                            }
                                        </dd>
                                    </div>
                                    <div className="flex items-baseline justify-between gap-4">
                                        <dt>Observed</dt>
                                        <dd>
                                            {formatCasablancaDateTime(
                                                currentComparison.observedAt,
                                            )}
                                        </dd>
                                    </div>
                                    <div className="flex items-baseline justify-between gap-4">
                                        <dt>
                                            {currentComparison.saving.minor < 0
                                                ? 'Difference above benchmark'
                                                : 'Customer saving'}
                                        </dt>
                                        <dd>
                                            {currentComparison.saving.formatted}
                                        </dd>
                                    </div>
                                    <div className="flex items-baseline justify-between gap-4">
                                        <dt>
                                            {currentComparison.saving.minor < 0
                                                ? 'Difference percentage'
                                                : 'Saving percentage'}
                                        </dt>
                                        <dd>
                                            {currentComparison.savingPercentage}
                                        </dd>
                                    </div>
                                </dl>
                            )}
                        </CardContent>
                    </Card>

                    {comparisonHistory.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Previous comparisons</CardTitle>
                                <CardDescription>
                                    Superseded benchmark observations from the
                                    last 30 days, kept for reference.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {comparisonHistory.map((comparison) => (
                                    <div
                                        key={comparison.publishedAt}
                                        className="flex flex-wrap items-center justify-between gap-3 border-t pt-3 text-sm first:border-t-0 first:pt-0"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-medium">
                                                    {comparison.marketName}
                                                </p>
                                                <Badge variant="outline">
                                                    Superseded
                                                </Badge>
                                                {comparison.isDemo && (
                                                    <Badge variant="secondary">
                                                        Demo data
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-muted-foreground">
                                                {
                                                    comparison.benchmarkPrice
                                                        .formatted
                                                }{' '}
                                                ·{' '}
                                                {
                                                    SOURCE_LABELS[
                                                        comparison.sourceType
                                                    ]
                                                }{' '}
                                                ·{' '}
                                                {formatCasablancaDateTime(
                                                    comparison.observedAt,
                                                )}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p>
                                                {comparison.saving.minor < 0
                                                    ? 'Difference above benchmark'
                                                    : 'Customer saving'}{' '}
                                                {comparison.saving.formatted}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {comparison.savingPercentage}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    )}
                </article>
            </div>
        </>
    );
}
