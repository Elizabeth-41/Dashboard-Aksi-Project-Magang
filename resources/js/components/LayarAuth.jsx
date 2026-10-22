import { Link } from 'react-router-dom';
import { Waves } from 'lucide-react';

/** Bingkai halaman auth (login, reset password, pemasangan 2FA). */
export default function LayarAuth({ judul, keterangan, lebar = 'max-w-md', children }) {
    return (
        <div className="flex min-h-screen flex-col bg-canvas">
            <div className="relative overflow-hidden bg-deep px-4 py-8">
                <svg className="pointer-events-none absolute bottom-0 left-0 w-full opacity-20" height="60" viewBox="0 0 220 60" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M0 35 Q 55 10 110 30 T 220 22 V60 H0 Z" fill="var(--color-river-light)" />
                    <path d="M0 48 Q 55 26 110 45 T 220 38 V60 H0 Z" fill="var(--color-gold)" opacity="0.5" />
                </svg>
                <Link to="/" className="relative z-10 mx-auto flex max-w-md items-center gap-2">
                    <Waves size={22} className="text-gold" />
                    <span className="text-sm font-semibold leading-tight text-white">
                        Smart City Banjarmasin
                        <span className="block text-[11px] font-normal text-white/60">Rencana Aksi 2019–2026</span>
                    </span>
                </Link>
            </div>

            <div className="flex flex-1 items-start justify-center px-4 py-8">
                <div className={`w-full ${lebar}`}>
                    <div className="rounded-xl border border-line bg-white p-5">
                        <h1 className="text-lg font-semibold text-ink">{judul}</h1>
                        {keterangan && <p className="mb-4 mt-0.5 text-xs text-sub">{keterangan}</p>}
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
