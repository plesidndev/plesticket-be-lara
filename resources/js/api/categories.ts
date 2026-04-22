import type { AxiosResponse } from 'axios';
import client from './client';
import type { ApiResponse, PaginatedResponse, Category } from '../types';

export const listCategories = (): Promise<AxiosResponse<ApiResponse<Category[]>>> =>
    client.get('/categories');

export const adminListCategories = (params?: Record<string, unknown>): Promise<AxiosResponse<PaginatedResponse<Category>>> =>
    client.get('/admin/categories', { params });

export const createCategory = (data: Record<string, unknown>): Promise<AxiosResponse<ApiResponse<Category>>> =>
    client.post('/admin/categories', data);

export const updateCategory = (id: number, data: Record<string, unknown>): Promise<AxiosResponse<ApiResponse<Category>>> =>
    client.put(`/admin/categories/${id}`, data);

export const deleteCategory = (id: number): Promise<AxiosResponse<ApiResponse<null>>> =>
    client.delete(`/admin/categories/${id}`);
