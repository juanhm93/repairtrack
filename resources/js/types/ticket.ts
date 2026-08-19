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

export type DeviceCatalog = Record<string, Record<string, string[]>>;

export type DeviceHistoryItem = {
    device_type: string;
    brand: string | null;
    model: string | null;
};

export type RepairTicket = {
    id: number;
    public_token: string;
    device_type: string;
    brand: string | null;
    model: string | null;
    serial_number: string | null;
    reported_issue: string;
    estimated_cost: string | null;
    received_at: string;
    estimated_delivery_at: string | null;
    status: TicketStatus;
    customer: Customer;
    history: TicketStatusHistoryItem[];
    photos: TicketPhoto[];
};

export const deviceTypeOptions = [
    { value: 'celular', label: 'Celular' },
    { value: 'tablet', label: 'Tablet' },
    { value: 'laptop', label: 'Laptop' },
    { value: 'pc_desktop', label: 'PC de escritorio' },
    { value: 'consola', label: 'Consola' },
    { value: 'otro', label: 'Otro' },
] as const;
