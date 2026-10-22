import { useCallback, useEffect, useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import { ArrowLeft, FileText, Image as ImageIcon, Trash2, Upload } from 'lucide-react';
import api, { errorValidasi, pesanError, siapkanCsrf } from '../lib/api';
import AksiBukti from '../components/AksiBukti';
import { useAuth } from '../contexts/AuthContext';
import { STATUS_KEGIATAN } from '../lib/pilihan';
import {
    Alert,
    Badge,
    Button,
    Card,
    EmptyState,
    Field,
    Input,
    Select,
    Spinner,
    StatusKegiatanBadge,
    StatusVerifikasiBadge,
    Textarea,
    formatTanggal,
    formatUkuran,
} from '../components/ui';

/** Dipakai sampai server memberi tahu batas yang sebenarnya (meta.batas_unggah_kb). */
const BATAS_BAKU_KB = 10 * 1024;

const RINGKASAN_GALAT_ISIAN = 'Bukti belum terkirim. Perbaiki isian yang ditandai merah di formulir unggah, lalu kirim ulang.';

function labelMb(kb) {
    return `${Number((kb / 1024).toFixed(1)).toLocaleString('id-ID')} MB`;
}

/**
 * Alasan kegagalan unggah yang bisa ditindaklanjuti pegawai, per jenis galat.
 * Galat validasi (422/413) dirinci per kolom; ringkasan ini tampil di atas.
 */
function alasanGagalUnggah(error) {
    const respons = error?.response;

    if (!respons) return 'Bukti belum terkirim: tidak dapat terhubung ke server. Periksa koneksi internet lalu coba lagi.';

    if (Object.keys(errorValidasi(error)).length > 0) {
        return RINGKASAN_GALAT_ISIAN;
    }

    switch (respons.status) {
        case 401:
            return 'Bukti belum terkirim: sesi login Anda sudah berakhir. Silakan masuk kembali.';
        case 403:
            return 'Bukti belum terkirim: akun Anda tidak berhak mengunggah bukti untuk kegiatan ini.';
        case 404:
            return 'Bukti belum terkirim: kegiatan ini tidak ditemukan, mungkin sudah dihapus.';
        case 419:
            return 'Bukti belum terkirim: halaman sudah terlalu lama terbuka. Muat ulang halaman lalu coba lagi.';
        case 429: {
            const detik = Number(respons.headers?.['retry-after']);
            return `Bukti belum terkirim: terlalu banyak unggahan dalam waktu singkat. Coba lagi ${detik > 0 ? `dalam ${detik} detik` : 'sebentar lagi'}.`;
        }
        default:
            return respons.status >= 500
                ? 'Bukti belum terkirim: server gagal menyimpan file. Coba lagi beberapa saat lagi; jika tetap gagal, hubungi admin.'
                : pesanError(error, 'Bukti belum terkirim. Coba lagi.');
    }
}

function BarisInfo({ label, children }) {
    return (
        <div className="flex flex-wrap gap-2 border-b border-line py-2 last:border-b-0">
            <span className="w-40 shrink-0 text-xs text-sub">{label}</span>
            <span className="min-w-0 flex-1 text-sm text-ink">{children}</span>
        </div>
    );
}

export default function DetailRencanaAksi() {
    const { id } = useParams();
    const location = useLocation();
    const auth = useAuth();
    const [rencana, setRencana] = useState(null);
    const [bukti, setBukti] = useState([]);
    const [memuat, setMemuat] = useState(true);
    const [galat, setGalat] = useState(null);
    const [pesanSukses, setPesanSukses] = useState(null);

    const [file, setFile] = useState(null);
    const [deskripsi, setDeskripsi] = useState('');
    const [galatUnggah, setGalatUnggah] = useState({});
    const [batasKb, setBatasKb] = useState(BATAS_BAKU_KB);
    const [mengunggah, setMengunggah] = useState(false);
    const [statusBaru, setStatusBaru] = useState('');
    const [menyimpanStatus, setMenyimpanStatus] = useState(false);
    const tujuanKembali = location.state?.dari === '/admin/kegiatan' ? '/admin/kegiatan' : '/bukti';

    const muat = useCallback(async () => {
        setMemuat(true);
        setGalat(null);

        try {
            const [resRencana, resBukti] = await Promise.all([
                api.get(`/rencana-aksi/${id}`),
                api.get(`/rencana-aksi/${id}/bukti`),
            ]);

            setRencana(resRencana.data.data);
            setStatusBaru(resRencana.data.data.status);
            setBukti(resBukti.data.data);
            if (resBukti.data.meta?.batas_unggah_kb) setBatasKb(resBukti.data.meta.batas_unggah_kb);
        } catch (error) {
            setGalat(error?.response?.status === 404 ? 'Kegiatan tidak ditemukan.' : 'Gagal memuat detail kegiatan.');
        } finally {
            setMemuat(false);
        }
    }, [id]);

    useEffect(() => {
        muat();
    }, [muat]);

    // Unit hanya boleh mengelola kegiatan miliknya; Admin Pusat semua kegiatan.
    const bolehKelola =
        auth.sudahLogin && rencana !== null && (auth.isAdminPusat || (auth.isUnit && auth.user.dinas_id === rencana.dinas_id));

    const unggah = async (e) => {
        e.preventDefault();
        setGalat(null);
        setGalatUnggah({});
        setPesanSukses(null);

        // Cek di browser dulu agar file besar tidak perlu dikirim hanya untuk ditolak.
        if (file && file.size > batasKb * 1024) {
            setGalatUnggah({
                file: `Ukuran file ${formatUkuran(file.size)} melebihi batas ${labelMb(batasKb)}. Perkecil atau kompres file (mis. PDF hasil pindai) lalu coba lagi.`,
            });
            setGalat(RINGKASAN_GALAT_ISIAN);
            return;
        }

        setMengunggah(true);

        try {
            await siapkanCsrf();
            const formData = new FormData();
            formData.append('file', file);
            formData.append('deskripsi', deskripsi);

            const { data } = await api.post(`/rencana-aksi/${id}/bukti`, formData);
            setPesanSukses(data.message ?? 'Bukti berhasil diunggah.');
            setFile(null);
            setDeskripsi('');
            e.target.reset();
            muat();
        } catch (error) {
            setGalatUnggah(errorValidasi(error));
            setGalat(alasanGagalUnggah(error));
        } finally {
            setMengunggah(false);
        }
    };

    const simpanStatus = async () => {
        setMenyimpanStatus(true);
        setGalat(null);

        try {
            await siapkanCsrf();
            const { data } = await api.patch(`/rencana-aksi/${id}`, { status: statusBaru });
            setRencana(data.data);
            setPesanSukses(data.message ?? 'Status kegiatan diperbarui.');
        } catch (error) {
            setGalat(pesanError(error, 'Gagal mengubah status.'));
        } finally {
            setMenyimpanStatus(false);
        }
    };

    const hapusBukti = async (buktiUuid) => {
        if (!window.confirm('Hapus bukti ini? Tindakan ini tercatat di audit trail.')) return;

        try {
            await siapkanCsrf();
            await api.delete(`/bukti/${buktiUuid}`);
            setPesanSukses('Bukti dihapus.');
            muat();
        } catch (error) {
            setGalat(pesanError(error, 'Gagal menghapus bukti.'));
        }
    };

    if (memuat) return <Spinner />;

    if (!rencana) {
        return (
            <Card>
                <Alert jenis="galat">{galat ?? 'Kegiatan tidak ditemukan.'}</Alert>
                <Link to={tujuanKembali} className="mt-3 inline-block text-xs text-river hover:underline">
                    Kembali ke {tujuanKembali === '/admin/kegiatan' ? 'Kelola Kegiatan' : 'pencarian bukti'}
                </Link>
            </Card>
        );
    }

    return (
        <div className="space-y-4">
            <Link to={tujuanKembali} className="inline-flex items-center gap-1.5 text-xs font-semibold text-river hover:underline">
                <ArrowLeft size={13} /> Kembali
            </Link>

            <div>
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-semibold text-ink">{rencana.judul}</h1>
                    <StatusKegiatanBadge status={rencana.status} label={rencana.status_label} />
                    {rencana.perlu_verifikasi === false && <Badge warna="abu">Bukti tanpa verifikasi</Badge>}
                </div>
                <p className="text-sm text-sub">
                    {rencana.dinas?.nama} · {rencana.dimensi} · {rencana.tahun_label}
                </p>
            </div>

            {galat && <Alert jenis="galat">{galat}</Alert>}
            {pesanSukses && <Alert jenis="sukses">{pesanSukses}</Alert>}

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    <Card title={`Bukti Dukung (${bukti.length})`} padding="p-0">
                        {bukti.length === 0 ? (
                            <EmptyState
                                judul="Belum ada bukti untuk kegiatan ini"
                                keterangan={bolehKelola ? 'Unggah bukti pertama lewat formulir di samping.' : 'Bukti akan tampil setelah diunggah dan diverifikasi.'}
                                ikon={FileText}
                            />
                        ) : (
                            <ul className="divide-y divide-line">
                                {bukti.map((item) => (
                                    <li key={item.id} className="flex flex-wrap items-start gap-3 p-4">
                                        {item.kelompok_tipe === 'gambar' ? (
                                            <ImageIcon size={16} className="shrink-0 text-river" />
                                        ) : (
                                            <FileText size={16} className="shrink-0 text-river" />
                                        )}

                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="truncate text-sm font-medium text-ink">{item.nama_file}</p>
                                                <StatusVerifikasiBadge status={item.status_verifikasi} label={item.status_verifikasi_label} />
                                            </div>
                                            <p className="mt-0.5 text-xs text-sub">{item.deskripsi}</p>
                                            <p className="mt-1 text-[11px] text-sub">
                                                {formatTanggal(item.created_at, true)} · {formatUkuran(item.ukuran_file)}
                                                {item.uploader && <> · diunggah oleh {item.uploader.name}</>}
                                            </p>
                                            {item.alasan_penolakan && (
                                                <p className="mt-1 text-[11px] font-medium text-danger">Alasan ditolak: {item.alasan_penolakan}</p>
                                            )}
                                        </div>

                                        <div className="flex shrink-0 gap-1.5">
                                            <AksiBukti bukti={item} />
                                            {bolehKelola && (
                                                <button
                                                    type="button"
                                                    onClick={() => hapusBukti(item.uuid)}
                                                    className="inline-flex items-center rounded-lg border border-line px-2 py-1 text-danger transition hover:bg-danger-soft"
                                                    aria-label={`Hapus ${item.nama_file}`}
                                                >
                                                    <Trash2 size={12} />
                                                </button>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card title="Rincian Kegiatan">
                        <BarisInfo label="Program">{rencana.program ?? '—'}</BarisInfo>
                        <BarisInfo label="Unit / OPD">{rencana.dinas?.nama}</BarisInfo>
                        <BarisInfo label="Dimensi">{rencana.dimensi}</BarisInfo>
                        <BarisInfo label="Jenis keluaran">{rencana.jenis_keluaran_label}</BarisInfo>
                        <BarisInfo label="Kategori">{rencana.kategori_label}</BarisInfo>
                        <BarisInfo label="Verifikasi bukti">
                            {rencana.perlu_verifikasi === false ? 'Tidak perlu — bukti langsung diterima' : 'Wajib ditinjau admin'}
                        </BarisInfo>
                        <BarisInfo label="Tahun pelaksanaan">
                            {rencana.tahun_label}
                            <span className="ml-1 text-xs text-sub">({rencana.tahun_pelaksanaan.join(', ')})</span>
                        </BarisInfo>
                    </Card>
                </div>

                <div className="space-y-4">
                    {bolehKelola ? (
                        <>
                            <Card title="Ubah Status Kegiatan">
                                <p className="mb-2 text-[11px] text-sub">Status diisi manual oleh unit/admin, tidak dihitung otomatis.</p>
                                <div className="flex gap-2">
                                    <Select value={statusBaru} onChange={(e) => setStatusBaru(e.target.value)} className="flex-1">
                                        {STATUS_KEGIATAN.map(([nilai, label]) => (
                                            <option key={nilai} value={nilai}>
                                                {label}
                                            </option>
                                        ))}
                                    </Select>
                                    <Button onClick={simpanStatus} memuat={menyimpanStatus} disabled={statusBaru === rencana.status}>
                                        Simpan
                                    </Button>
                                </div>
                            </Card>

                            <Card title="Unggah Bukti">
                                <form onSubmit={unggah} className="space-y-3">
                                    <Field
                                        label="File bukti"
                                        error={galatUnggah.file}
                                        wajib
                                        petunjuk={`PDF, gambar, atau dokumen Office. Maksimal ${labelMb(batasKb)}.`}
                                    >
                                        <Input
                                            type="file"
                                            onChange={(e) => {
                                                setFile(e.target.files?.[0] ?? null);
                                                setGalatUnggah(({ file: _, ...lainnya }) => lainnya);
                                            }}
                                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                                            required
                                            className="file:mr-2 file:rounded file:border-0 file:bg-river-soft file:px-2 file:py-1 file:text-xs file:font-semibold file:text-river"
                                        />
                                    </Field>

                                    <Field
                                        label="Keterangan bukti"
                                        error={galatUnggah.deskripsi}
                                        wajib
                                        petunjuk="Jelaskan isi bukti secara singkat (minimal 10 karakter); nama file saja tidak cukup."
                                    >
                                        <Textarea
                                            rows={3}
                                            value={deskripsi}
                                            onChange={(e) => setDeskripsi(e.target.value)}
                                            placeholder="Contoh: Dokumentasi rapat koordinasi peluncuran aplikasi, 12 Agustus 2026."
                                            required
                                        />
                                    </Field>

                                    <Button type="submit" memuat={mengunggah} className="w-full">
                                        <Upload size={14} /> Unggah bukti
                                    </Button>

                                    <p className="text-[11px] text-sub">
                                        {rencana.perlu_verifikasi === false
                                            ? 'Kegiatan ini tidak memerlukan verifikasi: bukti langsung diterima dan tampil ke publik.'
                                            : 'Bukti baru berstatus "Menunggu" hingga diverifikasi admin.'}
                                    </p>
                                </form>
                            </Card>
                        </>
                    ) : (
                        <Card title="Unggah Bukti">
                            <p className="text-xs text-sub">
                                {auth.sudahLogin
                                    ? 'Kegiatan ini di luar cakupan unit Anda, jadi hanya bisa dilihat.'
                                    : 'Masuk sebagai unit pemilik kegiatan untuk mengunggah bukti.'}
                            </p>
                            {!auth.sudahLogin && (
                                <Link to="/login" className="mt-2 inline-block text-xs font-semibold text-river hover:underline">
                                    Masuk
                                </Link>
                            )}
                        </Card>
                    )}
                </div>
            </div>
        </div>
    );
}
