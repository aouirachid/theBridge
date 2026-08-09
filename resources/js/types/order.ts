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

export type OrderStatusValue =
    | 'pending'
    | 'confirmed'
    | 'grouped'
    | 'allocated'
    | 'dispatched'
    | 'delivered'
    | 'cancelled';

export type LaravelPaginator<T> = {
    current_page: number;
    data: T[];
    first_page_url: string | null;
    from: number | null;
    last_page: number;
    last_page_url: string | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

export type OperatorOrderSummary = {
    reference: string;
    channel: OrderChannel;
    crop: string;
    quantityKg: string;
    unitPrice: string;
    total: string;
    deliveryZone: { code: DeliveryZoneCode; label: string };
    serviceDate: string;
    status: OrderStatusValue;
    eligibleForNextCycle: boolean;
    confirmedAt: string | null;
};

export type OperatorOrderFilters = {
    channel: string | null;
    status: string | null;
    serviceDate: string | null;
    offer: string | null;
    deliveryZone: string | null;
};

export type OperatorOrderFilterOptions = {
    channels: Array<{ value: string; label: string }>;
    statuses: Array<{ value: string; label: string }>;
    deliveryZones: Array<{ value: string; label: string }>;
    offers: Array<{ publicId: string; crop: string }>;
};

export type OperatorOrderIndexProps = {
    orders: LaravelPaginator<OperatorOrderSummary>;
    filters: OperatorOrderFilters;
    filterOptions: OperatorOrderFilterOptions;
};

export type OperatorOrderTransition = {
    from: OrderStatusValue | null;
    to: OrderStatusValue;
    occurredAt: string;
    actorLabel: 'Guest' | 'Operations staff';
};

export type OperatorOrderContact = {
    customerName: string;
    businessName: string | null;
    phone: string;
    email: string | null;
    deliveryAddress: string | null;
    deliveryNote: string | null;
};

export type OperatorOrderDetail = {
    reference: string;
    channel: OrderChannel;
    status: OrderStatusValue;
    crop: string;
    quantityKg: string;
    currency: 'MAD';
    unitPrice: string;
    total: string;
    deliveryZone: { code: DeliveryZoneCode; label: string };
    deliverySlot: { startsAt: string; endsAt: string; serviceDate: string };
    confirmedAt: string | null;
    eligibleForNextCycle: boolean;
    contact: OperatorOrderContact;
    transitions: OperatorOrderTransition[];
};

export type OperatorOrderShowProps = {
    order: OperatorOrderDetail;
    can: { cancel: boolean };
};
