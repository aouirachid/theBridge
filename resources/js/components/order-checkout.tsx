import { useHttp } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    review as reviewRoute,
    store as storeRoute,
} from '@/routes/offers/orders';
import type {
    DeliveryZoneCode,
    OrderChannel,
    OrderConfirmation,
    OrderConfirmPayload,
    OrderErrorBody,
    OrderProps,
    OrderReview,
    OrderReviewPayload,
} from '@/types';

function readErrorMessage(response: { data: string }): string {
    try {
        const body = JSON.parse(response.data) as OrderErrorBody;
        const message = body.error?.message ?? body.message;

        if (typeof message === 'string' && message.length > 0) {
            return message;
        }
    } catch {
        // ignore malformed response bodies
    }

    return 'The order could not be completed. Please try again.';
}

function formatDateTime(iso: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Africa/Casablanca',
    }).format(new Date(iso));
}

export default function OrderCheckout({
    offerPublicId,
    order,
}: {
    offerPublicId: string;
    order: OrderProps;
}) {
    const [review, setReview] = useState<OrderReview | null>(null);
    const [confirmation, setConfirmation] = useState<OrderConfirmation | null>(
        null,
    );
    const [conflictMessage, setConflictMessage] = useState<string | null>(null);

    const defaultSlot = order.deliverySlots[0]?.publicId ?? '';
    const defaultZone = order.deliveryZones[0]?.code ?? 'casablanca_centre';

    const reviewHttp = useHttp<OrderReviewPayload, OrderReview>({
        channel: 'b2c',
        quantity_kg: '',
        delivery_slot_public_id: defaultSlot,
    });

    const confirmHttp = useHttp<OrderConfirmPayload, OrderConfirmation>({
        submission_token: order.submissionToken ?? '',
        channel: 'b2c',
        quantity_kg: '',
        delivery_slot_public_id: defaultSlot,
        delivery_zone: defaultZone,
        customer_name: '',
        phone: '',
        email: '',
        delivery_address: '',
        delivery_note: '',
    });

    const { data: form, setData, errors, processing } = confirmHttp;
    const busy = reviewHttp.processing || processing;

    const noteReviewInputChanged = () => {
        setReview(null);
        setConflictMessage(null);
    };

    const setChannel = (channel: OrderChannel) => {
        setData('channel', channel);

        if (channel === 'b2b') {
            setData('delivery_address', null);
            setData('delivery_note', null);
        } else {
            setData('business_name', null);
        }

        noteReviewInputChanged();
    };

    const submitReview = () => {
        setConflictMessage(null);
        reviewHttp.setData({
            channel: form.channel,
            quantity_kg: form.quantity_kg,
            delivery_slot_public_id: form.delivery_slot_public_id,
        });
        reviewHttp.post(reviewRoute.url(offerPublicId), {
            onSuccess: (result) => setReview(result),
            onHttpException: (response) =>
                setConflictMessage(readErrorMessage(response)),
        });
    };

    const submitConfirmation = () => {
        setConflictMessage(null);
        confirmHttp.post(storeRoute.url(offerPublicId), {
            onSuccess: (result) => setConfirmation(result),
            onHttpException: (response) =>
                setConflictMessage(readErrorMessage(response)),
        });
    };

    if (confirmation !== null) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Order confirmed</CardTitle>
                    <CardDescription>
                        Save your reference, you will not be able to look this
                        order up again.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <dl className="grid gap-3 text-sm">
                        <div className="flex items-baseline justify-between gap-4">
                            <dt>Reference</dt>
                            <dd className="font-mono">
                                {confirmation.reference}
                            </dd>
                        </div>
                        <div className="flex items-baseline justify-between gap-4">
                            <dt>Product</dt>
                            <dd>
                                {confirmation.crop} · {confirmation.quantityKg}{' '}
                                kg
                            </dd>
                        </div>
                        <div className="flex items-baseline justify-between gap-4">
                            <dt>Delivery zone</dt>
                            <dd>{confirmation.deliveryZone.label}</dd>
                        </div>
                        <div className="flex items-baseline justify-between gap-4">
                            <dt>Service date</dt>
                            <dd>{confirmation.deliverySlot.serviceDate}</dd>
                        </div>
                        <div className="flex items-baseline justify-between gap-4">
                            <dt>Unit price</dt>
                            <dd>{confirmation.unitPrice.formatted}</dd>
                        </div>
                        <div className="flex items-baseline justify-between gap-4 border-t pt-3 font-medium">
                            <dt>Total</dt>
                            <dd>{confirmation.total.formatted}</dd>
                        </div>
                        <div className="flex items-baseline justify-between gap-4">
                            <dt>Contact</dt>
                            <dd>{confirmation.maskedPhone}</dd>
                        </div>
                        <div className="flex items-baseline justify-between gap-4">
                            <dt>Confirmed</dt>
                            <dd>{formatDateTime(confirmation.confirmedAt)}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Place an order</CardTitle>
                <CardDescription>
                    Review shows the exact server-calculated price before you
                    confirm.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label>I am ordering as</Label>
                        <ToggleGroup
                            type="single"
                            value={form.channel}
                            onValueChange={(value) => {
                                if (value === 'b2b' || value === 'b2c') {
                                    setChannel(value);
                                }
                            }}
                            variant="outline"
                            className="w-full"
                            disabled={busy}
                        >
                            <ToggleGroupItem value="b2c" className="flex-1">
                                Individual
                            </ToggleGroupItem>
                            <ToggleGroupItem value="b2b" className="flex-1">
                                Business
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="order-quantity">Quantity (kg)</Label>
                        <Input
                            id="order-quantity"
                            type="text"
                            inputMode="decimal"
                            placeholder="e.g. 5.00"
                            value={form.quantity_kg}
                            onChange={(event) => {
                                setData('quantity_kg', event.target.value);
                                noteReviewInputChanged();
                            }}
                            disabled={busy}
                            aria-invalid={errors.quantity_kg !== undefined}
                        />
                        <InputError
                            message={
                                reviewHttp.errors.quantity_kg ??
                                errors.quantity_kg
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label>Delivery slot</Label>
                        <Select
                            value={form.delivery_slot_public_id}
                            onValueChange={(value) => {
                                setData('delivery_slot_public_id', value);
                                noteReviewInputChanged();
                            }}
                            disabled={busy}
                        >
                            <SelectTrigger
                                className="w-full"
                                aria-invalid={
                                    errors.delivery_slot_public_id !== undefined
                                }
                            >
                                <SelectValue placeholder="Choose a slot" />
                            </SelectTrigger>
                            <SelectContent>
                                {order.deliverySlots.map((slot) => (
                                    <SelectItem
                                        key={slot.publicId}
                                        value={slot.publicId}
                                    >
                                        {slot.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={
                                reviewHttp.errors.delivery_slot_public_id ??
                                errors.delivery_slot_public_id
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label>Delivery zone</Label>
                        <Select
                            value={form.delivery_zone}
                            onValueChange={(value) =>
                                setData(
                                    'delivery_zone',
                                    value as DeliveryZoneCode,
                                )
                            }
                            disabled={busy}
                        >
                            <SelectTrigger
                                className="w-full"
                                aria-invalid={
                                    errors.delivery_zone !== undefined
                                }
                            >
                                <SelectValue placeholder="Choose a zone" />
                            </SelectTrigger>
                            <SelectContent>
                                {order.deliveryZones.map((zone) => (
                                    <SelectItem
                                        key={zone.code}
                                        value={zone.code}
                                    >
                                        {zone.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.delivery_zone} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="order-customer-name">Your name</Label>
                        <Input
                            id="order-customer-name"
                            type="text"
                            value={form.customer_name}
                            onChange={(event) =>
                                setData('customer_name', event.target.value)
                            }
                            disabled={busy}
                            aria-invalid={errors.customer_name !== undefined}
                        />
                        <InputError message={errors.customer_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="order-phone">Phone</Label>
                        <Input
                            id="order-phone"
                            type="tel"
                            value={form.phone}
                            onChange={(event) =>
                                setData('phone', event.target.value)
                            }
                            disabled={busy}
                            aria-invalid={errors.phone !== undefined}
                        />
                        <InputError message={errors.phone} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="order-email">Email (optional)</Label>
                        <Input
                            id="order-email"
                            type="email"
                            value={form.email ?? ''}
                            onChange={(event) =>
                                setData('email', event.target.value)
                            }
                            disabled={busy}
                            aria-invalid={errors.email !== undefined}
                        />
                        <InputError message={errors.email} />
                    </div>

                    {form.channel === 'b2b' && (
                        <div className="grid gap-2">
                            <Label htmlFor="order-business-name">
                                Business name
                            </Label>
                            <Input
                                id="order-business-name"
                                type="text"
                                value={form.business_name ?? ''}
                                onChange={(event) =>
                                    setData('business_name', event.target.value)
                                }
                                disabled={busy}
                                aria-invalid={
                                    errors.business_name !== undefined
                                }
                            />
                            <InputError message={errors.business_name} />
                        </div>
                    )}

                    {form.channel === 'b2c' && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="order-address">
                                    Delivery address (optional)
                                </Label>
                                <Input
                                    id="order-address"
                                    type="text"
                                    value={form.delivery_address ?? ''}
                                    onChange={(event) =>
                                        setData(
                                            'delivery_address',
                                            event.target.value,
                                        )
                                    }
                                    disabled={busy}
                                    aria-invalid={
                                        errors.delivery_address !== undefined
                                    }
                                />
                                <InputError message={errors.delivery_address} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="order-note">
                                    Delivery note (optional)
                                </Label>
                                <Input
                                    id="order-note"
                                    type="text"
                                    value={form.delivery_note ?? ''}
                                    onChange={(event) =>
                                        setData(
                                            'delivery_note',
                                            event.target.value,
                                        )
                                    }
                                    disabled={busy}
                                    aria-invalid={
                                        errors.delivery_note !== undefined
                                    }
                                />
                                <InputError message={errors.delivery_note} />
                            </div>
                        </>
                    )}

                    {conflictMessage !== null && (
                        <p className="text-sm text-red-600 dark:text-red-400">
                            {conflictMessage}
                        </p>
                    )}

                    <Button
                        type="button"
                        onClick={submitReview}
                        disabled={busy || reviewHttp.processing}
                    >
                        {reviewHttp.processing ? 'Reviewing…' : 'Review order'}
                    </Button>

                    {review !== null && (
                        <Card className="border-primary/40">
                            <CardHeader>
                                <CardTitle>Your review</CardTitle>
                                <CardDescription>
                                    Calculated by the server from the current
                                    offer.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm">
                                <div className="flex items-baseline justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Product
                                    </dt>
                                    <dd>
                                        {review.crop} · {review.quantityKg} kg
                                    </dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Slot
                                    </dt>
                                    <dd>
                                        {order.deliverySlots.find(
                                            (slot) =>
                                                slot.publicId ===
                                                review.deliverySlot.publicId,
                                        )?.label ??
                                            review.deliverySlot.serviceDate}
                                    </dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Unit price
                                    </dt>
                                    <dd>{review.unitPrice.formatted}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-4 border-t pt-3 font-medium">
                                    <dt>Total</dt>
                                    <dd>{review.total.formatted}</dd>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    <Button
                        type="button"
                        variant="default"
                        onClick={submitConfirmation}
                        disabled={busy || review === null}
                    >
                        {processing ? 'Confirming…' : 'Confirm order'}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
