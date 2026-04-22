import { useState, useEffect, useCallback } from 'react';
import { adminListCategories, createCategory, updateCategory, deleteCategory } from '../../api/categories';
import Layout from '../../components/Layout';
import Pagination from '../../components/Pagination';
import Modal from '../../components/Modal';
import type { Category, PaginationMeta } from '../../types';

interface CategoryForm {
    name: string;
    is_active: boolean;
}

type ModalState = null | 'create' | Category;

type FieldErrors = Partial<Record<string, string[]>>;

export default function AdminCategories() {
    const [categories, setCategories] = useState<Category[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [modal, setModal] = useState<ModalState>(null);
    const [form, setForm] = useState<CategoryForm>({ name: '', is_active: true });
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<FieldErrors>({});

    const load = useCallback(async () => {
        try {
            const res = await adminListCategories({ search, page, limit: 15 });
            setCategories(res.data.data);
            setMeta(res.data.meta);
        } catch { /* ignore */ }
    }, [search, page]);

    useEffect(() => { load(); }, [load]);

    const openCreate = () => { setForm({ name: '', is_active: true }); setErrors({}); setModal('create'); };
    const openEdit = (c: Category) => { setForm({ name: c.name, is_active: c.is_active }); setErrors({}); setModal(c); };

    const save = async () => {
        setSaving(true);
        setErrors({});
        try {
            if (modal === 'create') {
                await createCategory(form);
            } else {
                await updateCategory((modal as Category).id, form);
            }
            setModal(null);
            load();
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { errors?: FieldErrors; message?: string } } };
            if (axiosErr.response?.data?.errors) setErrors(axiosErr.response.data.errors);
            else setErrors({ general: [axiosErr.response?.data?.message ?? 'Failed.'] });
        } finally {
            setSaving(false);
        }
    };

    const remove = async (id: number) => {
        if (!confirm('Delete this category?')) return;
        try { await deleteCategory(id); load(); } catch { /* ignore */ }
    };

    const isCreate = modal === 'create';

    return (
        <Layout>
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-xl font-bold text-gray-900">Categories</h1>
                <button onClick={openCreate} className="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700">
                    + New Category
                </button>
            </div>

            <div className="mb-4">
                <input
                    type="search"
                    placeholder="Search categories…"
                    value={search}
                    onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                    className="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                />
            </div>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                        <tr>
                            <th className="px-4 py-3 text-left">ID</th>
                            <th className="px-4 py-3 text-left">Name</th>
                            <th className="px-4 py-3 text-left">Status</th>
                            <th className="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {categories.map(c => (
                            <tr key={c.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3 text-gray-400 text-xs">{c.id}</td>
                                <td className="px-4 py-3 font-medium text-gray-900">{c.name}</td>
                                <td className="px-4 py-3">
                                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${c.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                        {c.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </td>
                                <td className="px-4 py-3 flex gap-2">
                                    <button onClick={() => openEdit(c)} className="text-indigo-600 hover:underline text-xs">Edit</button>
                                    <button onClick={() => remove(c.id)} className="text-red-500 hover:underline text-xs">Delete</button>
                                </td>
                            </tr>
                        ))}
                        {categories.length === 0 && (
                            <tr><td colSpan={4} className="text-center py-8 text-gray-400">No categories found.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            <Pagination meta={meta} onPageChange={setPage} />

            {modal && (
                <Modal
                    title={isCreate ? 'New Category' : `Edit — ${(modal as Category).name}`}
                    onClose={() => setModal(null)}
                >
                    <div className="space-y-3">
                        {errors.general && <p className="text-red-600 text-sm">{errors.general[0]}</p>}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                placeholder="e.g. Music"
                                className={`w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 ${errors.name ? 'border-red-400' : 'border-gray-300'}`}
                            />
                            {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name[0]}</p>}
                        </div>
                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="cat_active"
                                checked={form.is_active}
                                onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                            />
                            <label htmlFor="cat_active" className="text-sm text-gray-700">Active</label>
                        </div>
                        <div className="flex gap-2 pt-2">
                            <button
                                onClick={save}
                                disabled={saving}
                                className="flex-1 bg-indigo-600 text-white text-sm py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {saving ? 'Saving…' : isCreate ? 'Create' : 'Save'}
                            </button>
                            <button onClick={() => setModal(null)} className="flex-1 border border-gray-300 text-sm py-2 rounded-lg hover:bg-gray-50">
                                Cancel
                            </button>
                        </div>
                    </div>
                </Modal>
            )}
        </Layout>
    );
}
