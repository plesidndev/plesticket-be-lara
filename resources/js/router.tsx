import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from './contexts/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Home from './pages/Home';
import EventList from './pages/EventList';
import EventDetail from './pages/EventDetail';
import BuyerLogin from './pages/auth/Login';
import BuyerRegister from './pages/auth/Register';
import Login from './pages/Login';
import Register from './pages/Register';
import Profile from './pages/Profile';
import AdminLogin from './pages/admin/Login';
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
    if (user?.role === 'SUPER_ADMIN') return <Navigate to="/plest-admin/events" replace />;
    if (user?.role === 'REGISTERED_USER') return <Navigate to="/admin/events" replace />;
    return <Navigate to="/home" replace />;
}

export default function Router() {
    return (
        <BrowserRouter>
            <Routes>
                {/* Public routes */}
                <Route path="/home" element={<Home />} />
                <Route path="/events" element={<EventList />} />
                <Route path="/events/:slug" element={<EventDetail />} />
                <Route path="/login" element={<BuyerLogin />} />
                <Route path="/register" element={<BuyerRegister />} />
                <Route path="/admin/login" element={<Login />} />
                <Route path="/admin/register" element={<Register />} />
                <Route path="/plest-admin/login" element={<AdminLogin />} />
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
