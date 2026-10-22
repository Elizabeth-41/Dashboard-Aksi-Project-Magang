import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import api, { errorValidasi, pesanError, siapkanCsrf } from '../lib/api';
import { Alert, Button, Field, Input } from '../components/ui';
import LayarAuth from '../components/LayarAuth';

/** Dibuka dari tautan email undangan/reset: /reset-password?token=…&email=… */
export default function ResetPassword() {
    const [params] = useSearchParams();
    const navigate = useNavigate();
    const token = params.get('token') ?? '';
    // Email berasal dari tautan undangan/reset dan terikat ke tokennya, jadi
    // tidak boleh diubah pengguna.
    const email = params.get('email') ?? '';
    const [form, setForm] = useState({ password: '', password_confirmation: '' });
    const [galat, setGalat] = useState(null);
    const [galatField, setGalatField] = useState({});
    const [mengirim, setMengirim] = useState(false);

    const kirim = async (e) => {
        e.preventDefault();
        setMengirim(true);
        setGalat(null);
        setGalatField({});

        try {
            await siapkanCsrf();
            await api.post('/reset-password', { ...form, email, token });
            navigate('/login', { replace: true, state: { pesan: 'Password berhasil diubah. Silakan masuk.' } });
        } catch (error) {
            setGalat(pesanError(error, 'Gagal mengubah password.'));
            setGalatField(errorValidasi(error));
        } finally {
            setMengirim(false);
        }
    };

    if (!token || !email) {
        return (
            <LayarAuth judul="Tautan tidak valid">
                <Alert jenis="galat" className="mb-3">
                    Tautan reset password tidak lengkap atau sudah kedaluwarsa.
                </Alert>
                <Link to="/lupa-password" className="text-xs text-river hover:underline">
                    Minta tautan baru
                </Link>
            </LayarAuth>
        );
    }

    return (
        <LayarAuth judul="Buat Password Baru" keterangan="Minimal 10 karakter, memuat huruf besar, huruf kecil, dan angka.">
            {galat && (
                <Alert jenis="galat" className="mb-3">
                    {galat}
                </Alert>
            )}

            <form onSubmit={kirim} className="space-y-3">
                <Field label="Email" error={galatField.email} petunjuk="Email akun mengikuti tautan yang Anda terima dan tidak dapat diubah.">
                    <Input
                        type="email"
                        value={email}
                        readOnly
                        aria-readonly="true"
                        tabIndex={-1}
                        autoComplete="username"
                        className="cursor-not-allowed bg-canvas text-sub"
                    />
                </Field>

                <Field label="Password baru" error={galatField.password} wajib>
                    <Input
                        type="password"
                        value={form.password}
                        onChange={(e) => setForm({ ...form, password: e.target.value })}
                        autoComplete="new-password"
                        minLength={10}
                        autoFocus
                        required
                    />
                </Field>

                <Field label="Ulangi password baru" wajib>
                    <Input
                        type="password"
                        value={form.password_confirmation}
                        onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
                        autoComplete="new-password"
                        minLength={10}
                        required
                    />
                </Field>

                <Button type="submit" memuat={mengirim} className="w-full">
                    Simpan password
                </Button>
            </form>
        </LayarAuth>
    );
}
