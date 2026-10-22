import axios from 'axios';

/**
 * Sanctum SPA: autentikasi memakai cookie sesi, jadi setiap permintaan harus
 * membawa kredensial. Axios otomatis mengirim header X-XSRF-TOKEN dari cookie
 * XSRF-TOKEN setelah /sanctum/csrf-cookie dipanggil.
 */
const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: { Accept: 'application/json' },
});

let csrfSiap = false;

/** Ambil cookie CSRF sekali sebelum permintaan tulis pertama. */
export async function siapkanCsrf() {
    if (csrfSiap) return;
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
    csrfSiap = true;
}

/** Sesi berakhir di sisi server (mis. dicabut lewat Manajemen Akses). */
export function lupakanCsrf() {
    csrfSiap = false;
}

/**
 * Pesan error yang layak ditampilkan ke pengguna, mengutamakan pesan dari
 * server (Bahasa Indonesia) sebelum jatuh ke pesan umum.
 */
export function pesanError(error, fallback = 'Terjadi kesalahan. Coba lagi.') {
    const data = error?.response?.data;

    if (data?.errors) {
        const pertama = Object.values(data.errors)[0];
        if (Array.isArray(pertama) && pertama[0]) return pertama[0];
    }

    return data?.message || fallback;
}

/** Galat validasi per-field dari Laravel, untuk ditempel di bawah input. */
export function errorValidasi(error) {
    const errors = error?.response?.data?.errors ?? {};

    return Object.fromEntries(Object.entries(errors).map(([field, pesan]) => [field, pesan[0]]));
}

export function kodeError(error) {
    return error?.response?.data?.code ?? null;
}

/**
 * Ambil seluruh halaman dari endpoint terpaginasi. Dipakai untuk agregasi di
 * sisi klien (mis. distribusi dimensi di dashboard) yang butuh data lengkap.
 */
export async function ambilSemua(url, params = {}, batasHalaman = 10) {
    const kumpulan = [];
    let halaman = 1;
    let meta = null;

    do {
        const { data } = await api.get(url, { params: { ...params, per_page: 200, page: halaman } });
        kumpulan.push(...data.data);
        meta = data.meta;
        halaman += 1;
    } while (meta && halaman <= meta.last_page && halaman <= batasHalaman);

    return { data: kumpulan, total: meta?.total ?? kumpulan.length };
}

export default api;
