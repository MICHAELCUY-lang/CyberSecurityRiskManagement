# OCTAVE Allegro Cyber Risk & Security Audit Platform

Platform manajemen risiko siber berbasis PHP menggunakan metodologi **OCTAVE Allegro**, dirancang untuk keperluan akademik dan audit keamanan profesional.

---

## Prasyarat

Pastikan sudah terinstall:

- **XAMPP** (PHP 8.x + MySQL 8.x) — [https://www.apachefriends.org](https://www.apachefriends.org)
- **PHP 8.0+** (sudah termasuk dalam XAMPP)
- **Git** (opsional)

---

## Instalasi

### 1. Clone atau Salin Project

```powershell
git clone https://github.com/username/repo.git c:\Security_Risk_Management
cd c:\Security_Risk_Management
```
Atau salin manual ke folder `c:\Security_Risk_Management\`.

### 2. Konfigurasi `.env`

Salin template ke `.env`:

```powershell
copy .env.example .env
```

Kemudian edit `.env` sesuai konfigurasi kamu:

```env
DB_HOST=localhost
DB_NAME=octave_audit
DB_USER=root
DB_PASS=                  # Kosongkan jika XAMPP tanpa password

AI_API_KEY=gsk_xxx...     # API key dari provider AI kamu
AI_PROVIDER=groq          # Pilihan: groq | openai | gemini
AI_MODEL=llama-3.3-70b-versatile
```

> **Catatan:** `.env` sudah ada di `.gitignore` — **tidak akan ikut ter-upload ke GitHub**. Hanya `.env.example` yang di-commit sebagai template.

**Provider yang didukung:**

| Provider | `AI_PROVIDER` | Contoh `AI_MODEL` | Dapatkan Key Di |
|---|---|---|---|
| Groq | `groq` | `llama-3.3-70b-versatile` | [console.groq.com](https://console.groq.com) |
| OpenAI | `openai` | `gpt-4o` | [platform.openai.com](https://platform.openai.com) |
| Google Gemini | `gemini` | `gemini-2.0-flash` | [aistudio.google.com](https://aistudio.google.com) |

### 3. Jalankan MySQL (XAMPP)

Buka **XAMPP Control Panel** → klik **Start** di baris **MySQL**.

### 4. Import Database

Buka terminal / PowerShell dan jalankan:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "SOURCE c:/Security_Risk_Management/schema.sql;"
```

Ini akan membuat database `octave_audit` beserta semua tabel dan data awal (18 vulnerabilitas OWASP + 15 item checklist).

### 5. Jalankan Server PHP

```powershell
cd c:\Security_Risk_Management
php -S localhost:8080
```

### 6. Buka di Browser

```
http://localhost:8080
```

---

## Struktur Folder

```
Security_Risk_Management/
├── .env                   ← Konfigurasi lokal (TIDAK di-commit)
├── .env.example           ← Template konfigurasi (aman di-commit)
├── .gitignore             ← Mengecualikan .env & uploads dari Git
├── README.md              ← Dokumentasi ini
├── config.php             ← Load .env, konstanta
├── db.php                 ← Koneksi PDO ke MySQL
├── schema.sql             ← Skema database + seed data
│
├── index.php              ← Redirect ke dashboard
├── dashboard.php          ← Halaman utama & KPI
├── organization.php       ← Manajemen organisasi
├── assets.php             ← Inventaris aset (CIA triad)
├── vulnerabilities.php    ← Pemetaan kerentanan & risk engine
├── risk.php               ← Risk register (sortable/filterable)
├── audit.php              ← Audit checklist & upload bukti
├── compliance.php         ← Skor kepatuhan & grafik
├── findings.php           ← Temuan otomatis
├── ai.php                 ← AI Advisor (Groq/OpenAI/Gemini)
│
├── partials/
│   ├── header.php         ← CSS design system
│   ├── sidebar.php        ← Navigasi sidebar
│   └── footer.php         ← Penutup layout
│
└── uploads/               ← Penyimpanan file bukti audit (di-ignore Git)
    └── .gitkeep           ← Menjaga folder tetap ada di repo
```

---

## Alur Penggunaan (OCTAVE Allegro)

```
Organization → Assets → Vulnerabilities → Risk Register
     → Audit → Compliance → Findings → AI Advisor
```

| Langkah | Halaman | Yang Dilakukan |
|---|---|---|
| 1 | **Organization** | Daftarkan organisasi, klik Set Active |
| 2 | **Assets** | Tambah aset, isi nilai CIA (1–3) |
| 3 | **Vulnerabilities** | Assign kerentanan OWASP ke aset, atur Likelihood & Impact |
| 4 | **Risk Register** | Lihat risk score otomatis dengan filter & sort |
| 5 | **Audit** | Isi status tiap kontrol audit, upload bukti (PDF/gambar) |
| 6 | **Compliance** | Lihat skor kepatuhan % dan breakdown per aset |
| 7 | **Findings** | Temuan otomatis dari risiko High/Critical & non-compliant |
| 8 | **AI Advisor** | Tanya rekomendasi keamanan ke AI |

---

## Risk Engine

```
Risk Score = Likelihood (1–5) × Impact (1–5)

1–4   → Low
5–9   → Medium
10–14 → High
15+   → Critical
```

## Compliance Formula

```
Compliance % = (Compliant / Total Non-N/A Controls) × 100

≥ 80%  → Compliant
50–79% → Needs Improvement
< 50%  → Non-Compliant
```

---

## Fitur Otomatis

- **Auto Risk Score** — dihitung saat kerentanan di-assign ke aset
- **Auto Checklist** — item checklist dibuat otomatis untuk kerentanan "Weak Password Policy" dan "No HTTPS"
- **Auto Findings** — temuan dibuat otomatis untuk risiko High/Critical dan hasil audit Non-Compliant
- **Auto Compliance Score** — diperbarui setiap kali status audit disimpan

---

## Reset Database (jika perlu)

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "DROP DATABASE IF EXISTS octave_audit;"
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "SOURCE c:/Security_Risk_Management/schema.sql;"
```

---

## Catatan Keamanan

- API key **tidak pernah** dikirim ke browser — semua request AI dilakukan server-side via cURL
- Semua query database menggunakan **PDO prepared statements**
- Upload file divalidasi berdasarkan ekstensi (`jpg`, `png`, `gif`, `pdf`, `txt`) dan ukuran maksimal **5 MB**
- Jangan expose file `.env` ke publik

---

## Teknologi

| Komponen | Teknologi |
|---|---|
| Backend | PHP 8.x (server-rendered) |
| Database | MySQL 8.x via PDO |
| Frontend | HTML + CSS (vanilla, no framework) |
| Charts | Chart.js 4.x (CDN) |
| AI | Groq / OpenAI / Google Gemini (cURL) |
