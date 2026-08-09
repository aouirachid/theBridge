export type OfferStatus = 'draft' | 'published' | 'superseded' | 'withdrawn';

export type StandardCostCode =
    | 'collection'
    | 'quality_control'
    | 'hub_handling_storage'
    | 'delivery_allocation';

export type OperatorOfferSummary = {
    id: number;
    publicId: string;
    crop: string;
    origin: string;
    status: OfferStatus;
    finalPrice: string;
    publishedAt: string | null;
    canEdit: boolean;
    canPublish: boolean;
    canReplace: boolean;
    publicUrlAvailable: boolean;
};

export type OperatorStandardCost = {
    code: string;
    amount: string;
};

export type OperatorCustomCost = {
    id: number;
    name: string;
    normalizedName: string;
    amount: string;
};

export type OperatorOfferPreview = {
    farmerPayment: string;
    operatingCost: string;
    platformMargin: string;
    finalPrice: string;
    farmerShare: string;
};

export type OperatorOfferEditor = {
    id: number;
    publicId: string;
    crop: string;
    origin: string;
    availableQuantity: string;
    availabilityStartsAt: string;
    availabilityEndsAt: string;
    farmerPayment: string;
    platformMargin: string;
    finalPrice: string;
    farmerSharePercentage: string;
    status: OfferStatus;
    standardCosts: Record<StandardCostCode, string>;
    customCosts: OperatorCustomCost[];
    preview: OperatorOfferPreview;
};

export type OperatorBenchmarkComparison = {
    id: number;
    marketName: string;
    benchmarkPrice: string;
    observedAt: string;
    sourceType: 'url' | 'document' | 'field_observation';
    sourceReference: string;
    isDemo: boolean;
    isRecorded: boolean;
    isPublished: boolean;
    isSuperseded: boolean;
    publishedAt: string | null;
    supersededAt: string | null;
};

export type OperatorOfferCapabilities = {
    update: boolean;
    publish: boolean;
    replace: boolean;
    withdraw: boolean;
    recordBenchmark: boolean;
};

export type OperatorDeliverySlot = {
    publicId: string;
    startsAt: string;
    endsAt: string;
};

export type PublicMoney = {
    minor: number;
    formatted: string;
};

export type PublicCost = {
    code: string | null;
    name: string;
    amount: PublicMoney;
};

export type PublicComparison = {
    marketName: string;
    sourceType: 'url' | 'document' | 'field_observation';
    sourceReference: string;
    observedAt: string;
    isDemo: boolean;
    benchmarkPrice: PublicMoney;
    saving: PublicMoney;
    savingPercentage: string;
    publishedAt: string;
    supersededAt: string | null;
    isSuperseded: boolean;
};

export type PublicOfferShow = {
    publicId: string;
    crop: string;
    origin: string;
    availableQuantityKg: string;
    availabilityStartsAt: string;
    availabilityEndsAt: string;
    farmerPayment: PublicMoney;
    costs: PublicCost[];
    platformMargin: PublicMoney;
    finalPrice: PublicMoney;
    farmerSharePercentage: string;
    publishedAt: string;
    supersededAt: string | null;
    isSuperseded: boolean;
    replacesPublicId: string | null;
    replacementPublicId: string | null;
};

export type DeliveryZoneCode =
    'casablanca_centre' | 'casablanca_east' | 'casablanca_west';

export type PublicDeliverySlot = {
    publicId: string;
    startsAt: string;
    endsAt: string;
    serviceDate: string;
    label: string;
};

export type OrderProps = {
    canOrder: boolean;
    deliveryZones: Array<{ code: DeliveryZoneCode; label: string }>;
    deliverySlots: PublicDeliverySlot[];
    submissionToken: string | null;
};
