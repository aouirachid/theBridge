import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { create, edit, index } from '@/routes/operator/offers';
import type { OperatorOfferSummary, OfferStatus } from '@/types';

const statusLabel: Record<OfferStatus, string> = {
    draft: 'Draft',
    published: 'Published',
    superseded: 'Superseded',
    withdrawn: 'Withdrawn',
};

const statusVariant: Record<
    OfferStatus,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    draft: 'secondary',
    published: 'default',
    superseded: 'outline',
    withdrawn: 'destructive',
};

export default function OperatorOffersIndex({
    offers,
}: {
    offers: OperatorOfferSummary[];
}) {
    return (
        <>
            <Head title="Product offers" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Product offers"
                        description="Create, review, and publish transparent tomato offers"
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            New offer
                        </Link>
                    </Button>
                </div>

                {offers.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>No product offers yet</CardTitle>
                            <CardDescription>
                                Create your first tomato offer to get started.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <div className="grid gap-4">
                        {offers.map((offer) => (
                            <Card key={offer.id}>
                                <CardContent className="flex flex-wrap items-center justify-between gap-4">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="truncate font-medium">
                                                {offer.crop}
                                            </p>
                                            <Badge
                                                variant={
                                                    statusVariant[offer.status]
                                                }
                                            >
                                                {statusLabel[offer.status]}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {offer.origin} · {offer.finalPrice}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {offer.canEdit && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link href={edit(offer.id)}>
                                                    Edit
                                                </Link>
                                            </Button>
                                        )}
                                        {offer.canPublish && (
                                            <Button size="sm" asChild>
                                                <Link href={edit(offer.id)}>
                                                    Review &amp; publish
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

OperatorOffersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Product offers',
            href: index(),
        },
    ],
};
