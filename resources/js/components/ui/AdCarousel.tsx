import { useState, useEffect, useCallback } from 'react';
import { AD_BANNERS } from '../../constants/categories';

export default function AdCarousel() {
    const [current, setCurrent] = useState(0);

    const next = useCallback(() => {
        setCurrent(c => (c + 1) % AD_BANNERS.length);
    }, []);

    useEffect(() => {
        const id = setInterval(next, 3500);
        return () => clearInterval(id);
    }, [next]);

    const banner = AD_BANNERS[current];

    return (
        <div className="px-5">
            <div className="relative w-full overflow-hidden rounded-2xl" style={{ aspectRatio: '16/7' }}>
                {/* Slides */}
                {AD_BANNERS.map((b, i) => (
                    <div
                        key={b.id}
                        className={`absolute inset-0 bg-linear-to-br ${b.gradient} transition-opacity duration-700 ${i === current ? 'opacity-100' : 'opacity-0'}`}
                    >
                        {/* decorative circles */}
                        <div className="absolute -top-8 -right-8 w-40 h-40 rounded-full bg-white/10 blur-2xl" />
                        <div className="absolute bottom-0 left-8 w-24 h-24 rounded-full bg-black/20 blur-xl" />
                    </div>
                ))}

                {/* Content */}
                <div className="relative z-10 h-full flex items-center px-6 gap-4">
                    <span className="text-5xl drop-shadow-lg">{banner.emoji}</span>
                    <div className="flex-1 min-w-0">
                        <p className="text-white/70 text-xs font-medium uppercase tracking-widest mb-1">Featured Event</p>
                        <h3 className="text-white font-bold text-lg leading-tight line-clamp-2">{banner.title}</h3>
                        <p className="text-white/70 text-xs mt-1 line-clamp-1">{banner.subtitle}</p>
                    </div>
                    <button className="shrink-0 bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white text-xs font-bold px-4 py-2 rounded-xl border border-white/30 transition-colors whitespace-nowrap">
                        {banner.cta}
                    </button>
                </div>

                {/* Dot indicators */}
                <div className="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5 z-10">
                    {AD_BANNERS.map((_, i) => (
                        <button
                            key={i}
                            onClick={() => setCurrent(i)}
                            className={`h-1.5 rounded-full transition-all duration-300 ${i === current ? 'w-6 bg-white' : 'w-1.5 bg-white/40'}`}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}
