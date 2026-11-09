import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Cell, Pie, PieChart, RadialBar, RadialBarChart, ResponsiveContainer, Tooltip } from 'recharts';
import { Building2, CalendarDays, ChevronDown, ChevronLeft, ChevronRight, FileCheck2, ListChecks } from 'lucide-react';
import api, { ambilSemua } from '../lib/api';
import { DIMENSI, useDaftarTahun } from '../lib/pilihan';
import { Alert, Card, EmptyState, MultiSelectTahun, ProgressBar, Spinner } from '../components/ui';
import { useAuth } from '../contexts/AuthContext';
import PanelKegiatanUnit from '../components/PanelKegiatanUnit';

const WARNA_PIE = ['#136477', '#c99a3c', '#1e8fa3', '#2e7d5b', '#0b3b4d', '#e7c877'];

function StatCard({ label, nilai, keterangan, ikon: Ikon }) {
    return (
        <div className="min-w-[150px] flex-1 rounded-xl border border-line bg-white p-4">
            <div className="flex items-center justify-between gap-2">
                <p className="text-xs font-medium text-sub">{label}</p>
                <Ikon size={15} className="shrink-0 text-river" />
            </div>
            <p className="mt-1 text-2xl font-semibold text-ink">{nilai}</p>
            {keterangan && <p className="mt-0.5 text-[11px] text-sub">{keterangan}</p>}
        </div>
    );
}

function QuickWinSection({ quickWins }) {
    const dimensiTersedia = useMemo(() => DIMENSI.filter((dimensi) => quickWins.some((quickWin) => quickWin.dimensi === dimensi)), [quickWins]);
    const [dimensiAktif, setDimensiAktif] = useState(dimensiTersedia[0] ?? '');
    const [indeks, setIndeks] = useState(0);
    const quickWinsDimensi = useMemo(() => quickWins.filter((quickWin) => quickWin.dimensi === dimensiAktif), [quickWins, dimensiAktif]);
    const slides = useMemo(
        () =>
            quickWinsDimensi.flatMap((quickWin) =>
                quickWin.bukti.map((bukti) => ({
                    ...quickWin,
                    bukti,
                })),
            ),
        [quickWinsDimensi],
    );
    const slide = slides[indeks] ?? slides[0];
    const [gambarTerlihat, setGambarTerlihat] = useState(true);

    useEffect(() => {
        if (!dimensiTersedia.includes(dimensiAktif)) {
            setDimensiAktif(dimensiTersedia[0] ?? '');
        }
    }, [dimensiAktif, dimensiTersedia]);

    useEffect(() => {
        setIndeks(0);
    }, [dimensiAktif]);

    useEffect(() => {
        setGambarTerlihat(false);
        const frame = window.requestAnimationFrame(() => setGambarTerlihat(true));

        return () => window.cancelAnimationFrame(frame);
    }, [slide?.bukti.url_lihat]);

    useEffect(() => {
        if (slides.length <= 1) return undefined;

        const timer = window.setInterval(() => {
            setIndeks((nilai) => (nilai + 1) % slides.length);
        }, 15000);

        return () => window.clearInterval(timer);
    }, [slides.length, dimensiAktif]);

    if (!slide) return null;

    return (
        <Card title={`Program Unggulan / Quick Win Tahun ${new Date().getFullYear()}`}>
            <div className="overflow-hidden rounded-xl border border-line bg-canvas">
                <div className="flex flex-wrap items-center justify-center gap-1 border-b border-line bg-white p-2 sm:gap-2 sm:p-3">
                    {DIMENSI.map((dimensi) => {
                        const tersedia = dimensiTersedia.includes(dimensi);
                        const aktif = dimensiAktif === dimensi;

                        return (
                            <button
                                key={dimensi}
                                type="button"
                                aria-pressed={aktif}
                                disabled={!tersedia}
                                className={`rounded-lg px-2.5 py-1.5 text-[11px] font-medium transition sm:px-3 sm:text-xs ${
                                    aktif
                                        ? 'bg-river text-white shadow-sm'
                                        : tersedia
                                          ? 'text-sub hover:bg-canvas hover:text-river'
                                          : 'cursor-default text-line/60'
                                }`}
                                onClick={() => {
                                    setDimensiAktif(dimensi);
                                    setIndeks(0);
                                }}
                            >
                                {dimensi}
                            </button>
                        );
                    })}
                </div>

                <div className="relative flex min-h-72 items-center justify-center bg-white sm:min-h-[30rem]">
                    <img
                        src={slide.bukti.url_lihat}
                        alt={`Bukti ${slide.judul}`}
                        className={`max-h-[34rem] w-full object-contain transition-opacity duration-500 ease-in-out ${gambarTerlihat ? 'opacity-100' : 'opacity-0'}`}
                    />
                    {slides.length > 1 && (
                        <>
                            <button
                                type="button"
                                aria-label="Quick Win sebelumnya"
                                className="absolute left-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-black/65 p-2.5 text-white shadow-md transition hover:bg-black/80 sm:left-3 sm:p-2"
                                onClick={() => setIndeks((nilai) => (nilai - 1 + slides.length) % slides.length)}
                            >
                                <ChevronLeft size={20} />
                            </button>
                            <button
                                type="button"
                                aria-label="Quick Win berikutnya"
                                className="absolute right-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-black/65 p-2.5 text-white shadow-md transition hover:bg-black/80 sm:right-3 sm:p-2"
                                onClick={() => setIndeks((nilai) => (nilai + 1) % slides.length)}
                            >
                                <ChevronRight size={20} />
                            </button>
                        </>
                    )}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2 border-t border-line bg-white px-4 py-3">
                    <div className="min-w-0">
                        <p className="text-xs font-semibold text-river">{slide.dimensi}</p>
                        <Link to={`/rencana-aksi/${slide.rencana_aksi_uuid}`} className="mt-1 block truncate text-sm font-medium text-ink hover:text-river hover:underline">
                            {slide.judul}
                        </Link>
                        <p className="mt-1 text-[11px] text-sub">{slide.unit}</p>
                    </div>
                    {slides.length > 1 && <span className="shrink-0 text-[11px] text-sub">{indeks + 1} / {slides.length}</span>}
                </div>
            </div>
        </Card>
    );
}

export default function Dashboard() {
    const daftarTahun = useDaftarTahun();
    const auth = useAuth();
    const [params, setParams] = useSearchParams();
    const tahunQuery = params.toString();
    const tahun = useMemo(() => params.getAll('tahun').map(Number).filter(Boolean), [tahunQuery]);
    const [dinas, setDinas] = useState([]);
    const [kegiatan, setKegiatan] = useState([]);
    const [quickWins, setQuickWins] = useState([]);
    const [totalKegiatan, setTotalKegiatan] = useState(0);
    const [memuat, setMemuat] = useState(true);
    const [galat, setGalat] = useState(null);
    const [capaianUnitTerbuka, setCapaianUnitTerbuka] = useState(false);

    useEffect(() => {
        let batal = false;
        setMemuat(true);
        setGalat(null);

        const query = tahun.length ? { tahun } : {};

        Promise.all([api.get('/dinas', { params: query }), ambilSemua('/rencana-aksi', query), api.get('/quick-wins')])
            .then(([resDinas, semuaKegiatan, resQuickWins]) => {
                if (batal) return;
                setDinas(resDinas.data.data);
                setKegiatan(semuaKegiatan.data);
                setTotalKegiatan(semuaKegiatan.total);
                setQuickWins(resQuickWins.data.data);
            })
            .catch(() => !batal && setGalat('Gagal memuat data dashboard.'))
            .finally(() => !batal && setMemuat(false));

        return () => {
            batal = true;
        };
    }, [tahun, tahunQuery]);

    const ringkasan = useMemo(() => {
        const unitAktif = dinas.filter((d) => (d.progres?.total ?? 0) > 0);
        const total = unitAktif.reduce((s, d) => s + d.progres.total, 0);
        const selesai = unitAktif.reduce((s, d) => s + d.progres.selesai, 0);
        const berjalan = unitAktif.reduce((s, d) => s + d.progres.berjalan, 0);
        const bukti = unitAktif.reduce((s, d) => s + d.progres.bukti_diterima, 0);

        return {
            unitAktif,
            total,
            selesai,
            berjalan,
            bukti,
            persentase: total === 0 ? 0 : Math.round((selesai / total) * 100),
        };
    }, [dinas]);

    // /api/dinas hanya memberi agregat per unit, jadi distribusi dimensi
    // dihitung dari daftar kegiatan yang sudah diambil.
    const perDimensi = useMemo(
        () =>
            DIMENSI.map((dimensi) => ({
                name: dimensi.replace('Smart ', ''),
                value: kegiatan.filter((k) => k.dimensi === dimensi).length,
            })),
        [kegiatan],
    );

    const urutUnit = useMemo(
        () => [...ringkasan.unitAktif].sort((a, b) => b.progres.persentase - a.progres.persentase),
        [ringkasan.unitAktif],
    );

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-ink">Dashboard Rencana Aksi</h1>
                    <p className="text-sm text-sub">Capaian Smart City per unit/OPD Kota Banjarmasin</p>
                </div>

                <div className="flex w-52 items-center gap-2">
                    <CalendarDays size={14} className="shrink-0 text-sub" />
                    <MultiSelectTahun
                        tahun={tahun}
                        pilihan={daftarTahun.daftar}
                        labelSemua={daftarTahun.labelSemua}
                        onChange={(nilai) => {
                            const baru = new URLSearchParams();
                            nilai.forEach((item) => baru.append('tahun', item));
                            setParams(baru);
                        }}
                    />
                </div>
            </div>

            {galat && <Alert jenis="galat">{galat}</Alert>}

            {auth.isUnit && <PanelKegiatanUnit />}

            {memuat ? (
                <Spinner />
            ) : (
                <>
                    <div className="flex flex-wrap gap-3">
                        <StatCard label="Total Rencana Aksi" nilai={totalKegiatan} ikon={ListChecks} keterangan={tahun.length ? `Tahun ${tahun.join(', ')}` : 'Seluruh periode'} />
                        <StatCard label="Unit Terlibat" nilai={ringkasan.unitAktif.length} ikon={Building2} keterangan={`dari ${dinas.length} unit terdaftar`} />
                        {auth.sudahLogin && <StatCard label="Bukti Terverifikasi" nilai={ringkasan.bukti} ikon={FileCheck2} keterangan="berstatus diterima" />}
                        <StatCard label="Kegiatan Selesai" nilai={ringkasan.selesai} ikon={ListChecks} keterangan={`${ringkasan.berjalan} sedang berjalan`} />
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <Card title="Capaian Keseluruhan">
                            <div className="relative h-44">
                                <ResponsiveContainer width="100%" height="100%">
                                    <RadialBarChart
                                        innerRadius="70%"
                                        outerRadius="100%"
                                        data={[{ name: 'Selesai', value: ringkasan.persentase, fill: 'var(--color-gold)' }]}
                                        startAngle={90}
                                        endAngle={-270}
                                    >
                                        <RadialBar background={{ fill: 'var(--color-line)' }} dataKey="value" cornerRadius={20} isAnimationActive={false} />
                                    </RadialBarChart>
                                </ResponsiveContainer>
                                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                    <span className="text-2xl font-bold text-deep">{ringkasan.persentase}%</span>
                                    <span className="text-[11px] text-sub">
                                        {ringkasan.selesai} dari {ringkasan.total} kegiatan
                                    </span>
                                </div>
                            </div>
                        </Card>

                        <Card title="Distribusi per Dimensi">
                            <div className="flex h-44 items-center gap-3">
                                {/* ResponsiveContainer butuh induk berukuran pasti; lebar persen
                                    di dalam flex membuatnya terhitung nol. */}
                                <div className="h-full w-1/2 min-w-0">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <PieChart>
                                            <Pie data={perDimensi} dataKey="value" nameKey="name" innerRadius={35} outerRadius={65} paddingAngle={2} isAnimationActive={false}>
                                                {perDimensi.map((entri, i) => (
                                                    <Cell key={entri.name} fill={WARNA_PIE[i % WARNA_PIE.length]} />
                                                ))}
                                            </Pie>
                                            <Tooltip formatter={(value, name) => [`${value} kegiatan`, name]} />
                                        </PieChart>
                                    </ResponsiveContainer>
                                </div>
                                <ul className="flex-1 space-y-1">
                                    {perDimensi.map((entri, i) => (
                                        <li key={entri.name} className="flex items-center gap-1.5 text-xs text-sub">
                                            <span className="h-2 w-2 shrink-0 rounded-full" style={{ background: WARNA_PIE[i % WARNA_PIE.length] }} />
                                            <span className="truncate">{entri.name}</span>
                                            <span className="ml-auto font-semibold text-ink">{entri.value}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </Card>
                    </div>

                    <Card padding="p-0">
                        <button
                            type="button"
                            className="flex w-full items-center justify-between gap-3 px-4 py-4 text-left"
                            aria-expanded={capaianUnitTerbuka}
                            onClick={() => setCapaianUnitTerbuka((terbuka) => !terbuka)}
                        >
                            <span className="text-sm font-semibold text-ink">
                                Capaian per Unit / OPD{tahun.length ? ` — ${tahun.join(', ')}` : ''}
                            </span>
                            <ChevronDown
                                size={16}
                                className={`shrink-0 text-sub transition-transform ${capaianUnitTerbuka ? 'rotate-180' : ''}`}
                            />
                        </button>

                        {capaianUnitTerbuka &&
                            (urutUnit.length === 0 ? (
                                <div className="px-4 pb-4">
                                    <EmptyState judul="Belum ada kegiatan pada periode ini" keterangan="Coba pilih tahun lain pada filter di atas." />
                                </div>
                            ) : (
                                <ul className="divide-y divide-line border-t border-line px-4">
                                    {urutUnit.map((unit) => (
                                        <li key={unit.id}>
                                            <Link
                                                to={auth.sudahLogin ? `/bukti?dinas=${encodeURIComponent(unit.slug)}` : '#'}
                                                className={`flex items-center gap-3 px-1 py-2.5 transition ${auth.sudahLogin ? 'cursor-pointer hover:bg-canvas' : ''}`}
                                                onClick={(event) => !auth.sudahLogin && event.preventDefault()}
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium text-ink">{unit.nama}</p>
                                                    <p className="text-[11px] text-sub">
                                                        {unit.progres.total} kegiatan · {unit.progres.selesai} selesai · {unit.progres.berjalan} berjalan
                                                    </p>
                                                </div>
                                                <div className="hidden w-32 sm:block">
                                                    <ProgressBar nilai={unit.progres.persentase} />
                                                </div>
                                                <span className="w-10 text-right text-xs font-semibold text-sub">{unit.progres.persentase}%</span>
                                                {auth.sudahLogin && <ChevronRight size={14} className="text-sub" />}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            ))}
                    </Card>

                    {quickWins.length > 0 && <QuickWinSection quickWins={quickWins} />}
                </>
            )}
        </div>
    );
}
