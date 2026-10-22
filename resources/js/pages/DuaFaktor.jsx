import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { KeyRound, ShieldCheck } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import api, { pesanError, siapkanCsrf } from '../lib/api';
import { Alert, Button, Field, Input, Spinner } from '../components/ui';
import LayarAuth from '../components/LayarAuth';

/**
 * Pemasangan 2FA: wajib bagi Admin Pusat (diblokir middleware `2fa` di API
 * sampai selesai), opsional bagi role lain.
 */
export default function DuaFaktor() {
    const auth = useAuth();
    const navigate = useNavigate();
    const [tahap, setTahap] = useState('memuat'); // memuat | password | pasang | selesai
    const [password, setPassword] = useState('');
    const [kode, setKode] = useState('');
    const [qr, setQr] = useState(null);
    const [secret, setSecret] = useState(null);
    const [kodePemulihan, setKodePemulihan] = useState([]);
    const [galat, setGalat] = useState(null);
    const [mengirim, setMengirim] = useState(false);

    useEffect(() => {
        if (auth.memuat) return;

        if (!auth.sudahLogin) {
            navigate('/login', { replace: true });

            return;
        }

        setTahap(auth.user.two_factor_enabled ? 'selesai' : 'password');
    }, [auth.memuat, auth.sudahLogin, auth.user, navigate]);

    useEffect(() => {
        if (tahap !== 'selesai') return;

        api.get('/user/two-factor-recovery-codes')
            .then(({ data }) => setKodePemulihan(data))
            .catch(() => setKodePemulihan([]));
    }, [tahap]);

    /** Fortify mewajibkan konfirmasi password sebelum rute 2FA bisa dipakai. */
    const konfirmasiPassword = async (e) => {
        e.preventDefault();
        setMengirim(true);
        setGalat(null);

        try {
            await siapkanCsrf();
            await api.post('/user/confirm-password', { password });
            await api.post('/user/two-factor-authentication');

            const [resQr, resSecret] = await Promise.all([
                api.get('/user/two-factor-qr-code'),
                api.get('/user/two-factor-secret-key'),
            ]);

            setQr(resQr.data.svg);
            setSecret(resSecret.data.secretKey);
            setPassword('');
            setTahap('pasang');
        } catch (error) {
            setGalat(pesanError(error, 'Password salah.'));
        } finally {
            setMengirim(false);
        }
    };

    const konfirmasiKode = async (e) => {
        e.preventDefault();
        setMengirim(true);
        setGalat(null);

        try {
            await api.post('/user/confirmed-two-factor-authentication', { code: kode });
            await auth.muatUser();
            setKode('');
            setTahap('selesai');
        } catch (error) {
            setGalat(pesanError(error, 'Kode tidak cocok. Coba kode terbaru dari aplikasi autentikator.'));
        } finally {
            setMengirim(false);
        }
    };

    const matikan = async () => {
        setMengirim(true);
        setGalat(null);

        try {
            await api.delete('/user/two-factor-authentication');
            await auth.muatUser();
            setTahap('password');
        } catch (error) {
            setGalat(pesanError(error, 'Gagal menonaktifkan 2FA.'));
        } finally {
            setMengirim(false);
        }
    };

    if (tahap === 'memuat') {
        return (
            <LayarAuth judul="Autentikasi Dua Faktor">
                <Spinner />
            </LayarAuth>
        );
    }

    return (
        <LayarAuth
            judul="Autentikasi Dua Faktor"
            keterangan={
                auth.wajibPasangDuaFaktor
                    ? 'Wajib untuk Admin Pusat. Akun belum bisa dipakai sebelum 2FA aktif.'
                    : 'Lapisan keamanan tambahan untuk akun Anda.'
            }
        >
            {galat && (
                <Alert jenis="galat" className="mb-3">
                    {galat}
                </Alert>
            )}

            {tahap === 'password' && (
                <form onSubmit={konfirmasiPassword} className="space-y-3">
                    {auth.wajibPasangDuaFaktor && (
                        <Alert jenis="info">
                            Sebagai Admin Pusat, Anda memegang akses ke semua unit dan Manajemen Akses, jadi 2FA wajib diaktifkan lebih dulu.
                        </Alert>
                    )}

                    <Field label="Konfirmasi password Anda" wajib petunjuk="Diminta sekali untuk mengamankan proses pemasangan.">
                        <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" autoFocus required />
                    </Field>

                    <Button type="submit" memuat={mengirim} className="w-full">
                        <ShieldCheck size={14} /> Mulai pasang 2FA
                    </Button>
                </form>
            )}

            {tahap === 'pasang' && (
                <div className="space-y-3">
                    <p className="text-xs text-sub">
                        Pindai QR berikut dengan Google Authenticator / Authy, lalu masukkan kode 6 digit yang muncul.
                    </p>

                    {qr && (
                        <div className="flex justify-center rounded-lg border border-line bg-white p-3">
                            <img
                                src={`data:image/svg+xml;base64,${window.btoa(qr)}`}
                                alt="QR code untuk memasang autentikasi dua faktor"
                                className="h-48 w-48"
                            />
                        </div>
                    )}

                    {secret && (
                        <p className="break-all text-center text-[11px] text-sub">
                            Tidak bisa memindai? Masukkan kunci ini manual: <span className="font-mono font-semibold text-ink">{secret}</span>
                        </p>
                    )}

                    <form onSubmit={konfirmasiKode} className="space-y-3">
                        <Field label="Kode dari aplikasi autentikator" wajib>
                            <Input value={kode} onChange={(e) => setKode(e.target.value)} inputMode="numeric" autoComplete="one-time-code" placeholder="123456" autoFocus required />
                        </Field>

                        <Button type="submit" memuat={mengirim} className="w-full">
                            Aktifkan 2FA
                        </Button>
                    </form>
                </div>
            )}

            {tahap === 'selesai' && (
                <div className="space-y-3">
                    <Alert jenis="sukses">Autentikasi dua faktor aktif untuk akun ini.</Alert>

                    {kodePemulihan.length > 0 && (
                        <div>
                            <p className="mb-1 flex items-center gap-1.5 text-xs font-semibold text-ink">
                                <KeyRound size={13} /> Kode pemulihan
                            </p>
                            <p className="mb-2 text-[11px] text-sub">
                                Simpan di tempat aman. Setiap kode bisa dipakai sekali untuk masuk bila aplikasi autentikator hilang.
                            </p>
                            <ul className="grid grid-cols-2 gap-1 rounded-lg bg-canvas p-3 font-mono text-[11px] text-ink">
                                {kodePemulihan.map((kodeItem) => (
                                    <li key={kodeItem}>{kodeItem}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Link to="/" className="flex-1">
                            <Button className="w-full">Buka dashboard</Button>
                        </Link>
                        {!auth.user?.two_factor_required && (
                            <Button varian="outline" onClick={matikan} memuat={mengirim}>
                                Nonaktifkan
                            </Button>
                        )}
                    </div>
                </div>
            )}
        </LayarAuth>
    );
}
