import { Download, ExternalLink } from 'lucide-react';

const KELAS_TOMBOL =
    'inline-flex items-center gap-1.5 rounded-lg border border-line px-2.5 py-1 text-xs font-semibold text-river transition hover:bg-canvas';

/**
 * Tombol "Lihat" (buka file di tab baru, tanpa mengunduh) dan "Unduh".
 * "Lihat" hanya muncul untuk PDF dan gambar; dokumen Office tak bisa
 * ditampilkan browser sehingga hanya bisa diunduh.
 */
export default function AksiBukti({ bukti }) {
    return (
        <>
            {bukti.url_lihat && (
                // Rute file dilayani grup web, jadi sesi login terbaca dari cookie
                // tanpa perlu header Referer.
                <a
                    href={bukti.url_lihat}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={KELAS_TOMBOL}
                    title={`Buka ${bukti.nama_file} di tab baru`}
                >
                    <ExternalLink size={12} /> Lihat
                </a>
            )}
            <a href={bukti.url_unduh} className={KELAS_TOMBOL} title={`Unduh ${bukti.nama_file}`}>
                <Download size={12} /> Unduh
            </a>
        </>
    );
}
