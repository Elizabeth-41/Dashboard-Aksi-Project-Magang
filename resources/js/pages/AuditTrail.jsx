import { useCallback, useEffect, useState } from 'react';
import { ChevronDown, ChevronRight, History, RotateCcw } from 'lucide-react';
import api, { pesanError } from '../lib/api';
import { Alert, Badge, Button, Card, EmptyState, Field, Input, Pagination, Select, Spinner, formatTanggal } from '../components/ui';

const JENIS = [
    ['rencana_aksi', 'Kegiatan'],
    ['bukti_dukung', 'Bukti dukung'],
    ['manajemen_akses', 'Manajemen akses'],
];

const AKSI = [
    ['created', 'Ditambahkan'],
    ['updated', 'Diubah'],
    ['deleted', 'Dihapus'],
];

const WARNA_AKSI = { created: 'hijau', updated: 'emas', deleted: 'merah' };
const LABEL_JENIS = Object.fromEntries(JENIS);
const LABEL_AKSI = Object.fromEntries(AKSI);

const FILTER_KOSONG = { log_name: '', event: '', dari: '', sampai: '' };

/** Nilai mentah dari log dibuat enak dibaca (null, boolean, array tahun, dll). */
function tampilkanNilai(nilai) {
    if (nilai === null || nilai === undefined || nilai === '') return '—';
    if (Array.isArray(nilai)) return nilai.join(', ');
    if (typeof nilai === 'boolean') return nilai ? 'ya' : 'tidak';

    return String(nilai);
}

/** Hanya kolom yang benar-benar berubah yang ditampilkan. */
function PerubahanNilai({ sebelum, sesudah }) {
    const kunci = [...new Set([...Object.keys(sebelum ?? {}), ...Object.keys(sesudah ?? {})])];

    if (kunci.length === 0) {
        return <p className="text-[11px] text-sub">Tidak ada rincian perubahan yang tercatat.</p>;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[420px] text-left text-xs">
                <thead className="text-[11px] text-sub">
                    <tr>
                        <th className="py-1 pr-3 font-medium">Kolom</th>
                        <th className="py-1 pr-3 font-medium">Sebelum</th>
                        <th className="py-1 font-medium">Sesudah</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-line">
                    {kunci.map((k) => (
                        <tr key={k}>
                            <td className="py-1 pr-3 font-medium text-ink">{k}</td>
                            <td className="py-1 pr-3 text-sub">{tampilkanNilai(sebelum?.[k])}</td>
                            <td className="py-1 text-ink">{tampilkanNilai(sesudah?.[k])}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function AuditTrail() {
    const [filter, setFilter] = useState(FILTER_KOSONG);
    const [halaman, setHalaman] = useState(1);
    const [catatan, setCatatan] = useState([]);
    const [meta, setMeta] = useState(null);
    const [memuat, setMemuat] = useState(true);
    const [galat, setGalat] = useState(null);
    const [dibuka, setDibuka] = useState(null);

    const muat = useCallback(async () => {
        setMemuat(true);
        setGalat(null);

        try {
            const { data } = await api.get('/admin/activity-log', {
                params: { page: halaman, ...Object.fromEntries(Object.entries(filter).filter(([, n]) => n)) },
            });
            setCatatan(data.data);
            setMeta(data.meta);
        } catch (error) {
            setGalat(pesanError(error, 'Gagal memuat audit trail.'));
        } finally {
            setMemuat(false);
        }
    }, [filter, halaman]);

    useEffect(() => {
        muat();
    }, [muat]);

    const ubah = (kunci, nilai) => {
        setFilter((lama) => ({ ...lama, [kunci]: nilai }));
        setHalaman(1);
    };

    return (
        <div className="space-y-4">
            <div>
                <h1 className="text-xl font-semibold text-ink">Audit Trail</h1>
                <p className="text-sm text-sub">
                    Rekam jejak perubahan status kegiatan, verifikasi dan penghapusan bukti, serta Manajemen Akses — siapa, apa, dan kapan.
                </p>
            </div>

            {galat && <Alert jenis="galat">{galat}</Alert>}

            <Card
                title="Filter"
                aksi={
                    <Button varian="ghost" ukuran="sm" onClick={() => { setFilter(FILTER_KOSONG); setHalaman(1); }}>
                        <RotateCcw size={12} /> Reset
                    </Button>
                }
            >
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label="Jenis data">
                        <Select value={filter.log_name} onChange={(e) => ubah('log_name', e.target.value)}>
                            <option value="">Semua jenis</option>
                            {JENIS.map(([nilai, label]) => (
                                <option key={nilai} value={nilai}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Aksi">
                        <Select value={filter.event} onChange={(e) => ubah('event', e.target.value)}>
                            <option value="">Semua aksi</option>
                            {AKSI.map(([nilai, label]) => (
                                <option key={nilai} value={nilai}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Dari tanggal">
                        <Input type="date" value={filter.dari} onChange={(e) => ubah('dari', e.target.value)} />
                    </Field>

                    <Field label="Sampai tanggal">
                        <Input type="date" value={filter.sampai} onChange={(e) => ubah('sampai', e.target.value)} />
                    </Field>
                </div>
            </Card>

            <Card title={meta ? `${meta.total} catatan` : 'Catatan Aktivitas'} padding="p-0">
                {memuat ? (
                    <Spinner />
                ) : catatan.length === 0 ? (
                    <EmptyState
                        judul="Belum ada catatan aktivitas"
                        keterangan="Perubahan status kegiatan, verifikasi bukti, dan Manajemen Akses akan tercatat di sini."
                        ikon={History}
                    />
                ) : (
                    <ul className="divide-y divide-line">
                        {catatan.map((item) => {
                            const terbuka = dibuka === item.id;
                            const adaRincian = item.sebelum || item.sesudah;

                            return (
                                <li key={item.id} className="px-4 py-3">
                                    <div className="flex flex-wrap items-start gap-2">
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm text-ink">{item.description}</p>
                                            <p className="mt-0.5 text-[11px] text-sub">
                                                {item.causer?.name ?? 'Sistem'}
                                                {item.causer?.role_label && <> · {item.causer.role_label}</>} · {formatTanggal(item.created_at, true)}
                                            </p>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-1.5">
                                            <Badge warna="biru">{LABEL_JENIS[item.log_name] ?? item.log_name}</Badge>
                                            <Badge warna={WARNA_AKSI[item.event] ?? 'abu'}>{LABEL_AKSI[item.event] ?? item.event}</Badge>
                                            {adaRincian && (
                                                <button
                                                    type="button"
                                                    onClick={() => setDibuka(terbuka ? null : item.id)}
                                                    className="inline-flex items-center gap-1 rounded-lg border border-line px-2 py-1 text-[11px] font-semibold text-river transition hover:bg-canvas"
                                                >
                                                    {terbuka ? <ChevronDown size={12} /> : <ChevronRight size={12} />} Rincian
                                                </button>
                                            )}
                                        </div>
                                    </div>

                                    {terbuka && (
                                        <div className="mt-2 rounded-lg bg-canvas p-3">
                                            <PerubahanNilai sebelum={item.sebelum} sesudah={item.sesudah} />
                                        </div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}

                {meta && (
                    <div className="px-4 pb-3">
                        <Pagination meta={meta} onPindah={setHalaman} />
                    </div>
                )}
            </Card>
        </div>
    );
}
