export type TicketStatus =
    | 'received'
    | 'in_review'
    | 'in_repair'
    | 'waiting_approval'
    | 'ready'
    | 'delivered'
    | 'not_repairable';

export type TicketStatusOption = {
    value: TicketStatus;
    label: string;
};

export type Customer = {
    id: number;
    name: string;
    phone: string | null;
    email: string;
};

export type TicketStatusHistoryItem = {
    id: number;
    from_status: TicketStatus | null;
    to_status: TicketStatus;
    note: string | null;
    changed_by: { id: number; name: string } | null;
    created_at: string;
};

export type TicketPhoto = {
    id: number;
    url: string;
    sort_order: number;
};

export type TicketNotificationLog = {
    to_email: string;
    status: 'queued' | 'sent' | 'failed';
    created_at: string;
};

export type DeviceCatalog = Record<string, Record<string, string[]>>;

export type DeviceHistoryItem = {
    device_type: string;
    brand: string | null;
    model: string | null;
};

export type TicketListItem = {
    id: number;
    device_type: string;
    brand: string | null;
    model: string | null;
    serial_number: string | null;
    estimated_cost: string | null;
    received_at: string;
    estimated_delivery_at: string | null;
    status: TicketStatus;
    customer: Customer;
};

export type RepairTicket = TicketListItem & {
    public_token: string;
    reported_issue: string;
    history: TicketStatusHistoryItem[];
    photos: TicketPhoto[];
    latest_notification?: TicketNotificationLog | null;
};

export type PublicTicketHistoryItem = {
    to_status: TicketStatus;
    to_status_label: string;
    note: string | null;
    created_at: string;
};

export type PublicTicketPage = {
    app: {
        name: string;
    };
    ticket: {
        device_type: string;
        brand: string | null;
        model: string | null;
        status: TicketStatus;
        status_label: string;
        received_at: string;
        estimated_delivery_at: string | null;
        history: PublicTicketHistoryItem[];
    };
    customer: {
        name: string;
    };
};

export type TicketIndexFilters = {
    status: TicketStatus | null;
    q: string | null;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    first_page_url: string | null;
    last_page_url: string | null;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
};

export const deviceTypeOptions = [
    { value: 'celular', label: 'Celular' },
    { value: 'tablet', label: 'Tablet' },
    { value: 'laptop', label: 'Laptop' },
    { value: 'pc_desktop', label: 'PC de escritorio' },
    { value: 'consola', label: 'Consola' },
    { value: 'otro', label: 'Otro' },
] as const;

export const deviceTypeLabel = (deviceType: string): string =>
    deviceTypeOptions.find((option) => option.value === deviceType)?.label ??
    deviceType;

export const ticketStatusVariant = (
    status: TicketStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'not_repairable') {
        return 'destructive';
    }

    if (status === 'delivered' || status === 'received') {
        return 'secondary';
    }

    if (status === 'waiting_approval' || status === 'in_review') {
        return 'outline';
    }

    return 'default';
};

export const ticketNotificationStatusLabel = (
    status: TicketNotificationLog['status'],
): string => {
    if (status === 'queued') {
        return 'En cola';
    }

    if (status === 'sent') {
        return 'Enviado';
    }

    return 'Fallido';
};
