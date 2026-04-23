import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { login } from '../../api/auth';
import { useAuth } from '../../contexts/AuthContext';

export default function BuyerLogin() {
    const navigate = useNavigate();
    const { login: storeLogin } = useAuth();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            const res = await login(email, password);
            const { token, user } = res.data.data;
            if (user.role === 'SUPER_ADMIN') {
                setError('Use the admin portal to sign in.');
                return;
            }
            if (user.role === 'REGISTERED_USER') {
                setError('Use the organizer portal to sign in.');
                return;
            }
            storeLogin(token, user);
            navigate('/home', { replace: true });
        } catch (err: unknown) {
            const msg = (err as { response?: { data?: { message?: string } } })
                ?.response?.data?.message;
            setError(msg ?? 'Login failed. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-zinc-950 flex items-center justify-center px-4">
            <div className="w-full max-w-sm">
                {/* Logo */}
                <div className="text-center mb-8">
                    <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-violet-600 mb-4">
                        <span className="text-2xl">🎟️</span>
                    </div>
                    <h1 className="text-2xl font-bold text-zinc-50">Welcome back</h1>
                    <p className="text-sm text-zinc-400 mt-1">Sign in to your Plesticket account</p>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && (
                        <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-xl">
                            {error}
                        </div>
                    )}

                    <div>
                        <label className="block text-xs font-medium text-zinc-400 mb-1.5">Email</label>
                        <input
                            type="email"
                            value={email}
                            onChange={e => setEmail(e.target.value)}
                            required
                            placeholder="you@example.com"
                            className="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-sm text-zinc-50 placeholder:text-zinc-600 focus:outline-none focus:border-violet-500 transition-colors"
                        />
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-zinc-400 mb-1.5">Password</label>
                        <input
                            type="password"
                            value={password}
                            onChange={e => setPassword(e.target.value)}
                            required
                            placeholder="••••••••"
                            className="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-sm text-zinc-50 placeholder:text-zinc-600 focus:outline-none focus:border-violet-500 transition-colors"
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={loading}
                        className="w-full bg-violet-600 hover:bg-violet-500 disabled:opacity-50 disabled:pointer-events-none text-white font-semibold py-3 rounded-xl transition-colors mt-2"
                    >
                        {loading ? 'Signing in…' : 'Sign in'}
                    </button>
                </form>

                <p className="text-center text-sm text-zinc-500 mt-6">
                    Don't have an account?{' '}
                    <Link to="/register" className="text-violet-400 hover:text-violet-300 font-medium">
                        Sign up
                    </Link>
                </p>

                <div className="mt-8 pt-6 border-t border-zinc-800 text-center space-y-1">
                    <Link to="/admin/login" className="block text-xs text-zinc-600 hover:text-zinc-400 transition-colors">
                        Organizer portal →
                    </Link>
                    <Link to="/plest-admin/login" className="block text-xs text-zinc-600 hover:text-zinc-400 transition-colors">
                        Admin portal →
                    </Link>
                </div>
            </div>
        </div>
    );
}
