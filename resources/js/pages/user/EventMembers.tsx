import { useState, useEffect, useCallback, type ReactNode } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { listMembers, addMember, updateMember, removeMember } from '../../api/members';
import Layout from '../../components/Layout';
import Pagination from '../../components/Pagination';
import Modal from '../../components/Modal';
import type { OrganizerMember, PaginationMeta, OrganizerRoleValue } from '../../types';

const ROLES: OrganizerRoleValue[] = ['EO_STAFF', 'GATE_OFFICER', 'MITRA_TICKET_BOX', 'BAND', 'MEDIA', 'SPONSOR'];

interface MemberForm {
    name: string;
    email: string;
    password: string;
    role: OrganizerRoleValue;
    is_active?: boolean;
}

const emptyForm: MemberForm = { name: '', email: '', password: '', role: 'EO_STAFF' };

type ModalState = null | 'add' | OrganizerMember;

type FieldErrors = Partial<Record<string, string[]>>;

export default function EventMembers() {
    const { id: eventId } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const [members, setMembers] = useState<OrganizerMember[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState<ModalState>(null);
    const [form, setForm] = useState<MemberForm>(emptyForm);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<FieldErrors>({});

    const load = useCallback(async () => {
        try {
            const res = await listMembers(eventId!, { page, limit: 15 });
            setMembers(res.data.data);
            setMeta(res.data.meta);
        } catch {
            navigate('/admin/events');
        }
    }, [eventId, page, navigate]);

    useEffect(() => { load(); }, [load]);

    const openAdd = () => { setForm(emptyForm); setErrors({}); setModal('add'); };
    const openEdit = (m: OrganizerMember) => {
        setForm({ name: m.name, email: m.email ?? '', password: '', role: m.role, is_active: m.is_active });
        setErrors({});
        setModal(m);
    };

    const save = async () => {
        setSaving(true);
        setErrors({});
        try {
            if (modal === 'add') {
                await addMember(eventId!, form);
            } else {
                const payload = { ...form };
                if (!payload.password) delete payload.password;
                await updateMember(eventId!, (modal as OrganizerMember).id, payload);
            }
            setModal(null);
            load();
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { errors?: FieldErrors } } };
            if (axiosErr.response?.data?.errors) setErrors(axiosErr.response.data.errors);
        } finally {
            setSaving(false);
        }
    };

    const remove = async (memberId: string) => {
        if (!confirm('Remove this member?')) return;
        try { await removeMember(eventId!, memberId); load(); } catch { /* ignore */ }
    };

    const isAdd = modal === 'add';

    return (
        <Layout>
            <div className="flex items-center gap-3 mb-6">
                <button onClick={() => navigate('/admin/events')} className="text-gray-400 hover:text-gray-600 text-sm">← Back</button>
                <h1 className="text-xl font-bold text-gray-900">Organizer Members</h1>
            </div>

            <div className="flex justify-end mb-4">
                <button onClick={openAdd} className="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700">
                    + Add Member
                </button>
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
                        {members.map(m => (
                            <tr key={m.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3 font-mono text-xs text-gray-500">{m.uid ?? '—'}</td>
                                <td className="px-4 py-3 font-medium text-gray-900">{m.name}</td>
                                <td className="px-4 py-3 text-gray-600">{m.email ?? '—'}</td>
                                <td className="px-4 py-3">
                                    <span className="px-2 py-0.5 bg-indigo-50 text-indigo-700 text-xs rounded-full font-medium">{m.role}</span>
                                </td>
                                <td className="px-4 py-3">
                                    <span className={`px-2 py-0.5 rounded-full text-xs ${m.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                        {m.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </td>
                                <td className="px-4 py-3 flex gap-2">
                                    <button onClick={() => openEdit(m)} className="text-indigo-600 hover:underline text-xs">Edit</button>
                                    <button onClick={() => remove(m.id)} className="text-red-500 hover:underline text-xs">Remove</button>
                                </td>
                            </tr>
                        ))}
                        {members.length === 0 && (
                            <tr><td colSpan={6} className="text-center py-8 text-gray-400">No members yet.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            <Pagination meta={meta} onPageChange={setPage} />

            {modal && (
                <Modal
                    title={isAdd ? 'Add Member' : `Edit — ${(modal as OrganizerMember).name}`}
                    onClose={() => setModal(null)}
                >
                    <div className="space-y-3">
                        <FormField label="Name *" error={errors.name}>
                            <input
                                value={form.name} required
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                className={inputCls(!!errors.name)}
                            />
                        </FormField>
                        <FormField label="Email" error={errors.email}>
                            <input
                                type="email" value={form.email}
                                onChange={(e) => setForm({ ...form, email: e.target.value })}
                                className={inputCls(!!errors.email)}
                                placeholder="Optional"
                            />
                        </FormField>
                        <FormField label={isAdd ? 'Password *' : 'New Password'} error={errors.password}>
                            <input
                                type="password" value={form.password} required={isAdd}
                                onChange={(e) => setForm({ ...form, password: e.target.value })}
                                className={inputCls(!!errors.password)}
                                placeholder={isAdd ? 'Min 8 characters' : 'Leave blank to keep current'}
                            />
                        </FormField>
                        <FormField label="Role *" error={errors.role}>
                            <select
                                value={form.role}
                                onChange={(e) => setForm({ ...form, role: e.target.value as OrganizerRoleValue })}
                                className={inputCls(!!errors.role)}
                            >
                                {ROLES.map(r => <option key={r} value={r}>{r}</option>)}
                            </select>
                        </FormField>
                        {!isAdd && (
                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox" id="is_active"
                                    checked={form.is_active ?? true}
                                    onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                                />
                                <label htmlFor="is_active" className="text-sm text-gray-700">Active</label>
                            </div>
                        )}
                        <div className="flex gap-2 pt-2">
                            <button
                                onClick={save} disabled={saving}
                                className="flex-1 bg-indigo-600 text-white text-sm py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {saving ? 'Saving…' : isAdd ? 'Add' : 'Save'}
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

const inputCls = (hasErr: boolean) =>
    `w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 ${hasErr ? 'border-red-400' : 'border-gray-300'}`;

interface FormFieldProps {
    label: string;
    error?: string[];
    children: ReactNode;
}

function FormField({ label, error, children }: FormFieldProps) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
            {children}
            {error && <p className="text-red-500 text-xs mt-1">{Array.isArray(error) ? error[0] : error}</p>}
        </div>
    );
}
