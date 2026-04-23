import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { buyerRegister } from '../../api/auth';
import { useAuth } from '../../contexts/AuthContext';

export default function BuyerRegister() {
    const navigate = useNavigate();
    const { login: storeLogin } = useAuth();
    const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '', phone: '' });
    const [error, setError] = useState('');
    const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
    const [loading, setLoading] = useState(false);

    const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
        setForm(f => ({ ...f, [key]: e.target.value }));

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setFieldErrors({});
        setLoading(true);
        try {
            const payload: Record<string, string> = {
                name: form.name,
                email: form.email,
                password: form.password,
                password_confirmation: form.password_confirmation,
            };
            if (form.phone) payload.phone = form.phone;

            const res = await buyerRegister(payload);
            const { token, user } = res.data.data;
            storeLogin(token, user);
            navigate('/home', { replace: true });
        } catch (err: unknown) {
            const data = (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })
                ?.response?.data;
            setFieldErrors(data?.errors ?? {});
            setError(data?.message ?? 'Registration failed. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const fieldError = (key: string) => fieldErrors[key]?.[0];

    return (
        <div className="min-h-screen bg-zinc-950 flex items-center justify-center px-4 py-10">
            <div className="w-full max-w-sm">
                {/* Logo */}
                <div className="text-center mb-8">
                    <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-violet-600 mb-4">
                        <span className="text-2xl">🎟️</span>
                    </div>
                    <h1 className="text-2xl font-bold text-zinc-50">Create account</h1>
                    <p className="text-sm text-zinc-400 mt-1">Join Plesticket and discover events</p>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && !Object.keys(fieldErrors).length && (
                        <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-xl">
                            {error}
                        </div>
                    )}

                    <Field label="Full Name" error={fieldError('name')}>
                        <input
                            type="text"
                            value={form.name}
                            onChange={set('name')}
                            required
                            placeholder="Your full name"
                            className={inputCls(fieldError('name'))}
                        />
                    </Field>

                    <Field label="Email" error={fieldError('email')}>
                        <input
                            type="email"
                            value={form.email}
                            onChange={set('email')}
                            required
                            placeholder="you@example.com"
                            className={inputCls(fieldError('email'))}
                        />
                    </Field>

                    <Field label="Phone (optional)" error={fieldError('phone')}>
                        <input
                            type="tel"
                            value={form.phone}
                            onChange={set('phone')}
                            placeholder="+62 812 3456 7890"
                            className={inputCls(fieldError('phone'))}
                        />
                    </Field>

                    <Field label="Password" error={fieldError('password')}>
                        <input
                            type="password"
                            value={form.password}
                            onChange={set('password')}
                            required
                            placeholder="Min. 8 characters"
                            className={inputCls(fieldError('password'))}
                        />
                    </Field>

                    <Field label="Confirm Password" error={fieldError('password_confirmation')}>
                        <input
                            type="password"
                            value={form.password_confirmation}
                            onChange={set('password_confirmation')}
                            required
                            placeholder="Repeat password"
                            className={inputCls(fieldError('password_confirmation'))}
                        />
                    </Field>

                    <button
                        type="submit"
                        disabled={loading}
                        className="w-full bg-violet-600 hover:bg-violet-500 disabled:opacity-50 disabled:pointer-events-none text-white font-semibold py-3 rounded-xl transition-colors mt-2"
                    >
                        {loading ? 'Creating account…' : 'Create account'}
                    </button>
                </form>

                <p className="text-center text-sm text-zinc-500 mt-6">
                    Already have an account?{' '}
                    <Link to="/login" className="text-violet-400 hover:text-violet-300 font-medium">
                        Sign in
                    </Link>
                </p>
            </div>
        </div>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="block text-xs font-medium text-zinc-400 mb-1.5">{label}</label>
            {children}
            {error && <p className="text-xs text-red-400 mt-1">{error}</p>}
        </div>
    );
}

function inputCls(error?: string) {
    return `w-full bg-zinc-900 border ${error ? 'border-red-500/60' : 'border-zinc-800'} rounded-xl px-4 py-3 text-sm text-zinc-50 placeholder:text-zinc-600 focus:outline-none focus:border-violet-500 transition-colors`;
}
