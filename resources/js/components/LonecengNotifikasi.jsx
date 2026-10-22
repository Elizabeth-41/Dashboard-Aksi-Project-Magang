import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { Bell, Check } from 'lucide-react';
import api from '../lib/api';
import { formatTanggal } from './ui';

/** Jalur internal dari URL absolut yang disimpan di notifikasi. */
function jalurDari(url) {
    if (!url) return null;
    try {
        return new URL(url).pathname;
    } catch {
        return url.startsWith('/') ? url : null;
    }
}

export default function LonecengNotifikasi() {
    const [terbuka, setTerbuka] = useState(false);
    const [daftar, setDaftar] = useState([]);
    const [belumDibaca, setBelumDibaca] = useState(0);
    const wadah = useRef(null);

    const muat = useCallback(async () => {
        try {
            const { data } = await api.get('/notifikasi', { params: { per_page: 8 } });
            setDaftar(data.data);
            setBelumDibaca(data.meta?.belum_dibaca ?? 0);
        } catch {
            // Notifikasi bukan fitur kritis; diamkan agar tidak mengganggu halaman.
        }
    }, []);

    useEffect(() => {
        muat();
    }, [muat]);

    useEffect(() => {
        if (!terbuka) return undefined;

        const tutupDiLuar = (event) => {
            if (wadah.current && !wadah.current.contains(event.target)) setTerbuka(false);
        };

        document.addEventListener('mousedown', tutupDiLuar);

        return () => document.removeEventListener('mousedown', tutupDiLuar);
    }, [terbuka]);

    const tandaiSatu = async (id) => {
        await api.patch(`/notifikasi/${id}`);
        muat();
    };

    const tandaiSemua = async () => {
        await api.post('/notifikasi/baca-semua');
        muat();
    };

    return (
        <div className="relative" ref={wadah}>
            <button
                type="button"
                onClick={() => {
                    setTerbuka((nilai) => !nilai);
                    if (!terbuka) muat();
                }}
                className="relative rounded-lg border border-line p-1.5 text-sub transition hover:bg-canvas"
                aria-label={`Notifikasi${belumDibaca > 0 ? `, ${belumDibaca} belum dibaca` : ''}`}
            >
                <Bell size={16} />
                {belumDibaca > 0 && (
                    <span className="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold text-white">
                        {belumDibaca > 9 ? '9+' : belumDibaca}
                    </span>
                )}
            </button>

            {terbuka && (
                <div className="absolute right-0 top-10 z-40 w-80 overflow-hidden rounded-xl border border-line bg-white shadow-lg">
                    <div className="flex items-center justify-between border-b border-line px-3 py-2">
                        <p className="text-xs font-semibold text-ink">Notifikasi</p>
                        {belumDibaca > 0 && (
                            <button type="button" onClick={tandaiSemua} className="inline-flex items-center gap-1 text-[11px] font-semibold text-river hover:underline">
                                <Check size={12} /> Tandai semua dibaca
                            </button>
                        )}
                    </div>

                    <div className="max-h-80 overflow-y-auto">
                        {daftar.length === 0 ? (
                            <p className="px-3 py-6 text-center text-xs text-sub">Belum ada notifikasi.</p>
                        ) : (
                            daftar.map((notif) => {
                                const jalur = jalurDari(notif.url);
                                const isi = (
                                    <>
                                        <p className="text-xs font-semibold text-ink">{notif.judul}</p>
                                        <p className="mt-0.5 line-clamp-3 text-[11px] text-sub">{notif.pesan}</p>
                                        <p className="mt-1 text-[10px] text-sub">{formatTanggal(notif.created_at, true)}</p>
                                    </>
                                );

                                return (
                                    <div
                                        key={notif.id}
                                        className={`border-b border-line px-3 py-2 last:border-b-0 ${notif.dibaca ? '' : 'bg-river-soft/40'}`}
                                    >
                                        {jalur ? (
                                            <Link
                                                to={jalur}
                                                onClick={() => {
                                                    if (!notif.dibaca) tandaiSatu(notif.id);
                                                    setTerbuka(false);
                                                }}
                                                className="block"
                                            >
                                                {isi}
                                            </Link>
                                        ) : (
                                            <button type="button" onClick={() => tandaiSatu(notif.id)} className="block w-full text-left">
                                                {isi}
                                            </button>
                                        )}
                                    </div>
                                );
                            })
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
