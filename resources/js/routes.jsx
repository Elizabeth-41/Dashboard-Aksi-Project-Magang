import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import AppLayout from './components/AppLayout';
import { Spinner } from './components/ui';
import { useAuth } from './contexts/AuthContext';
import AuditTrail from './pages/AuditTrail';
import Dashboard from './pages/Dashboard';
import DetailRencanaAksi from './pages/DetailRencanaAksi';
import KegiatanUnit from './pages/KegiatanUnit';
import KelolaKegiatan from './pages/KelolaKegiatan';
import Laporan from './pages/Laporan';
import Login from './pages/Login';
import LupaPassword from './pages/LupaPassword';
import ManajemenAkses from './pages/ManajemenAkses';
import PencarianBukti from './pages/PencarianBukti';
import ResetPassword from './pages/ResetPassword';
import Tentang from './pages/Tentang';
import TidakDitemukan from './pages/TidakDitemukan';
import DuaFaktor from './pages/DuaFaktor';
import VerifikasiBukti from './pages/VerifikasiBukti';

/**
 * Penjaga rute: wajib login, opsional dibatasi role. Admin Pusat yang belum
 * memasang 2FA (middleware `2fa` di API) dialihkan ke halaman pemasangan.
 */
function Terlindungi({ peran, children }) {
    const auth = useAuth();
    const lokasi = useLocation();

    if (auth.memuat) return <Spinner />;

    if (!auth.sudahLogin) {
        return <Navigate to="/login" replace state={{ dari: lokasi.pathname + lokasi.search }} />;
    }

    if (auth.wajibPasangDuaFaktor) {
        return <Navigate to="/dua-faktor" replace />;
    }

    if (peran && !peran.includes(auth.user.role)) {
        return <Navigate to="/" replace />;
    }

    return children;
}

function WajibLogin({ children }) {
    const auth = useAuth();
    const lokasi = useLocation();

    if (auth.memuat) return <Spinner />;
    if (!auth.sudahLogin) return <Navigate to="/login" replace state={{ dari: lokasi.pathname + lokasi.search }} />;

    return children;
}

/**
 * Halaman login/reset tidak relevan bagi yang sudah login. Tujuan setelah
 * login diputuskan di sini — bukan di halaman Login — karena penjaga ini
 * ikut me-render ulang begitu state user terisi dan akan mendahului
 * navigasi manual apa pun.
 */
function HanyaTamu({ children }) {
    const auth = useAuth();

    if (auth.memuat) return <Spinner />;

    if (!auth.sudahLogin) return children;

    return <Navigate to={auth.wajibPasangDuaFaktor ? '/dua-faktor' : '/'} replace />;
}

export default function RuteAplikasi() {
    return (
        <Routes>
            <Route element={<AppLayout />}>
                <Route index element={<Dashboard />} />
                <Route path="bukti" element={<WajibLogin><PencarianBukti /></WajibLogin>} />
                <Route path="rencana-aksi/:id" element={<WajibLogin><DetailRencanaAksi /></WajibLogin>} />
                <Route path="tentang" element={<Tentang />} />

                <Route
                    path="laporan"
                    element={
                        <Terlindungi>
                            <Laporan />
                        </Terlindungi>
                    }
                />
                <Route
                    path="kegiatan-unit"
                    element={
                        <Terlindungi peran={['unit']}>
                            <KegiatanUnit />
                        </Terlindungi>
                    }
                />
                <Route
                    path="verifikasi"
                    element={
                        <Terlindungi peran={['admin_pusat']}>
                            <VerifikasiBukti />
                        </Terlindungi>
                    }
                />
                <Route
                    path="admin/kegiatan"
                    element={
                        <Terlindungi peran={['admin_pusat']}>
                            <KelolaKegiatan />
                        </Terlindungi>
                    }
                />
                <Route
                    path="admin/audit"
                    element={
                        <Terlindungi peran={['admin_pusat']}>
                            <AuditTrail />
                        </Terlindungi>
                    }
                />
                <Route
                    path="admin/akses"
                    element={
                        <Terlindungi peran={['admin_pusat']}>
                            <ManajemenAkses />
                        </Terlindungi>
                    }
                />

                <Route path="*" element={<TidakDitemukan />} />
            </Route>

            <Route
                path="/login"
                element={
                    <HanyaTamu>
                        <Login />
                    </HanyaTamu>
                }
            />
            <Route
                path="/lupa-password"
                element={
                    <HanyaTamu>
                        <LupaPassword />
                    </HanyaTamu>
                }
            />
            <Route
                path="/reset-password"
                element={
                    <HanyaTamu>
                        <ResetPassword />
                    </HanyaTamu>
                }
            />
            <Route path="/dua-faktor" element={<DuaFaktor />} />
        </Routes>
    );
}
