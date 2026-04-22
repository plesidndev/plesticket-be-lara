import { useState, useEffect, useCallback } from 'react';
import { listUsers, updateUser, deleteUser } from '../../api/users';
import Layout from '../../components/Layout';
import Pagination from '../../components/Pagination';
import Modal from '../../components/Modal';
import type { User, PaginationMeta } from '../../types';

const ROLES = ['SUPER_ADMIN', 'REGISTERED_USER'] as const;

interface EditForm {
    name: string;
    role: string;
    is_active: boolean;
}

export default function AdminUsers() {
    const [users, setUsers] = useState<User[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [role, setRole] = useState('');
    const [editing, setEditing] = useState<User | null>(null);
    const [form, setForm] = useState<EditForm>({ name: '', role: '', is_active: true });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const load = useCallback(async () => {
        try {
            const res = await listUsers({ search, role, page, limit: 15 });
            setUsers(res.data.data);
            setMeta(res.data.meta);
        } catch { /* ignore */ }
    }, [search, role, page]);

    useEffect(() => { load(); }, [load]);

    const openEdit = (u: User) => {
        setEditing(u);
        setForm({ name: u.name, role: u.role, is_active: u.is_active });
        setError('');
    };

    const save = async () => {
        setSaving(true);
        setError('');
        try {
            await updateUser(editing!.uid, form);
            setEditing(null);
            load();
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            setError(axiosErr.response?.data?.message ?? 'Failed to save.');
        } finally {
            setSaving(false);
        }
    };

    const remove = async (uid: string) => {
        if (!confirm('Delete this user?')) return;
        try { await deleteUser(uid); load(); } catch { /* ignore */ }
    };

    return (
        <Layout>
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-xl font-bold text-gray-900">Users</h1>
            </div>

            <div className="flex gap-3 mb-4">
                <input
                    type="search"
                    placeholder="Search name or email…"
                    value={search}
                    onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                    className="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                />
                <select
                    value={role}
                    onChange={(e) => { setRole(e.target.value); setPage(1); }}
                    className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                >
                    <option value="">All roles</option>
                    {ROLES.map(r => <option key={r} value={r}>{r}</option>)}
                </select>
            </div>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                        <tr>
                            <th className="px-4 py-3 text-left">UID</th>
                            <th className="px-4 py-3 text-left">Name</th>
                            <th className="px-4 py-3 text-left">Email</th>
                            <th className="px-4 py-3 text-left">Role</th>
                            <th className="px-4 py-3 text-left">Active</th>
                            <th className="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {users.map(u => (
                            <tr key={u.uid} className="hover:bg-gray-50">
                                <td className="px-4 py-3 font-mono text-xs text-gray-500">{u.uid}</td>
                                <td className="px-4 py-3 font-medium text-gray-900">{u.name}</td>
                                <td className="px-4 py-3 text-gray-600">{u.email}</td>
                                <td className="px-4 py-3">
                                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${u.role === 'SUPER_ADMIN' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600'}`}>
                                        {u.role}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <span className={`px-2 py-0.5 rounded-full text-xs ${u.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                        {u.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </td>
                                <td className="px-4 py-3 flex gap-2">
                                    <button onClick={() => openEdit(u)} className="text-indigo-600 hover:underline text-xs">Edit</button>
                                    <button onClick={() => remove(u.uid)} className="text-red-500 hover:underline text-xs">Delete</button>
                                </td>
                            </tr>
                        ))}
                        {users.length === 0 && (
                            <tr><td colSpan={6} className="text-center py-8 text-gray-400">No users found.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            <Pagination meta={meta} onPageChange={setPage} />

            {editing && (
                <Modal title={`Edit — ${editing.name}`} onClose={() => setEditing(null)}>
                    <div className="space-y-3">
                        <div>
                            <label className="text-sm font-medium text-gray-700">Name</label>
                            <input
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                className="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                            />
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-700">Role</label>
                            <select
                                value={form.role}
                                onChange={(e) => setForm({ ...form, role: e.target.value })}
                                className="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                            >
                                {ROLES.map(r => <option key={r} value={r}>{r}</option>)}
                            </select>
                        </div>
                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="is_active"
                                checked={form.is_active}
                                onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                            />
                            <label htmlFor="is_active" className="text-sm text-gray-700">Active</label>
                        </div>
                        {error && <p className="text-red-600 text-sm">{error}</p>}
                        <div className="flex gap-2 pt-2">
                            <button
                                onClick={save}
                                disabled={saving}
                                className="flex-1 bg-indigo-600 text-white text-sm py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {saving ? 'Saving…' : 'Save'}
                            </button>
                            <button onClick={() => setEditing(null)} className="flex-1 border border-gray-300 text-sm py-2 rounded-lg hover:bg-gray-50">
                                Cancel
                            </button>
                        </div>
                    </div>
                </Modal>
            )}
        </Layout>
    );
}
