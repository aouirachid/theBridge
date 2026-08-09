import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, RotateCcw } from 'lucide-react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, show } from '@/routes/operator/orders';
import type { OperatorOrderIndexProps, OrderStatusValue } from '@/types';

const statusLabel: Record<OrderStatusValue, string> = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    grouped: 'Grouped',
    allocated: 'Allocated',
    dispatched: 'Dispatched',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
};

const statusVariant: Record<
    OrderStatusValue,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    pending: 'secondary',
    confirmed: 'default',
    grouped: 'outline',
    allocated: 'outline',
    dispatched: 'outline',
    delivered: 'secondary',
    cancelled: 'destructive',
};

type FilterPatch = {
    channel?: string | null;
    status?: string | null;
    serviceDate?: string | null;
    offer?: string | null;
    deliveryZone?: string | null;
};

export default function OperatorOrdersIndex({
    orders,
    filters,
    filterOptions,
}: OperatorOrderIndexProps) {
    const currentFilters = (): FilterPatch => ({
        channel: filters.channel,
        status: filters.status,
        serviceDate: filters.serviceDate,
        offer: filters.offer,
        deliveryZone: filters.deliveryZone,
    });

    const applyFilters = (patch: FilterPatch) => {
        router.get(
            index.url({ query: { ...currentFilters(), ...patch } }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const clearFilters = () => {
        applyFilters({
            channel: null,
            status: null,
            serviceDate: null,
            offer: null,
            deliveryZone: null,
        });
    };

    const hasFilters =
        filters.channel !== null ||
        filters.status !== null ||
        filters.serviceDate !== null ||
        filters.offer !== null ||
        filters.deliveryZone !== null;

    return (
        <>
            <Head title="Orders" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Orders"
                    description="Monitor confirmed orders, reconcile volume, and cancel before grouping"
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Filter the order list before grouping.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            <div className="grid gap-2">
                                <Label htmlFor="filter-channel">Channel</Label>
                                <Select
                                    value={filters.channel ?? 'all'}
                                    onValueChange={(value) =>
                                        applyFilters({
                                            channel:
                                                value === 'all' ? null : value,
                                        })
                                    }
                                >
                                    <SelectTrigger id="filter-channel">
                                        <SelectValue placeholder="All channels" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All channels
                                        </SelectItem>
                                        {filterOptions.channels.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="filter-status">Status</Label>
                                <Select
                                    value={filters.status ?? 'all'}
                                    onValueChange={(value) =>
                                        applyFilters({
                                            status:
                                                value === 'all' ? null : value,
                                        })
                                    }
                                >
                                    <SelectTrigger id="filter-status">
                                        <SelectValue placeholder="All statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All statuses
                                        </SelectItem>
                                        {filterOptions.statuses.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="filter-service-date">
                                    Service date
                                </Label>
                                <Input
                                    id="filter-service-date"
                                    type="date"
                                    value={filters.serviceDate ?? ''}
                                    onChange={(event) =>
                                        applyFilters({
                                            serviceDate:
                                                event.target.value || null,
                                        })
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="filter-offer">Offer</Label>
                                <Select
                                    value={filters.offer ?? 'all'}
                                    onValueChange={(value) =>
                                        applyFilters({
                                            offer:
                                                value === 'all' ? null : value,
                                        })
                                    }
                                >
                                    <SelectTrigger id="filter-offer">
                                        <SelectValue placeholder="All offers" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All offers
                                        </SelectItem>
                                        {filterOptions.offers.map((option) => (
                                            <SelectItem
                                                key={option.publicId}
                                                value={option.publicId}
                                            >
                                                {option.crop}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="filter-delivery-zone">
                                    Delivery zone
                                </Label>
                                <Select
                                    value={filters.deliveryZone ?? 'all'}
                                    onValueChange={(value) =>
                                        applyFilters({
                                            deliveryZone:
                                                value === 'all' ? null : value,
                                        })
                                    }
                                >
                                    <SelectTrigger id="filter-delivery-zone">
                                        <SelectValue placeholder="All zones" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All zones
                                        </SelectItem>
                                        {filterOptions.deliveryZones.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {hasFilters && (
                            <div className="mt-4 flex justify-end">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={clearFilters}
                                >
                                    <RotateCcw />
                                    Clear filters
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {orders.data.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>No orders found</CardTitle>
                            <CardDescription>
                                No orders match the current filters.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[900px] text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="px-4 py-3 font-medium">
                                                Reference
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Channel
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Crop
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Quantity
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Unit price
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Total
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Delivery zone
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Service date
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {orders.data.map((order) => (
                                            <tr
                                                key={order.reference}
                                                className="border-b last:border-b-0"
                                            >
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={show(
                                                            order.reference,
                                                        )}
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        {order.reference}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {order.channel === 'b2c'
                                                        ? 'Individual'
                                                        : 'Business'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {order.crop}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {order.quantityKg} kg
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {order.unitPrice}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {order.total}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {order.deliveryZone.label}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {order.serviceDate}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Badge
                                                            variant={
                                                                statusVariant[
                                                                    order.status
                                                                ]
                                                            }
                                                        >
                                                            {
                                                                statusLabel[
                                                                    order.status
                                                                ]
                                                            }
                                                        </Badge>
                                                        {order.eligibleForNextCycle &&
                                                            order.status ===
                                                                'confirmed' && (
                                                                <Badge variant="outline">
                                                                    Eligible
                                                                </Badge>
                                                            )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {orders.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Showing {orders.from ?? 0}–{orders.to ?? 0} of{' '}
                            {orders.total}
                        </p>
                        <div className="flex items-center gap-2">
                            {orders.prev_page_url !== null ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={orders.prev_page_url}
                                        preserveState
                                        preserveScroll
                                    >
                                        <ArrowLeft />
                                        Previous
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    <ArrowLeft />
                                    Previous
                                </Button>
                            )}
                            <span className="px-2 text-sm text-muted-foreground">
                                Page {orders.current_page} of {orders.last_page}
                            </span>
                            {orders.next_page_url !== null ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={orders.next_page_url}
                                        preserveState
                                        preserveScroll
                                    >
                                        Next
                                        <ArrowRight />
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Next
                                    <ArrowRight />
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

OperatorOrdersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Orders',
            href: index(),
        },
    ],
};
