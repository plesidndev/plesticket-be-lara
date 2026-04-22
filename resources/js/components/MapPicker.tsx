import { useEffect, useRef } from 'react';
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

const INDONESIA_CENTER: L.LatLngTuple = [-2.5, 118.0];

export default function MapPicker({ lat, lng, onChange }: Props) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<L.Map | null>(null);
    const markerRef = useRef<L.Marker | null>(null);

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

    const clear = () => {
        if (markerRef.current) {
            markerRef.current.remove();
            markerRef.current = null;
        }
        onChange({ lat: '', lng: '' });
    };

    return (
        <div className="space-y-2">
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
                    <span className="text-gray-400">Click on the map to pin the location</span>
                )}
            </div>
        </div>
    );
}
