import { useState, useRef } from 'react';
import { useAuth } from '../contexts/AuthContext';
import client from '../api/client';
import Layout from '../components/Layout';

export default function Profile() {
    const { user } = useAuth();
    const [uploading, setUploading] = useState(false);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [preview, setPreview] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const handleFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setPreview(URL.createObjectURL(file));
    };

    const upload = async () => {
        const file = inputRef.current?.files?.[0];
        if (!file) return;

        const form = new FormData();
        form.append('photo', file);

        setUploading(true);
        setMessage('');
        setError('');
        try {
            await client.post('/profile/photo', form);
            setMessage('Photo updated successfully.');
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            setError(axiosErr.response?.data?.message ?? 'Upload failed.');
        } finally {
            setUploading(false);
        }
    };

    return (
        <Layout>
            <h1 className="text-xl font-bold text-gray-900 mb-6">Profile</h1>

            <div className="bg-white rounded-xl border border-gray-200 p-6 max-w-md">
                <div className="flex items-center gap-4 mb-6">
                    <div className="w-16 h-16 rounded-full bg-indigo-100 flex items-center justify-center overflow-hidden">
                        {preview ? (
                            <img src={preview} className="w-full h-full object-cover" alt="preview" />
                        ) : user?.photo ? (
                            <img src={`/storage/${user.photo}`} className="w-full h-full object-cover" alt="avatar" />
                        ) : (
                            <span className="text-2xl font-bold text-indigo-400">{user?.name?.[0]?.toUpperCase()}</span>
                        )}
                    </div>
                    <div>
                        <p className="font-semibold text-gray-900">{user?.name}</p>
                        <p className="text-sm text-gray-500">{user?.email}</p>
                        <p className="text-xs text-indigo-600 font-medium mt-0.5">{user?.role}</p>
                    </div>
                </div>

                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3 text-sm">
                        <div><span className="text-gray-500">Username</span><p className="font-medium">{user?.username ?? '—'}</p></div>
                        <div><span className="text-gray-500">Phone</span><p className="font-medium">{user?.phone ?? '—'}</p></div>
                        <div><span className="text-gray-500">Date of Birth</span><p className="font-medium">{user?.date_of_birth ?? '—'}</p></div>
                        <div><span className="text-gray-500">UID</span><p className="font-medium">{user?.uid}</p></div>
                    </div>

                    <div className="border-t border-gray-100 pt-4">
                        <p className="text-sm font-medium text-gray-700 mb-2">Update Photo</p>
                        <input ref={inputRef} type="file" accept="image/*" onChange={handleFile} className="text-sm text-gray-600" />
                        <button
                            onClick={upload}
                            disabled={uploading}
                            className="mt-3 w-full bg-indigo-600 text-white text-sm py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                        >
                            {uploading ? 'Uploading…' : 'Upload Photo'}
                        </button>
                        {message && <p className="text-green-600 text-sm mt-2">{message}</p>}
                        {error && <p className="text-red-600 text-sm mt-2">{error}</p>}
                    </div>
                </div>
            </div>
        </Layout>
    );
}
