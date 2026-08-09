import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Phone } from 'lucide-react';
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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index } from '@/routes/operator/orders';
import { store as storeCancellation } from '@/routes/operator/orders/cancellations';
import type { OperatorOrderShowProps, OrderStatusValue } from '@/types';

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

function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString();
}

export default function OperatorOrderShow({
    order,
    can,
}: OperatorOrderShowProps) {
    const cancelForm = useForm({});

    const cancelOrder = () => {
        cancelForm.post(storeCancellation.url({ order: order.reference }));
    };

    const cancellable = can.cancel && order.status === 'confirmed';

    return (
        <>
            <Head title={`Order ${order.reference}`} />

            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title={`Order ${order.reference}`}
                        description={`${order.channel === 'b2c' ? 'Individual' : 'Business'} order · ${order.crop}`}
                    />
                    <Badge variant={statusVariant[order.status]}>
                        {statusLabel[order.status]}
                    </Badge>
                </div>

                <Button variant="outline" size="sm" asChild>
                    <Link href={index()} preserveScroll>
                        <ArrowLeft />
                        Back to orders
                    </Link>
                </Button>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Price snapshot</CardTitle>
                            <CardDescription>
                                Values frozen by the server at confirmation.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm">
                            <div className="flex justify-between">
                                <span>Crop</span>
                                <span className="font-medium">
                                    {order.crop}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span>Quantity</span>
                                <span className="font-medium">
                                    {order.quantityKg} kg
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span>Unit price</span>
                                <span className="font-medium">
                                    {order.unitPrice}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span>Total</span>
                                <span className="font-medium">
                                    {order.total}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span>Confirmed at</span>
                                <span className="font-medium">
                                    {order.confirmedAt !== null
                                        ? formatDateTime(order.confirmedAt)
                                        : '—'}
                                </span>
                            </div>
                            {order.eligibleForNextCycle && (
                                <div className="flex justify-between">
                                    <span>Consolidation</span>
                                    <Badge variant="outline">Eligible</Badge>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Delivery</CardTitle>
                            <CardDescription>
                                Zone and chosen delivery slot.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm">
                            <div className="flex justify-between">
                                <span>Zone</span>
                                <span className="font-medium">
                                    {order.deliveryZone.label}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span>Service date</span>
                                <span className="font-medium">
                                    {order.deliverySlot.serviceDate}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span>Starts at</span>
                                <span className="font-medium">
                                    {formatDateTime(
                                        order.deliverySlot.startsAt,
                                    )}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span>Ends at</span>
                                <span className="font-medium">
                                    {formatDateTime(order.deliverySlot.endsAt)}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Private contact</CardTitle>
                        <CardDescription>
                            Authorized details only visible to operations staff.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2 text-sm sm:grid-cols-2">
                        <div className="flex justify-between">
                            <span>Name</span>
                            <span className="font-medium">
                                {order.contact.customerName}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span>Business</span>
                            <span className="font-medium">
                                {order.contact.businessName ?? '—'}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span>Phone</span>
                            <span className="flex items-center gap-1 font-medium">
                                <Phone />
                                {order.contact.phone}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span>Email</span>
                            <span className="font-medium">
                                {order.contact.email ?? '—'}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span>Delivery address</span>
                            <span className="text-right font-medium">
                                {order.contact.deliveryAddress ?? '—'}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span>Delivery note</span>
                            <span className="text-right font-medium">
                                {order.contact.deliveryNote ?? '—'}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>History</CardTitle>
                        <CardDescription>
                            Order lifecycle transitions, oldest first.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {order.transitions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No recorded transitions yet.
                            </p>
                        ) : (
                            order.transitions.map((transition, index) => (
                                <div
                                    key={index}
                                    className="flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-sm first:border-t-0 first:pt-0"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline">
                                            {transition.from ?? '—'}
                                        </Badge>
                                        <span className="text-muted-foreground">
                                            →
                                        </span>
                                        <Badge>{transition.to}</Badge>
                                    </div>
                                    <div className="flex items-center gap-3 text-muted-foreground">
                                        <span>{transition.actorLabel}</span>
                                        <span>
                                            {formatDateTime(
                                                transition.occurredAt,
                                            )}
                                        </span>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {cancellable && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Cancel order</CardTitle>
                            <CardDescription>
                                Releases the reserved quantity before grouping.
                                This cannot be undone.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="destructive">
                                        Cancel order
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Cancel this order?
                                        </DialogTitle>
                                        <DialogDescription>
                                            {order.quantityKg} kg will be
                                            released back to the offer and the
                                            order status becomes cancelled.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button variant="secondary">
                                                Keep order
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            variant="destructive"
                                            onClick={cancelOrder}
                                            disabled={cancelForm.processing}
                                        >
                                            {cancelForm.processing
                                                ? 'Cancelling…'
                                                : 'Cancel order'}
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

OperatorOrderShow.layout = {
    breadcrumbs: [
        {
            title: 'Orders',
            href: index(),
        },
        {
            title: 'Order detail',
            href: index(),
        },
    ],
};
