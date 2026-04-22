import { useState, useEffect, type ReactNode } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { myEvents } from '../../api/events';
import Layout from '../../components/Layout';
import StatusBadge from '../../components/StatusBadge';
import type { Event } from '../../types';

export default function UserEventDetail() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const [event, setEvent] = useState<Event | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        myEvents({ limit: 100 }).then(res => {
            const ev = res.data.data.find(e => e.id === id);
            if (!ev) { navigate('/admin/events'); return; }
            setEvent(ev);
        }).finally(() => setLoading(false));
    }, [id, navigate]);

    if (loading) return <Layout><div className="text-gray-400 text-sm">Loading…</div></Layout>;
    if (!event) return null;

    return (
        <Layout>
            <div className="flex items-center gap-3 mb-6">
                <button onClick={() => navigate('/admin/events')} className="text-gray-400 hover:text-gray-600 text-sm">← Back</button>
                <h1 className="text-xl font-bold text-gray-900 truncate">{event.title}</h1>
                <StatusBadge status={event.verification_status} />
                <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${event.is_published ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'}`}>
                    {event.is_published ? 'Active' : 'Inactive'}
                </span>
            </div>

            {event.verification_status === 'rejected' && event.rejection_reason && (
                <div className="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg mb-4">
                    <strong>Rejected:</strong> {event.rejection_reason}
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {/* Basic Info */}
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 className="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Event Info</h2>
                    <dl className="space-y-2 text-sm">
                        <Row label="Event ID" value={event.event_id} />
                        <Row label="Slug" value={event.slug} />
                        <Row label="Category" value={event.category ?? '—'} />
                        <Row label="Description" value={event.description ?? '—'} />
                        {event.banner_url && (
                            <div className="pt-2">
                                <img src={event.banner_url} alt="banner" className="rounded-lg w-full object-cover max-h-40" />
                            </div>
                        )}
                    </dl>
                </div>

                {/* PIC */}
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 className="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Person in Charge</h2>
                    <dl className="space-y-2 text-sm">
                        <Row label="Name" value={event.pic?.name} />
                        <Row label="Identity Type" value={event.pic?.identity_type_label} />
                    </dl>
                </div>

                {/* Schedule */}
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 className="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Schedule</h2>
                    <dl className="space-y-2 text-sm">
                        <Row label="Start" value={`${event.schedule?.start_date ?? '—'} ${event.schedule?.start_time ?? ''}`.trim()} />
                        <Row label="End" value={`${event.schedule?.end_date ?? '—'} ${event.schedule?.end_time ?? ''}`.trim()} />
                    </dl>
                </div>

                {/* Location */}
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 className="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Location</h2>
                    <dl className="space-y-2 text-sm">
                        <Row label="Online" value={event.location?.is_online ? 'Yes' : 'No'} />
                        {!event.location?.is_online && (
                            <>
                                <Row label="Venue" value={event.location?.venue_name ?? '—'} />
                                <Row label="Address" value={event.location?.address ?? '—'} />
                                <Row label="City" value={event.location?.city ?? '—'} />
                                <Row label="Province" value={event.location?.province ?? '—'} />
                                {event.location?.latitude && (
                                    <Row label="Coordinates" value={`${event.location.latitude}, ${event.location.longitude}`} />
                                )}
                            </>
                        )}
                    </dl>
                </div>

                {/* Ticket Types */}
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
            </div>

            {/* Action buttons */}
            <div className="flex gap-3 mt-6">
                {(event.verification_status === 'pending' || event.verification_status === 'rejected') && (
                    <button
                        onClick={() => navigate(`/admin/events/${event.id}/edit`)}
                        className="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700"
                    >
                        Edit Event
                    </button>
                )}
                {event.verification_status === 'verified' && (
                    <button
                        onClick={() => navigate(`/admin/events/${event.id}/members`)}
                        className="bg-green-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-green-700"
                    >
                        Manage Members
                    </button>
                )}
            </div>
        </Layout>
    );
}

interface RowProps { label: string; value?: string | null; }
function Row({ label, value }: RowProps): ReactNode {
    return (
        <div className="flex gap-2">
            <dt className="text-gray-500 w-36 shrink-0">{label}</dt>
            <dd className="font-medium text-gray-900">{value ?? '—'}</dd>
        </div>
    );
}
