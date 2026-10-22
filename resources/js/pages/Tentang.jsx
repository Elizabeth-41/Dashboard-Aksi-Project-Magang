import { Link } from 'react-router-dom';
import { Building2, FileCheck2, Search, Waves } from 'lucide-react';
import { Card } from '../components/ui';
import { DIMENSI } from '../lib/pilihan';

const DIMENSI_PENJELASAN = {
    'Smart Governance': 'Tata kelola pemerintahan yang efisien, transparan, dan terintegrasi secara digital.',
    'Smart Branding': 'Penguatan citra kota lewat pariwisata, budaya sungai, dan produk unggulan daerah.',
    'Smart Economy': 'Ekosistem ekonomi digital, UMKM, dan perdagangan yang tumbuh inklusif.',
    'Smart Living': 'Layanan kesehatan, transportasi, dan permukiman yang layak serta mudah diakses.',
    'Smart Society': 'Masyarakat yang melek digital, aman, dan terlayani dengan cepat.',
    'Smart Environment': 'Pengelolaan sungai, sampah, dan energi yang berkelanjutan.',
};

export default function Tentang() {
    return (
        <div className="space-y-4">
            <div>
                <h1 className="text-xl font-semibold text-ink">Tentang Rencana Aksi Smart City</h1>
                <p className="text-sm text-sub">Kota Banjarmasin — "Kota Seribu Sungai"</p>
            </div>

            <Card>
                <div className="flex items-start gap-3">
                    <Waves size={20} className="mt-0.5 shrink-0 text-gold" />
                    <div className="space-y-2 text-sm text-ink">
                        <p>
                            Rencana Aksi Smart City adalah kumpulan kegiatan yang dijalankan seluruh perangkat daerah Kota Banjarmasin untuk
                            mewujudkan kota cerdas pada periode <strong>2019–2026</strong>. Setiap kegiatan dimiliki satu unit pengampu
                            (<em>leading sector</em>) dan dikelompokkan ke dalam enam dimensi Smart City nasional.
                        </p>
                        <p>
                            Dashboard ini menggantikan laporan statis sebelumnya agar capaian tiap unit bisa dipantau publik secara terbuka,
                            lengkap dengan <strong>bukti dukung</strong> yang sudah diverifikasi.
                        </p>
                    </div>
                </div>
            </Card>

            <Card title="Enam Dimensi Smart City">
                <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {DIMENSI.map((dimensi) => (
                        <li key={dimensi} className="rounded-lg bg-canvas p-3">
                            <p className="text-sm font-semibold text-deep">{dimensi}</p>
                            <p className="mt-0.5 text-xs text-sub">{DIMENSI_PENJELASAN[dimensi]}</p>
                        </li>
                    ))}
                </ul>
            </Card>

            <Card title="Cara Kerja Dashboard">
                <ol className="space-y-3">
                    {[
                        {
                            ikon: Building2,
                            judul: 'Unit mengelola kegiatannya',
                            isi: 'Setiap unit/OPD memperbarui status kegiatan (Belum Mulai, Berjalan, Selesai) dan mengunggah bukti dukung disertai keterangan.',
                        },
                        {
                            ikon: FileCheck2,
                            judul: 'Admin memverifikasi bukti',
                            isi: 'Bukti baru berstatus "Menunggu". Admin Pusat meninjau lalu menerima, atau menolak dengan alasan agar unit dapat memperbaikinya.',
                        },
                        {
                            ikon: Search,
                            judul: 'Publik memantau capaian',
                            isi: 'Pengunjung dapat melihat dashboard dan menelusuri bukti yang sudah diterima, tanpa perlu masuk.',
                        },
                    ].map((langkah) => (
                        <li key={langkah.judul} className="flex items-start gap-3">
                            <span className="mt-0.5 rounded-lg bg-river-soft p-1.5 text-river">
                                <langkah.ikon size={15} />
                            </span>
                            <div>
                                <p className="text-sm font-medium text-ink">{langkah.judul}</p>
                                <p className="text-xs text-sub">{langkah.isi}</p>
                            </div>
                        </li>
                    ))}
                </ol>
            </Card>

            <Card title="Penyelenggara">
                <p className="text-sm text-ink">
                    Dikelola oleh <strong>Dinas Komunikasi, Informatika, dan Statistik (Diskominfotik) Kota Banjarmasin</strong> bersama seluruh
                    perangkat daerah pengampu kegiatan.
                </p>
                <p className="mt-2 text-xs text-sub">
                    Unit pengampu yang membutuhkan akses pengelolaan dapat menghubungi Admin Pusat di Diskominfotik untuk didaftarkan.
                </p>
                <Link to="/" className="mt-3 inline-block text-xs font-semibold text-river hover:underline">
                    Lihat dashboard capaian
                </Link>
            </Card>
        </div>
    );
}
