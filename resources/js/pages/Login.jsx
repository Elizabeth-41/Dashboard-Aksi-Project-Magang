import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { errorValidasi, pesanError } from '../lib/api';
import { Alert, Button, Field, Input } from '../components/ui';
import LayarAuth from '../components/LayarAuth';

export default function Login() {
    const auth = useAuth();
    const navigate = useNavigate();
    const lokasi = useLocation();
    const [form, setForm] = useState({ email: '', password: '' });
    const [kodeDuaFaktor, setKodeDuaFaktor] = useState('');
    const [tahapDuaFaktor, setTahapDuaFaktor] = useState(false);
    const [pakaiKodePemulihan, setPakaiKodePemulihan] = useState(false);
    const [galat, setGalat] = useState(null);
    const [galatField, setGalatField] = useState({});
    const [mengirim, setMengirim] = useState(false);

    const tujuan = lokasi.state?.dari ?? '/';

    const masuk = async (e) => {
        e.preventDefault();
        setMengirim(true);
        setGalat(null);
        setGalatField({});

        try {
            const { perluDuaFaktor } = await auth.login(form.email, form.password);

            if (perluDuaFaktor) {
                setTahapDuaFaktor(true);

                return;
            }

            // Setelah user termuat, penjaga <HanyaTamu> yang memutuskan tujuan
            // (dashboard, atau pemasangan 2FA bagi Admin Pusat).
            navigate(tujuan, { replace: true });
        } catch (error) {
            setGalat(pesanError(error, 'Login gagal'));
            setGalatField(errorValidasi(error));
        } finally {
            setMengirim(false);
        }
    };

    const kirimKode = async (e) => {
        e.preventDefault();
        setMengirim(true);
        setGalat(null);

        try {
            const user = await auth.kirimTantanganDuaFaktor(kodeDuaFaktor.trim(), pakaiKodePemulihan);

            if (user === null) {
                setGalat('Verifikasi gagal. Silakan masuk ulang.');

                return;
            }

            navigate(tujuan, { replace: true });
        } catch (error) {
            setGalat(pesanError(error, 'Kode tidak valid.'));
        } finally {
            setMengirim(false);
        }
    };

    return (
        <LayarAuth judul={tahapDuaFaktor ? 'Verifikasi Dua Faktor' : 'Masuk'} keterangan={tahapDuaFaktor ? 'Masukkan kode dari aplikasi autentikator Anda.' : 'Gunakan email yang sudah didaftarkan Admin Pusat.'}>
            {galat && (
                <Alert jenis="galat" className="mb-3">
                    {galat}
                </Alert>
            )}

            {tahapDuaFaktor ? (
                <form onSubmit={kirimKode} className="space-y-3">
                    <Field label={pakaiKodePemulihan ? 'Kode pemulihan' : 'Kode autentikator'} wajib>
                        <Input
                            value={kodeDuaFaktor}
                            onChange={(e) => setKodeDuaFaktor(e.target.value)}
                            autoFocus
                            autoComplete="one-time-code"
                            inputMode={pakaiKodePemulihan ? 'text' : 'numeric'}
                            placeholder={pakaiKodePemulihan ? 'xxxxxxxxxx-xxxxxxxxxx' : '123456'}
                            required
                        />
                    </Field>

                    <Button type="submit" memuat={mengirim} className="w-full">
                        Verifikasi
                    </Button>

                    <button
                        type="button"
                        onClick={() => {
                            setPakaiKodePemulihan((nilai) => !nilai);
                            setKodeDuaFaktor('');
                        }}
                        className="w-full text-center text-xs text-river hover:underline"
                    >
                        {pakaiKodePemulihan ? 'Gunakan kode autentikator' : 'Gunakan kode pemulihan'}
                    </button>
                </form>
            ) : (
                <form onSubmit={masuk} className="space-y-3">
                    <Field label="Email" error={galatField.email} wajib>
                        <Input
                            type="email"
                            value={form.email}
                            onChange={(e) => setForm({ ...form, email: e.target.value })}
                            autoComplete="username"
                            autoFocus
                            required
                        />
                    </Field>

                    <Field label="Password" error={galatField.password} wajib>
                        <Input
                            type="password"
                            value={form.password}
                            onChange={(e) => setForm({ ...form, password: e.target.value })}
                            autoComplete="current-password"
                            required
                        />
                    </Field>

                    <Button type="submit" memuat={mengirim} className="w-full">
                        Masuk
                    </Button>

                    <div className="flex items-center justify-between text-xs">
                        <Link to="/lupa-password" className="text-river hover:underline">
                            Lupa password?
                        </Link>
                        <Link to="/" className="text-sub hover:underline">
                            Lihat dashboard publik
                        </Link>
                    </div>
                </form>
            )}
        </LayarAuth>
    );
}
