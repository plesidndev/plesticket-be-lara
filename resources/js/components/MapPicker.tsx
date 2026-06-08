import { useEffect, useRef, useState } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Fix default marker icon paths broken by Vite bundling
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

delete (L.Icon.Default.prototype as unknown as Record<string, unknown>)._getIconUrl;
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

interface Props {
    lat: string | number | null;
    lng: string | number | null;
    onChange: (coords: { lat: string; lng: string }) => void;
}

interface NominatimResult {
    place_id: number;
    display_name: string;
    lat: string;
    lon: string;
}

const INDONESIA_CENTER: L.LatLngTuple = [-2.5, 118.0];

export default function MapPicker({ lat, lng, onChange }: Props) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<L.Map | null>(null);
    const markerRef = useRef<L.Marker | null>(null);

    const [query, setQuery] = useState('');
    const [results, setResults] = useState<NominatimResult[]>([]);
    const [searching, setSearching] = useState(false);
    const [showResults, setShowResults] = useState(false);
    const searchRef = useRef<HTMLDivElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const hasPosition = lat !== '' && lat !== null && lng !== '' && lng !== null;
    const position: L.LatLngTuple | null = hasPosition
        ? [Number(lat), Number(lng)]
        : null;

    useEffect(() => {
        if (!containerRef.current || mapRef.current) return;

        const map = L.map(containerRef.current).setView(
            position ?? INDONESIA_CENTER,
            position ? 15 : 5,
        );

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);

        if (position) {
            markerRef.current = L.marker(position, { draggable: true }).addTo(map);
            markerRef.current.on('dragend', () => {
                const { lat: la, lng: lo } = markerRef.current!.getLatLng();
                onChange({ lat: String(la), lng: String(lo) });
            });
        }

        map.on('click', (e: L.LeafletMouseEvent) => {
            const { lat: la, lng: lo } = e.latlng;
            if (markerRef.current) {
                markerRef.current.setLatLng([la, lo]);
            } else {
                markerRef.current = L.marker([la, lo], { draggable: true }).addTo(map);
                markerRef.current.on('dragend', () => {
                    const { lat: dla, lng: dlo } = markerRef.current!.getLatLng();
                    onChange({ lat: String(dla), lng: String(dlo) });
                });
            }
            onChange({ lat: String(la), lng: String(lo) });
        });

        mapRef.current = map;

        return () => {
            map.remove();
            mapRef.current = null;
            markerRef.current = null;
        };
    }, []);

    // Sync external clear (lat/lng reset to '')
    useEffect(() => {
        if (!mapRef.current) return;
        if (!hasPosition && markerRef.current) {
            markerRef.current.remove();
            markerRef.current = null;
        }
    }, [hasPosition]);

    // Close results when clicking outside
    useEffect(() => {
        if (!showResults) return;
        const handle = (e: MouseEvent) => {
            if (searchRef.current && !searchRef.current.contains(e.target as Node)) {
                setShowResults(false);
            }
        };
        document.addEventListener('mousedown', handle);
        return () => document.removeEventListener('mousedown', handle);
    }, [showResults]);

    const handleQueryChange = (value: string) => {
        setQuery(value);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        if (!value.trim()) { setResults([]); setShowResults(false); return; }
        debounceRef.current = setTimeout(async () => {
            setSearching(true);
            try {
                const res = await fetch(
                    `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(value)}&format=json&limit=5&countrycodes=id`,
                    { headers: { 'Accept-Language': 'id,en' } },
                );
                const data: NominatimResult[] = await res.json();
                setResults(data);
                setShowResults(true);
            } catch {
                setResults([]);
            } finally {
                setSearching(false);
            }
        }, 500);
    };

    const selectResult = (r: NominatimResult) => {
        const la = parseFloat(r.lat);
        const lo = parseFloat(r.lon);
        setShowResults(false);
        setQuery(r.display_name.split(',')[0]);

        if (mapRef.current) {
            mapRef.current.flyTo([la, lo], 16, { duration: 1 });
        }
        if (markerRef.current) {
            markerRef.current.setLatLng([la, lo]);
        } else if (mapRef.current) {
            markerRef.current = L.marker([la, lo], { draggable: true }).addTo(mapRef.current);
            markerRef.current.on('dragend', () => {
                const { lat: dla, lng: dlo } = markerRef.current!.getLatLng();
                onChange({ lat: String(dla), lng: String(dlo) });
            });
        }
        onChange({ lat: String(la), lng: String(lo) });
    };

    const clear = () => {
        if (markerRef.current) {
            markerRef.current.remove();
            markerRef.current = null;
        }
        onChange({ lat: '', lng: '' });
    };

    return (
        <div className="space-y-2">
            {/* Search box */}
            <div className="relative" ref={searchRef}>
                <div className="relative">
                    <input
                        type="text"
                        value={query}
                        onChange={e => handleQueryChange(e.target.value)}
                        placeholder="Search location…"
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    />
                    {searching && (
                        <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">…</span>
                    )}
                </div>

                {showResults && results.length > 0 && (
                    <ul className="absolute z-9999 top-full mt-1 left-0 right-0 bg-white border border-gray-200 rounded-lg shadow-lg max-h-52 overflow-y-auto">
                        {results.map(r => (
                            <li key={r.place_id}>
                                <button
                                    type="button"
                                    onClick={() => selectResult(r)}
                                    className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-indigo-50 border-b border-gray-100 last:border-0"
                                >
                                    {r.display_name}
                                </button>
                            </li>
                        ))}
                    </ul>
                )}

                {showResults && results.length === 0 && !searching && (
                    <div className="absolute z-9999 top-full mt-1 left-0 right-0 bg-white border border-gray-200 rounded-lg shadow p-3 text-sm text-gray-400">
                        No results found.
                    </div>
                )}
            </div>

            <div ref={containerRef} style={{ width: '100%', height: '320px', borderRadius: '8px' }} />
            <div className="flex items-center justify-between text-xs text-gray-500">
                {position ? (
                    <>
                        <span>
                            Lat: <span className="font-mono text-gray-700">{Number(lat).toFixed(7)}</span>
                            &nbsp;&nbsp;Lng: <span className="font-mono text-gray-700">{Number(lng).toFixed(7)}</span>
                        </span>
                        <button type="button" onClick={clear} className="text-red-400 hover:text-red-600">Clear</button>
                    </>
                ) : (
                    <span className="text-gray-400">Search above or click on the map to pin the location</span>
                )}
            </div>
        </div>
    );
}
