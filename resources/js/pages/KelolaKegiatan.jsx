import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { Pencil, Plus, RotateCcw, Search, X } from 'lucide-react';
import api, { errorValidasi, pesanError, siapkanCsrf } from '../lib/api';
import {
    DIMENSI,
    JENIS_KELUARAN,
    KATEGORI,
    STATUS_KEGIATAN,
    rentangTahun,
    useDaftarDinas,
} from '../lib/pilihan';
import {
    Alert,
    Badge,
    Button,
    Card,
    EmptyState,
    Field,
    Input,
    Pagination,
    Select,
    Spinner,
    StatusKegiatanBadge,
    Textarea,
} from '../components/ui';

const TAHUN_INI = new Date().getFullYear();

/** Tahun mulai yang bisa dipilih: dari awal data sampai beberapa periode ke depan. */
const PILIHAN_TAHUN = rentangTahun(TAHUN_INI - 5, TAHUN_INI + 5);

const FORM_KOSONG = {
    dinas_id: '',
    judul: '',
    program: '',
    dimensi: DIMENSI[0],
    jenis_keluaran: 'aplikasi',
    kategori: 'tik',
    status: 'belum_mulai',
    perlu_verifikasi: true,
    tahun_pelaksanaan: [],
};

const FILTER_KOSONG = { q: '', dinas: '' };

export default function KelolaKegiatan() {
    const dinasList = useDaftarDinas();
    const [daftar, setDaftar] = useState([]);
    const [meta, setMeta] = useState(null);
    const [halaman, setHalaman] = useState(1);
    const [filter, setFilter] = useState(FILTER_KOSONG);
    const [memuat, setMemuat] = useState(true);
    const [form, setForm] = useState(FORM_KOSONG);
    const [sedangUbah, setSedangUbah] = useState(null);
    const [formTerbuka, setFormTerbuka] = useState(false);
    const [galat, setGalat] = useState(null);
    const [galatField, setGalatField] = useState({});
    const [pesanSukses, setPesanSukses] = useState(null);
    const [mengirim, setMengirim] = useState(false);
    const [mengubahMassal, setMengubahMassal] = useState(false);
    const kotakCentang = useRef(null);
    const pilihanTahun = [...new Set([...PILIHAN_TAHUN, ...form.tahun_pelaksanaan])].sort((a, b) => b - a);

    const filterAktif = useMemo(() => Object.fromEntries(Object.entries(filter).filter(([, nilai]) => nilai)), [filter]);

    const muat = useCallback(async () => {
        setMemuat(true);

        try {
            const { data } = await api.get('/rencana-aksi', { params: { page: halaman, ...filterAktif } });
            setDaftar(data.data);
            setMeta(data.meta);
        } catch {
            setGalat('Gagal memuat daftar kegiatan.');
        } finally {
            setMemuat(false);
        }
    }, [halaman, filterAktif]);

    useEffect(() => {
        muat();
    }, [muat]);

    // Keadaan kotak centang mengikuti SELURUH kegiatan yang cocok dengan filter
    // (bukan hanya halaman aktif): tercentang = semua wajib, sebagian = campuran.
    const verifikasi = meta?.verifikasi ?? { wajib: 0, tanpa: 0 };
    const totalCocok = meta?.total ?? 0;
    const semuaWajib = totalCocok > 0 && verifikasi.tanpa === 0;
    const campuran = verifikasi.wajib > 0 && verifikasi.tanpa > 0;

    useEffect(() => {
        if (kotakCentang.current) kotakCentang.current.indeterminate = campuran;
    }, [campuran]);

    const ubahFilter = (kunci, nilai) => {
        setFilter((lama) => ({ ...lama, [kunci]: nilai }));
        setHalaman(1);
    };

    const ubahVerifikasiMassal = async (perluVerifikasi) => {
        const yangBerubah = perluVerifikasi ? verifikasi.tanpa : verifikasi.wajib;
        const cakupan = Object.keys(filterAktif).length > 0 ? 'yang cocok dengan pencarian/filter ini' : 'di seluruh daftar';

        const pesan = perluVerifikasi
            ? `Wajibkan verifikasi untuk ${yangBerubah} kegiatan ${cakupan}? Bukti baru akan masuk antrean admin.`
            : `Hentikan verifikasi untuk ${yangBerubah} kegiatan ${cakupan}? Bukti baru langsung tampil ke publik tanpa ditinjau, dan bukti yang masih menunggu otomatis diterima.`;

        if (yangBerubah === 0 || !window.confirm(pesan)) return;

        setMengubahMassal(true);
        setGalat(null);
        setPesanSukses(null);

        try {
            await siapkanCsrf();
            const { data } = await api.patch('/admin/rencana-aksi/verifikasi-massal', {
                ...filterAktif,
                perlu_verifikasi: perluVerifikasi,
            });
            setPesanSukses(data.message);
            muat();
        } catch (error) {
            setGalat(pesanError(error, 'Gagal mengubah pengaturan verifikasi.'));
        } finally {
            setMengubahMassal(false);
        }
    };

    const bukaTambah = () => {
        setSedangUbah(null);
        setForm(FORM_KOSONG);
        setGalatField({});
        setFormTerbuka(true);
    };

    const bukaUbah = (kegiatan) => {
        setSedangUbah(kegiatan);
        setForm({
            dinas_id: String(kegiatan.dinas_id),
            judul: kegiatan.judul,
            program: kegiatan.program ?? '',
            dimensi: kegiatan.dimensi,
            jenis_keluaran: kegiatan.jenis_keluaran,
            kategori: kegiatan.kategori,
            status: kegiatan.status,
            perlu_verifikasi: kegiatan.perlu_verifikasi ?? true,
            tahun_pelaksanaan: kegiatan.tahun_pelaksanaan.map(Number),
        });
        setGalatField({});
        setFormTerbuka(true);
    };

    const simpan = async (e) => {
        e.preventDefault();
        setMengirim(true);
        setGalat(null);
        setGalatField({});

        const { tahun_pelaksanaan, ...isian } = form;
        const payload = {
            ...isian,
            dinas_id: Number(form.dinas_id),
            program: form.program || null,
            tahun_pelaksanaan,
        };

        // Mematikan verifikasi akan melepas bukti yang masih menunggu, jadi
        // admin dikonfirmasi lebih dulu.
        if (sedangUbah && sedangUbah.perlu_verifikasi && !form.perlu_verifikasi) {
            const lanjut = window.confirm(
                'Bukti kegiatan ini tidak akan diverifikasi lagi. Bukti baru langsung tampil ke publik, dan bukti yang masih menunggu akan otomatis diterima. Lanjutkan?',
            );

            if (!lanjut) {
                setMengirim(false);

                return;
            }
        }

        try {
            await siapkanCsrf();
            const { data } = sedangUbah
                ? await api.patch(`/admin/rencana-aksi/${sedangUbah.uuid}`, payload)
                : await api.post('/admin/rencana-aksi', payload);

            setPesanSukses(data.message ?? (sedangUbah ? 'Kegiatan diperbarui.' : 'Kegiatan baru ditambahkan.'));
            setFormTerbuka(false);
            setForm(FORM_KOSONG);
            muat();
        } catch (error) {
            setGalatField(errorValidasi(error));
            setGalat(pesanError(error, 'Gagal menyimpan kegiatan.'));
        } finally {
            setMengirim(false);
        }
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-ink">Kelola Kegiatan</h1>
                    <p className="text-sm text-sub">Tambah dan ubah rencana aksi, serta atur kewajiban verifikasi buktinya.</p>
                </div>
                <Button onClick={bukaTambah}>
                    <Plus size={14} /> Tambah kegiatan
                </Button>
            </div>

            {galat && <Alert jenis="galat">{galat}</Alert>}
            {pesanSukses && <Alert jenis="sukses">{pesanSukses}</Alert>}

            {formTerbuka && (
                <Card
                    title={sedangUbah ? `Ubah: ${sedangUbah.judul}` : 'Kegiatan Baru'}
                    aksi={
                        <Button varian="ghost" ukuran="sm" onClick={() => setFormTerbuka(false)}>
                            <X size={12} /> Tutup
                        </Button>
                    }
                >
                    <form onSubmit={simpan} className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Field label="Judul kegiatan / rencana aksi" error={galatField.judul} wajib className="sm:col-span-2">
                            <Input value={form.judul} onChange={(e) => setForm({ ...form, judul: e.target.value })} required />
                        </Field>

                        <Field label="Program induk" error={galatField.program} petunjuk="Boleh dikosongkan untuk kegiatan tanpa program induk." className="sm:col-span-2">
                            <Textarea rows={2} value={form.program} onChange={(e) => setForm({ ...form, program: e.target.value })} />
                        </Field>

                        <Field label="Unit / OPD (leading sector)" error={galatField.dinas_id} wajib>
                            <Select value={form.dinas_id} onChange={(e) => setForm({ ...form, dinas_id: e.target.value })} required>
                                <option value="">Pilih unit</option>
                                {dinasList.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.nama}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field label="Dimensi" error={galatField.dimensi} wajib>
                            <Select value={form.dimensi} onChange={(e) => setForm({ ...form, dimensi: e.target.value })}>
                                {DIMENSI.map((d) => (
                                    <option key={d} value={d}>
                                        {d}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field label="Jenis keluaran" error={galatField.jenis_keluaran} wajib>
                            <Select value={form.jenis_keluaran} onChange={(e) => setForm({ ...form, jenis_keluaran: e.target.value })}>
                                {JENIS_KELUARAN.map(([nilai, label]) => (
                                    <option key={nilai} value={nilai}>
                                        {label}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field label="Kategori" error={galatField.kategori} wajib>
                            <Select value={form.kategori} onChange={(e) => setForm({ ...form, kategori: e.target.value })}>
                                {KATEGORI.map(([nilai, label]) => (
                                    <option key={nilai} value={nilai}>
                                        {label}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field
                            label="Verifikasi bukti"
                            error={galatField.perlu_verifikasi}
                            petunjuk={
                                form.perlu_verifikasi
                                    ? 'Bukti masuk antrean admin dan baru tampil ke publik setelah diterima.'
                                    : 'Bukti langsung diterima dan tampil ke publik tanpa ditinjau admin.'
                            }
                        >
                            <Select
                                value={form.perlu_verifikasi ? '1' : '0'}
                                onChange={(e) => setForm({ ...form, perlu_verifikasi: e.target.value === '1' })}
                            >
                                <option value="1">Wajib diverifikasi admin</option>
                                <option value="0">Tidak perlu verifikasi</option>
                            </Select>
                        </Field>

                        <Field label="Status awal" error={galatField.status}>
                            <Select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                                {STATUS_KEGIATAN.map(([nilai, label]) => (
                                    <option key={nilai} value={nilai}>
                                        {label}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <div className="sm:col-span-2">
                            <span className="mb-1 block text-xs font-medium text-sub">
                                Periode pelaksanaan <span className="text-danger">*</span>
                            </span>
                            <div className="grid grid-cols-3 gap-2 sm:grid-cols-6">
                                {pilihanTahun.map((tahun) => (
                                    <label key={tahun} className="flex cursor-pointer items-center gap-1.5 rounded-lg border border-line px-2 py-2 text-xs text-ink hover:bg-canvas">
                                        <input
                                            type="checkbox"
                                            checked={form.tahun_pelaksanaan.includes(tahun)}
                                            onChange={() =>
                                                setForm({
                                                    ...form,
                                                    tahun_pelaksanaan: form.tahun_pelaksanaan.includes(tahun)
                                                        ? form.tahun_pelaksanaan.filter((nilai) => nilai !== tahun)
                                                        : [...form.tahun_pelaksanaan, tahun].sort((a, b) => a - b),
                                                })
                                            }
                                        />
                                        {tahun}
                                    </label>
                                ))}
                            </div>
                            {galatField.tahun_pelaksanaan ? (
                                <span className="mt-1 block text-[11px] font-medium text-danger">{galatField.tahun_pelaksanaan}</span>
                            ) : (
                                <span className="mt-1 block text-[11px] text-sub">
                                    Pilih satu atau beberapa tahun secara bebas. Minimal satu tahun wajib dipilih.
                                </span>
                            )}
                        </div>

                        <div className="sm:col-span-2">
                            <Button type="submit" memuat={mengirim} disabled={form.tahun_pelaksanaan.length === 0}>
                                {sedangUbah ? 'Simpan perubahan' : 'Tambah kegiatan'}
                            </Button>
                        </div>
                    </form>
                </Card>
            )}

            <Card title={meta ? `${meta.total} kegiatan` : 'Daftar Kegiatan'} padding="p-0">
                <div className="space-y-3 border-b border-line px-4 pb-4">
                    <label className="flex cursor-pointer items-start gap-2.5 rounded-lg bg-canvas px-3 py-2.5">
                        <input
                            ref={kotakCentang}
                            type="checkbox"
                            className="mt-0.5 h-4 w-4 shrink-0 cursor-pointer accent-river disabled:cursor-not-allowed"
                            checked={semuaWajib}
                            disabled={mengubahMassal || memuat || totalCocok === 0}
                            onChange={(e) => ubahVerifikasiMassal(e.target.checked)}
                        />
                        <span className="min-w-0">
                            <span className="block text-sm font-medium text-ink">
                                Wajib verifikasi untuk semua kegiatan yang tampil
                                {mengubahMassal && <span className="ml-1 text-xs text-sub">(menyimpan…)</span>}
                            </span>
                            <span className="block text-[11px] text-sub">
                                {totalCocok === 0
                                    ? 'Tidak ada kegiatan pada filter ini.'
                                    : `${verifikasi.wajib} dari ${totalCocok} kegiatan${
                                          Object.keys(filterAktif).length > 0 ? ' pada filter ini' : ''
                                      } wajib diverifikasi. Berlaku untuk semua halaman, bukan hanya yang terlihat.`}
                            </span>
                        </span>
                    </label>

                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,16rem)_auto]">
                        <div className="relative">
                            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-sub" />
                            <Input
                                key={filter.q === '' ? 'kosong' : 'isi'}
                                defaultValue={filter.q}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') ubahFilter('q', e.target.value.trim());
                                }}
                                onBlur={(e) => e.target.value.trim() !== filter.q && ubahFilter('q', e.target.value.trim())}
                                placeholder="Cari judul, program, atau nama unit…"
                                aria-label="Cari kegiatan"
                                className="pl-8"
                            />
                        </div>

                        <Select aria-label="Filter unit" value={filter.dinas} onChange={(e) => ubahFilter('dinas', e.target.value)}>
                            <option value="">Semua unit</option>
                            {dinasList.map((d) => (
                                <option key={d.id} value={d.slug}>
                                    {d.nama}
                                </option>
                            ))}
                        </Select>

                        <Button
                            varian="ghost"
                            onClick={() => {
                                setFilter(FILTER_KOSONG);
                                setHalaman(1);
                            }}
                            disabled={Object.keys(filterAktif).length === 0}
                        >
                            <RotateCcw size={12} /> Reset
                        </Button>
                    </div>
                </div>

                {memuat ? (
                    <Spinner />
                ) : daftar.length === 0 ? (
                    <EmptyState judul="Tidak ada Rencana Kegiatan Aksi yang ditemukan" keterangan="Coba kata kunci lain atau pilih unit yang berbeda." />
                ) : (
                    <ul className="divide-y divide-line">
                        {daftar.map((kegiatan) => (
                            <li key={kegiatan.id} className="flex flex-wrap items-start gap-3 p-4">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link
                                            to={`/rencana-aksi/${kegiatan.uuid}`}
                                            state={{ dari: '/admin/kegiatan' }}
                                            className="truncate text-sm font-medium text-ink hover:text-river hover:underline"
                                        >
                                            {kegiatan.judul}
                                        </Link>
                                        <StatusKegiatanBadge status={kegiatan.status} label={kegiatan.status_label} />
                                        {kegiatan.perlu_verifikasi === false && <Badge warna="abu">Tanpa verifikasi</Badge>}
                                    </div>
                                    <p className="mt-0.5 text-[11px] text-sub">
                                        {kegiatan.dinas?.nama} · {kegiatan.dimensi} · {kegiatan.jenis_keluaran_label} · {kegiatan.kategori_label} ·{' '}
                                        {kegiatan.tahun_label} · {kegiatan.jumlah_bukti_diterima ?? 0} bukti diterima
                                    </p>
                                </div>

                                <Button ukuran="sm" varian="outline" onClick={() => bukaUbah(kegiatan)}>
                                    <Pencil size={12} /> Ubah
                                </Button>
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
