import { type ReactNode } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

const navItem = 'flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors';
const active   = 'bg-indigo-100 text-indigo-700';
const inactive = 'text-gray-600 hover:bg-gray-100';

export default function Layout({ children }: { children: ReactNode }) {
    const { user, isAdmin, logout } = useAuth();
    const navigate = useNavigate();

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    return (
        <div className="flex h-screen bg-gray-50">
            <aside className="w-56 bg-white border-r border-gray-200 flex flex-col">
                <div className="p-4 border-b border-gray-200">
                    <span className="text-lg font-bold text-indigo-600">Plesticket</span>
                </div>

                <nav className="flex-1 p-3 space-y-1">
                    {isAdmin ? (
                        <>
                            <NavLink to="/plest-admin/events" className={({ isActive }) => `${navItem} ${isActive ? active : inactive}`}>Events</NavLink>
                            <NavLink to="/plest-admin/users" className={({ isActive }) => `${navItem} ${isActive ? active : inactive}`}>Users</NavLink>
                            <NavLink to="/plest-admin/categories" className={({ isActive }) => `${navItem} ${isActive ? active : inactive}`}>Categories</NavLink>
                        </>
                    ) : (
                        <NavLink to="/admin/events" className={({ isActive }) => `${navItem} ${isActive ? active : inactive}`}>My Events</NavLink>
                    )}
                    <NavLink to="/admin/profile" className={({ isActive }) => `${navItem} ${isActive ? active : inactive}`}>Profile</NavLink>
                </nav>

                <div className="p-3 border-t border-gray-200">
                    <div className="text-xs text-gray-500 mb-1 px-2">{user?.name}</div>
                    <div className="text-xs text-gray-400 px-2 mb-2">{user?.role}</div>
                    <button onClick={handleLogout} className="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                        Logout
                    </button>
                </div>
            </aside>

            <main className="flex-1 overflow-auto">
                <div className="p-6">{children}</div>
            </main>
        </div>
    );
}
