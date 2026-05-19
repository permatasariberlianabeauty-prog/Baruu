# 🚀 Panduan Upload NOXARA ke cPanel (IDCloudHost)

## Persiapan

### Yang Dibutuhkan
- Hosting cPanel (IDCloudHost atau shared hosting PHP 8.2)
- Domain aktif sudah diarahkan ke hosting
- Akses phpMyAdmin untuk import database

---

## Langkah 1 — Upload File

### Via File Manager cPanel
1. Login ke cPanel → **File Manager**
2. Masuk ke folder `public_html` (atau subfolder jika subdomain)
3. Klik **Upload** → pilih semua file NOXARA (compress ke ZIP dulu)
4. Setelah upload, klik kanan file ZIP → **Extract**
5. Pastikan struktur file berada langsung di `public_html/`:
   ```
   public_html/
   ├── index.php
   ├── .htaccess
   ├── config/
   ├── admin/
   ├── pages/
   └── ...
   ```

### Via FTP (FileZilla)
1. Host: `ftp.domainkamu.com`
2. Username & Password: dari cPanel → FTP Accounts
3. Port: 21
4. Upload semua file ke `/public_html/`

---

## Langkah 2 — Buat Database MySQL

1. cPanel → **MySQL Databases**
2. Buat database baru: `username_noxara`
3. Buat user MySQL: `username_noxara_usr` dengan password kuat
4. **Add User to Database** → pilih user + database → **All Privileges**

---

## Langkah 3 — Import Database

1. cPanel → **phpMyAdmin**
2. Klik nama database yang baru dibuat (kiri)
3. Tab **Import** → pilih file `database/dashboard.sql`
4. Klik **Go** → tunggu selesai
5. Pastikan muncul 43+ tabel

---

## Langkah 4 — Konfigurasi Aplikasi

Edit file **`config/config.php`**:

```php
define('DB_HOST',  'localhost');
define('DB_NAME',  'username_noxara');      // ← Ganti ini
define('DB_USER',  'username_noxara_usr');  // ← Ganti ini
define('DB_PASS',  'password_database');    // ← Ganti ini

define('BASE_URL', 'https://domainkamu.com'); // ← Ganti domain
define('SITE_EMAIL', 'admin@domainkamu.com'); // ← Ganti email

define('CRON_SECRET', 'random_string_unik_123'); // ← Ganti dengan string random
```

---

## Langkah 5 — Permission Folder Upload

Di File Manager, set permission folder berikut ke **755**:
```
uploads/
uploads/avatars/
uploads/deposits/
uploads/products/
uploads/banners/
uploads/ads/
uploads/chat/
logs/
logs/sessions/
```

Caranya: Klik kanan folder → **Change Permissions** → centang sesuai

---

## Langkah 6 — Verifikasi Instalasi

Buka browser: `https://domainkamu.com`

✅ Harus muncul landing page NOXARA

Akses admin panel: `https://domainkamu.com/admin/login.php`
- Username: `superadmin`
- Password: `password` *(ganti setelah login pertama!)*

---

## Langkah 7 — Setup Cron Jobs

Di cPanel → **Cron Jobs**, tambahkan:

```bash
# Credit mining profits - setiap 15 menit
*/15 * * * * php /home/username/public_html/cron/daily_profit.php --secret=CRON_SECRET

# Check packages & expiry - setiap hari jam 00:05
5 0 * * * php /home/username/public_html/cron/check_packages.php --secret=CRON_SECRET

# Reset missions & leaderboard - setiap hari jam 00:01
1 0 * * * php /home/username/public_html/cron/reset_missions.php --secret=CRON_SECRET

# Backup database - setiap hari jam 02:00
0 2 * * * php /home/username/public_html/cron/backup.php --secret=CRON_SECRET
```

> ⚠️ Ganti `username` dengan username cPanel Anda
> ⚠️ Ganti `CRON_SECRET` dengan nilai di config.php

---

## Langkah 8 — SSL/HTTPS

1. cPanel → **SSL/TLS** → **Let's Encrypt**
2. Issue certificate untuk domain Anda
3. Aktifkan **Force HTTPS Redirect**

---

## Konfigurasi Awal Admin

Setelah berhasil login admin:

### 1. Ganti Password Admin
Admin Panel → *(klik username pojok kanan)* → Ganti password

### 2. Set Data Bank Tujuan Deposit
Admin → **Pengaturan** → tambahkan rekening bank untuk menerima deposit member

### 3. Set Kontak
Admin → **Pengaturan** → isi nomor WhatsApp CS, email, Telegram

### 4. Edit Platform Info
Admin → **Pengaturan** → edit konten Tentang Kami, Syarat & Ketentuan, dll

### 5. Tambah Banner
Admin → **Banner** → upload gambar banner slider untuk landing page

---

## Troubleshooting

### ❌ Error "Database connection failed"
→ Cek kembali `DB_NAME`, `DB_USER`, `DB_PASS` di `config/config.php`

### ❌ Error 500 (Internal Server Error)
→ Aktifkan error display sementara: set `APP_DEBUG = true` di config.php
→ Cek file `logs/php_errors.log`

### ❌ Upload gambar gagal
→ Set permission folder `uploads/` ke 755
→ Cek quota disk hosting

### ❌ Halaman kosong/putih
→ PHP version belum 8.2 → cPanel → **PHP Selector** → pilih PHP 8.2

### ❌ Session tidak berfungsi
→ Folder `logs/sessions/` harus writable (755)
→ Atau hapus custom session path di `config/session.php`

---

## Keamanan Tambahan (Recommended)

1. **Ganti default admin password** segera setelah install
2. **Set CRON_SECRET** yang unik dan panjang
3. **Aktifkan 2FA** jika tersedia di hosting
4. **Backup rutin** - cek folder `logs/backups/`
5. **Monitor login logs** - Admin → Members → Login Logs

---

## Struktur Folder Setelah Upload

```
public_html/
├── .htaccess              ✅ Sudah ada
├── index.php              ✅ Landing page
├── config/                ✅ Konfigurasi (JANGAN expose ke publik)
├── includes/              ✅ Helper functions
├── auth/                  ✅ Login/register pages
├── pages/                 ✅ Halaman member
├── admin/                 ✅ Panel admin
├── api/                   ✅ AJAX endpoints
├── cron/                  ✅ Scheduled jobs
├── assets/                ✅ CSS/JS/images
├── uploads/               📁 File uploads (buat manual jika belum ada)
│   ├── avatars/
│   ├── deposits/
│   ├── products/
│   ├── banners/
│   ├── ads/
│   └── chat/
├── logs/                  📁 Log files (buat manual)
│   └── sessions/
└── database/
    └── dashboard.sql      ✅ File SQL untuk import
```

---

## Support

Jika mengalami masalah, hubungi developer atau buka issue di repositori.

**NOXARA** — *Invest Smarter, Grow Faster* 🚀
