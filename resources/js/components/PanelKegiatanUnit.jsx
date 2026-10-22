import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRight, ClipboardList, Upload } from 'lucide-react';
import api from '../lib/api';
import { useAuth } from '../contexts/AuthContext';
import { Card, EmptyState, StatusKegiatanBadge } from './ui';

const JUMLAH_TAMPIL = 5;

/**
 * Pintasan di dashboard bagi akun unit: kegiatan yang jadi tanggung jawabnya,
 * didahulukan yang belum punya bukti supaya jelas apa yang perlu dikerjakan.
 */
export default function PanelKegiatanUnit() {
    const auth = useAuth();
    const [kegiatan, setKegiatan] = useState([]);
    const [total, setTotal] = useState(0);
    const [memuat, setMemuat] = useState(true);

    useEffect(() => {
        if (!auth.user?.dinas_id) return undefined;

        let batal = false;
        setMemuat(true);

        api.get('/rencana-aksi', { params: { dinas: auth.user.dinas?.slug, per_page: 100 } })
            .then(({ data }) => {
                if (batal) return;

                const urut = [...data.data].sort(
                    (a, b) => (a.jumlah_bukti ?? 0) - (b.jumlah_bukti ?? 0) || a.judul.localeCompare(b.judul),
                );

                setKegiatan(urut.slice(0, JUMLAH_TAMPIL));
                setTotal(data.meta?.total ?? urut.length);
            })
            .catch(() => {})
            .finally(() => !batal && setMemuat(false));

        return () => {
            batal = true;
        };
    }, [auth.user?.dinas_id]);

    if (!auth.user?.dinas_id || memuat) return null;

    return (
        <Card
            title={`Kegiatan ${auth.user.dinas?.nama ?? 'Unit Anda'}${total ? ` (${total})` : ''}`}
            aksi={
                <Link to="/kegiatan-unit" className="inline-flex items-center gap-1 text-xs font-semibold text-river hover:underline">
                    Lihat semua <ArrowRight size={12} />
                </Link>
            }
            padding="p-0"
        >
            {kegiatan.length === 0 ? (
                <EmptyState judul="Belum ada kegiatan terdaftar untuk unit ini" ikon={ClipboardList} />
            ) : (
                <ul className="divide-y divide-line">
                    {kegiatan.map((item) => (
                        <li key={item.id} className="flex flex-wrap items-center gap-3 px-4 py-3">
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link to={`/rencana-aksi/${item.uuid}`} className="truncate text-sm font-medium text-ink hover:text-river hover:underline">
                                        {item.judul}
                                    </Link>
                                    <StatusKegiatanBadge status={item.status} label={item.status_label} />
                                </div>
                                <p className="mt-0.5 text-[11px] text-sub">
                                    {item.tahun_label} ·{' '}
                                    {(item.jumlah_bukti ?? 0) === 0 ? (
                                        <span className="font-medium text-danger">belum ada bukti</span>
                                    ) : (
                                        `${item.jumlah_bukti} bukti diunggah`
                                    )}
                                </p>
                            </div>

                            <Link
                                to={`/rencana-aksi/${item.uuid}`}
                                className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-line px-2.5 py-1 text-xs font-semibold text-river transition hover:bg-canvas"
                            >
                                <Upload size={12} /> Unggah bukti
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}
