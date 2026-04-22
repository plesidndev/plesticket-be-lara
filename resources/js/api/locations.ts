import type { AxiosResponse } from 'axios';
import client from './client';
import type { PaginatedResponse, Province, City } from '../types';

export const listProvinces = (params?: Record<string, unknown>): Promise<AxiosResponse<PaginatedResponse<Province>>> =>
    client.get('/provinces', { params });

export const listCities = (params?: Record<string, unknown>): Promise<AxiosResponse<PaginatedResponse<City>>> =>
    client.get('/cities', { params });
