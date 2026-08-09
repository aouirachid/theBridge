import type { DeliveryZoneCode, PublicMoney } from './product-offer';

export type OrderChannel = 'b2c' | 'b2b';

export type OrderReviewPayload = {
    channel: OrderChannel;
    quantity_kg: string;
    delivery_slot_public_id: string;
};

export type OrderConfirmPayload = {
    submission_token: string;
    channel: OrderChannel;
    quantity_kg: string;
    delivery_slot_public_id: string;
    delivery_zone: DeliveryZoneCode;
    customer_name: string;
    phone: string;
    email?: string | null;
    business_name?: string | null;
    delivery_address?: string | null;
    delivery_note?: string | null;
};

export type OrderReview = {
    channel: OrderChannel;
    crop: string;
    quantityKg: string;
    deliverySlot: {
        publicId: string;
        startsAt: string;
        endsAt: string;
        serviceDate: string;
    };
    unitPrice: PublicMoney;
    total: PublicMoney;
};

export type OrderConfirmation = {
    reference: string;
    channel: OrderChannel;
    crop: string;
    quantityKg: string;
    deliveryZone: { code: DeliveryZoneCode; label: string };
    deliverySlot: {
        startsAt: string;
        endsAt: string;
        serviceDate: string;
    };
    unitPrice: PublicMoney;
    total: PublicMoney;
    currency: 'MAD';
    status: 'confirmed';
    confirmedAt: string;
    maskedPhone: string;
    eligibleForNextCycle: true;
};

export type OrderErrorBody = {
    error?: { code?: string; message?: string };
    message?: string;
};
