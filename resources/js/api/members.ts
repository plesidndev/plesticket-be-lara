import type { AxiosResponse } from 'axios';
import client from './client';
import type { ApiResponse, PaginatedResponse, OrganizerMember } from '../types';

export const listMembers = (eventId: string, params?: Record<string, unknown>): Promise<AxiosResponse<PaginatedResponse<OrganizerMember>>> =>
    client.get(`/events/${eventId}/members`, { params });

export const addMember = (eventId: string, data: Record<string, unknown>): Promise<AxiosResponse<ApiResponse<OrganizerMember>>> =>
    client.post(`/events/${eventId}/members`, data);

export const updateMember = (eventId: string, memberId: number, data: Record<string, unknown>): Promise<AxiosResponse<ApiResponse<OrganizerMember>>> =>
    client.put(`/events/${eventId}/members/${memberId}`, data);

export const removeMember = (eventId: string, memberId: number): Promise<AxiosResponse<ApiResponse<null>>> =>
    client.delete(`/events/${eventId}/members/${memberId}`);
