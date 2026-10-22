import { useState } from 'react';
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom';
import {
    BarChart3,
    ClipboardList,
    History,
    Home,
    Info,
    LogIn,
    LogOut,
    Menu,
    Search,
    ShieldAlert,
    ShieldCheck,
    Users,
    Waves,
    X,
} from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import LonecengNotifikasi from './LonecengNotifikasi';

/** Menu disaring sesuai role; Guest hanya melihat menu publik. */
function menuUntuk(auth) {
    return [
        { to: '/', label: 'Dashboard', icon: Home, end: true },
        { to: '/bukti', label: 'Pencarian Bukti', icon: Search, perluLogin: true },
        { to: '/kegiatan-unit', label: 'Kegiatan Unit', icon: ClipboardList, tampil: auth.isUnit },
        { to: '/laporan', label: 'Laporan', icon: BarChart3, perluLogin: true },
        { to: '/verifikasi', label: 'Verifikasi Bukti', icon: ShieldCheck, tampil: auth.isAdminPusat },
        { to: '/admin/kegiatan', label: 'Kelola Kegiatan', icon: ClipboardList, tampil: auth.isAdminPusat },
        { to: '/admin/akses', label: 'Manajemen Akses', icon: Users, tampil: auth.isAdminPusat },
        { to: '/admin/audit', label: 'Audit Trail', icon: History, tampil: auth.isAdminPusat },
        { to: '/tentang', label: 'Tentang', icon: Info },
    ].filter((item) => (item.tampil ?? true) && (!item.perluLogin || auth.sudahLogin));
}

export default function AppLayout() {
    const auth = useAuth();
    const navigate = useNavigate();
    const [menuTerbuka, setMenuTerbuka] = useState(false);
    const menu = menuUntuk(auth);

    const keluar = async () => {
        await auth.logout();
        navigate('/');
    };

    return (
        <div className="flex min-h-screen bg-canvas">
            {menuTerbuka && (
                <button
                    type="button"
                    aria-label="Tutup menu"
                    onClick={() => setMenuTerbuka(false)}
                    className="fixed inset-0 z-20 bg-deep/40"
                />
            )}

            <aside
                className={`fixed inset-y-0 left-0 z-30 flex w-60 shrink-0 flex-col overflow-hidden bg-deep transition-transform ${
                    menuTerbuka ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <svg className="pointer-events-none absolute bottom-0 left-0 w-full opacity-20" height="90" viewBox="0 0 220 90" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M0 60 Q 55 30 110 55 T 220 45 V90 H0 Z" fill="var(--color-river-light)" />
                    <path d="M0 75 Q 55 50 110 72 T 220 65 V90 H0 Z" fill="var(--color-gold)" opacity="0.5" />
                </svg>

                <div className="relative z-10 flex items-center justify-between p-5">
                    <Link to="/" className="flex items-center gap-2" onClick={() => setMenuTerbuka(false)}>
                        <Waves size={20} className="text-gold" />
                        <span className="text-sm font-semibold leading-tight text-white">
                            Smart City
                            <br />
                            Banjarmasin
                        </span>
                    </Link>
                    <button type="button" className="text-white/70" onClick={() => setMenuTerbuka(false)} aria-label="Tutup menu">
                        <X size={18} />
                    </button>
                </div>

                <nav className="relative z-10 flex-1 space-y-1 px-3">
                    {menu.map((item) => (
                        <NavLink
                            key={item.to}
                            to={item.to}
                            end={item.end}
                            onClick={() => setMenuTerbuka(false)}
                            className={({ isActive }) =>
                                `flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm transition ${
                                    isActive ? 'bg-white/15 text-white' : 'text-white/65 hover:bg-white/10 hover:text-white'
                                }`
                            }
                        >
                            <item.icon size={16} />
                            {item.label}
                        </NavLink>
                    ))}
                </nav>

                <div className="relative z-10 p-4">
                    <p className="text-[10px] uppercase tracking-wide text-white/40">Rencana Aksi 2019–2026</p>
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-white px-4 py-3 md:px-6">
                    <button type="button" className="text-sub" onClick={() => setMenuTerbuka((nilai) => !nilai)} aria-label="Buka menu">
                        <Menu size={20} />
                    </button>

                    <p className="hidden text-sm text-sub md:block">Monitoring capaian Rencana Aksi Smart City Kota Banjarmasin</p>

                    <div className="ml-auto flex items-center gap-2">
                        {auth.sudahLogin ? (
                            <>
                                <LonecengNotifikasi />
                                <div className="hidden text-right sm:block">
                                    <p className="text-xs font-semibold text-ink">{auth.user.name}</p>
                                    <p className="text-[11px] text-sub">
                                        {auth.user.role_label}
                                        {auth.user.dinas ? ` · ${auth.user.dinas.nama}` : ''}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={keluar}
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-line px-2.5 py-1.5 text-xs font-semibold text-sub transition hover:bg-canvas"
                                >
                                    <LogOut size={13} /> Keluar
                                </button>
                            </>
                        ) : (
                            <Link
                                to="/login"
                                className="inline-flex items-center gap-1.5 rounded-lg bg-river px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-deep"
                            >
                                <LogIn size={13} /> Masuk
                            </Link>
                        )}
                    </div>
                </header>

                {auth.wajibPasangDuaFaktor && (
                    <div className="flex flex-wrap items-center gap-2 border-b border-gold/40 bg-warning-soft px-4 py-2 text-xs text-ink md:px-6">
                        <ShieldAlert size={15} className="shrink-0 text-gold" />
                        <span>Akun Admin Pusat wajib mengaktifkan autentikasi dua faktor sebelum bisa memakai fitur pengelolaan.</span>
                        <Link to="/dua-faktor" className="font-semibold text-river underline">
                            Aktifkan sekarang
                        </Link>
                    </div>
                )}

                <main className="min-w-0 flex-1 p-4 md:p-6">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
