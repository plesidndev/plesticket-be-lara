export type UserRole = 'SUPER_ADMIN' | 'REGISTERED_USER' | 'BUYER';

export interface User {
    id: number;
    uid: string;
    name: string;
    username: string | null;
    email: string;
    phone: string | null;
    role: UserRole;
    is_active: boolean;
    date_of_birth: string | null;
    photo: string | null;
}

export type VerificationStatus = 'pending' | 'verified' | 'rejected' | 'suspended';

export type OrganizerRoleValue =
    | 'EO_STAFF'
    | 'GATE_OFFICER'
    | 'MITRA_TICKET_BOX'
    | 'BAND'
    | 'MEDIA'
    | 'SPONSOR';

export interface OrganizerMember {
    id: number;
    uid: string | null;
    owner_id: number;
    event_id: string;
    name: string;
    email: string | null;
    role: OrganizerRoleValue;
    is_active: boolean;
}

export interface TicketType {
    id: number;
    name: string;
    description: string | null;
    price: string;
    quota: number;
    is_active: boolean;
    sale_start: string | null;
    sale_end: string | null;
}

export interface EventPic {
    name: string;
    identity_type: string;
    identity_type_label: string;
    identity_number: string | null;
    npwp: string | null;
}

export interface EventSchedule {
    start_date: string | null;
    end_date: string | null;
    start_time: string | null;
    end_time: string | null;
}

export interface EventLocation {
    is_online: boolean;
    venue_name: string | null;
    address: string | null;
    city: string | null;
    province: string | null;
    latitude: string | null;
    longitude: string | null;
}

export interface Event {
    id: string;
    event_id: string;
    title: string;
    slug: string;
    description: string | null;
    category: string | null;
    banner_url: string | null;
    pic: EventPic | null;
    schedule: EventSchedule;
    location: EventLocation;
    verification_status: VerificationStatus;
    verification_label: string;
    rejection_reason: string | null;
    verified_at: string | null;
    show_status: boolean;
    is_published: boolean;
    created_at: string;
    ticket_types: TicketType[] | undefined;
    organizer: User | null;
    verified_by: User | null;
}

export interface Category {
    id: number;
    name: string;
    is_active: boolean;
    created_at: string;
}

export interface Province {
    id: number;
    code: string;
    name: string;
}

export interface City {
    id: number;
    province_code: string;
    name: string;
    type: 'KABUPATEN' | 'KOTA';
}

export interface PaginationMeta {
    total: number;
    page: number;
    limit: number;
    pages: number;
}

export interface ApiResponse<T> {
    status: string;
    message: string;
    data: T;
}

export interface PaginatedResponse<T> {
    status: string;
    message: string;
    data: T[];
    meta: PaginationMeta;
}
