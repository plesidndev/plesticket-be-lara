import { useState, useEffect, type ReactNode } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { adminGetEvent, verifyEvent, rejectEvent, suspendEvent } from '../../api/events';
import Layout from '../../components/Layout';
import StatusBadge from '../../components/StatusBadge';
import Modal from '../../components/Modal';
import type { Event } from '../../types';

export default function AdminEventDetail() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const [event, setEvent] = useState<Event | null>(null);
    const [rejectModal, setRejectModal] = useState(false);
    const [reason, setReason] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const load = async () => {
        try {
            const res = await adminGetEvent(id!);
            setEvent(res.data.data);
        } catch {
            navigate('/plest-admin/events');
        }
    };

    useEffect(() => { load(); }, [id]);

    const action = async (fn: (...args: string[]) => Promise<unknown>, ...args: string[]) => {
        setLoading(true);
        setError('');
        try {
            await fn(...args);
            load();
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            setError(axiosErr.response?.data?.message ?? 'Action failed.');
        } finally {
            setLoading(false);
        }
    };

    const handleReject = async () => {
        if (!reason.trim()) return;
        await action(rejectEvent, id!, reason);
        setRejectModal(false);
        setReason('');
    };

    if (!event) return <Layout><div className="text-gray-400 text-sm">Loading…</div></Layout>;

    const canVerify  = event.verification_status === 'pending';
    const canReject  = event.verification_status === 'pending';
    const canSuspend = event.verification_status === 'verified';

    return (
        <Layout>
            <div className="flex items-center gap-3 mb-6">
                <button onClick={() => navigate(-1)} className="text-gray-400 hover:text-gray-600 text-sm">← Back</button>
                <h1 className="text-xl font-bold text-gray-900">{event.title}</h1>
                <StatusBadge status={event.verification_status} />
            </div>

            {error && <div className="bg-red-50 text-red-700 text-sm px-4 py-3 rounded-lg mb-4">{error}</div>}

            <div className="flex gap-3 mb-6">
                {canVerify && (
                    <button onClick={() => action(verifyEvent, id!)} disabled={loading}
                        className="bg-green-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-green-700 disabled:opacity-50">
                        Verify
                    </button>
                )}
                {canReject && (
                    <button onClick={() => setRejectModal(true)} disabled={loading}
                        className="bg-red-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-red-700 disabled:opacity-50">
                        Reject
                    </button>
                )}
                {canSuspend && (
                    <button onClick={() => action(suspendEvent, id!)} disabled={loading}
                        className="bg-gray-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-gray-700 disabled:opacity-50">
                        Suspend
                    </button>
                )}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 className="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Event Info</h2>
                    <dl className="space-y-2 text-sm">
                        <Row label="Event ID" value={event.event_id} />
                        <Row label="Slug" value={event.slug} />
                        <Row label="Category" value={event.category ?? '—'} />
                        <Row label="Description" value={event.description ?? '—'} />
                        {event.rejection_reason && <Row label="Rejection Reason" value={event.rejection_reason} className="text-red-600" />}
                        {event.verified_at && <Row label="Verified At" value={new Date(event.verified_at).toLocaleString()} />}
                    </dl>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 className="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">PIC (Person in Charge)</h2>
                    <dl className="space-y-2 text-sm">
                        <Row label="Name" value={event.pic?.name} />
                        <Row label="Identity Type" value={event.pic?.identity_type_label} />
                        <Row label="Identity Number" value={event.pic?.identity_number ?? '—'} />
                        <Row label="NPWP" value={event.pic?.npwp ?? '—'} />
                    </dl>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 className="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Schedule</h2>
                    <dl className="space-y-2 text-sm">
                        <Row label="Start" value={`${event.schedule?.start_date ?? '—'} ${event.schedule?.start_time ?? ''}`.trim()} />
                        <Row label="End" value={`${event.schedule?.end_date ?? '—'} ${event.schedule?.end_time ?? ''}`.trim()} />
                    </dl>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 className="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Location</h2>
                    <dl className="space-y-2 text-sm">
                        <Row label="Online" value={event.location?.is_online ? 'Yes' : 'No'} />
                        <Row label="Venue" value={event.location?.venue_name ?? '—'} />
                        <Row label="Address" value={event.location?.address ?? '—'} />
                        <Row label="City" value={event.location?.city ?? '—'} />
                        <Row label="Province" value={event.location?.province ?? '—'} />
                        {event.location?.latitude && (
                            <Row label="Coordinates" value={`${event.location.latitude}, ${event.location.longitude}`} />
                        )}
                    </dl>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 className="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Organizer</h2>
                    <dl className="space-y-2 text-sm">
                        <Row label="Name" value={event.organizer?.name} />
                        <Row label="Email" value={event.organizer?.email} />
                        <Row label="UID" value={event.organizer?.uid} />
                    </dl>
                </div>

                {event.ticket_types && event.ticket_types.length > 0 && (
                    <div className="bg-white rounded-xl border border-gray-200 p-5 lg:col-span-2">
                        <h2 className="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Ticket Types</h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-gray-500 text-xs border-b border-gray-100">
                                    <tr>
                                        <th className="pb-2 text-left">Name</th>
                                        <th className="pb-2 text-left">Price</th>
                                        <th className="pb-2 text-left">Quota</th>
                                        <th className="pb-2 text-left">Sale Period</th>
                                        <th className="pb-2 text-left">Active</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {event.ticket_types.map(t => (
                                        <tr key={t.id}>
                                            <td className="py-2 font-medium">{t.name}</td>
                                            <td className="py-2">{Number(t.price) === 0 ? 'Free' : `Rp ${Number(t.price).toLocaleString('id-ID')}`}</td>
                                            <td className="py-2">{t.quota}</td>
                                            <td className="py-2 text-xs text-gray-500">
                                                {t.sale_start ? new Date(t.sale_start).toLocaleDateString() : '—'} → {t.sale_end ? new Date(t.sale_end).toLocaleDateString() : '—'}
                                            </td>
                                            <td className="py-2">
                                                <span className={`px-2 py-0.5 rounded-full text-xs ${t.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                                    {t.is_active ? 'Yes' : 'No'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {event.verified_by && (
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h2 className="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Verified By</h2>
                        <dl className="space-y-2 text-sm">
                            <Row label="Name" value={event.verified_by?.name} />
                            <Row label="Email" value={event.verified_by?.email} />
                        </dl>
                    </div>
                )}
            </div>

            {rejectModal && (
                <Modal title="Reject Event" onClose={() => setRejectModal(false)}>
                    <div className="space-y-3">
                        <div>
                            <label className="text-sm font-medium text-gray-700">Reason</label>
                            <textarea
                                rows={4}
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                placeholder="Explain why this event is being rejected…"
                                className="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"
                            />
                        </div>
                        <div className="flex gap-2">
                            <button
                                onClick={handleReject}
                                disabled={!reason.trim() || loading}
                                className="flex-1 bg-red-600 text-white text-sm py-2 rounded-lg hover:bg-red-700 disabled:opacity-50"
                            >
                                Confirm Reject
                            </button>
                            <button onClick={() => setRejectModal(false)} className="flex-1 border border-gray-300 text-sm py-2 rounded-lg hover:bg-gray-50">
                                Cancel
                            </button>
                        </div>
                    </div>
                </Modal>
            )}
        </Layout>
    );
}

interface RowProps {
    label: string;
    value?: string | null;
    className?: string;
}

function Row({ label, value, className = 'text-gray-900' }: RowProps): ReactNode {
    return (
        <div className="flex gap-2">
            <dt className="text-gray-500 w-36 shrink-0">{label}</dt>
            <dd className={`font-medium ${className}`}>{value ?? '—'}</dd>
        </div>
    );
}
