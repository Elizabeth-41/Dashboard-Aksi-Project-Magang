import { useEffect, useMemo, useState } from 'react';
import api from './api';

/** Nilai-nilai ini mengikuti enum PHP di app/Enums. */
export const DIMENSI = [
    'Smart Governance',
    'Smart Branding',
    'Smart Economy',
    'Smart Living',
    'Smart Society',
    'Smart Environment',
];

export const JENIS_KELUARAN = [
    ['aplikasi', 'Aplikasi'],
    ['literasi', 'Literasi'],
    ['infrastruktur', 'Infrastruktur'],
    ['sdm', 'SDM'],
    ['kebijakan', 'Kebijakan'],
];

export const KATEGORI = [
    ['tik', 'TIK'],
    ['non_tik', 'Non-TIK'],
];

export const STATUS_KEGIATAN = [
    ['belum_mulai', 'Belum Mulai'],
    ['berjalan', 'Berjalan'],
    ['selesai', 'Selesai'],
];

export const STATUS_VERIFIKASI = [
    ['menunggu', 'Menunggu'],
    ['diterima', 'Diterima'],
    ['ditolak', 'Ditolak'],
];

export const TIPE_FILE = [
    ['pdf', 'PDF'],
    ['gambar', 'Gambar'],
    ['dokumen', 'Dokumen Office'],
];

/** Dipakai selama rentang dari server belum termuat. */
export const TAHUN_AWAL = 2019;
export const TAHUN_AKHIR = 2026;

/** Periode rencana aksi mengikuti RPJMD: 5 tahun, tahun mulai ikut dihitung. */
export const PANJANG_PERIODE = 5;

/** Daftar tahun menurun (terbaru dulu) untuk pilihan filter. */
export function rentangTahun(awal, akhir) {
    return Array.from({ length: Math.max(0, akhir - awal + 1) }, (_, i) => akhir - i);
}

/**
 * Rentang tahun yang benar-benar dipakai kegiatan (bukan batas tetap), supaya
 * kegiatan periode baru seperti 2027–2031 ikut muncul di filter tahun.
 */
export function useDaftarTahun() {
    const [rentang, setRentang] = useState({ awal: TAHUN_AWAL, akhir: TAHUN_AKHIR });

    useEffect(() => {
        let batal = false;

        api.get('/rentang-tahun')
            .then(({ data }) => {
                if (!batal) setRentang(data.data);
            })
            .catch(() => {});

        return () => {
            batal = true;
        };
    }, []);

    return useMemo(
        () => ({
            ...rentang,
            daftar: rentangTahun(rentang.awal, rentang.akhir),
            labelSemua: `Semua tahun (${rentang.awal}–${rentang.akhir})`,
        }),
        [rentang],
    );
}

export const ROLE = [
    ['unit', 'Unit / OPD'],
    ['admin_pusat', 'Admin Pusat'],
];

/** Daftar unit dipakai di hampir semua filter; dimuat sekali per halaman. */
export function useDaftarDinas() {
    const [dinas, setDinas] = useState([]);

    useEffect(() => {
        let batal = false;

        api.get('/dinas')
            .then(({ data }) => {
                if (!batal) setDinas(data.data);
            })
            .catch(() => {});

        return () => {
            batal = true;
        };
    }, []);

    return dinas;
}
