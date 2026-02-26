# Mini GRC & Security Audit Platform
**Methodology:** OCTAVE Allegro + OWASP Vulnerability Integration

Platform manajemen risiko siber berbasis PHP yang mengintegrasikan alur kerja **OCTAVE Allegro** (pengukuran kriteria, pemrofilan aset, dan skenario ancaman) dengan manajemen kerentanan **OWASP** untuk audit keamanan profesional. Dibangun untuk memenuhi spesifikasi Mini GRC.

---

## Prasyarat Server & Sistem

- **PHP 8.2+** (PDO, cURL, JSON extensions diaktifkan)
- **MySQL 8.x** atau MariaDB setara
- **Browser Modern** (Chrome/Edge/Firefox) untuk rendering UI dan Ekspor PDF.

---

## Panduan Instalasi & Konfigurasi (Untuk Pemula)

Jika Anda belum pernah menggunakan database atau web server sebelumnya, ikuti langkah-langkah ini dari awal secara perlahan:

### 1. Apa yang Harus Didownload?
Anda membutuhkan aplikasi bernama **XAMPP** yang di dalamnya sudah terdapat PHP dan sistem database (MySQL/MariaDB).
1. Download XAMPP dari situs resminya: [apachefriends.org](https://www.apachefriends.org/download.html)
2. Pilih versi XAMPP untuk Windows dengan **PHP 8.2** ke atas.
3. Instal XAMPP di komputer Anda (biasanya terinstal di `C:\xampp`). Biarkan pengaturannya secara default dan tinggal klik "Next" hingga selesai.

### 2. Menjalankan Server Database & Web Server
1. Buka aplikasi **XAMPP Control Panel** dari menu Start komputer Anda.
2. Klik tombol **Start** pada baris **Apache** (untuk web server) dan **MySQL** (untuk database server).
3. Pastikan indikator layar (kata "Apache" dan "MySQL") keduanya sudah disorot berwarna **hijau**.

### 3. Import Database (SANGAT PENTING!)
Aplikasi ini memiliki 3 file database (`.sql`) yang **WAJIB** di-import secara berurutan. Jangan sampai ada file yang terlewat!

1. Buka aplikasi browser Anda (Google Chrome/Edge/Firefox) dan ketikkan alamat: `http://localhost/phpmyadmin`
2. Di panel sebelah kiri atas, klik tombol **New** (Baru/Buat).
3. Pada kolom *Database name* (Nama basis data), ketik: `security_audit`
4. Biarkan pengaturan Collation yang ada di sebelahnya secara default, lalu klik tombol **Create** (Buat).
5. Pada panel navigasi di sebelah kiri, klik database `security_audit` yang baru saja Anda buat. **(Anda wajib mengkliknya sebelum melakukan Import selanjutnya).**
6. Klik tab menu **Import** (Ekspor/Impor) di bagian atas tampilan.
7. Pada bagian *File to import*, klik tombol **Choose File** (Pilih File) dan cari file yang berada dalam folder kode aplikasi ini di komputer Anda. Anda **harus mengimpor 3 file secara berurutan, satu demi satu**:
   - **Langkah 1:** Pilih file `schema_new.sql` lalu geser ke paling bawah halaman phpMyAdmin dan klik tombol **Import** (atau tombol **'Go'**). *Tunggu sampai muncul notifikasi centang hijau "Import has been successfully finished".*
   - **Langkah 2:** Kembali klik tab **Import**, pilih file `schema_update.sql` lalu klik **Import/Go**. *Tunggu sampai sukses.*
   - **Langkah 3:** Terakhir, klik tab **Import** sekali lagi, pilih file `octave_schema.sql` lalu klik **Import/Go**. *Tunggu sampai sukses.*

*(Selamat! Semua tabel database aplikasi kini sudah siap).*

### 4. Konfigurasi File Pengaturan (`.env`)
Aplikasi ini membaca konfigurasi (seperti nama database) melalui file bernama `.env`.

1. Di dalam folder utama aplikasi (`Security_Risk_Management`), cari file bernama `.env.example`.
2. **Copy** (Salin) dan **Paste** file tersebut di tempat yang sama, lalu ubah/rename namanya (hapus kata ".example") sehingga **hanya menjadi `.env`** saja.
3. Buka file `.env` yang baru dibuat dengan menggunakan Notepad, WordPad, atau Visual Studio Code. Pastikan pengaturannya seperti ini di bagian atas:
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
*(Catatan: Biarkan nilainya seperti di atas. Nilai `DB_PASS=` memang dibiarkan kosong karena bawaan instalasi XAMPP adalah tanpa password untuk `root`).*

### 5. Menjalankan Aplikasi
Cara termudah menjalankan aplikasi ini (karena Anda sudah memiliki XAMPP) adalah:

1. Copy/pindahkan seluruh folder aplikasi `Security_Risk_Management` ke dalam direktori instalasi XAMPP, tepatnya salin ke folder `C:\xampp\htdocs\`.
2. Agar lebih mudah, Anda bisa me-rename foldernya menjadi `security_audit` saat dipindahkan ke `htdocs` (jadi letaknya di `C:\xampp\htdocs\security_audit`).
3. Buka browser dan ketik alamat web: `http://localhost/Security_Risk_Management/dashboard.php` (sesuaikan dengan nama folder, jika Anda me-rename foldernya menjadi `security_audit`, maka akses: `http://localhost/security_audit/dashboard.php`).

Atau, jika Anda sudah familiar dengan PowerShell/Command Prompt:
```powershell
cd c:\Security_Risk_Management
php -S localhost:8080
```
Lalu buka browser Anda ke: `http://localhost:8080/dashboard.php`

---

### Sukses! Mari Login

Masuk ke dalam aplikasi menggunakan akun default administrator pertama ini:
- **Email / Username:** `admin@admin.com`
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
