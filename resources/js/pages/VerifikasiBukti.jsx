import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Check, FileText, Image as ImageIcon, ShieldCheck, X } from 'lucide-react';
import api, { pesanError, siapkanCsrf } from '../lib/api';
import AksiBukti from '../components/AksiBukti';
import { useDaftarTahun, useDaftarDinas } from '../lib/pilihan';
import {
    Alert,
    Button,
    Card,
    EmptyState,
    Field,
    Pagination,
    Select,
    Spinner,
    Textarea,
    formatTanggal,
    formatUkuran,
} from '../components/ui';

export default function VerifikasiBukti() {
    const daftarTahun = useDaftarTahun();
    const dinasList = useDaftarDinas();
    const [filter, setFilter] = useState({ dinas: '', tahun: '' });
    const [halaman, setHalaman] = useState(1);
    const [antrean, setAntrean] = useState([]);
    const [meta, setMeta] = useState(null);
    const [memuat, setMemuat] = useState(true);
    const [galat, setGalat] = useState(null);
    const [pesanSukses, setPesanSukses] = useState(null);
    const [menolak, setMenolak] = useState(null); // id bukti yang sedang diisi alasannya
    const [alasan, setAlasan] = useState('');
    const [memproses, setMemproses] = useState(null);

    const muat = useCallback(async () => {
        setMemuat(true);
        setGalat(null);

        try {
            const { data } = await api.get('/verifikasi/antrean', {
                params: { ...Object.fromEntries(Object.entries(filter).filter(([, v]) => v)), page: halaman },
            });
            setAntrean(data.data);
            setMeta(data.meta);
        } catch (error) {
            setGalat(pesanError(error, 'Gagal memuat antrean verifikasi.'));
        } finally {
            setMemuat(false);
        }
    }, [filter, halaman]);

    useEffect(() => {
        muat();
    }, [muat]);

    const putuskan = async (bukti, keputusan) => {
        setMemproses(bukti.id);
        setGalat(null);

        try {
            await siapkanCsrf();
            const { data } = await api.patch(`/bukti/${bukti.uuid}/verifikasi`, {
                status_verifikasi: keputusan,
                ...(keputusan === 'ditolak' ? { alasan_penolakan: alasan } : {}),
            });

            setPesanSukses(data.message ?? 'Keputusan verifikasi tersimpan.');
            setMenolak(null);
            setAlasan('');
            muat();
        } catch (error) {
            setGalat(pesanError(error, 'Gagal menyimpan keputusan verifikasi.'));
        } finally {
            setMemproses(null);
        }
    };

    return (
        <div className="space-y-4">
            <div>
                <h1 className="text-xl font-semibold text-ink">Verifikasi Bukti</h1>
                <p className="text-sm text-sub">Bukti berstatus "Menunggu" dalam cakupan Anda, terlama lebih dulu.</p>
            </div>

            <Card title="Filter">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Field label="Unit / OPD">
                        <Select
                            value={filter.dinas}
                            onChange={(e) => {
                                setFilter({ ...filter, dinas: e.target.value });
                                setHalaman(1);
                            }}
                        >
                            <option value="">Semua unit dalam cakupan</option>
                            {dinasList.map((d) => (
                                <option key={d.id} value={d.slug}>
                                    {d.nama}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Tahun kegiatan">
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
                </div>
            </Card>

            {galat && <Alert jenis="galat">{galat}</Alert>}
            {pesanSukses && <Alert jenis="sukses">{pesanSukses}</Alert>}

            <Card title={meta ? `${meta.total} bukti menunggu verifikasi` : 'Antrean'} padding="p-0">
                {memuat ? (
                    <Spinner />
                ) : antrean.length === 0 ? (
                    <EmptyState
                        judul="Tidak ada bukti yang menunggu"
                        keterangan="Semua bukti dalam cakupan Anda sudah ditinjau."
                        ikon={ShieldCheck}
                    />
                ) : (
                    <ul className="divide-y divide-line">
                        {antrean.map((bukti) => (
                            <li key={bukti.id} className="p-4">
                                <div className="flex flex-wrap items-start gap-3">
                                    {bukti.kelompok_tipe === 'gambar' ? (
                                        <ImageIcon size={16} className="shrink-0 text-river" />
                                    ) : (
                                        <FileText size={16} className="shrink-0 text-river" />
                                    )}

                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-ink">{bukti.nama_file}</p>
                                        <p className="mt-0.5 text-xs text-sub">{bukti.deskripsi}</p>

                                        {bukti.rencana_aksi && (
                                            <p className="mt-1 text-[11px] text-sub">
                                                <Link to={`/rencana-aksi/${bukti.rencana_aksi.uuid}`} className="font-medium text-river hover:underline">
                                                    {bukti.rencana_aksi.judul}
                                                </Link>
                                                {bukti.rencana_aksi.dinas && <> · {bukti.rencana_aksi.dinas.nama}</>}
                                            </p>
                                        )}

                                        <p className="mt-1 text-[11px] text-sub">
                                            {formatTanggal(bukti.created_at, true)} · {formatUkuran(bukti.ukuran_file)}
                                            {bukti.uploader && <> · diunggah oleh {bukti.uploader.name}</>}
                                        </p>
                                    </div>

                                    <div className="flex shrink-0 flex-wrap gap-1.5">
                                        <AksiBukti bukti={bukti} />
                                        <Button
                                            ukuran="sm"
                                            varian="primary"
                                            onClick={() => putuskan(bukti, 'diterima')}
                                            memuat={memproses === bukti.id && menolak !== bukti.id}
                                        >
                                            <Check size={12} /> Terima
                                        </Button>
                                        <Button
                                            ukuran="sm"
                                            varian="outline"
                                            onClick={() => {
                                                setMenolak(menolak === bukti.id ? null : bukti.id);
                                                setAlasan('');
                                            }}
                                        >
                                            <X size={12} /> Tolak
                                        </Button>
                                    </div>
                                </div>

                                {menolak === bukti.id && (
                                    <div className="mt-3 rounded-lg bg-canvas p-3">
                                        <Field label="Alasan penolakan" wajib petunjuk="Dikirim ke unit pengunggah sebagai notifikasi agar bisa diperbaiki.">
                                            <Textarea
                                                rows={2}
                                                value={alasan}
                                                onChange={(e) => setAlasan(e.target.value)}
                                                placeholder="Contoh: Dokumen tidak terbaca pada halaman 3, mohon unggah ulang."
                                                autoFocus
                                            />
                                        </Field>
                                        <div className="mt-2 flex gap-2">
                                            <Button
                                                ukuran="sm"
                                                varian="danger"
                                                onClick={() => putuskan(bukti, 'ditolak')}
                                                memuat={memproses === bukti.id}
                                                disabled={alasan.trim().length < 5}
                                            >
                                                Kirim penolakan
                                            </Button>
                                            <Button ukuran="sm" varian="outline" onClick={() => setMenolak(null)}>
                                                Batal
                                            </Button>
                                        </div>
                                    </div>
                                )}
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
