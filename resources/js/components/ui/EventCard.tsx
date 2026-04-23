import { useNavigate } from 'react-router-dom';
import type { Event } from '../../types';

interface EventCardProps {
    event: Event;
}

function formatDate(raw: string | null): string {
    if (!raw) return '—';
    return new Date(raw + 'T00:00:00').toLocaleDateString('id-ID', {
        day: 'numeric', month: 'short', year: 'numeric',
    });
}

export default function EventCard({ event }: EventCardProps) {
    const navigate = useNavigate();

    const tickets    = event.ticket_types ?? [];
    const minPrice   = tickets.length > 0 ? Math.min(...tickets.map(t => Number(t.price))) : null;
    const priceLabel = minPrice === null ? '—' : minPrice === 0 ? 'Free' : `Rp${minPrice.toLocaleString('id-ID')}`;
    const dateLabel  = formatDate(event.schedule?.start_date ?? null);
    const organizer  = event.organizer;

    return (
        <button
            onClick={() => navigate(`/events/${event.slug}`)}
            className="shrink-0 w-52 bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md hover:scale-[1.02] transition-all text-left flex flex-col"
        >
            {/* Banner */}
            <div className="relative w-full" style={{ aspectRatio: '16/9' }}>
                {event.banner_url ? (
                    <img src={event.banner_url} alt={event.title} className="w-full h-full object-cover" />
                ) : (
                    <div className="w-full h-full bg-linear-to-br from-violet-100 to-pink-100 flex items-center justify-center">
                        <span className="text-3xl opacity-30">🎟️</span>
                    </div>
                )}
            </div>

            {/* Body */}
            <div className="p-3 flex flex-col flex-1">
                <p className="text-xs font-bold text-gray-900 leading-snug line-clamp-2 mb-2">
                    {event.title}
                </p>

                <div className="flex items-center gap-1 mb-1.5">
                    <span className="text-sm leading-none">📅</span>
                    <span className="text-[11px] font-semibold text-blue-500 truncate">{dateLabel}</span>
                </div>

                <p className="text-sm font-bold text-gray-900 mb-2">{priceLabel}</p>

                <div className="mt-auto pt-2 border-t border-gray-100 flex items-center gap-1.5">
                    {organizer?.photo ? (
                        <img src={organizer.photo} alt={organizer.name} className="w-5 h-5 rounded-full object-cover shrink-0" />
                    ) : (
                        <div className="w-5 h-5 rounded-full bg-violet-100 flex items-center justify-center shrink-0">
                            <span className="text-[9px] font-bold text-violet-600">
                                {organizer?.name?.[0]?.toUpperCase() ?? 'O'}
                            </span>
                        </div>
                    )}
                    <span className="text-[10px] text-gray-500 truncate">{organizer?.name ?? '—'}</span>
                </div>
            </div>
        </button>
    );
}
