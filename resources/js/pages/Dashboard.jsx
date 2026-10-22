import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Cell, Pie, PieChart, RadialBar, RadialBarChart, ResponsiveContainer, Tooltip } from 'recharts';
import { Building2, CalendarDays, ChevronRight, FileCheck2, ListChecks } from 'lucide-react';
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

export default function Dashboard() {
    const daftarTahun = useDaftarTahun();
    const auth = useAuth();
    const [params, setParams] = useSearchParams();
    const tahunQuery = params.toString();
    const tahun = useMemo(() => params.getAll('tahun').map(Number).filter(Boolean), [tahunQuery]);
    const [dinas, setDinas] = useState([]);
    const [kegiatan, setKegiatan] = useState([]);
    const [totalKegiatan, setTotalKegiatan] = useState(0);
    const [memuat, setMemuat] = useState(true);
    const [galat, setGalat] = useState(null);

    useEffect(() => {
        let batal = false;
        setMemuat(true);
        setGalat(null);

        const query = tahun.length ? { tahun } : {};

        Promise.all([api.get('/dinas', { params: query }), ambilSemua('/rencana-aksi', query)])
            .then(([resDinas, semuaKegiatan]) => {
                if (batal) return;
                setDinas(resDinas.data.data);
                setKegiatan(semuaKegiatan.data);
                setTotalKegiatan(semuaKegiatan.total);
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

                    <Card title={`Capaian per Unit / OPD${tahun.length ? ` — ${tahun.join(', ')}` : ''}`}>
                        {urutUnit.length === 0 ? (
                            <EmptyState judul="Belum ada kegiatan pada periode ini" keterangan="Coba pilih tahun lain pada filter di atas." />
                        ) : (
                            <ul className="divide-y divide-line">
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
                        )}
                    </Card>
                </>
            )}
        </div>
    );
}
