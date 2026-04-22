import { useState, type FormEvent, type ChangeEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import client from '../api/client';
import { useAuth } from '../contexts/AuthContext';

interface FormState {
    name: string;
    username: string;
    email: string;
    password: string;
    phone: string;
    date_of_birth: string;
}

type FieldErrors = Partial<Record<keyof FormState | 'general', string[]>>;

export default function Register() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const [form, setForm] = useState<FormState>({
        name: '', username: '', email: '', password: '',
        phone: '', date_of_birth: '',
    });
    const [errors, setErrors] = useState<FieldErrors>({});
    const [loading, setLoading] = useState(false);

    const set = (key: keyof FormState, val: string) =>
        setForm(f => ({ ...f, [key]: val }));

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setErrors({});
        setLoading(true);
        try {
            const res = await client.post('/auth/register', form);
            const { token, user } = res.data.data;
            login(token, user);
            navigate('/events');
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { errors?: FieldErrors; message?: string } } };
            if (axiosErr.response?.data?.errors) {
                setErrors(axiosErr.response.data.errors);
            } else {
                setErrors({ general: [axiosErr.response?.data?.message ?? 'Registration failed.'] });
            }
        } finally {
            setLoading(false);
        }
    };

    const inputClass = (key: keyof FormState) =>
        `w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 ${errors[key] ? 'border-red-400' : 'border-gray-300'}`;

    const inputProps = (key: keyof FormState) => ({
        value: form[key],
        onChange: (e: ChangeEvent<HTMLInputElement>) => set(key, e.target.value),
        className: inputClass(key),
    });

    return (
        <div className="min-h-screen flex items-center justify-center bg-linear-to-br from-indigo-50 to-gray-100 py-8">
            <div className="bg-white rounded-2xl shadow-lg w-full max-w-md p-8">
                <div className="text-center mb-6">
                    <h1 className="text-2xl font-bold text-indigo-600">Plesticket</h1>
                    <p className="text-sm text-gray-500 mt-1">Create your account</p>
                </div>

                {errors.general && (
                    <div className="bg-red-50 text-red-700 text-sm px-4 py-3 rounded-lg mb-4">
                        {errors.general[0]}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                        <input {...inputProps('name')} required placeholder="John Doe" />
                        {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name[0]}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                        <input {...inputProps('username')} required placeholder="johndoe" />
                        {errors.username && <p className="text-red-500 text-xs mt-1">{errors.username[0]}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input {...inputProps('email')} type="email" required placeholder="john@example.com" />
                        {errors.email && <p className="text-red-500 text-xs mt-1">{errors.email[0]}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                        <input {...inputProps('password')} type="password" required placeholder="Min 8 characters" />
                        {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password[0]}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                        <input {...inputProps('phone')} type="tel" required placeholder="08123456789" />
                        {errors.phone && <p className="text-red-500 text-xs mt-1">{errors.phone[0]}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Date of Birth *</label>
                        <input {...inputProps('date_of_birth')} type="date" required />
                        {errors.date_of_birth && <p className="text-red-500 text-xs mt-1">{errors.date_of_birth[0]}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={loading}
                        className="w-full bg-indigo-600 text-white rounded-lg py-2 text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                    >
                        {loading ? 'Creating account…' : 'Create Account'}
                    </button>
                </form>

                <p className="text-center text-sm text-gray-500 mt-5">
                    Already have an account?{' '}
                    <Link to="/login" className="text-indigo-600 hover:underline font-medium">Sign in</Link>
                </p>
            </div>
        </div>
    );
}
