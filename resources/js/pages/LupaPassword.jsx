import { useState } from 'react';
import { Link } from 'react-router-dom';
import api, { errorValidasi, pesanError, siapkanCsrf } from '../lib/api';
import { Alert, Button, Field, Input } from '../components/ui';
import LayarAuth from '../components/LayarAuth';

export default function LupaPassword() {
    const [email, setEmail] = useState('');
    const [sukses, setSukses] = useState(null);
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
            const { data } = await api.post('/forgot-password', { email });
            setSukses(data.message ?? 'Tautan reset password sudah dikirim ke email Anda.');
        } catch (error) {
            setGalat(pesanError(error, 'Gagal mengirim tautan reset.'));
            setGalatField(errorValidasi(error));
        } finally {
            setMengirim(false);
        }
    };

    return (
        <LayarAuth judul="Lupa Password" keterangan="Kami kirimkan tautan untuk membuat password baru.">
            {sukses && (
                <Alert jenis="sukses" className="mb-3">
                    {sukses}
                </Alert>
            )}
            {galat && (
                <Alert jenis="galat" className="mb-3">
                    {galat}
                </Alert>
            )}

            <form onSubmit={kirim} className="space-y-3">
                <Field label="Email" error={galatField.email} wajib>
                    <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="username" autoFocus required />
                </Field>

                <Button type="submit" memuat={mengirim} className="w-full">
                    Kirim tautan reset
                </Button>

                <Link to="/login" className="block text-center text-xs text-river hover:underline">
                    Kembali ke halaman masuk
                </Link>
            </form>
        </LayarAuth>
    );
}
