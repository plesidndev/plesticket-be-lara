import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { myEvents, toggleEventActive } from '../../api/events';
import Layout from '../../components/Layout';
import Pagination from '../../components/Pagination';
import StatusBadge from '../../components/StatusBadge';
import type { Event, PaginationMeta } from '../../types';

export default function MyEvents() {
    const navigate = useNavigate();
    const [events, setEvents] = useState<Event[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [page, setPage] = useState(1);

    const load = useCallback(async () => {
        try {
            const res = await myEvents({ page, limit: 15 });
            setEvents(res.data.data);
            setMeta(res.data.meta);
        } catch { /* ignore */ }
    }, [page]);

    useEffect(() => { load(); }, [load]);

    const toggle = async (id: string, isActive: boolean) => {
        const action = isActive ? 'deactivate' : 'activate';
        if (!confirm(`${action.charAt(0).toUpperCase() + action.slice(1)} this event?`)) return;
        try { await toggleEventActive(id); load(); } catch { /* ignore */ }
    };

    return (
        <Layout>
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-xl font-bold text-gray-900">My Events</h1>
                <button
                    onClick={() => navigate('/admin/events/create')}
                    className="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors"
                >
                    + Create Event
                </button>
            </div>

            {events.length === 0 ? (
                <div className="text-center py-16 text-gray-400">
                    <p className="text-lg mb-2">No events yet</p>
                    <button onClick={() => navigate('/admin/events/create')} className="text-indigo-600 hover:underline text-sm">
                        Create your first event
                    </button>
                </div>
            ) : (
                <>
                    <div className="grid gap-4">
                        {events.map(ev => (
                            <div key={ev.id} className="bg-white rounded-xl border border-gray-200 p-5 flex items-start justify-between gap-4">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 mb-1">
                                        <span className="font-mono text-xs text-gray-400">{ev.event_id}</span>
                                        <StatusBadge status={ev.verification_status} />
                                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${ev.is_published ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'}`}>
                                            {ev.is_published ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                    <h3 className="font-semibold text-gray-900 truncate">{ev.title}</h3>
                                    <p className="text-sm text-gray-500 mt-0.5">
                                        {ev.schedule?.start_date ?? '—'} · {ev.location?.city ?? 'Online'}
                                    </p>
                                    {ev.verification_status === 'rejected' && ev.rejection_reason && (
                                        <p className="text-xs text-red-600 mt-1">Rejected: {ev.rejection_reason}</p>
                                    )}
                                </div>
                                <div className="flex gap-2 shrink-0">
                                    <button
                                        onClick={() => navigate(`/admin/events/${ev.id}`)}
                                        className="text-xs text-gray-600 hover:underline"
                                    >
                                        Detail
                                    </button>
                                    {ev.verification_status === 'verified' && (
                                        <button
                                            onClick={() => navigate(`/admin/events/${ev.id}/members`)}
                                            className="text-xs text-green-600 hover:underline"
                                        >
                                            Members
                                        </button>
                                    )}
                                    {(ev.verification_status === 'pending' || ev.verification_status === 'rejected') && (
                                        <button
                                            onClick={() => navigate(`/admin/events/${ev.id}/edit`)}
                                            className="text-xs text-indigo-600 hover:underline"
                                        >
                                            Edit
                                        </button>
                                    )}
                                    <button
                                        onClick={() => toggle(ev.id, ev.is_published ?? true)}
                                        className={`text-xs hover:underline ${ev.is_published ? 'text-red-500' : 'text-green-600'}`}
                                    >
                                        {ev.is_published ? 'Deactivate' : 'Activate'}
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                    <Pagination meta={meta} onPageChange={setPage} />
                </>
            )}
        </Layout>
    );
}
