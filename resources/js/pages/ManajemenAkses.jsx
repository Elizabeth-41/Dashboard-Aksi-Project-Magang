import { useCallback, useEffect, useState } from 'react';
import { Ban, RotateCcw, Trash2, UserPlus } from 'lucide-react';
import api, { errorValidasi, pesanError, siapkanCsrf } from '../lib/api';
import { ROLE, useDaftarDinas } from '../lib/pilihan';
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
    formatTanggal,
} from '../components/ui';

const FORM_KOSONG = { email: '', role: 'unit', dinas_id: '' };

export default function ManajemenAkses() {
    const dinasList = useDaftarDinas();
    const [daftar, setDaftar] = useState([]);
    const [memuat, setMemuat] = useState(true);
    const [form, setForm] = useState(FORM_KOSONG);
    const [galat, setGalat] = useState(null);
    const [galatField, setGalatField] = useState({});
    const [pesanSukses, setPesanSukses] = useState(null);
    const [mengirim, setMengirim] = useState(false);

    const muat = useCallback(async () => {
        setMemuat(true);

        try {
            // Jumlah akun kecil (satu per unit), jadi endpoint ini tidak terpaginasi.
            const { data } = await api.get('/admin/allowed-users');
            setDaftar(data.data);
        } catch (error) {
            setGalat(pesanError(error, 'Gagal memuat daftar akses.'));
        } finally {
            setMemuat(false);
        }
    }, []);

    useEffect(() => {
        muat();
    }, [muat]);

    /** Setiap akun wajib terikat unit; Admin Pusat lazimnya dari Diskominfotik. */
    const gantiRole = (role) => {
        const diskominfotik = dinasList.find((d) => d.slug === 'diskominfotik');

        setForm((lama) => ({
            ...lama,
            role,
            dinas_id: role === 'admin_pusat' && diskominfotik ? String(diskominfotik.id) : '',
        }));
    };

    const tambah = async (e) => {
        e.preventDefault();
        setMengirim(true);
        setGalat(null);
        setGalatField({});
        setPesanSukses(null);

        try {
            await siapkanCsrf();
            const { data } = await api.post('/admin/allowed-users', {
                email: form.email,
                role: form.role,
                dinas_id: Number(form.dinas_id),
            });

            setPesanSukses(data.message ?? `Undangan dikirim ke ${form.email}.`);
            setForm(FORM_KOSONG);
            muat();
        } catch (error) {
            setGalatField(errorValidasi(error));
            setGalat(pesanError(error, 'Gagal menambah akses.'));
        } finally {
            setMengirim(false);
        }
    };

    const ubahStatus = async (item, status) => {
        setGalat(null);

        try {
            await siapkanCsrf();
            await api.patch(`/admin/allowed-users/${item.id}`, { status });
            setPesanSukses(status === 'dicabut' ? `Akses ${item.email} dicabut.` : `Akses ${item.email} diaktifkan kembali.`);
            muat();
        } catch (error) {
            setGalat(pesanError(error, 'Gagal mengubah status akses.'));
        }
    };

    const hapus = async (item) => {
        if (!window.confirm(`Hapus ${item.email} dari daftar akses? Tindakan ini tercatat di audit trail.`)) return;

        try {
            await siapkanCsrf();
            await api.delete(`/admin/allowed-users/${item.id}`);
            setPesanSukses(`${item.email} dihapus dari daftar akses.`);
            muat();
        } catch (error) {
            setGalat(pesanError(error, 'Gagal menghapus akses.'));
        }
    };

    return (
        <div className="space-y-4">
            <div>
                <h1 className="text-xl font-semibold text-ink">Manajemen Akses</h1>
                <p className="text-sm text-sub">Hanya email pada daftar ini yang bisa masuk ke aplikasi.</p>
            </div>

            {galat && <Alert jenis="galat">{galat}</Alert>}
            {pesanSukses && <Alert jenis="sukses">{pesanSukses}</Alert>}

            <Card title="Tambah Akses">
                <form onSubmit={tambah} className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <Field label="Email" error={galatField.email} wajib className="sm:col-span-2">
                        <Input
                            type="email"
                            value={form.email}
                            onChange={(e) => setForm({ ...form, email: e.target.value })}
                            placeholder="nama@banjarmasinkota.go.id"
                            required
                        />
                    </Field>

                    <Field label="Role" error={galatField.role} wajib>
                        <Select value={form.role} onChange={(e) => gantiRole(e.target.value)}>
                            {ROLE.map(([nilai, label]) => (
                                <option key={nilai} value={nilai}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field
                        label="Unit / OPD"
                        error={galatField.dinas_id}
                        wajib
                        petunjuk={form.role === 'admin_pusat' ? 'Admin Pusat tetap berwenang atas semua unit; unit ini penanda asal instansinya.' : undefined}
                    >
                        <Select value={form.dinas_id} onChange={(e) => setForm({ ...form, dinas_id: e.target.value })} required>
                            <option value="">Pilih unit</option>
                            {dinasList.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.nama}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <div className="sm:col-span-4">
                        <Button type="submit" memuat={mengirim}>
                            <UserPlus size={14} /> Tambah & kirim undangan
                        </Button>
                        <p className="mt-1 text-[11px] text-sub">
                            Akun dibuatkan otomatis dan diberi tautan pembuatan password lewat email.
                        </p>
                    </div>
                </form>
            </Card>

            <Card title={`${daftar.length} akun terdaftar`} padding="p-0">
                {memuat ? (
                    <Spinner />
                ) : daftar.length === 0 ? (
                    <EmptyState judul="Belum ada akun terdaftar" ikon={UserPlus} />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="border-b border-line bg-canvas text-xs text-sub">
                                <tr>
                                    <th className="px-4 py-2 font-medium">Email</th>
                                    <th className="px-4 py-2 font-medium">Role</th>
                                    <th className="px-4 py-2 font-medium">Unit</th>
                                    <th className="px-4 py-2 font-medium">Status</th>
                                    <th className="px-4 py-2 font-medium">Terakhir login</th>
                                    <th className="px-4 py-2 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {daftar.map((item) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-2.5">
                                            <p className="font-medium text-ink">{item.email}</p>
                                            <p className="text-[11px] text-sub">Diundang {formatTanggal(item.invited_at)}</p>
                                        </td>
                                        <td className="px-4 py-2.5 text-xs text-sub">{item.role_label}</td>
                                        <td className="px-4 py-2.5 text-xs text-sub">{item.dinas?.nama ?? '—'}</td>
                                        <td className="px-4 py-2.5">
                                            <Badge warna={item.status === 'aktif' ? 'hijau' : 'merah'}>{item.status_label ?? item.status}</Badge>
                                        </td>
                                        <td className="px-4 py-2.5 text-xs text-sub">{formatTanggal(item.last_login_at, true)}</td>
                                        <td className="px-4 py-2.5">
                                            <div className="flex gap-1.5">
                                                {item.status === 'aktif' ? (
                                                    <Button ukuran="sm" varian="outline" onClick={() => ubahStatus(item, 'dicabut')}>
                                                        <Ban size={12} /> Cabut
                                                    </Button>
                                                ) : (
                                                    <Button ukuran="sm" varian="outline" onClick={() => ubahStatus(item, 'aktif')}>
                                                        <RotateCcw size={12} /> Aktifkan
                                                    </Button>
                                                )}
                                                <button
                                                    type="button"
                                                    onClick={() => hapus(item)}
                                                    className="inline-flex items-center rounded-lg border border-line px-2 py-1 text-danger transition hover:bg-danger-soft"
                                                    aria-label={`Hapus ${item.email}`}
                                                >
                                                    <Trash2 size={12} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

            </Card>
        </div>
    );
}
