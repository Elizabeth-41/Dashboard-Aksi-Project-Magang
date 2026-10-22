import { useCallback, useEffect, useState } from 'react';
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { FileSpreadsheet, FileText, RotateCcw } from 'lucide-react';
import api, { pesanError } from '../lib/api';
import { DIMENSI, useDaftarTahun, useDaftarDinas } from '../lib/pilihan';
import { Alert, Button, Card, EmptyState, Field, MultiSelectTahun, ProgressBar, Select, Spinner } from '../components/ui';

const WARNA_BATANG = ['#136477', '#c99a3c', '#1e8fa3', '#2e7d5b', '#0b3b4d', '#e7c877'];

function Ringkasan({ label, nilai, keterangan }) {
    return (
        <div className="min-w-[140px] flex-1 rounded-lg bg-canvas p-3">
            <p className="text-xs text-sub">{label}</p>
            <p className="mt-0.5 text-xl font-semibold text-ink">{nilai}</p>
            {keterangan && <p className="text-[11px] text-sub">{keterangan}</p>}
        </div>
    );
}

export default function Laporan() {
    const daftarTahun = useDaftarTahun();
    const dinasList = useDaftarDinas();
    const [filter, setFilter] = useState({ tahun: [], dinas: '', dimensi: '' });
    const [laporan, setLaporan] = useState(null);
    const [memuat, setMemuat] = useState(true);
    const [galat, setGalat] = useState(null);

    const queryAktif = Object.fromEntries(Object.entries(filter).filter(([, nilai]) => Array.isArray(nilai) ? nilai.length : nilai));

    const muat = useCallback(async () => {
        setMemuat(true);
        setGalat(null);

        try {
            const { data } = await api.get('/laporan', { params: queryAktif });
            setLaporan(data.data);
        } catch (error) {
            setGalat(pesanError(error, 'Gagal memuat laporan.'));
        } finally {
            setMemuat(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filter]);

    useEffect(() => {
        muat();
    }, [muat]);

    // Unduhan memakai navigasi biasa agar browser menangani "Save as".
    const unduh = (format) => {
        const params = new URLSearchParams();
        filter.tahun.forEach((tahun) => params.append('tahun[]', tahun));
        if (filter.dinas) params.set('dinas', filter.dinas);
        if (filter.dimensi) params.set('dimensi', filter.dimensi);
        params.set('format', format);
        window.location.href = `/api/laporan/export?${params}`;
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-ink">Laporan Capaian</h1>
                    <p className="text-sm text-sub">Rekap per dimensi, unit, dan tahun — siap diekspor.</p>
                </div>

                <div className="flex gap-2">
                    <Button varian="outline" onClick={() => unduh('xlsx')}>
                        <FileSpreadsheet size={14} /> Excel
                    </Button>
                    <Button varian="gold" onClick={() => unduh('pdf')}>
                        <FileText size={14} /> PDF
                    </Button>
                </div>
            </div>

            <Card
                title="Filter"
                aksi={
                    <Button varian="ghost" ukuran="sm" onClick={() => setFilter({ tahun: [], dinas: '', dimensi: '' })}>
                        <RotateCcw size={12} /> Reset
                    </Button>
                }
            >
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Field label="Tahun">
                        <MultiSelectTahun
                            tahun={filter.tahun}
                            pilihan={daftarTahun.daftar}
                            labelSemua={daftarTahun.labelSemua}
                            onChange={(tahun) => setFilter({ ...filter, tahun })}
                        />
                    </Field>

                    <Field label="Unit / OPD">
                        <Select value={filter.dinas} onChange={(e) => setFilter({ ...filter, dinas: e.target.value })}>
                            <option value="">Semua unit</option>
                            {dinasList.map((d) => (
                                <option key={d.id} value={d.slug}>
                                    {d.nama}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Dimensi">
                        <Select value={filter.dimensi} onChange={(e) => setFilter({ ...filter, dimensi: e.target.value })}>
                            <option value="">Semua dimensi</option>
                            {DIMENSI.map((d) => (
                                <option key={d} value={d}>
                                    {d}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>
            </Card>

            {galat && <Alert jenis="galat">{galat}</Alert>}

            {memuat ? (
                <Spinner />
            ) : !laporan ? null : (
                <>
                    <Card title="Ringkasan">
                        <div className="flex flex-wrap gap-2">
                            <Ringkasan label="Total kegiatan" nilai={laporan.ringkasan.total} keterangan={`${laporan.ringkasan.jumlah_unit} unit`} />
                            <Ringkasan label="Selesai" nilai={laporan.ringkasan.selesai} keterangan={`${laporan.ringkasan.persentase}% capaian`} />
                            <Ringkasan label="Berjalan" nilai={laporan.ringkasan.berjalan} />
                            <Ringkasan label="Belum mulai" nilai={laporan.ringkasan.belum_mulai} />
                            <Ringkasan
                                label="Bukti diterima"
                                nilai={laporan.ringkasan.bukti_diterima}
                                keterangan={`${laporan.ringkasan.bukti_menunggu} menunggu · ${laporan.ringkasan.bukti_ditolak} ditolak`}
                            />
                        </div>
                    </Card>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Card title="Capaian per Dimensi">
                            <div className="h-64">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={laporan.per_dimensi.map((d) => ({ ...d, nama: d.dimensi.replace('Smart ', '') }))} margin={{ left: -20 }}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="var(--color-line)" vertical={false} />
                                        <XAxis dataKey="nama" tick={{ fontSize: 11, fill: 'var(--color-sub)' }} interval={0} angle={-20} textAnchor="end" height={50} />
                                        <YAxis tick={{ fontSize: 11, fill: 'var(--color-sub)' }} allowDecimals={false} />
                                        <Tooltip formatter={(value, name) => [value, name === 'total' ? 'Total kegiatan' : name]} />
                                        <Bar dataKey="total" radius={[4, 4, 0, 0]} isAnimationActive={false}>
                                            {laporan.per_dimensi.map((entri, i) => (
                                                <Cell key={entri.dimensi} fill={WARNA_BATANG[i % WARNA_BATANG.length]} />
                                            ))}
                                        </Bar>
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </Card>

                        <Card title="Capaian per Tahun">
                            <div className="h-64">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={laporan.per_tahun} margin={{ left: -20 }}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="var(--color-line)" vertical={false} />
                                        <XAxis dataKey="tahun" tick={{ fontSize: 11, fill: 'var(--color-sub)' }} />
                                        <YAxis tick={{ fontSize: 11, fill: 'var(--color-sub)' }} allowDecimals={false} />
                                        <Tooltip />
                                        <Bar dataKey="selesai" stackId="a" fill="var(--color-success)" name="Selesai" isAnimationActive={false} />
                                        <Bar dataKey="berjalan" stackId="a" fill="var(--color-gold)" name="Berjalan" isAnimationActive={false} />
                                        <Bar dataKey="belum_mulai" stackId="a" fill="var(--color-river-light)" name="Belum mulai" radius={[4, 4, 0, 0]} isAnimationActive={false} />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </Card>
                    </div>

                    <Card title={`Capaian per Unit / OPD (${laporan.per_unit.length})`} padding="p-0">
                        {laporan.per_unit.length === 0 ? (
                            <EmptyState judul="Tidak ada data untuk filter ini" />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[640px] text-left text-sm">
                                    <thead className="border-b border-line bg-canvas text-xs text-sub">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">Unit / OPD</th>
                                            <th className="px-4 py-2 text-right font-medium">Total</th>
                                            <th className="px-4 py-2 text-right font-medium">Belum mulai</th>
                                            <th className="px-4 py-2 text-right font-medium">Berjalan</th>
                                            <th className="px-4 py-2 text-right font-medium">Selesai</th>
                                            <th className="px-4 py-2 font-medium">Capaian</th>
                                            <th className="px-4 py-2 text-right font-medium">Bukti</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {laporan.per_unit.map((unit) => (
                                            <tr key={unit.dinas_id}>
                                                <td className="px-4 py-2 text-ink">{unit.dinas}</td>
                                                <td className="px-4 py-2 text-right text-sub">{unit.total}</td>
                                                <td className="px-4 py-2 text-right text-sub">{unit.belum_mulai}</td>
                                                <td className="px-4 py-2 text-right text-sub">{unit.berjalan}</td>
                                                <td className="px-4 py-2 text-right font-semibold text-ink">{unit.selesai}</td>
                                                <td className="px-4 py-2">
                                                    <div className="flex items-center gap-2">
                                                        <ProgressBar nilai={unit.persentase} className="w-24" />
                                                        <span className="text-xs text-sub">{unit.persentase}%</span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-2 text-right text-sub">{unit.bukti_diterima}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                </>
            )}
        </div>
    );
}
