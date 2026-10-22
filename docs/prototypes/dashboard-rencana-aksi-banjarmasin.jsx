import React, { useState, useRef } from "react";
import {
  PieChart, Pie, Cell, RadialBarChart, RadialBar, ResponsiveContainer,
} from "recharts";
import {
  Home, ClipboardList, FolderOpen, Users, BarChart3, ShieldCheck,
  Search, Upload, FileText, ChevronRight, CalendarDays, Waves,
  Image as ImageIcon,
} from "lucide-react";

// ---- Design tokens (Kota Seribu Sungai: river blues + sasirangan gold) ----
const c = {
  deep: "#0B3B4D",      // deep river blue - sidebar / headings
  river: "#136477",     // mid teal-blue - primary accent
  riverLight: "#1E8FA3",
  gold: "#C99A3C",      // sasirangan gold - highlight / progress
  goldSoft: "#E7C877",
  bg: "#F4F6F7",
  card: "#FFFFFF",
  ink: "#1B2A2F",
  sub: "#5B6B70",
  line: "#E3E8E9",
  success: "#2E7D5B",
};

// Bukti sekarang menempel pada rencana aksi (kegiatan) masing-masing, bukan flat per dinas
const DINAS_SEED = [
  {
    id: "diskominfotik", name: "Diskominfotik", progress: 82, dimensi: "Governance",
    rencana: [
      { id: "r1", title: "Integrasi Data Lintas OPD", status: "Selesai",
        bukti: [
          { name: "Laporan-Integrasi-Data.pdf", date: "12 Agu 2026", by: "Diskominfotik" },
          { name: "Dokumentasi-Rapat-Koordinasi.jpg", date: "10 Agu 2026", by: "Diskominfotik" },
        ] },
      { id: "r2", title: "Penyediaan Command Center", status: "Berjalan",
        bukti: [{ name: "Foto-Ruang-Command-Center.png", date: "02 Agu 2026", by: "Diskominfotik" }] },
    ],
  },
  {
    id: "bappeda", name: "Bappedalitbang", progress: 71, dimensi: "Governance",
    rencana: [
      { id: "r1", title: "Penyusunan Masterplan Smart City", status: "Berjalan",
        bukti: [{ name: "Draft-Masterplan-2026.docx", date: "15 Agu 2026", by: "Bappedalitbang" }] },
      { id: "r2", title: "Evaluasi Indikator Kinerja", status: "Belum Mulai", bukti: [] },
    ],
  },
  {
    id: "dpupr", name: "DPUPR", progress: 64, dimensi: "Living",
    rencana: [
      { id: "r1", title: "Smart Lighting Jalan Umum", status: "Berjalan",
        bukti: [{ name: "Foto-Instalasi-Lampu-PJU.jpg", date: "08 Agu 2026", by: "DPUPR" }] },
      { id: "r2", title: "Monitoring Infrastruktur Jembatan", status: "Belum Mulai", bukti: [] },
    ],
  },
  {
    id: "dinkes", name: "Dinas Kesehatan", progress: 58, dimensi: "Living",
    rencana: [
      { id: "r1", title: "Aplikasi Layanan Kesehatan Digital", status: "Berjalan",
        bukti: [{ name: "Screenshot-Aplikasi-Sehat.png", date: "01 Agu 2026", by: "Dinas Kesehatan" }] },
      { id: "r2", title: "Rekam Medis Elektronik Puskesmas", status: "Belum Mulai", bukti: [] },
    ],
  },
  {
    id: "dlh", name: "Dinas Lingkungan Hidup", progress: 76, dimensi: "Environment",
    rencana: [
      { id: "r1", title: "Sensor Kualitas Udara Real-time", status: "Selesai",
        bukti: [
          { name: "Laporan-Uji-Sensor-Udara.pdf", date: "14 Agu 2026", by: "Dinas Lingkungan Hidup" },
          { name: "Data-Sensor-Agustus.xlsx", date: "13 Agu 2026", by: "Dinas Lingkungan Hidup" },
        ] },
      { id: "r2", title: "Bank Sampah Digital", status: "Berjalan",
        bukti: [{ name: "Foto-Bank-Sampah-RT05.jpg", date: "09 Agu 2026", by: "Dinas Lingkungan Hidup" }] },
    ],
  },
  {
    id: "dishub", name: "Dinas Perhubungan", progress: 45, dimensi: "Living",
    rencana: [
      { id: "r1", title: "Smart Traffic Light Simpang Utama", status: "Belum Mulai", bukti: [] },
      { id: "r2", title: "CCTV Pemantau Lalu Lintas", status: "Berjalan",
        bukti: [{ name: "Denah-Titik-CCTV.pdf", date: "06 Agu 2026", by: "Dinas Perhubungan" }] },
    ],
  },
  {
    id: "dinsos", name: "Dinas Sosial", progress: 69, dimensi: "Society",
    rencana: [
      { id: "r1", title: "Basis Data Terpadu Kesejahteraan", status: "Berjalan",
        bukti: [{ name: "Rekap-DTKS-Q2.xlsx", date: "11 Agu 2026", by: "Dinas Sosial" }] },
      { id: "r2", title: "Sosialisasi Bantuan Sosial Digital", status: "Selesai",
        bukti: [{ name: "Dokumentasi-Sosialisasi.jpg", date: "03 Agu 2026", by: "Dinas Sosial" }] },
    ],
  },
];

const DIMENSI_COUNT = ["Governance", "Branding", "Economy", "Environment", "Living", "Society"].map(
  (label, i) => ({
    name: label,
    value: DINAS_SEED.filter((d) => d.dimensi === label).length || (i === 1 || i === 2 ? 1 : 0),
  })
);
const PIE_COLORS = [c.river, c.gold, c.riverLight, c.success, c.deep, c.goldSoft];

const NAV = [
  { icon: Home, label: "Overview", active: true },
  { icon: ClipboardList, label: "Rencana Aksi" },
  { icon: FolderOpen, label: "Dokumen" },
  { icon: Users, label: "Dinas / OPD" },
  { icon: BarChart3, label: "Laporan" },
  { icon: ShieldCheck, label: "Admin" },
];

function StatCard({ label, value, delta, positive }) {
  return (
    <div className="rounded-xl p-4 flex-1 min-w-[150px]" style={{ background: c.card, border: `1px solid ${c.line}` }}>
      <p className="text-xs font-medium" style={{ color: c.sub }}>{label}</p>
      <div className="flex items-baseline gap-2 mt-1">
        <span className="text-2xl font-semibold" style={{ color: c.ink }}>{value}</span>
        <span
          className="text-xs font-semibold px-1.5 py-0.5 rounded"
          style={{
            color: positive ? c.success : "#B3401C",
            background: positive ? "#E7F3EC" : "#FBEAE3",
          }}
        >
          {positive ? "+" : ""}{delta}
        </span>
      </div>
    </div>
  );
}

const IMG_EXT = ["jpg", "jpeg", "png", "gif", "webp"];
const extOf = (name) => (name.split(".").pop() || "").toLowerCase();

// Kartu preview mengambang: gambar asli (file yang benar-benar diunggah lewat
// input) atau mockup bertipe berdasarkan ekstensi untuk data contoh.
function FilePreviewThumb({ file }) {
  const ext = extOf(file.name);
  if (file.previewUrl) {
    return <img src={file.previewUrl} alt={file.name} className="w-52 h-36 object-cover rounded-lg" />;
  }
  if (IMG_EXT.includes(ext)) {
    return (
      <div
        className="w-52 h-36 rounded-lg flex flex-col items-center justify-center gap-2"
        style={{ background: `linear-gradient(135deg, ${c.riverLight}26, ${c.gold}26)` }}
      >
        <ImageIcon size={26} style={{ color: c.river }} />
        <span className="text-xs" style={{ color: c.sub }}>Pratinjau gambar</span>
      </div>
    );
  }
  return (
    <div className="w-52 h-36 rounded-lg flex flex-col items-center justify-center gap-2" style={{ background: c.bg, border: `1px dashed ${c.line}` }}>
      <FileText size={26} style={{ color: c.river }} />
      <span className="text-[10px] font-bold px-2 py-0.5 rounded" style={{ background: c.deep, color: "#fff" }}>
        {ext.toUpperCase() || "FILE"}
      </span>
    </div>
  );
}

function BuktiRow({ file, onHover, onLeave }) {
  return (
    <div
      onMouseMove={(e) => onHover(e, file)}
      onMouseLeave={onLeave}
      className="flex items-center justify-between py-2 border-b last:border-b-0 cursor-default"
      style={{ borderColor: c.line }}
    >
      <div className="flex items-center gap-2 min-w-0">
        <FileText size={16} style={{ color: c.river, flexShrink: 0 }} />
        <div className="min-w-0">
          <p className="text-sm font-medium truncate" style={{ color: c.ink }}>{file.name}</p>
          <p className="text-xs" style={{ color: c.sub }}>{file.date} &middot; diunggah oleh {file.by}</p>
        </div>
      </div>
    </div>
  );
}

function UploadButton({ onUpload, label = "Upload Bukti" }) {
  const inputRef = useRef(null);
  return (
    <>
      <button
        onClick={() => inputRef.current?.click()}
        className="flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-lg transition-opacity hover:opacity-90 flex-shrink-0"
        style={{ background: c.river, color: "#fff" }}
      >
        <Upload size={12} /> {label}
      </button>
      <input
        ref={inputRef}
        type="file"
        className="hidden"
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) onUpload(f);
          e.target.value = "";
        }}
      />
    </>
  );
}

export default function DashboardRencanaAksi() {
  const [role, setRole] = useState("admin"); // "admin" | "dinas"
  const [dinasData, setDinasData] = useState(DINAS_SEED);
  const [selectedId, setSelectedId] = useState(null);
  const [hover, setHover] = useState(null); // { x, y, file }
  const myDinasId = "dlh"; // simulasi user dinas yang sedang login

  const selected = dinasData.find((d) => d.id === selectedId);
  const myDinas = dinasData.find((d) => d.id === myDinasId);
  const overallTarget = Math.round(dinasData.reduce((s, d) => s + d.progress, 0) / dinasData.length);
  const totalBukti = dinasData.reduce((s, d) => s + d.rencana.reduce((n, r) => n + r.bukti.length, 0), 0);

  const showPreview = (e, file) => setHover({ x: e.clientX, y: e.clientY, file });
  const hidePreview = () => setHover(null);

  // Bukti selalu ditambahkan ke rencana aksi yang spesifik, bukan ke dinas secara umum
  const addBukti = (dinasId, rencanaId, file) => {
    const isImg = IMG_EXT.includes(extOf(file.name));
    const entry = {
      name: file.name,
      date: "Hari ini",
      by: role === "admin" ? "Admin (untuk " + dinasData.find((d) => d.id === dinasId).name + ")" : dinasData.find((d) => d.id === dinasId).name,
      previewUrl: isImg ? URL.createObjectURL(file) : null,
    };
    setDinasData((prev) =>
      prev.map((d) =>
        d.id !== dinasId
          ? d
          : {
              ...d,
              rencana: d.rencana.map((r) => (r.id === rencanaId ? { ...r, bukti: [entry, ...r.bukti] } : r)),
            }
      )
    );
  };

  return (
    <div className="flex w-full min-h-[720px]" style={{ background: c.bg, fontFamily: "'Inter', ui-sans-serif, system-ui" }}>
      {/* Sidebar */}
      <aside className="w-56 flex-shrink-0 flex flex-col relative overflow-hidden" style={{ background: c.deep }}>
        <svg className="absolute bottom-0 left-0 w-full opacity-20" height="90" viewBox="0 0 220 90" preserveAspectRatio="none">
          <path d="M0 60 Q 55 30 110 55 T 220 45 V90 H0 Z" fill={c.riverLight} />
          <path d="M0 75 Q 55 50 110 72 T 220 65 V90 H0 Z" fill={c.gold} opacity="0.5" />
        </svg>
        <div className="p-5 relative z-10">
          <div className="flex items-center gap-2">
            <Waves size={20} color={c.gold} />
            <span className="text-white font-semibold text-sm leading-tight">Smart City<br />Banjarmasin</span>
          </div>
        </div>
        <nav className="flex-1 px-3 relative z-10">
          {NAV.map((item) => (
            <div
              key={item.label}
              className="flex items-center gap-2.5 px-3 py-2.5 rounded-lg mb-1 cursor-pointer text-sm"
              style={{
                background: item.active ? "rgba(255,255,255,0.12)" : "transparent",
                color: item.active ? "#fff" : "rgba(255,255,255,0.65)",
              }}
            >
              <item.icon size={16} />
              {item.label}
            </div>
          ))}
        </nav>
        <div className="p-4 relative z-10">
          <p className="text-[10px] uppercase tracking-wide" style={{ color: "rgba(255,255,255,0.4)" }}>Rencana Aksi 2019&ndash;2026</p>
        </div>
      </aside>

      {/* Main */}
      <main className="flex-1 p-6 overflow-auto">
        {/* Top bar */}
        <div className="flex items-center justify-between mb-5 flex-wrap gap-3">
          <div>
            <h1 className="text-xl font-semibold" style={{ color: c.ink }}>
              {role === "admin" ? "Dashboard Admin" : `Dashboard \u2014 ${myDinas.name}`}
            </h1>
            <p className="text-sm" style={{ color: c.sub }}>Monitoring capaian Rencana Aksi Smart City</p>
          </div>
          <div className="flex items-center gap-2">
            <div className="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg" style={{ background: c.card, border: `1px solid ${c.line}`, color: c.sub }}>
              <CalendarDays size={13} /> 01 Jan &ndash; 19 Agu 2026
            </div>
            {/* Demo-only role switcher */}
            <div className="flex rounded-lg overflow-hidden" style={{ border: `1px solid ${c.line}` }}>
              {["admin", "dinas"].map((r) => (
                <button
                  key={r}
                  onClick={() => { setRole(r); setSelectedId(null); }}
                  className="text-xs font-semibold px-3 py-1.5"
                  style={{
                    background: role === r ? c.river : c.card,
                    color: role === r ? "#fff" : c.sub,
                  }}
                >
                  {r === "admin" ? "Lihat sebagai Admin" : "Lihat sebagai Dinas"}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Stat cards */}
        <div className="flex gap-3 flex-wrap mb-4">
          <StatCard label="Total Rencana Aksi" value={dinasData.length * 4} delta="6%" positive />
          <StatCard label="Dinas Terlibat" value={dinasData.length} delta="0%" positive />
          <StatCard label="Bukti Diunggah" value={totalBukti} delta="12%" positive />
          <StatCard label="Rata-rata Capaian" value={`${overallTarget}%`} delta="3%" positive />
        </div>

        {/* Target + Dimensi */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div className="rounded-xl p-4" style={{ background: c.card, border: `1px solid ${c.line}` }}>
            <p className="text-sm font-semibold mb-2" style={{ color: c.ink }}>Target Capaian Keseluruhan</p>
            <div className="h-44 relative">
              <ResponsiveContainer width="100%" height="100%">
                <RadialBarChart innerRadius="70%" outerRadius="100%" data={[{ value: overallTarget, fill: c.gold }]} startAngle={90} endAngle={-270}>
                  <RadialBar background={{ fill: c.line }} dataKey="value" cornerRadius={20} />
                </RadialBarChart>
              </ResponsiveContainer>
              <div className="absolute inset-0 flex items-center justify-center">
                <span className="text-2xl font-bold" style={{ color: c.deep }}>{overallTarget}%</span>
              </div>
            </div>
          </div>
          <div className="rounded-xl p-4" style={{ background: c.card, border: `1px solid ${c.line}` }}>
            <p className="text-sm font-semibold mb-2" style={{ color: c.ink }}>Distribusi per Dimensi Smart City</p>
            <div className="h-44 flex items-center">
              <ResponsiveContainer width="55%" height="100%">
                <PieChart>
                  <Pie data={DIMENSI_COUNT} dataKey="value" innerRadius={35} outerRadius={65} paddingAngle={2}>
                    {DIMENSI_COUNT.map((_, i) => <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />)}
                  </Pie>
                </PieChart>
              </ResponsiveContainer>
              <div className="flex-1 space-y-1">
                {DIMENSI_COUNT.map((d, i) => (
                  <div key={d.name} className="flex items-center gap-1.5 text-xs" style={{ color: c.sub }}>
                    <span className="w-2 h-2 rounded-full" style={{ background: PIE_COLORS[i % PIE_COLORS.length] }} />
                    {d.name}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Dinas list + Bukti pendukung (ADMIN) */}
        {role === "admin" && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="rounded-xl p-4" style={{ background: c.card, border: `1px solid ${c.line}` }}>
              <p className="text-sm font-semibold mb-3" style={{ color: c.ink }}>Dinas / OPD</p>
              <div className="space-y-1">
                {dinasData.map((d) => (
                  <button
                    key={d.id}
                    onClick={() => setSelectedId(d.id)}
                    className="w-full flex items-center justify-between px-2 py-2 rounded-lg text-left transition-colors"
                    style={{ background: selectedId === d.id ? "#EAF2F3" : "transparent" }}
                  >
                    <div className="flex items-center gap-2 min-w-0">
                      <span className="text-sm font-medium truncate" style={{ color: c.ink }}>{d.name}</span>
                    </div>
                    <div className="flex items-center gap-2 flex-shrink-0">
                      <div className="w-20 h-1.5 rounded-full" style={{ background: c.line }}>
                        <div className="h-1.5 rounded-full" style={{ width: `${d.progress}%`, background: d.progress > 70 ? c.success : c.gold }} />
                      </div>
                      <span className="text-xs font-semibold w-8 text-right" style={{ color: c.sub }}>{d.progress}%</span>
                      <ChevronRight size={14} style={{ color: c.sub }} />
                    </div>
                  </button>
                ))}
              </div>
            </div>

            <div className="rounded-xl p-4" style={{ background: c.card, border: `1px solid ${c.line}` }}>
              <p className="text-sm font-semibold mb-3" style={{ color: c.ink }}>
                Bukti Pendukung {selected ? `\u2014 ${selected.name}` : ""}
              </p>
              {!selected ? (
                <div className="h-40 flex flex-col items-center justify-center text-center gap-2" style={{ color: c.sub }}>
                  <Search size={22} />
                  <p className="text-sm">Pilih salah satu Dinas di sebelah kiri<br />untuk melihat bukti pendukungnya per kegiatan.</p>
                </div>
              ) : (
                <div className="space-y-3 max-h-[360px] overflow-y-auto pr-1">
                  {selected.rencana.map((r) => (
                    <div key={r.id} className="rounded-lg p-3" style={{ background: c.bg }}>
                      <div className="flex items-center justify-between gap-2 mb-2">
                        <div className="min-w-0">
                          <p className="text-sm font-medium truncate" style={{ color: c.ink }}>{r.title}</p>
                          <span
                            className="text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                            style={{
                              background: r.status === "Selesai" ? "#E7F3EC" : r.status === "Berjalan" ? "#FCF2E0" : "#EAF2F3",
                              color: r.status === "Selesai" ? c.success : r.status === "Berjalan" ? c.gold : c.river,
                            }}
                          >
                            {r.status}
                          </span>
                        </div>
                        <UploadButton onUpload={(file) => addBukti(selected.id, r.id, file)} label="Upload" />
                      </div>
                      {r.bukti.length === 0 ? (
                        <p className="text-xs" style={{ color: c.sub }}>Belum ada bukti untuk kegiatan ini.</p>
                      ) : (
                        <div className="pl-0.5">
                          {r.bukti.map((f, i) => (
                            <BuktiRow key={i} file={f} onHover={showPreview} onLeave={hidePreview} />
                          ))}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}

        {/* Rencana aksi + bukti per kegiatan (DINAS) */}
        {role === "dinas" && (
          <div className="rounded-xl p-4" style={{ background: c.card, border: `1px solid ${c.line}` }}>
            <p className="text-sm font-semibold mb-3" style={{ color: c.ink }}>
              Rencana Kegiatan &amp; Bukti Pendukung &mdash; {myDinas.name}
            </p>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {myDinas.rencana.map((r) => (
                <div key={r.id} className="rounded-lg p-3" style={{ background: c.bg }}>
                  <div className="flex items-center justify-between gap-2 mb-2">
                    <div className="min-w-0">
                      <p className="text-sm font-medium truncate" style={{ color: c.ink }}>{r.title}</p>
                      <span
                        className="text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                        style={{
                          background: r.status === "Selesai" ? "#E7F3EC" : r.status === "Berjalan" ? "#FCF2E0" : "#EAF2F3",
                          color: r.status === "Selesai" ? c.success : r.status === "Berjalan" ? c.gold : c.river,
                        }}
                      >
                        {r.status}
                      </span>
                    </div>
                    <UploadButton onUpload={(file) => addBukti(myDinas.id, r.id, file)} label="Upload" />
                  </div>
                  {r.bukti.length === 0 ? (
                    <p className="text-xs" style={{ color: c.sub }}>Belum ada bukti untuk kegiatan ini.</p>
                  ) : (
                    <div>
                      {r.bukti.map((f, i) => (
                        <BuktiRow key={i} file={f} onHover={showPreview} onLeave={hidePreview} />
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Kartu preview mengambang saat kursor di atas nama bukti */}
        {hover && (
          <div
            className="fixed z-50 pointer-events-none rounded-xl p-2 shadow-xl"
            style={{
              left: Math.min(hover.x + 16, (typeof window !== "undefined" ? window.innerWidth : 1200) - 230),
              top: Math.max(hover.y - 90, 8),
              background: c.card,
              border: `1px solid ${c.line}`,
            }}
          >
            <FilePreviewThumb file={hover.file} />
            <p className="text-xs font-medium mt-1.5 truncate w-52" style={{ color: c.ink }}>{hover.file.name}</p>
          </div>
        )}
      </main>
    </div>
  );
}
