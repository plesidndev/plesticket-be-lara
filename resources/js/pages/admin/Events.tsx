import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { adminListEvents } from '../../api/events';
import Layout from '../../components/Layout';
import Pagination from '../../components/Pagination';
import StatusBadge from '../../components/StatusBadge';
import type { Event, PaginationMeta, VerificationStatus } from '../../types';

const STATUSES: Array<VerificationStatus | ''> = ['', 'pending', 'verified', 'rejected', 'suspended'];

export default function AdminEvents() {
    const navigate = useNavigate();
    const [events, setEvents] = useState<Event[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState<VerificationStatus | ''>('');

    const load = useCallback(async () => {
        try {
            const res = await adminListEvents({ search, verification_status: status, page, limit: 15 });
            setEvents(res.data.data);
            setMeta(res.data.meta);
        } catch { /* ignore */ }
    }, [search, status, page]);

    useEffect(() => { load(); }, [load]);

    return (
        <Layout>
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-xl font-bold text-gray-900">All Events</h1>
            </div>

            <div className="flex gap-3 mb-4">
                <input
                    type="search"
                    placeholder="Search events…"
                    value={search}
                    onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                    className="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                />
                <select
                    value={status}
                    onChange={(e) => { setStatus(e.target.value as VerificationStatus | ''); setPage(1); }}
                    className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                >
                    {STATUSES.map(s => <option key={s} value={s}>{s || 'All statuses'}</option>)}
                </select>
            </div>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                        <tr>
                            <th className="px-4 py-3 text-left">Event ID</th>
                            <th className="px-4 py-3 text-left">Title</th>
                            <th className="px-4 py-3 text-left">Organizer</th>
                            <th className="px-4 py-3 text-left">Date</th>
                            <th className="px-4 py-3 text-left">Status</th>
                            <th className="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {events.map(ev => (
                            <tr key={ev.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3 font-mono text-xs text-gray-500">{ev.event_id}</td>
                                <td className="px-4 py-3 font-medium text-gray-900 max-w-xs truncate">{ev.title}</td>
                                <td className="px-4 py-3 text-gray-600">{ev.organizer?.name ?? '—'}</td>
                                <td className="px-4 py-3 text-gray-600">{ev.schedule?.start_date ?? '—'}</td>
                                <td className="px-4 py-3"><StatusBadge status={ev.verification_status} /></td>
                                <td className="px-4 py-3">
                                    <button
                                        onClick={() => navigate(`/plest-admin/events/${ev.id}`)}
                                        className="text-indigo-600 hover:underline text-xs"
                                    >
                                        View
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {events.length === 0 && (
                            <tr><td colSpan={6} className="text-center py-8 text-gray-400">No events found.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            <Pagination meta={meta} onPageChange={setPage} />
        </Layout>
    );
}
