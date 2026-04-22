import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { AuthProvider } from './contexts/AuthContext';
import Router from './router';
import '../css/app.css';

const root = document.getElementById('app');
if (!root) throw new Error('Root element #app not found');

createRoot(root).render(
    <StrictMode>
        <AuthProvider>
            <Router />
        </AuthProvider>
    </StrictMode>
);
