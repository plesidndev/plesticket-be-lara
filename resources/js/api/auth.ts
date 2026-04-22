import type { AxiosResponse } from 'axios';
import client from './client';
import type { ApiResponse, User } from '../types';

interface LoginData {
    token: string;
    user: User;
}

export const login = (email: string, password: string): Promise<AxiosResponse<ApiResponse<LoginData>>> =>
    client.post('/auth/login', { email, password });

export const register = (data: Record<string, string>): Promise<AxiosResponse<ApiResponse<LoginData>>> =>
    client.post('/auth/register', data);

export const me = (): Promise<AxiosResponse<ApiResponse<User>>> =>
    client.get('/auth/me');

export const logout = (): Promise<AxiosResponse<ApiResponse<null>>> =>
    client.post('/auth/logout');
