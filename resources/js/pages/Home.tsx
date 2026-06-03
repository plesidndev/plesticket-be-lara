import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { listPublic } from '../api/events';
import { useAuth } from '../contexts/AuthContext';
import AdCarousel from '../components/ui/AdCarousel';
import SectionHeader from '../components/ui/SectionHeader';
import EventCard from '../components/ui/EventCard';
import CategoryCard from '../components/ui/CategoryCard';
import { THEME } from '../constants/colors';
import { CATEGORIES } from '../constants/categories';
import type { Event } from '../types';

export default function Home() {
    const navigate = useNavigate();
    const { user } = useAuth();
    const [recommended, setRecommended] = useState<Event[]>([]);
    const [nearest, setNearest] = useState<Event[]>([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const searchRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        Promise.all([
            listPublic({ limit: 10, page: 1 }),
            listPublic({ limit: 10, page: 1, sort: 'newest' }),
        ])
            .then(([rec, near]) => {
                setRecommended(rec.data.data);
                setNearest(near.data.data);
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        if (search.trim()) navigate(`/events?search=${encodeURIComponent(search.trim())}`);
    };

    const handleCategoryClick = (name: string) => {
        navigate(`/events?category=${encodeURIComponent(name)}`);
    };

    return (
        <div className={`min-h-screen ${THEME.bgPage} ${THEME.textPrimary} pb-10`}>

            {/* ── Top Bar ── */}
            <div className={`sticky top-0 z-30 bg-zinc-950/95 backdrop-blur-md border-b ${THEME.border} px-5 pt-5 pb-3`}>
                <div className="flex items-center justify-between mb-3">
                    <div>
                        <div className="flex items-center gap-1.5">
                            <svg className="w-3.5 h-3.5 text-violet-400" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                            </svg>
                            <span className={`text-xs font-medium ${THEME.textSecondary}`}>Jakarta, Indonesia</span>
                            <svg className="w-3 h-3 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                        <h1 className={`text-xl font-extrabold ${THEME.textPrimary} mt-0.5`}>
                            {user ? `Hi ${user.name.split(' ')[0]}, ` : ''}Find your next event 🎟️
                        </h1>
                    </div>
                    <div className="flex items-center gap-2">
                        {user ? (
                            <button
                                onClick={() => navigate(user.role === 'SUPER_ADMIN' ? '/plest-admin/events' : '/admin/events')}
                                className="w-9 h-9 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center"
                            >
                                <svg className="w-4 h-4 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                        ) : (
                            <button
                                onClick={() => navigate('/login')}
                                className="text-xs font-semibold text-violet-400 border border-violet-500/40 px-3 py-1.5 rounded-xl hover:bg-violet-500/10 transition-colors"
                            >
                                Sign in
                            </button>
                        )}
                        <button className="relative w-9 h-9 rounded-full bg-[#1A1A2C] border border-[#252538] flex items-center justify-center">
                            <svg className="w-4 h-4 text-[#9090B4]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span className="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-violet-500 border-2 border-[#09090F]" />
                        </button>
                    </div>
                </div>

                {/* Search bar */}
                <form onSubmit={handleSearch} className="relative">
                    <svg className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#55556A]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.5 4.5a7.5 7.5 0 0012.15 12.15z" />
                    </svg>
                    <input
                        ref={searchRef}
                        type="text"
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder="Search events, concerts, sports…"
                        className={`w-full ${THEME.bgCardAlt} border ${THEME.border} rounded-xl pl-10 pr-4 py-2.5 text-sm ${THEME.textPrimary} placeholder:${THEME.textMuted} focus:outline-none focus:border-violet-500/60 transition-colors`}
                    />
                    {search && (
                        <button
                            type="button"
                            onClick={() => setSearch('')}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-[#55556A] hover:text-[#9090B4]"
                        >
                            ✕
                        </button>
                    )}
                </form>
            </div>

            <div className="mt-5 space-y-7">

                {/* ── Section 1: Ad Carousel ── */}
                <AdCarousel />

                {/* ── Section 2: Recommended Events ── */}
                <section>
                    <SectionHeader
                        title="Recommended For You"
                        onSeeAll={() => navigate('/events')}
                    />
                    {loading ? (
                        <HorizontalSkeleton />
                    ) : recommended.length > 0 ? (
                        <div className="flex gap-4 overflow-x-auto scrollbar-hide px-5 pb-1">
                            {recommended.map(ev => <EventCard key={ev.id} event={ev} />)}
                        </div>
                    ) : (
                        <EmptyStrip message="No events yet — check back soon" />
                    )}
                </section>

                {/* ── Section 3: Browse by Category ── */}
                <section>
                    <SectionHeader title="Browse by Category" />
                    <div className="grid grid-cols-3 gap-3 px-5">
                        {CATEGORIES.map(cat => (
                            <CategoryCard
                                key={cat.id}
                                category={cat}
                                onClick={() => handleCategoryClick(cat.name)}
                            />
                        ))}
                    </div>
                </section>

                {/* ── Section 4: Nearest Events ── */}
                <section>
                    <SectionHeader
                        title="Nearest Events"
                        onSeeAll={() => navigate('/events')}
                    />
                    {loading ? (
                        <HorizontalSkeleton />
                    ) : nearest.length > 0 ? (
                        <div className="flex gap-4 overflow-x-auto scrollbar-hide px-5 pb-1">
                            {nearest.map(ev => <EventCard key={ev.id} event={ev} />)}
                        </div>
                    ) : (
                        <EmptyStrip message="No nearby events found" />
                    )}
                </section>

            </div>
        </div>
    );
}

function HorizontalSkeleton() {
    return (
        <div className="flex gap-4 px-5 pb-1 overflow-hidden">
            {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="shrink-0 w-44 rounded-2xl bg-zinc-900 border border-zinc-800 animate-pulse">
                    <div className="w-full bg-zinc-800" style={{ aspectRatio: '4/3' }} />
                    <div className="p-3 space-y-2">
                        <div className="h-3 bg-zinc-800 rounded w-4/5" />
                        <div className="h-2.5 bg-zinc-800 rounded w-3/5" />
                        <div className="h-2.5 bg-zinc-800 rounded w-2/5" />
                    </div>
                </div>
            ))}
        </div>
    );
}

function EmptyStrip({ message }: { message: string }) {
    return (
        <div className="px-5">
            <div className={`rounded-xl border border-dashed border-zinc-800 py-8 text-center ${THEME.textMuted} text-sm`}>
                {message}
            </div>
        </div>
    );
}
