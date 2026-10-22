import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import api, { lupakanCsrf, siapkanCsrf } from '../lib/api';

const AuthContext = createContext(null);

/**
 * Identitas user yang sedang login. Guest tetap boleh memakai aplikasi
 * (dashboard & pencarian bukti read-only), jadi `user === null` bukan error.
 */
export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [memuat, setMemuat] = useState(true);

    const muatUser = useCallback(async () => {
        try {
            const { data } = await api.get('/user');
            setUser(data.data);
            return data.data;
        } catch {
            setUser(null);
            return null;
        } finally {
            setMemuat(false);
        }
    }, []);

    useEffect(() => {
        muatUser();
    }, [muatUser]);

    /**
     * Fortify menjawab 200 + {two_factor: true} — bukan galat — ketika akun
     * perlu kode OTP; pada keadaan itu user belum login sampai tantangan
     * dua faktor dijawab.
     *
     * @returns {Promise<{perluDuaFaktor: boolean, user: object|null}>}
     */
    const login = useCallback(
        async (email, password) => {
            await siapkanCsrf();
            const { data } = await api.post('/login', { email, password });

            if (data?.two_factor) {
                return { perluDuaFaktor: true, user: null };
            }

            return { perluDuaFaktor: false, user: await muatUser() };
        },
        [muatUser],
    );

    /** Jawab tantangan OTP / kode pemulihan, lalu muat identitas user. */
    const kirimTantanganDuaFaktor = useCallback(
        async (nilai, pakaiKodePemulihan = false) => {
            await siapkanCsrf();
            await api.post('/two-factor-challenge', pakaiKodePemulihan ? { recovery_code: nilai } : { code: nilai });

            return muatUser();
        },
        [muatUser],
    );

    const logout = useCallback(async () => {
        try {
            await siapkanCsrf();
            await api.post('/logout');
        } finally {
            lupakanCsrf();
            setUser(null);
        }
    }, []);

    const nilai = useMemo(
        () => ({
            user,
            memuat,
            login,
            kirimTantanganDuaFaktor,
            logout,
            muatUser,
            sudahLogin: user !== null,
            isUnit: user?.role === 'unit',
            isAdminPusat: user?.role === 'admin_pusat',
            // Admin Pusat tanpa 2FA hanya boleh membuka halaman pemasangan 2FA.
            wajibPasangDuaFaktor: Boolean(user?.two_factor_required && !user?.two_factor_enabled),
        }),
        [user, memuat, login, kirimTantanganDuaFaktor, logout, muatUser],
    );

    return <AuthContext.Provider value={nilai}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    const konteks = useContext(AuthContext);

    if (konteks === null) {
        throw new Error('useAuth harus dipakai di dalam <AuthProvider>.');
    }

    return konteks;
}
