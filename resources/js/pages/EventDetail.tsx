import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { getBySlug } from '../api/events';
import { THEME } from '../constants/colors';
import type { Event, TicketType } from '../types';

function formatDate(raw: string | null, opts?: Intl.DateTimeFormatOptions): string {
    if (!raw) return '—';
    return new Date(raw + 'T00:00:00').toLocaleDateString('id-ID', opts ?? {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    });
}

function formatTime(t: string | null): string {
    if (!t) return '';
    return t.substring(0, 5); // "HH:MM"
}

function formatPrice(price: string | number): string {
    const n = Number(price);
    return n === 0 ? 'Free' : `Rp${n.toLocaleString('id-ID')}`;
}

export default function EventDetail() {
    const { slug } = useParams<{ slug: string }>();
    const navigate  = useNavigate();
    const [event, setEvent]     = useState<Event | null>(null);
    const [loading, setLoading] = useState(true);
    const [selected, setSelected] = useState<TicketType | null>(null);

    useEffect(() => {
        if (!slug) return;
        setLoading(true);
        getBySlug(slug)
            .then(res => setEvent(res.data.data))
            .catch(() => navigate('/events', { replace: true }))
            .finally(() => setLoading(false));
    }, [slug, navigate]);

    if (loading) return <Skeleton />;
    if (!event) return null;

    const activeTickets = (event.ticket_types ?? []).filter(t => t.is_active);
    const minPrice      = activeTickets.length > 0
        ? Math.min(...activeTickets.map(t => Number(t.price)))
        : null;
    const ctaLabel = activeTickets.length === 0
        ? 'Not Available'
        : minPrice === 0
            ? 'Register Free'
            : `Buy from Rp${(minPrice ?? 0).toLocaleString('id-ID')}`;
    const canBuy = activeTickets.length > 0;

    const startDate = formatDate(event.schedule?.start_date ?? null);
    const endDate   = event.schedule?.end_date && event.schedule.end_date !== event.schedule.start_date
        ? formatDate(event.schedule.end_date)
        : null;
    const startTime = formatTime(event.schedule?.start_time ?? null);
    const endTime   = formatTime(event.schedule?.end_time ?? null);
    const timeRange = [startTime, endTime].filter(Boolean).join(' – ');

    return (
        <div className={`min-h-screen ${THEME.bgPage} pb-28`}>

            {/* ── Hero Banner ── */}
            <div className="relative w-full bg-zinc-800" style={{ aspectRatio: '16/7' }}>
                {event.banner_url ? (
                    <img src={event.banner_url} alt={event.title} className="w-full h-full object-cover" />
                ) : (
                    <div className="w-full h-full bg-linear-to-br from-violet-900 to-pink-900" />
                )}
                {/* Gradient overlay for readability */}
                <div className="absolute inset-0 bg-linear-to-t from-black/60 via-transparent to-black/20" />

                {/* Back + share overlay */}
                <div className="absolute top-0 left-0 right-0 flex items-center justify-between px-4 pt-4">
                    <button
                        onClick={() => navigate(-1)}
                        className="w-9 h-9 rounded-full bg-black/40 backdrop-blur-sm flex items-center justify-center text-white"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button
                        onClick={() => navigator.share?.({ title: event.title, url: window.location.href })}
                        className="w-9 h-9 rounded-full bg-black/40 backdrop-blur-sm flex items-center justify-center text-white"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                        </svg>
                    </button>
                </div>
            </div>

            {/* ── Main Card ── */}
            <div className="mx-4 -mt-6 relative z-10">
                <div className="bg-white rounded-3xl shadow-xl overflow-hidden">

                    {/* Title section */}
                    <div className="px-5 pt-5 pb-4 border-b border-gray-100">
                        {event.category && (
                            <span className="inline-block bg-violet-100 text-violet-700 text-xs font-semibold px-2.5 py-1 rounded-full mb-2 capitalize">
                                {event.category}
                            </span>
                        )}
                        <h1 className="text-xl font-bold text-gray-900 leading-snug mb-4">
                            {event.title}
                        </h1>

                        {/* Meta rows */}
                        <div className="space-y-2.5">
                            <MetaRow icon="📅">
                                <span className="font-semibold text-gray-800">{startDate}</span>
                                {endDate && <span className="text-gray-500"> – {endDate}</span>}
                                {timeRange && <span className="text-gray-500"> · {timeRange}</span>}
                            </MetaRow>

                            <MetaRow icon="📍">
                                {event.location?.is_online ? (
                                    <span className="font-semibold text-gray-800">Online Event</span>
                                ) : (
                                    <span>
                                        {event.location?.venue_name && (
                                            <span className="font-semibold text-gray-800">{event.location.venue_name}</span>
                                        )}
                                        {event.location?.city && (
                                            <span className="text-gray-500">
                                                {event.location.venue_name ? ', ' : ''}{event.location.city}
                                                {event.location.province ? `, ${event.location.province}` : ''}
                                            </span>
                                        )}
                                    </span>
                                )}
                            </MetaRow>

                            {event.organizer && (
                                <MetaRow icon={null}>
                                    <div className="flex items-center gap-2">
                                        {event.organizer.photo ? (
                                            <img src={event.organizer.photo} className="w-6 h-6 rounded-full object-cover" alt="" />
                                        ) : (
                                            <div className="w-6 h-6 rounded-full bg-violet-100 flex items-center justify-center shrink-0">
                                                <span className="text-[10px] font-bold text-violet-600">
                                                    {event.organizer.name[0].toUpperCase()}
                                                </span>
                                            </div>
                                        )}
                                        <span className="text-sm text-gray-600">{event.organizer.name}</span>
                                    </div>
                                </MetaRow>
                            )}
                        </div>
                    </div>

                    {/* About */}
                    {event.description && (
                        <div className="px-5 py-4 border-b border-gray-100">
                            <h2 className="text-sm font-bold text-gray-900 mb-2">About</h2>
                            <p className="text-sm text-gray-600 leading-relaxed whitespace-pre-line">
                                {event.description}
                            </p>
                        </div>
                    )}

                    {/* Tickets */}
                    {activeTickets.length > 0 && (
                        <div className="px-5 py-4 border-b border-gray-100">
                            <h2 className="text-sm font-bold text-gray-900 mb-3">Tickets</h2>
                            <div className="space-y-3">
                                {activeTickets.map(ticket => (
                                    <TicketCard
                                        key={ticket.id}
                                        ticket={ticket}
                                        selected={selected?.id === ticket.id}
                                        onSelect={() => setSelected(
                                            selected?.id === ticket.id ? null : ticket
                                        )}
                                    />
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Location detail */}
                    {!event.location?.is_online && event.location?.address && (
                        <div className="px-5 py-4">
                            <h2 className="text-sm font-bold text-gray-900 mb-2">Location</h2>
                            <p className="text-sm text-gray-600">{event.location.address}</p>
                            {event.location.city && (
                                <p className="text-sm text-gray-500">
                                    {event.location.city}{event.location.province ? `, ${event.location.province}` : ''}
                                </p>
                            )}
                            {event.location.latitude && (
                                <a
                                    href={`https://maps.google.com/?q=${event.location.latitude},${event.location.longitude}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-xs text-violet-600 font-medium mt-2 hover:underline"
                                    onClick={e => e.stopPropagation()}
                                >
                                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                    Open in Maps
                                </a>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {/* ── Sticky Bottom CTA ── */}
            <div className="fixed bottom-0 left-0 right-0 z-30 bg-zinc-950/95 backdrop-blur-md border-t border-zinc-800 px-4 py-3">
                <div className="flex items-center gap-3 max-w-lg mx-auto">
                    <div className="flex-1 min-w-0">
                        {selected ? (
                            <>
                                <p className="text-xs text-zinc-400 truncate">{selected.name}</p>
                                <p className="text-base font-bold text-zinc-50">{formatPrice(selected.price)}</p>
                            </>
                        ) : (
                            <>
                                <p className="text-xs text-zinc-400">Starting from</p>
                                <p className="text-base font-bold text-zinc-50">
                                    {minPrice === null ? '—' : minPrice === 0 ? 'Free' : `Rp${minPrice.toLocaleString('id-ID')}`}
                                </p>
                            </>
                        )}
                    </div>
                    <button
                        disabled={!canBuy}
                        className="shrink-0 bg-violet-600 hover:bg-violet-500 disabled:bg-zinc-700 disabled:text-zinc-500 text-white font-bold px-6 py-3 rounded-xl transition-colors text-sm"
                    >
                        {canBuy ? (selected ? 'Buy Now' : ctaLabel) : 'Not Available'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Sub-components ───────────────────────────────────────────────────

function MetaRow({ icon, children }: { icon: string | null; children: React.ReactNode }) {
    return (
        <div className="flex items-start gap-2.5">
            {icon ? (
                <span className="text-lg leading-snug shrink-0 mt-px">{icon}</span>
            ) : (
                <span className="w-7 shrink-0" />
            )}
            <div className="text-sm leading-snug flex-1">{children}</div>
        </div>
    );
}

function TicketCard({ ticket, selected, onSelect }: {
    ticket: TicketType;
    selected: boolean;
    onSelect: () => void;
}) {
    const saleStart = ticket.sale_start ? new Date(ticket.sale_start) : null;
    const saleEnd   = ticket.sale_end   ? new Date(ticket.sale_end)   : null;
    const now       = new Date();
    const onSale    = (!saleStart || saleStart <= now) && (!saleEnd || saleEnd >= now);

    return (
        <button
            onClick={onSelect}
            disabled={!onSale}
            className={`w-full text-left rounded-2xl border-2 p-4 transition-all ${
                selected
                    ? 'border-violet-500 bg-violet-50'
                    : onSale
                        ? 'border-gray-200 hover:border-violet-300 bg-white'
                        : 'border-gray-100 bg-gray-50 opacity-60'
            }`}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex-1 min-w-0">
                    <p className="font-bold text-gray-900 text-sm">{ticket.name}</p>
                    {ticket.description && (
                        <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">{ticket.description}</p>
                    )}
                    {saleEnd && onSale && (
                        <p className="text-[11px] text-orange-500 font-medium mt-1">
                            Sale ends {saleEnd.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })}
                        </p>
                    )}
                    {!onSale && (
                        <p className="text-[11px] text-gray-400 mt-1">
                            {saleStart && saleStart > now
                                ? `Opens ${saleStart.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })}`
                                : 'Sale ended'}
                        </p>
                    )}
                </div>
                <div className="text-right shrink-0">
                    <p className="font-bold text-gray-900 text-base leading-tight">
                        {formatPrice(ticket.price)}
                    </p>
                    {ticket.quota > 0 && (
                        <p className="text-[11px] text-gray-400 mt-0.5">{ticket.quota} left</p>
                    )}
                </div>
            </div>

            {/* Selection indicator */}
            {onSale && (
                <div className="mt-3 flex justify-end">
                    <span className={`text-xs font-semibold px-3 py-1 rounded-full transition-colors ${
                        selected
                            ? 'bg-violet-600 text-white'
                            : 'bg-gray-100 text-gray-500'
                    }`}>
                        {selected ? '✓ Selected' : 'Select'}
                    </span>
                </div>
            )}
        </button>
    );
}

function Skeleton() {
    return (
        <div className="min-h-screen bg-zinc-950 pb-28 animate-pulse">
            <div className="w-full bg-zinc-800" style={{ aspectRatio: '16/7' }} />
            <div className="mx-4 -mt-6 relative z-10 bg-white rounded-3xl shadow-xl p-5 space-y-4">
                <div className="h-4 bg-gray-200 rounded w-16" />
                <div className="h-6 bg-gray-200 rounded w-3/4" />
                <div className="h-4 bg-gray-200 rounded w-1/2" />
                <div className="h-4 bg-gray-200 rounded w-2/3" />
                <div className="h-4 bg-gray-200 rounded w-1/3" />
                <div className="border-t border-gray-100 pt-4 space-y-3">
                    <div className="h-20 bg-gray-100 rounded-2xl" />
                    <div className="h-20 bg-gray-100 rounded-2xl" />
                </div>
            </div>
        </div>
    );
}
