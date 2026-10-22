import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { FileText, Image as ImageIcon, RotateCcw, Search } from 'lucide-react';
import api from '../lib/api';
import AksiBukti from '../components/AksiBukti';
import { useAuth } from '../contexts/AuthContext';
import { DIMENSI, JENIS_KELUARAN, KATEGORI, STATUS_KEGIATAN, STATUS_VERIFIKASI, useDaftarTahun, TIPE_FILE, useDaftarDinas } from '../lib/pilihan';
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
    StatusVerifikasiBadge,
    formatTanggal,
    formatUkuran,
} from '../components/ui';

const FILTER_KOSONG = {
    q: '',
    dinas: '',
    dimensi: '',
    jenis_keluaran: '',
    kategori: '',
    status: '',
    status_verifikasi: '',
    tahun: '',
    tipe_file: '',
};

function IkonBukti({ kelompok }) {
    const Ikon = kelompok === 'gambar' ? ImageIcon : FileText;

    return <Ikon size={16} className="shrink-0 text-river" />;
}

export default function PencarianBukti() {
    const daftarTahun = useDaftarTahun();
    const auth = useAuth();
    const dinasList = useDaftarDinas();
    const [params, setParams] = useSearchParams();
    const [hasil, setHasil] = useState([]);
    const [meta, setMeta] = useState(null);
    const [memuat, setMemuat] = useState(true);
    const [galat, setGalat] = useState(null);

    // URL adalah sumber kebenaran filter agar hasil pencarian bisa dibagikan.
    const filter = { ...FILTER_KOSONG, ...Object.fromEntries(params) };

    const cari = useCallback(async () => {
        setMemuat(true);
        setGalat(null);

        try {
            const { data } = await api.get('/bukti/search', { params: Object.fromEntries(params) });
            setHasil(data.data);
            setMeta(data.meta);
        } catch (error) {
            setGalat('Gagal memuat hasil pencarian.');
            setHasil([]);
        } finally {
            setMemuat(false);
        }
    }, [params]);

    useEffect(() => {
        cari();
    }, [cari]);

    // Mengubah filter selalu mengembalikan ke halaman 1 agar tidak tersesat di
    // halaman yang sudah tidak ada.
    const ubahFilter = (kunci, nilai) => {
        const baru = new URLSearchParams(params);
        if (nilai) baru.set(kunci, nilai);
        else baru.delete(kunci);
        baru.delete('page');
        setParams(baru);
    };

    const pindahHalaman = (halaman) => {
        const baru = new URLSearchParams(params);
        baru.set('page', halaman);
        setParams(baru);
    };

    return (
        <div className="space-y-4">
            <div>
                <h1 className="text-xl font-semibold text-ink">Pencarian Bukti Dukung</h1>
                <p className="text-sm text-sub">
                    {auth.sudahLogin
                        ? 'Cari bukti dari seluruh unit; bukti yang belum terverifikasi hanya tampil untuk unit pemilik dan admin.'
                        : 'Menampilkan bukti yang sudah diverifikasi dan diterima.'}
                </p>
            </div>

            <Card
                title="Filter"
                aksi={
                    <Button varian="ghost" ukuran="sm" onClick={() => setParams({})}>
                        <RotateCcw size={12} /> Reset
                    </Button>
                }
            >
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label="Kata kunci" className="sm:col-span-2">
                        <div className="relative">
                            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-sub" />
                            <Input
                                defaultValue={filter.q}
                                onKeyDown={(e) => e.key === 'Enter' && ubahFilter('q', e.target.value)}
                                onBlur={(e) => e.target.value !== filter.q && ubahFilter('q', e.target.value)}
                                placeholder="Nama file, keterangan, atau judul kegiatan"
                                className="pl-8"
                            />
                        </div>
                    </Field>

                    <Field label="Unit / OPD">
                        <Select value={filter.dinas} onChange={(e) => ubahFilter('dinas', e.target.value)}>
                            <option value="">Semua unit</option>
                            {dinasList.map((d) => (
                                <option key={d.id} value={d.slug}>
                                    {d.nama}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Dimensi">
                        <Select value={filter.dimensi} onChange={(e) => ubahFilter('dimensi', e.target.value)}>
                            <option value="">Semua dimensi</option>
                            {DIMENSI.map((d) => (
                                <option key={d} value={d}>
                                    {d}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Jenis keluaran">
                        <Select value={filter.jenis_keluaran} onChange={(e) => ubahFilter('jenis_keluaran', e.target.value)}>
                            <option value="">Semua jenis</option>
                            {JENIS_KELUARAN.map(([nilai, label]) => (
                                <option key={nilai} value={nilai}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Kategori">
                        <Select value={filter.kategori} onChange={(e) => ubahFilter('kategori', e.target.value)}>
                            <option value="">TIK & Non-TIK</option>
                            {KATEGORI.map(([nilai, label]) => (
                                <option key={nilai} value={nilai}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Status kegiatan">
                        <Select value={filter.status} onChange={(e) => ubahFilter('status', e.target.value)}>
                            <option value="">Semua status</option>
                            {STATUS_KEGIATAN.map(([nilai, label]) => (
                                <option key={nilai} value={nilai}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Tahun">
                        <Select value={filter.tahun} onChange={(e) => ubahFilter('tahun', e.target.value)}>
                            <option value="">Semua tahun</option>
                            {daftarTahun.daftar.map((t) => (
                                <option key={t} value={t}>
                                    {t}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Tipe file">
                        <Select value={filter.tipe_file} onChange={(e) => ubahFilter('tipe_file', e.target.value)}>
                            <option value="">Semua tipe</option>
                            {TIPE_FILE.map(([nilai, label]) => (
                                <option key={nilai} value={nilai}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    {auth.sudahLogin && (
                        <Field label="Status verifikasi">
                            <Select value={filter.status_verifikasi} onChange={(e) => ubahFilter('status_verifikasi', e.target.value)}>
                                <option value="">Semua status</option>
                                {STATUS_VERIFIKASI.map(([nilai, label]) => (
                                    <option key={nilai} value={nilai}>
                                        {label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    )}
                </div>
            </Card>

            {galat && <Alert jenis="galat">{galat}</Alert>}

            <Card title={meta ? `${meta.total} bukti ditemukan` : 'Hasil'} padding="p-0">
                {memuat ? (
                    <Spinner />
                ) : hasil.length === 0 ? (
                    <EmptyState
                        judul="Tidak ada bukti yang ditemukan"
                        keterangan="Coba longgarkan filter, atau ubah kata kuncinya."
                    />
                ) : (
                    <ul className="divide-y divide-line">
                        {hasil.map((bukti) => (
                            <li key={bukti.id} className="flex flex-wrap items-start gap-3 p-4">
                                <IkonBukti kelompok={bukti.kelompok_tipe} />

                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="truncate text-sm font-medium text-ink">{bukti.nama_file}</p>
                                        <StatusVerifikasiBadge status={bukti.status_verifikasi} label={bukti.status_verifikasi_label} />
                                    </div>

                                    <p className="mt-0.5 line-clamp-2 text-xs text-sub">{bukti.deskripsi}</p>

                                    {bukti.rencana_aksi && (
                                        <p className="mt-1 text-[11px] text-sub">
                                            <Link to={`/rencana-aksi/${bukti.rencana_aksi.uuid}`} className="font-medium text-river hover:underline">
                                                {bukti.rencana_aksi.judul}
                                            </Link>
                                            {bukti.rencana_aksi.dinas && <> · {bukti.rencana_aksi.dinas.nama}</>} · {bukti.rencana_aksi.tahun_label}
                                        </p>
                                    )}

                                    <p className="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-sub">
                                        <span>{formatTanggal(bukti.created_at)}</span>
                                        <span>·</span>
                                        <span>{formatUkuran(bukti.ukuran_file)}</span>
                                        {bukti.rencana_aksi && (
                                            <StatusKegiatanBadge status={bukti.rencana_aksi.status} label={bukti.rencana_aksi.status_label} />
                                        )}
                                    </p>

                                    {bukti.alasan_penolakan && (
                                        <p className="mt-1 text-[11px] font-medium text-danger">Alasan ditolak: {bukti.alasan_penolakan}</p>
                                    )}
                                </div>

                                <div className="flex shrink-0 flex-wrap gap-1.5">
                                    <AksiBukti bukti={bukti} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {meta && (
                    <div className="px-4 pb-3">
                        <Pagination meta={meta} onPindah={pindahHalaman} />
                    </div>
                )}
            </Card>
        </div>
    );
}
