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
            const res = await myEvents({ page, limit: 12 });
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
                <div>
                    <h1 className="text-xl font-bold text-gray-900">My Events</h1>
                    {meta && <p className="text-sm text-gray-400 mt-0.5">{meta.total} event{meta.total !== 1 ? 's' : ''}</p>}
                </div>
                <button
                    onClick={() => navigate('/admin/events/create')}
                    className="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors"
                >
                    + Create Event
                </button>
            </div>

            {events.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-24 text-center">
                    <div className="w-16 h-16 rounded-full bg-indigo-50 flex items-center justify-center mb-4">
                        <svg className="w-8 h-8 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <p className="text-gray-500 font-medium mb-1">No events yet</p>
                    <p className="text-sm text-gray-400 mb-4">Create your first event to get started</p>
                    <button
                        onClick={() => navigate('/admin/events/create')}
                        className="bg-indigo-600 text-white text-sm px-5 py-2 rounded-lg hover:bg-indigo-700 transition-colors"
                    >
                        + Create Event
                    </button>
                </div>
            ) : (
                <>
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                        {events.map(ev => (
                            <div
                                key={ev.id}
                                className="bg-white rounded-2xl border border-gray-200 overflow-hidden flex flex-col hover:shadow-md transition-shadow"
                            >
                                {/* Banner */}
                                <div
                                    className="relative w-full bg-gray-100 cursor-pointer"
                                    style={{ aspectRatio: '1920/800' }}
                                    onClick={() => navigate(`/admin/events/${ev.id}`)}
                                >
                                    {ev.banner_url ? (
                                        <img
                                            src={ev.banner_url}
                                            alt={ev.title}
                                            className="w-full h-full object-cover"
                                        />
                                    ) : (
                                        <div className="w-full h-full flex flex-col items-center justify-center gap-1">
                                            <svg className="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <span className="text-xs text-gray-400">No banner</span>
                                        </div>
                                    )}
                                    {/* Status overlay */}
                                    <div className="absolute top-2 left-2 flex gap-1.5">
                                        <StatusBadge status={ev.verification_status} />
                                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${ev.is_published ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'}`}>
                                            {ev.is_published ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                </div>

                                {/* Content */}
                                <div className="flex flex-col flex-1 p-4">
                                    <p className="font-mono text-xs text-gray-400 mb-1">{ev.event_id}</p>
                                    <h3
                                        className="font-semibold text-gray-900 leading-snug mb-1 cursor-pointer hover:text-indigo-600 line-clamp-2"
                                        onClick={() => navigate(`/admin/events/${ev.id}`)}
                                    >
                                        {ev.title}
                                    </h3>
                                    <p className="text-xs text-gray-500 mb-1">
                                        {ev.schedule?.start_date ?? '—'}
                                        {ev.schedule?.start_time ? ` · ${ev.schedule.start_time}` : ''}
                                    </p>
                                    <p className="text-xs text-gray-500">
                                        {ev.location?.is_online ? '🌐 Online' : (ev.location?.city ?? '—')}
                                    </p>

                                    {ev.verification_status === 'rejected' && ev.rejection_reason && (
                                        <p className="text-xs text-red-500 mt-2 line-clamp-2">⚠ {ev.rejection_reason}</p>
                                    )}

                                    {/* Actions */}
                                    <div className="flex items-center gap-3 mt-auto pt-3 border-t border-gray-100">
                                        <button
                                            onClick={() => navigate(`/admin/events/${ev.id}`)}
                                            className="text-xs text-gray-500 hover:text-gray-800 font-medium"
                                        >
                                            Detail
                                        </button>
                                        {(ev.verification_status === 'pending' || ev.verification_status === 'rejected') && (
                                            <button
                                                onClick={() => navigate(`/admin/events/${ev.id}/edit`)}
                                                className="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                            >
                                                Edit
                                            </button>
                                        )}
                                        {ev.verification_status === 'verified' && (
                                            <button
                                                onClick={() => navigate(`/admin/events/${ev.id}/members`)}
                                                className="text-xs text-green-600 hover:text-green-800 font-medium"
                                            >
                                                Members
                                            </button>
                                        )}
                                        <button
                                            onClick={() => toggle(ev.id, ev.is_published ?? true)}
                                            className={`text-xs font-medium ml-auto ${ev.is_published ? 'text-red-500 hover:text-red-700' : 'text-green-600 hover:text-green-800'}`}
                                        >
                                            {ev.is_published ? 'Deactivate' : 'Activate'}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="mt-6">
                        <Pagination meta={meta} onPageChange={setPage} />
                    </div>
                </>
            )}
        </Layout>
    );
}
