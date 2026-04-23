import { type ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

interface Props {
    children: ReactNode;
    adminOnly?: boolean;
}

export default function ProtectedRoute({ children, adminOnly = false }: Props) {
    const { user } = useAuth();

    if (!user) return <Navigate to={adminOnly ? '/plest-admin/login' : '/admin/login'} replace />;
    if (adminOnly && user.role !== 'SUPER_ADMIN') return <Navigate to="/admin/events" replace />;
    if (!adminOnly && user.role === 'SUPER_ADMIN') return <Navigate to="/plest-admin/events" replace />;
    if (!adminOnly && user.role === 'BUYER') return <Navigate to="/home" replace />;

    return <>{children}</>;
}
