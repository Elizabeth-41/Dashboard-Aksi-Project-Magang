import { useEffect, useRef, useState } from 'react';
import { AlertTriangle, Check, CheckCircle2, ChevronDown, Info, Loader2, SearchX } from 'lucide-react';

/** Kartu putih standar; seluruh panel halaman memakainya agar konsisten. */
export function Card({ title, aksi, children, className = '', padding = 'p-4' }) {
    return (
        <section className={`rounded-xl border border-line bg-white ${padding} ${className}`}>
            {(title || aksi) && (
                <div className={`mb-3 flex flex-wrap items-center justify-between gap-2 ${padding === 'p-0' ? 'px-4 pt-4' : ''}`}>
                    {title && <h2 className="text-sm font-semibold text-ink">{title}</h2>}
                    {aksi}
                </div>
            )}
            {children}
        </section>
    );
}

const VARIAN_TOMBOL = {
    primary: 'bg-river text-white hover:bg-deep disabled:hover:bg-river',
    gold: 'bg-gold text-white hover:brightness-95',
    outline: 'border border-line bg-white text-ink hover:bg-canvas',
    danger: 'bg-danger text-white hover:brightness-95',
    ghost: 'text-river hover:bg-river-soft',
};

const UKURAN_TOMBOL = {
    sm: 'px-2.5 py-1 text-xs',
    md: 'px-3.5 py-2 text-sm',
};

export function Button({ varian = 'primary', ukuran = 'md', memuat = false, children, className = '', ...props }) {
    return (
        <button
            {...props}
            disabled={props.disabled || memuat}
            className={`inline-flex items-center justify-center gap-1.5 rounded-lg font-semibold transition disabled:cursor-not-allowed disabled:opacity-60 ${VARIAN_TOMBOL[varian]} ${UKURAN_TOMBOL[ukuran]} ${className}`}
        >
            {memuat && <Loader2 size={14} className="animate-spin" />}
            {children}
        </button>
    );
}

const WARNA_BADGE = {
    hijau: 'bg-success-soft text-success',
    emas: 'bg-warning-soft text-gold',
    biru: 'bg-river-soft text-river',
    merah: 'bg-danger-soft text-danger',
    abu: 'bg-canvas text-sub',
};

export function Badge({ warna = 'abu', children, className = '' }) {
    return (
        <span className={`inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold ${WARNA_BADGE[warna]} ${className}`}>
            {children}
        </span>
    );
}

const WARNA_STATUS_KEGIATAN = { belum_mulai: 'biru', berjalan: 'emas', selesai: 'hijau' };
const WARNA_STATUS_VERIFIKASI = { menunggu: 'emas', diterima: 'hijau', ditolak: 'merah' };

export function StatusKegiatanBadge({ status, label }) {
    return <Badge warna={WARNA_STATUS_KEGIATAN[status] ?? 'abu'}>{label ?? status}</Badge>;
}

export function StatusVerifikasiBadge({ status, label }) {
    return <Badge warna={WARNA_STATUS_VERIFIKASI[status] ?? 'abu'}>{label ?? status}</Badge>;
}

/** Input berlabel dengan slot pesan galat dari validasi Laravel. */
export function Field({ label, error, petunjuk, wajib = false, children, className = '' }) {
    return (
        <label className={`block ${className}`}>
            <span className="mb-1 block text-xs font-medium text-sub">
                {label}
                {wajib && <span className="text-danger"> *</span>}
            </span>
            {children}
            {petunjuk && !error && <span className="mt-1 block text-[11px] text-sub">{petunjuk}</span>}
            {error && <span className="mt-1 block text-[11px] font-medium text-danger">{error}</span>}
        </label>
    );
}

const KELAS_INPUT =
    'w-full rounded-lg border border-line bg-white px-3 py-2 text-sm text-ink placeholder:text-sub/60 focus:border-river focus:outline-none disabled:bg-canvas';

export function Input({ className = '', ...props }) {
    return <input {...props} className={`${KELAS_INPUT} ${className}`} />;
}

export function Textarea({ className = '', ...props }) {
    return <textarea {...props} className={`${KELAS_INPUT} ${className}`} />;
}

export function Select({ className = '', children, ...props }) {
    return (
        <select {...props} className={`${KELAS_INPUT} ${className}`}>
            {children}
        </select>
    );
}

export function MultiSelectTahun({ tahun, pilihan, onChange, labelSemua = 'Semua tahun' }) {
    const [terbuka, setTerbuka] = useState(false);
    const ref = useRef(null);
    const semua = tahun.length === 0;

    useEffect(() => {
        const tutup = (event) => {
            if (!ref.current?.contains(event.target)) setTerbuka(false);
        };

        document.addEventListener('mousedown', tutup);
        return () => document.removeEventListener('mousedown', tutup);
    }, []);

    const toggle = (nilai) => {
        onChange(tahun.includes(nilai) ? tahun.filter((item) => item !== nilai) : [...tahun, nilai].sort((a, b) => a - b));
    };

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setTerbuka((nilai) => !nilai)}
                className="flex w-full items-center justify-between rounded-lg border border-line bg-white px-3 py-2 text-left text-sm text-ink focus:border-river focus:outline-none"
                aria-expanded={terbuka}
            >
                <span className="truncate">{semua ? labelSemua : `${tahun.length} tahun dipilih`}</span>
                <ChevronDown size={15} className="shrink-0 text-sub" />
            </button>
            {terbuka && (
                <div className="absolute z-40 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-line bg-white p-1 shadow-lg">
                    <button type="button" onClick={() => onChange([])} className="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-xs hover:bg-canvas">
                        <span className="flex h-4 w-4 items-center justify-center rounded border border-line">{semua && <Check size={12} />}</span>
                        {labelSemua}
                    </button>
                    {pilihan.map((nilai) => {
                        const aktif = tahun.includes(nilai);

                        return (
                            <button key={nilai} type="button" onClick={() => toggle(nilai)} className="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-xs hover:bg-canvas">
                                <span className="flex h-4 w-4 items-center justify-center rounded border border-line">{aktif && <Check size={12} />}</span>
                                {nilai}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

const IKON_ALERT = { info: Info, sukses: CheckCircle2, galat: AlertTriangle };
const KELAS_ALERT = {
    info: 'bg-river-soft text-river',
    sukses: 'bg-success-soft text-success',
    galat: 'bg-danger-soft text-danger',
};

export function Alert({ jenis = 'info', children, className = '' }) {
    const Ikon = IKON_ALERT[jenis];

    return (
        <div className={`flex items-start gap-2 rounded-lg px-3 py-2 text-sm ${KELAS_ALERT[jenis]} ${className}`} role={jenis === 'galat' ? 'alert' : undefined}>
            <Ikon size={16} className="mt-0.5 shrink-0" />
            <div className="min-w-0">{children}</div>
        </div>
    );
}

export function Spinner({ label = 'Memuat…' }) {
    return (
        <div className="flex items-center justify-center gap-2 py-10 text-sm text-sub">
            <Loader2 size={16} className="animate-spin" /> {label}
        </div>
    );
}

export function EmptyState({ judul, keterangan, ikon: Ikon = SearchX, aksi }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 px-4 py-10 text-center">
            <Ikon size={24} className="text-sub" />
            <p className="text-sm font-medium text-ink">{judul}</p>
            {keterangan && <p className="max-w-md text-xs text-sub">{keterangan}</p>}
            {aksi}
        </div>
    );
}

/** Bilah progres tipis untuk persentase capaian unit. */
export function ProgressBar({ nilai, className = '' }) {
    return (
        <div className={`h-1.5 w-full overflow-hidden rounded-full bg-line ${className}`}>
            <div
                className={`h-full rounded-full ${nilai > 70 ? 'bg-success' : 'bg-gold'}`}
                style={{ width: `${Math.min(100, Math.max(0, nilai))}%` }}
            />
        </div>
    );
}

export function Pagination({ meta, onPindah }) {
    if (!meta || meta.last_page <= 1) return null;

    return (
        <nav className="mt-3 flex items-center justify-between gap-2 text-xs text-sub" aria-label="Navigasi halaman">
            <span>
                Halaman {meta.current_page} dari {meta.last_page} · {meta.total} data
            </span>
            <span className="flex gap-2">
                <Button varian="outline" ukuran="sm" disabled={meta.current_page <= 1} onClick={() => onPindah(meta.current_page - 1)}>
                    Sebelumnya
                </Button>
                <Button varian="outline" ukuran="sm" disabled={meta.current_page >= meta.last_page} onClick={() => onPindah(meta.current_page + 1)}>
                    Berikutnya
                </Button>
            </span>
        </nav>
    );
}

export function formatUkuran(bytes) {
    if (!bytes) return '—';
    const kb = bytes / 1024;

    return kb < 1024 ? `${Math.round(kb)} KB` : `${(kb / 1024).toFixed(1)} MB`;
}

export function formatTanggal(iso, denganJam = false) {
    if (!iso) return '—';

    return new Date(iso).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        ...(denganJam ? { hour: '2-digit', minute: '2-digit' } : {}),
    });
}
