import type { AxiosResponse } from 'axios';
import client from './client';
import type { ApiResponse, PaginatedResponse, Event } from '../types';

type EventResponse = AxiosResponse<ApiResponse<Event>>;
type EventsResponse = AxiosResponse<PaginatedResponse<Event>>;

export const listPublic = (params?: Record<string, unknown>): Promise<EventsResponse> =>
    client.get('/events', { params });

export const getBySlug = (slug: string): Promise<EventResponse> =>
    client.get(`/events/${slug}`);

export const myEvents = (params?: Record<string, unknown>): Promise<EventsResponse> =>
    client.get('/events/my', { params });

export const createEvent = (data: FormData): Promise<EventResponse> =>
    client.post('/events', data);

export const updateEvent = (id: string, data: FormData): Promise<EventResponse> =>
    client.post(`/events/${id}`, data);

export const uploadEventBanner = (id: string, file: File): Promise<EventResponse> => {
    const fd = new FormData();
    fd.append('banner', file);
    return client.post(`/events/${id}/banner`, fd);
};

export const toggleEventActive = (id: string): Promise<EventResponse> =>
    client.patch(`/events/${id}/toggle`);

export const adminListEvents = (params?: Record<string, unknown>): Promise<EventsResponse> =>
    client.get('/admin/events', { params });

export const adminGetEvent = (id: string): Promise<EventResponse> =>
    client.get(`/admin/events/${id}`);

export const verifyEvent = (id: string): Promise<EventResponse> =>
    client.post(`/admin/events/${id}/verify`);

export const rejectEvent = (id: string, reason: string): Promise<EventResponse> =>
    client.post(`/admin/events/${id}/reject`, { reason });

export const suspendEvent = (id: string): Promise<EventResponse> =>
    client.post(`/admin/events/${id}/suspend`);
