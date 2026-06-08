import { useState, useEffect, useRef, lazy, Suspense, type FormEvent, type ReactNode, type ChangeEvent } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { createEvent, updateEvent, myEvents } from '../../api/events';
import { listCategories } from '../../api/categories';
import { listProvinces, listCities } from '../../api/locations';
import Layout from '../../components/Layout';
import type { Category, Province, City } from '../../types';

const MapPicker = lazy(() => import('../../components/MapPicker'));

const IDENTITY_TYPES: { value: string; label: string }[] = [
    { value: 'ktp',      label: 'KTP' },
    { value: 'sim',      label: 'SIM' },
    { value: 'passport', label: 'Passport' },
];

interface TicketFormRow {
    name: string;
    description: string;
    price: string;
    quota: string;
    sale_start: string;
    sale_end: string;
}

interface EventFormState {
    title: string;
    slug: string;
    description: string;
    category: string;
    banner_url: string;  // existing URL (edit mode)
    pic_name: string;
    pic_identity_type: string;
    pic_identity_number: string;
    pic_npwp: string;
    start_date: string;
    end_date: string;
    start_time: string;
    end_time: string;
    is_online: boolean;
    venue_name: string;
    address: string;
    city: string;
    province: string;
    latitude: string;
    longitude: string;
    show_status: boolean;
    ticket_types: TicketFormRow[];
}

const emptyTicket: TicketFormRow = { name: '', description: '', price: '', quota: '', sale_start: '', sale_end: '' };

const empty: EventFormState = {
    title: '', slug: '', description: '', category: '', banner_url: '',
    pic_name: '', pic_identity_type: 'ktp', pic_identity_number: '', pic_npwp: '',
    start_date: '', end_date: '', start_time: '', end_time: '',
    is_online: false, venue_name: '', address: '', city: '', province: '', latitude: '', longitude: '',
    show_status: true,
    ticket_types: [],
};

type FieldErrors = Partial<Record<string, string[] | string>>;

export default function EventForm() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const isEdit = Boolean(id);
    const [form, setForm] = useState<EventFormState>(empty);
    const [bannerFile, setBannerFile] = useState<File | null>(null);
    const [bannerPreview, setBannerPreview] = useState<string | null>(null);
    const [bannerError, setBannerError] = useState('');
    const bannerRef = useRef<HTMLInputElement>(null);
    const [categories, setCategories] = useState<Category[]>([]);
    const [provinces, setProvinces] = useState<Province[]>([]);
    const [cities, setCities] = useState<City[]>([]);
    const [provinceCode, setProvinceCode] = useState('');
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<FieldErrors>({});

    useEffect(() => {
        listCategories().then(res => setCategories(res.data.data)).catch(() => {});
        listProvinces({ limit: 100 }).then(res => setProvinces(res.data.data)).catch(() => {});
    }, []);

    useEffect(() => {
        if (!isEdit) return;
        myEvents({ limit: 100 }).then(res => {
            const ev = res.data.data.find(e => e.id === id);
            if (!ev) { navigate('/admin/events'); return; }
            setForm({
                title: ev.title ?? '',
                slug: ev.slug ?? '',
                description: ev.description ?? '',
                category: ev.category ?? '',
                banner_url: ev.banner_url ?? '',
                pic_name: ev.pic?.name ?? '',
                pic_identity_type: ev.pic?.identity_type ?? 'ktp',
                pic_identity_number: '',
                pic_npwp: '',
                start_date: ev.schedule?.start_date ?? '',
                end_date: ev.schedule?.end_date ?? '',
                start_time: ev.schedule?.start_time ?? '',
                end_time: ev.schedule?.end_time ?? '',
                is_online: ev.location?.is_online ?? false,
                venue_name: ev.location?.venue_name ?? '',
                address: ev.location?.address ?? '',
                city: ev.location?.city ?? '',
                province: ev.location?.province ?? '',
                latitude: ev.location?.latitude ?? '',
                longitude: ev.location?.longitude ?? '',
                show_status: ev.show_status ?? true,
                ticket_types: (ev.ticket_types ?? []).map(t => ({
                    name: t.name, description: t.description ?? '',
                    price: String(t.price), quota: String(t.quota),
                    sale_start: t.sale_start ? t.sale_start.slice(0, 16) : '',
                    sale_end:   t.sale_end   ? t.sale_end.slice(0, 16)   : '',
                })),
            });
        });
    }, [id, isEdit, navigate]);

    useEffect(() => {
        if (!provinceCode) { setCities([]); return; }
        listCities({ province_code: provinceCode, limit: 500 })
            .then(res => setCities(res.data.data))
            .catch(() => {});
    }, [provinceCode]);

    useEffect(() => {
        if (!form.province || provinces.length === 0) return;
        const match = provinces.find(p => p.name === form.province);
        if (match) setProvinceCode(match.code);
    }, [form.province, provinces]);

    const set = <K extends keyof EventFormState>(key: K, val: EventFormState[K]) =>
        setForm(f => ({ ...f, [key]: val }));

    const handleBanner = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setBannerError('');
        setBannerFile(file);
        setBannerPreview(URL.createObjectURL(file));
    };

    const addTicket = () => setForm(f => ({ ...f, ticket_types: [...f.ticket_types, { ...emptyTicket }] }));
    const removeTicket = (i: number) => setForm(f => ({ ...f, ticket_types: f.ticket_types.filter((_, idx) => idx !== i) }));
    const setTicket = (i: number, key: keyof TicketFormRow, val: string) =>
        setForm(f => {
            const tickets = [...f.ticket_types];
            tickets[i] = { ...tickets[i], [key]: val };
            return { ...f, ticket_types: tickets };
        });

    const buildFormData = (): FormData => {
        const fd = new FormData();
        const { ticket_types, ...rest } = form;

        Object.entries(rest).forEach(([k, v]) => {
            if (v !== null && v !== undefined && v !== '') {
                fd.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : String(v));
            }
        });

        ticket_types.forEach((t, i) => {
            Object.entries(t).forEach(([k, v]) => {
                if (v !== '') fd.append(`ticket_types[${i}][${k}]`, String(v));
            });
        });

        if (bannerFile) fd.append('banner', bannerFile);

        return fd;
    };

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setErrors({});
        setSaving(true);
        try {
            const fd = buildFormData();
            if (isEdit) {
                await updateEvent(id!, fd);
            } else {
                await createEvent(fd);
            }
            navigate('/admin/events');
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { errors?: FieldErrors } } };
            if (axiosErr.response?.data?.errors) setErrors(axiosErr.response.data.errors);
        } finally {
            setSaving(false);
        }
    };

    const field = (key: keyof EventFormState) => ({
        value: form[key] as string,
        onChange: (e: ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
            set(key, e.target.value as EventFormState[typeof key]),
        className: `w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 ${errors[key] ? 'border-red-400' : 'border-gray-300'}`,
    });

    const errMsg = (key: string) => {
        const e = errors[key];
        return Array.isArray(e) ? e[0] : e;
    };

    return (
        <Layout>
            <div className="flex items-center gap-3 mb-6">
                <button onClick={() => navigate('/admin/events')} className="text-gray-400 hover:text-gray-600 text-sm">← Back</button>
                <h1 className="text-xl font-bold text-gray-900">{isEdit ? 'Edit Event' : 'Create Event'}</h1>
            </div>

            <form onSubmit={submit} className="space-y-6 max-w-2xl">
                <Section title="Basic Info">
                    <Field label="Title *" error={errMsg('title')}>
                        <input {...field('title')} required placeholder="Event title" />
                    </Field>
                    <Field label="Slug" error={errMsg('slug')}>
                        <input {...field('slug')} placeholder="auto-generated if empty" />
                    </Field>
                    <Field label="Description" error={errMsg('description')}>
                        <textarea {...field('description')} rows={3} style={{ resize: 'none' }} placeholder="Event description" />
                    </Field>
                    <Field label="Category" error={errMsg('category')}>
                        <select {...field('category')}>
                            <option value="">— Select category —</option>
                            {categories.map(c => <option key={c.id} value={c.name}>{c.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Banner Image" error={bannerError || errMsg('banner')}>
                        <div className="space-y-2">
                            {(bannerPreview || form.banner_url) && (
                                <img
                                    src={bannerPreview ?? form.banner_url}
                                    alt="banner preview"
                                    className="w-full rounded-lg object-cover"
                                    style={{ aspectRatio: '1920/800' }}
                                />
                            )}
                            <label className={`flex items-center gap-2 cursor-pointer w-full border rounded-lg px-3 py-2 text-sm ${bannerError ? 'border-red-400' : 'border-gray-300'} hover:bg-gray-50`}>
                                <span className="text-gray-500">
                                    {bannerFile ? bannerFile.name : isEdit ? 'Replace banner…' : 'Choose image…'}
                                </span>
                                <input
                                    ref={bannerRef}
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={handleBanner}
                                    className="hidden"
                                />
                            </label>
                            <p className="text-xs text-gray-400">Required size: 1920 × 800 px · JPG, PNG or WebP · max 5 MB</p>
                        </div>
                    </Field>
                </Section>

                <Section title="Person in Charge (PIC)">
                    <Field label="PIC Name *" error={errMsg('pic_name')}>
                        <input {...field('pic_name')} required placeholder="Full name" />
                    </Field>
                    <div className="grid grid-cols-3 gap-3">
                        <Field label="Identity Type *" error={errMsg('pic_identity_type')}>
                            <select {...field('pic_identity_type')} required>
                                {IDENTITY_TYPES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                            </select>
                        </Field>
                        <div className="col-span-2">
                            <Field label="Identity Number *" error={errMsg('pic_identity_number')}>
                                <input {...field('pic_identity_number')} required={!isEdit} placeholder={isEdit ? 'Leave blank to keep current' : 'ID number'} />
                            </Field>
                        </div>
                    </div>
                    <Field label="NPWP" error={errMsg('pic_npwp')}>
                        <input {...field('pic_npwp')} placeholder="Optional" />
                    </Field>
                </Section>

                <Section title="Schedule">
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Start Date *" error={errMsg('start_date')}>
                            <input {...field('start_date')} type="date" required />
                        </Field>
                        <Field label="End Date *" error={errMsg('end_date')}>
                            <input {...field('end_date')} type="date" required />
                        </Field>
                        <Field label="Start Time" error={errMsg('start_time')}>
                            <input {...field('start_time')} type="time" />
                        </Field>
                        <Field label="End Time" error={errMsg('end_time')}>
                            <input {...field('end_time')} type="time" />
                        </Field>
                    </div>
                </Section>

                <Section title="Location">
                    <div className="flex items-center gap-2 mb-3">
                        <input
                            type="checkbox" id="is_online"
                            checked={form.is_online}
                            onChange={(e) => set('is_online', e.target.checked)}
                        />
                        <label htmlFor="is_online" className="text-sm text-gray-700">Online event</label>
                    </div>
                    {!form.is_online && (
                        <>
                            <Field label="Venue Name" error={errMsg('venue_name')}>
                                <input {...field('venue_name')} placeholder="e.g. Gelora Bung Karno" />
                            </Field>
                            <Field label="Address" error={errMsg('address')}>
                                <textarea {...field('address')} rows={2} style={{ resize: 'none' }} placeholder="Street address" />
                            </Field>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Province" error={errMsg('province')}>
                                    <select
                                        value={provinceCode}
                                        onChange={e => {
                                            const code = e.target.value;
                                            const name = provinces.find(p => p.code === code)?.name ?? '';
                                            setProvinceCode(code);
                                            set('province', name);
                                            set('city', '');
                                        }}
                                        className={`w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 ${errors.province ? 'border-red-400' : 'border-gray-300'}`}
                                    >
                                        <option value="">— Select province —</option>
                                        {provinces.map(p => <option key={p.code} value={p.code}>{p.name}</option>)}
                                    </select>
                                </Field>
                                <Field label="City" error={errMsg('city')}>
                                    <select
                                        value={form.city}
                                        onChange={e => set('city', e.target.value)}
                                        disabled={!provinceCode}
                                        className={`w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 disabled:bg-gray-50 disabled:text-gray-400 ${errors.city ? 'border-red-400' : 'border-gray-300'}`}
                                    >
                                        <option value="">{provinceCode ? '— Select city —' : '— Select province first —'}</option>
                                        {cities.map(c => <option key={c.id} value={c.name}>{c.type} {c.name}</option>)}
                                    </select>
                                </Field>
                            </div>
                            <Field label="Pin Location on Map" error={errMsg('latitude')}>
                                <Suspense fallback={<div className="w-full h-80 rounded-lg bg-gray-100 animate-pulse" />}>
                                    <MapPicker
                                        lat={form.latitude}
                                        lng={form.longitude}
                                        onChange={({ lat, lng }) => { set('latitude', lat); set('longitude', lng); }}
                                    />
                                </Suspense>
                            </Field>
                        </>
                    )}
                </Section>

                <Section title="Ticket Types">
                    {form.ticket_types.length === 0 && (
                        <p className="text-sm text-gray-400">No ticket types yet. Add at least one.</p>
                    )}
                    {form.ticket_types.map((t, i) => (
                        <div key={i} className="border border-gray-200 rounded-lg p-4 space-y-3 relative">
                            <button type="button" onClick={() => removeTicket(i)}
                                className="absolute top-3 right-3 text-red-400 hover:text-red-600 text-xs">
                                Remove
                            </button>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Name *" error={errMsg(`ticket_types.${i}.name`)}>
                                    <input
                                        value={t.name} required
                                        onChange={e => setTicket(i, 'name', e.target.value)}
                                        placeholder="e.g. Regular, VIP"
                                        className={`w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 ${errors[`ticket_types.${i}.name`] ? 'border-red-400' : 'border-gray-300'}`}
                                    />
                                </Field>
                                <Field label="Description" error={errMsg(`ticket_types.${i}.description`)}>
                                    <input
                                        value={t.description}
                                        onChange={e => setTicket(i, 'description', e.target.value)}
                                        placeholder="Optional"
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                    />
                                </Field>
                                <Field label="Price (Rp) *" error={errMsg(`ticket_types.${i}.price`)}>
                                    <input
                                        type="number" min="0" value={t.price} required
                                        onChange={e => setTicket(i, 'price', e.target.value)}
                                        placeholder="0 = free"
                                        className={`w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 ${errors[`ticket_types.${i}.price`] ? 'border-red-400' : 'border-gray-300'}`}
                                    />
                                </Field>
                                <Field label="Quota *" error={errMsg(`ticket_types.${i}.quota`)}>
                                    <input
                                        type="number" min="1" value={t.quota} required
                                        onChange={e => setTicket(i, 'quota', e.target.value)}
                                        placeholder="Total seats"
                                        className={`w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 ${errors[`ticket_types.${i}.quota`] ? 'border-red-400' : 'border-gray-300'}`}
                                    />
                                </Field>
                                <Field label="Sale Start" error={errMsg(`ticket_types.${i}.sale_start`)}>
                                    <input
                                        type="datetime-local" value={t.sale_start}
                                        onChange={e => setTicket(i, 'sale_start', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                    />
                                </Field>
                                <Field label="Sale End" error={errMsg(`ticket_types.${i}.sale_end`)}>
                                    <input
                                        type="datetime-local" value={t.sale_end}
                                        onChange={e => setTicket(i, 'sale_end', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                    />
                                </Field>
                            </div>
                        </div>
                    ))}
                    <button type="button" onClick={addTicket}
                        className="mt-1 text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        + Add Ticket Type
                    </button>
                </Section>

                <Section title="Visibility">
                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox" id="show_status"
                            checked={form.show_status}
                            onChange={(e) => set('show_status', e.target.checked)}
                        />
                        <label htmlFor="show_status" className="text-sm text-gray-700">Show in public listing (after verification)</label>
                    </div>
                </Section>

                <div className="flex gap-3">
                    <button type="submit" disabled={saving}
                        className="bg-indigo-600 text-white text-sm px-6 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                        {saving ? 'Saving…' : isEdit ? 'Update Event' : 'Create Event'}
                    </button>
                    <button type="button" onClick={() => navigate('/admin/events')}
                        className="border border-gray-300 text-sm px-6 py-2 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                </div>
            </form>
        </Layout>
    );
}

interface SectionProps { title: string; children: ReactNode; }
function Section({ title, children }: SectionProps) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5">
            <h2 className="font-semibold text-gray-700 text-sm uppercase tracking-wide mb-4">{title}</h2>
            <div className="space-y-3">{children}</div>
        </div>
    );
}

interface FieldProps { label: string; error?: string; children: ReactNode; }
function Field({ label, error, children }: FieldProps) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
            {children}
            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
        </div>
    );
}
