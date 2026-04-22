import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from './contexts/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Login from './pages/Login';
import Register from './pages/Register';
import Profile from './pages/Profile';
import AdminUsers from './pages/admin/Users';
import AdminEvents from './pages/admin/Events';
import AdminEventDetail from './pages/admin/EventDetail';
import AdminCategories from './pages/admin/Categories';
import MyEvents from './pages/user/MyEvents';
import EventForm from './pages/user/EventForm';
import EventMembers from './pages/user/EventMembers';
import UserEventDetail from './pages/user/EventDetail';

function Root() {
    const { user } = useAuth();
    if (!user) return <Navigate to="/login" replace />;
    return <Navigate to={user.role === 'SUPER_ADMIN' ? '/plest-admin/events' : '/admin/events'} replace />;
}

export default function Router() {
    return (
        <BrowserRouter>
            <Routes>
                <Route path="/login" element={<Login />} />
                <Route path="/register" element={<Register />} />
                <Route path="/" element={<Root />} />

                {/* Super Admin routes */}
                <Route path="/plest-admin/events" element={<ProtectedRoute adminOnly><AdminEvents /></ProtectedRoute>} />
                <Route path="/plest-admin/events/:id" element={<ProtectedRoute adminOnly><AdminEventDetail /></ProtectedRoute>} />
                <Route path="/plest-admin/users" element={<ProtectedRoute adminOnly><AdminUsers /></ProtectedRoute>} />
                <Route path="/plest-admin/categories" element={<ProtectedRoute adminOnly><AdminCategories /></ProtectedRoute>} />

                {/* Registered user routes */}
                <Route path="/admin/events" element={<ProtectedRoute><MyEvents /></ProtectedRoute>} />
                <Route path="/admin/events/create" element={<ProtectedRoute><EventForm /></ProtectedRoute>} />
                <Route path="/admin/events/:id" element={<ProtectedRoute><UserEventDetail /></ProtectedRoute>} />
                <Route path="/admin/events/:id/edit" element={<ProtectedRoute><EventForm /></ProtectedRoute>} />
                <Route path="/admin/events/:id/members" element={<ProtectedRoute><EventMembers /></ProtectedRoute>} />
                <Route path="/admin/profile" element={<ProtectedRoute><Profile /></ProtectedRoute>} />

                <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
        </BrowserRouter>
    );
}
