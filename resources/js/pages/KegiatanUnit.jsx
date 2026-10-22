import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ClipboardList, Search, Upload } from 'lucide-react';
import api from '../lib/api';
import { useAuth } from '../contexts/AuthContext';
import { STATUS_KEGIATAN, useDaftarTahun } from '../lib/pilihan';
import {
    Alert,
    Button,
    Card,
    EmptyState,
    Field,
    Input,
    Pagination,
    Select,
    Spinner,
    StatusKegiatanBadge,
} from '../components/ui';

/**
 * Daftar kegiatan milik unit yang sedang login, sebagai jalan masuk ke halaman
 * unggah bukti.
 */
export default function KegiatanUnit() {
    const daftarTahun = useDaftarTahun();
    const auth = useAuth();
    const dinasDipilih = auth.user?.dinas?.slug ?? '';
    const [filter, setFilter] = useState({ status: '', tahun: '', q: '' });
    const [halaman, setHalaman] = useState(1);
    const [kegiatan, setKegiatan] = useState([]);
    const [meta, setMeta] = useState(null);
    const [memuat, setMemuat] = useState(true);
    const [galat, setGalat] = useState(null);

    const muat = useCallback(async () => {
        if (!dinasDipilih) return;

        setMemuat(true);
        setGalat(null);

        try {
            const { data } = await api.get('/rencana-aksi', {
                params: {
                    dinas: dinasDipilih,
                    page: halaman,
                    ...Object.fromEntries(Object.entries(filter).filter(([, nilai]) => nilai)),
                },
            });
            setKegiatan(data.data);
            setMeta(data.meta);
        } catch {
            setGalat('Gagal memuat daftar kegiatan.');
        } finally {
            setMemuat(false);
        }
    }, [dinasDipilih, halaman, filter]);

    useEffect(() => {
        muat();
    }, [muat]);

    const namaUnit = auth.user?.dinas?.nama;

    return (
        <div className="space-y-4">
            <div>
                <h1 className="text-xl font-semibold text-ink">Kegiatan Unit</h1>
                <p className="text-sm text-sub">
                    Rencana aksi yang menjadi tanggung jawab {namaUnit ?? 'unit Anda'}. Buka salah satu kegiatan untuk mengunggah bukti
                    atau memperbarui statusnya.
                </p>
            </div>

            {galat && <Alert jenis="galat">{galat}</Alert>}

            <Card title="Filter">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">

                    <Field label="Status kegiatan">
                        <Select
                            value={filter.status}
                            onChange={(e) => {
                                setFilter({ ...filter, status: e.target.value });
                                setHalaman(1);
                            }}
                        >
                            <option value="">Semua status</option>
                            {STATUS_KEGIATAN.map(([nilai, label]) => (
                                <option key={nilai} value={nilai}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Tahun">
                        <Select
                            value={filter.tahun}
                            onChange={(e) => {
                                setFilter({ ...filter, tahun: e.target.value });
                                setHalaman(1);
                            }}
                        >
                            <option value="">Semua tahun</option>
                            {daftarTahun.daftar.map((t) => (
                                <option key={t} value={t}>
                                    {t}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Kata kunci">
                        <div className="relative">
                            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-sub" />
                            <Input
                                defaultValue={filter.q}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        setFilter({ ...filter, q: e.target.value });
                                        setHalaman(1);
                                    }
                                }}
                                placeholder="Judul atau program"
                                className="pl-8"
                            />
                        </div>
                    </Field>
                </div>
            </Card>

            <Card title={meta ? `${meta.total} kegiatan` : 'Daftar Kegiatan'} padding="p-0">
                {memuat ? (
                    <Spinner />
                ) : kegiatan.length === 0 ? (
                    <EmptyState
                        judul="Tidak ada Rencana Kegiatan Aksi yang ditemukan"
                        keterangan="Coba longgarkan filter di atas."
                        ikon={ClipboardList}
                    />
                ) : (
                    <ul className="divide-y divide-line">
                        {kegiatan.map((item) => (
                            <li key={item.id} className="flex flex-wrap items-start gap-3 p-4">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link to={`/rencana-aksi/${item.uuid}`} className="truncate text-sm font-medium text-ink hover:text-river hover:underline">
                                            {item.judul}
                                        </Link>
                                        <StatusKegiatanBadge status={item.status} label={item.status_label} />
                                    </div>
                                    <p className="mt-0.5 text-[11px] text-sub">
                                        {item.dimensi} · {item.jenis_keluaran_label} · {item.kategori_label} · {item.tahun_label}
                                    </p>
                                    <p className="mt-1 text-[11px] text-sub">
                                        {item.jumlah_bukti ?? 0} bukti diunggah
                                        {typeof item.jumlah_bukti_diterima === 'number' && <> · {item.jumlah_bukti_diterima} diterima</>}
                                    </p>
                                </div>

                                <Link to={`/rencana-aksi/${item.uuid}`}>
                                    <Button ukuran="sm">
                                        <Upload size={12} /> Unggah bukti
                                    </Button>
                                </Link>
                            </li>
                        ))}
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
