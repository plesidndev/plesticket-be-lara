import { createContext, useContext, useState, type ReactNode } from 'react';
import { logout as apiLogout } from '../api/auth';
import type { User } from '../types';

interface AuthContextValue {
    user: User | null;
    token: string | null;
    isAdmin: boolean;
    login: (token: string, user: User) => void;
    logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(() => {
        try { return JSON.parse(localStorage.getItem('user') ?? 'null'); } catch { return null; }
    });
    const [token, setToken] = useState<string | null>(() => localStorage.getItem('token'));

    const login = (token: string, user: User) => {
        localStorage.setItem('token', token);
        localStorage.setItem('user', JSON.stringify(user));
        setToken(token);
        setUser(user);
    };

    const logout = async () => {
        try { await apiLogout(); } catch { /* ignore */ }
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        setToken(null);
        setUser(null);
    };

    const isAdmin = user?.role === 'SUPER_ADMIN';

    return (
        <AuthContext.Provider value={{ user, token, isAdmin, login, logout }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth(): AuthContextValue {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error('useAuth must be used within AuthProvider');
    return ctx;
}
