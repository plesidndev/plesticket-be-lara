import { useState, useEffect, useCallback, type FormEvent } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { listPublic } from '../api/events';
import Pagination from '../components/Pagination';
import { THEME } from '../constants/colors';
import { CATEGORIES } from '../constants/categories';
import type { Event, PaginationMeta } from '../types';

const SORT_OPTIONS = [
    { value: 'upcoming',  label: 'Upcoming' },
    { value: 'date_desc', label: 'Latest Date' },
    { value: 'newest',    label: 'Just Added' },
] as const;

type SortValue = typeof SORT_OPTIONS[number]['value'];

export default function EventList() {
    const navigate = useNavigate();
    const [searchParams, setSearchParams] = useSearchParams();

    const [events, setEvents] = useState<Event[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(true);

    const activeCategory = searchParams.get('category') ?? '';
    const activeSort     = (searchParams.get('sort') ?? 'upcoming') as SortValue;
    const currentPage    = Number(searchParams.get('page') ?? 1);
    const searchQuery    = searchParams.get('search') ?? '';

    const [inputValue, setInputValue] = useState(searchQuery);

    const setParam = (key: string, value: string) => {
        setSearchParams(prev => {
            const next = new URLSearchParams(prev);
            if (value) next.set(key, value); else next.delete(key);
            next.delete('page'); // reset to page 1 on filter change
            return next;
        });
    };

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = {
                limit: 12,
                page:  currentPage,
                sort:  activeSort,
            };
            if (activeCategory) params.category = activeCategory;
            if (searchQuery)    params.search   = searchQuery;

            const res = await listPublic(params);
            setEvents(res.data.data);
            setMeta(res.data.meta);
        } catch { /* ignore */ } finally {
            setLoading(false);
        }
    }, [activeCategory, activeSort, currentPage, searchQuery]);

    useEffect(() => { load(); }, [load]);

    // Sync input with URL param when navigating back
    useEffect(() => { setInputValue(searchQuery); }, [searchQuery]);

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        setParam('search', inputValue.trim());
    };

    const handleCategoryClick = (name: string) => {
        const val = name.toLowerCase();
        setParam('category', activeCategory === val ? '' : val);
    };

    return (
        <div className={`min-h-screen ${THEME.bgPage} ${THEME.textPrimary}`}>

            {/* ── Header ── */}
            <div className={`sticky top-0 z-20 ${THEME.bgPage} border-b ${THEME.border} px-4 pt-4 pb-3`}>
                <div className="flex items-center gap-3 mb-3">
                    <button
                        onClick={() => navigate('/home')}
                        className="text-zinc-400 hover:text-zinc-200 transition-colors"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <h1 className="text-base font-bold text-zinc-50 flex-1">
                        {activeCategory
                            ? (CATEGORIES.find(c => c.name.toLowerCase() === activeCategory)?.icon ?? '') + ' ' +
                              (activeCategory.charAt(0).toUpperCase() + activeCategory.slice(1))
                            : 'Browse Events'}
                    </h1>
                    {meta && (
                        <span className={`text-xs ${THEME.textSecondary}`}>
                            {meta.total} event{meta.total !== 1 ? 's' : ''}
                        </span>
                    )}
                </div>

                {/* Search */}
                <form onSubmit={handleSearch} className="relative mb-3">
                    <svg className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.5 4.5a7.5 7.5 0 0012.15 12.15z" />
                    </svg>
                    <input
                        type="text"
                        value={inputValue}
                        onChange={e => setInputValue(e.target.value)}
                        placeholder="Search events…"
                        className="w-full bg-zinc-900 border border-zinc-800 rounded-xl pl-10 pr-10 py-2.5 text-sm text-zinc-50 placeholder:text-zinc-600 focus:outline-none focus:border-violet-500 transition-colors"
                    />
                    {inputValue && (
                        <button
                            type="button"
                            onClick={() => { setInputValue(''); setParam('search', ''); }}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-300"
                        >
                            ✕
                        </button>
                    )}
                </form>

                {/* Category chips */}
                <div className="flex gap-2 overflow-x-auto scrollbar-hide pb-0.5">
                    <Chip
                        label="All"
                        active={!activeCategory}
                        onClick={() => setParam('category', '')}
                    />
                    {CATEGORIES.map(cat => (
                        <Chip
                            key={cat.id}
                            label={`${cat.icon} ${cat.name}`}
                            active={activeCategory === cat.name.toLowerCase()}
                            onClick={() => handleCategoryClick(cat.name)}
                        />
                    ))}
                </div>
            </div>

            {/* ── Sort bar ── */}
            <div className={`flex items-center gap-2 px-4 py-2.5 border-b ${THEME.border} overflow-x-auto scrollbar-hide`}>
                <span className="text-xs text-zinc-500 shrink-0">Sort by:</span>
                {SORT_OPTIONS.map(opt => (
                    <button
                        key={opt.value}
                        onClick={() => setParam('sort', opt.value)}
                        className={`shrink-0 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors ${
                            activeSort === opt.value
                                ? 'bg-violet-600 text-white'
                                : 'bg-zinc-900 text-zinc-400 hover:text-zinc-200 border border-zinc-800'
                        }`}
                    >
                        {opt.label}
                    </button>
                ))}
            </div>

            {/* ── Event Grid ── */}
            <div className="p-4">
                {loading ? (
                    <GridSkeleton />
                ) : events.length === 0 ? (
                    <Empty query={searchQuery} category={activeCategory} />
                ) : (
                    <>
                        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            {events.map(ev => <EventGridCard key={ev.id} event={ev} />)}
                        </div>
                        <div className="mt-6">
                            <Pagination
                                meta={meta}
                                onPageChange={p => setSearchParams(prev => {
                                    const next = new URLSearchParams(prev);
                                    next.set('page', String(p));
                                    return next;
                                })}
                            />
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}

// ── Sub-components ──────────────────────────────────────────────────

function Chip({ label, active, onClick }: { label: string; active: boolean; onClick: () => void }) {
    return (
        <button
            onClick={onClick}
            className={`shrink-0 text-xs font-medium px-3 py-1.5 rounded-full transition-colors whitespace-nowrap ${
                active
                    ? 'bg-violet-600 text-white'
                    : 'bg-zinc-900 text-zinc-400 hover:text-zinc-200 border border-zinc-800'
            }`}
        >
            {label}
        </button>
    );
}

function formatDate(raw: string | null): string {
    if (!raw) return '—';
    return new Date(raw + 'T00:00:00').toLocaleDateString('id-ID', {
        day: 'numeric', month: 'long', year: 'numeric',
    });
}

function EventGridCard({ event }: { event: Event }) {
    const navigate   = useNavigate();
    const tickets    = event.ticket_types ?? [];
    const minPrice   = tickets.length > 0 ? Math.min(...tickets.map(t => Number(t.price))) : null;
    const priceLabel = minPrice === null ? '—' : minPrice === 0 ? 'Free' : `Rp${minPrice.toLocaleString('id-ID')}`;
    const dateLabel  = formatDate(event.schedule?.start_date ?? null);
    const organizer  = event.organizer;

    return (
        <button
            onClick={() => navigate(`/events/${event.slug}`)}
            className="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow text-left flex flex-col w-full"
        >
            {/* Banner */}
            <div className="relative w-full" style={{ aspectRatio: '16/7' }}>
                {event.banner_url ? (
                    <img src={event.banner_url} alt={event.title} className="w-full h-full object-cover" />
                ) : (
                    <div className="w-full h-full bg-linear-to-br from-violet-100 to-pink-100 flex items-center justify-center">
                        <span className="text-5xl opacity-30">🎟️</span>
                    </div>
                )}
            </div>

            {/* Body */}
            <div className="p-4 flex flex-col flex-1">
                {/* Title */}
                <h3 className="font-bold text-gray-900 text-base leading-snug line-clamp-2 mb-3">
                    {event.title}
                </h3>

                {/* Date */}
                <div className="flex items-center gap-2 mb-3">
                    <span className="text-xl leading-none">📅</span>
                    <span className="text-sm font-semibold text-blue-500">{dateLabel}</span>
                </div>

                {/* Price */}
                <p className="text-xl font-bold text-gray-900">
                    {priceLabel}
                </p>

                {/* Divider + Organizer */}
                <div className="mt-4 pt-3 border-t border-gray-100 flex items-center gap-2.5">
                    {organizer?.photo ? (
                        <img
                            src={organizer.photo}
                            alt={organizer.name}
                            className="w-8 h-8 rounded-full object-cover shrink-0"
                        />
                    ) : (
                        <div className="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center shrink-0">
                            <span className="text-xs font-bold text-violet-600">
                                {organizer?.name?.[0]?.toUpperCase() ?? 'O'}
                            </span>
                        </div>
                    )}
                    <span className="text-sm font-medium text-gray-600 truncate">
                        {organizer?.name ?? '—'}
                    </span>
                </div>
            </div>
        </button>
    );
}

function GridSkeleton() {
    return (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="bg-white rounded-2xl overflow-hidden shadow-sm animate-pulse">
                    <div className="w-full bg-gray-200" style={{ aspectRatio: '16/7' }} />
                    <div className="p-4 space-y-3">
                        <div className="h-4 bg-gray-200 rounded w-4/5" />
                        <div className="h-4 bg-gray-200 rounded w-3/5" />
                        <div className="h-5 bg-gray-200 rounded w-2/5" />
                        <div className="h-5 bg-gray-200 rounded w-1/3" />
                        <div className="pt-3 border-t border-gray-100 flex items-center gap-2">
                            <div className="w-8 h-8 bg-gray-200 rounded-full" />
                            <div className="h-3 bg-gray-200 rounded w-24" />
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}

function Empty({ query, category }: { query: string; category: string }) {
    return (
        <div className="flex flex-col items-center justify-center py-20 text-center">
            <span className="text-5xl mb-4">🔍</span>
            <p className="text-zinc-50 font-semibold mb-1">No events found</p>
            <p className="text-sm text-zinc-500">
                {query ? `No results for "${query}"` : category ? `No ${category} events yet` : 'Check back soon'}
            </p>
        </div>
    );
}
