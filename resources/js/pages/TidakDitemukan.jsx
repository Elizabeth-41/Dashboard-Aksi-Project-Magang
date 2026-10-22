import { Link } from 'react-router-dom';
import { Compass } from 'lucide-react';
import { Card, EmptyState } from '../components/ui';

export default function TidakDitemukan() {
    return (
        <Card>
            <EmptyState
                ikon={Compass}
                judul="Halaman tidak ditemukan"
                keterangan="Tautan mungkin salah ketik atau halaman sudah dipindahkan."
                aksi={
                    <Link to="/" className="mt-2 text-xs font-semibold text-river hover:underline">
                        Kembali ke dashboard
                    </Link>
                }
            />
        </Card>
    );
}
