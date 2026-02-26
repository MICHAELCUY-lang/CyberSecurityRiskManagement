# Mini GRC & Security Audit Platform
**Methodology:** OCTAVE Allegro + OWASP Vulnerability Integration

Platform manajemen risiko siber berbasis PHP yang mengintegrasikan alur kerja **OCTAVE Allegro** (pengukuran kriteria, pemrofilan aset, dan skenario ancaman) dengan manajemen kerentanan **OWASP** untuk audit keamanan profesional. Dibangun untuk memenuhi spesifikasi Mini GRC.

---

## Prasyarat Server & Sistem

- **PHP 8.2+** (PDO, cURL, JSON extensions diaktifkan)
- **MySQL 8.x** atau MariaDB setara
- **Browser Modern** (Chrome/Edge/Firefox) untuk rendering UI dan Ekspor PDF.

---

## Panduan Instalasi & Konfigurasi

### 1. Persiapan Database (PENTING)
Agar aplikasi dapat berjalan, Anda **WAJIB** menggunakan database `security_audit` beserta skema terbarunya. **Jangan gunakan database versi lama (`octave_audit`).**

1. Buka terminal MySQL, PowerShell, atau phpMyAdmin.
2. Buat database dan import skema menggunakan file `schema_new.sql` yang telah disediakan di root direktori.

**Melalui Command Line (Contoh XAMPP Desktop):**
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "SOURCE c:/Security_Risk_Management/schema_new.sql;"
```
*(Perintah ini akan secara otomatis membuat ulang database `security_audit`, menata tabel, dan memasukkan data referensi/katalog OWASP).*

### 2. Konfigurasi Environment (`.env`)
Aplikasi ini membaca konfigurasi melalui file `.env`.

1. Salin template `.env.example` menjadi `.env`:
   ```powershell
   copy .env.example .env
   ```
2. Buka `.env` dan sesuaikan pengaturan DB serta API Key AI:
   ```env
   # Database Configuration
   DB_HOST=localhost
   DB_NAME=security_audit
   DB_USER=root
   DB_PASS=

   # AI Integration (WAJIB diisi untuk fitur AI Explainer/Advisor)
   AI_API_KEY=gsk_your_api_key_here
   AI_PROVIDER=groq
   AI_MODEL=llama-3.3-70b-versatile
   ```

### 3. Menjalankan Server Aplikasi
Jalankan development server PHP bawaan langsung di direktori project:

```powershell
cd c:\Security_Risk_Management
php -S localhost:8080
```

Selanjutnya, buka browser dan akses: `http://localhost:8080/dashboard.php`

Masuk menggunakan kredensial default:
- **Email/Username:** `admin@admin.com`
- **Password:** `admin123`

---

## Alur Kerja Aplikasi (Workflow)

Aplikasi ini menggabungkan penilaian risiko dengan audit kepatuhan praktis end-to-end:

1. **Dashboard & Auth:** Login dan lihat metrik seluruh laporan audit.
2. **New Audit:** Buat sesi audit baru, pilih Organisasi untuk kalkulasi *Exposure Level* otomatis, dan tentukan Auditee.
3. **Asset Profiles:** Daftarkan aset informasi dan tentukan nilai C-I-A (Confidentiality, Integrity, Availability) untuk mendapatkan skor kritikalitas aset otomatis.
4. **Vulnerability Assessment (OWASP):** Di menu aset, klik tombol **OWASP**. Centang kerentanan yang terdeteksi. Sistem secara otomatis akan memetakan *Impact*, menghitung *Likelihood*, dan membuat **OCTAVE Threat Scenarios**.
5. **AI Explainer:** Gunakan ikon **✨ AI** pada daftar kerentanan OWASP untuk menampilkan modal penjelasan teknis dan bisnis cerdas dari AI.
6. **Dynamic Control Checklist:** Klik 'Resume Checklist'. Sistem membangun daftar periksa kontrol spesifik secara otomatis berdasarkan kerentanan OWASP yang Anda pilih. (Status: Compliant / Partial / Non-Compliant / N/A).
7. **Risk Register & 3x3 Matrix:** Lihat hasil kalkulasi risiko dan tetapkan respon mitigasi. Tersedia *Risk Matrix HTML visualizer* format 3x3.
8. **Compliance & Findings:** Dapatkan ringkasan kepatuhan (Standard: Compliant ≥85%, Needs Improvement 60-84%, Non-Compliant <60%). Fitur *Auto Findings* langsung membuat rekomendasi teknis untuk tiap kontrol yang gagal (Partial/Non-Compliant).
9. **Final Audit Report & Export:** Tentukan *Final Audit Opinion* dari struktur laporan komprehensif. Laporan dapat diekspor langsung berformat **Native PDF**.
