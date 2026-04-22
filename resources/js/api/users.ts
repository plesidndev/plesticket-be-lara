import type { AxiosResponse } from 'axios';
import client from './client';
import type { ApiResponse, PaginatedResponse, User } from '../types';

export const listUsers = (params?: Record<string, unknown>): Promise<AxiosResponse<PaginatedResponse<User>>> =>
    client.get('/users', { params });

export const getUser = (uid: string): Promise<AxiosResponse<ApiResponse<User>>> =>
    client.get(`/users/${uid}`);

export const updateUser = (uid: string, data: Record<string, unknown>): Promise<AxiosResponse<ApiResponse<User>>> =>
    client.put(`/users/${uid}`, data);

export const deleteUser = (uid: string): Promise<AxiosResponse<ApiResponse<null>>> =>
    client.delete(`/users/${uid}`);
