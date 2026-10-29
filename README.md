# 🚀 Dokumentasi Resmi & Spesifikasi API PAD-BE
### Project Agent Delivery — Backend Service
**Universitas Gadjah Mada (UGM)**  
*Laravel 12 / PHP 8.3 / Laravel Sanctum / Laravel Socialite*

---

## 📑 Daftar Isi
1. [Arsitektur & Konfigurasi Lingkungan (Environment)](#1-arsitektur--konfigurasi-lingkungan-environment)
2. [Sistem Peran & Izin Pengguna (RBAC)](#2-sistem-peran--izin-pengguna-rbac)
3. [Format Standar Request & Response](#3-format-standar-request--response)
4. [Katalog Endpoint & Panduan Frontend (FE)](#4-katalog-endpoint--panduan-frontend-fe)
   - [4.1 Modul Autentikasi & SSO UGM](#41-modul-autentikasi--sso-ugm)
   - [4.2 Modul Pengelolaan Whitelist KRS & Pengguna](#42-modul-pengelolaan-whitelist-krs--pengguna)
   - [4.3 Modul Tim Eksklusif (GEMASTIK / LIDM)](#43-modul-tim-eksklusif-gemastik--lidm)
   - [4.4 Modul Konfigurasi Sistem (App Settings)](#44-modul-konfigurasi-sistem-app-settings)
   - [4.5 Modul Master Data Roles & Skillset](#45-modul-master-data-roles--skillset)
   - [4.6 Modul Pendaftaran & Form Profiling Mahasiswa](#46-modul-pendaftaran--form-profiling-mahasiswa)
   - [4.7 Modul Pengumuman Kelompok Mahasiswa (My Team)](#47-modul-pengumuman-kelompok-mahasiswa-my-team)
   - [4.8 Endpoint Kompatibilitas / Legacy Alias](#48-endpoint-kompatibilitas--legacy-alias)
5. [Contoh Integrasi Frontend (Axios Instance & Interceptor)](#5-contoh-integrasi-frontend-axios-instance--interceptor)
6. [Panduan Testing & Debugging Backend (BE)](#6-panduan-testing--debugging-backend-be)
   - [6.1 Menjalankan Server & Environment PHP 8.3](#61-menjalankan-server--environment-php-83)
   - [6.2 Menjalankan Automated Feature Tests (PHPUnit)](#62-menjalankan-automated-feature-tests-phpunit)
   - [6.3 Web Interactive Tester & Token Generator Dev](#63-web-interactive-tester--token-generator-dev)
   - [6.4 Debugging Cepat via Laravel Tinker](#64-debugging-cepat-via-laravel-tinker)
   - [6.5 Kumpulan Perintah Pengujian Manual cURL](#65-kumpulan-perintah-pengujian-manual-curl)
   - [6.6 Troubleshooting & Solusi Kendala Umum](#66-troubleshooting--solusi-kendala-umum)

---

## 1. Arsitektur & Konfigurasi Lingkungan (Environment)

### Base URL
- **Local (Artisan Serve):** `http://localhost:8000`
- **Local (Laragon Virtual Host):** `http://pad-be.test`
- **Staging / Production:** Diselaraskan dengan konfigurasi server (`APP_URL`)

### Konvensi Routing & Versioning
Seluruh antarmuka API didaftarkan dengan dua alias:
1. **Versi Direkomendasikan (v1):** `/api/v1/...` *(Gunakan versi ini pada aplikasi Frontend)*
2. **Direct Root Alias:** `/api/...` *(Kompatibilitas langsung)*

### Header Standar
Setiap request ke backend wajib menyertakan header:
```http
Accept: application/json
Content-Type: application/json
```
Untuk endpoint yang dilindungi (*protected routes*):
```http
Authorization: Bearer <personal_access_token>
```
Untuk unggah file (Import KRS):
```http
Content-Type: multipart/form-data
```

---

## 2. Sistem Peran & Izin Pengguna (RBAC)

Sistem menggunakan Enum `App\Enums\UserRole` dan Middleware `role:...` untuk membatasi hak akses:

| Peran (Value) | Label | Hak Akses Utama | Alias yang Diterima (Case-Insensitive) |
| :--- | :--- | :--- | :--- |
| `admin` | Admin (Full) | Seluruh fitur sistem, inisiasi dosen, kelola whitelist & pengaturan | `admin`, `full_admin`, `master_admin`, `full admin` |
| `lecturer` | Dosen Pengampu | Kelola whitelist KRS, tim eksklusif, pengaturan sistem, master skills | `lecturer`, `dosen`, `dosen_pengampu` |
| `student` | Mahasiswa | Mengisi form profiling mandiri, melihat pengumuman tim & peran final | `student`, `mahasiswa` |
| `assistant` | Asisten Praktikum | Membantu operasional praktikum & evaluasi | `assistant`, `asisten`, `asprak`, `asisten_praktikum` |
| `practitioner`| Praktisi Industri | Meninjau progres & proyek industri | `practitioner`, `praktisi`, `praktisi_industri` |

> 💡 **Prinsip Khusus Master Admin:** Pengguna dengan peran `admin` memiliki otorisasi penuh (*bypass*) terhadap seluruh rute bertanda `role:lecturer` maupun `role:student`.

---

## 3. Format Standar Request & Response

### Response Berhasil (HTTP 200 / 201)
```json
{
  "status": "success",
  "message": "Pesan keberhasilan operasi.",
  "data": { ... }
}
```

### Response Validasi Gagal (HTTP 422)
```json
{
  "message": "Pilihan role 1 dan role 2 tidak boleh sama.",
  "errors": {
    "role_2_id": [
      "Pilihan role 1 dan role 2 tidak boleh sama."
    ]
  }
}
```

### Response Akses Ditolak / Forbidden (HTTP 403)
```json
{
  "status": "error",
  "message": "Akses ditolak. Anda tidak memiliki izin untuk mengakses resource ini."
}
```

### Response Belum Login / Token Invalid (HTTP 401)
```json
{
  "status": "error",
  "message": "Unauthenticated."
}
```

---

## 4. Katalog Endpoint & Panduan Frontend (FE)

### 4.1 Modul Autentikasi & SSO UGM

Sistem autentikasi menggunakan alur **Google OAuth 2.0 (SSO UGM)**.
Aturan validasi autentikasi:
1. Akun Google harus terverifikasi (`email_verified = true`).
2. Domain email Google harus berakhiran `@mail.ugm.ac.id`.
3. Email harus sudah terdaftar sebelumnya di database sistem (whitelist KRS) dengan `status = 'active'`.

---

#### 4.1.1 GET `/api/v1/auth/google/redirect`
Mendapatkan URL otorisasi Google OAuth untuk diarahkan ke user browser.

- **Auth:** Publik
- **Query Parameters:**
  | Parameter | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `redirect_uri` | string | Tidak | URL callback FE jika dinamis/berbeda dari default `.env` |

- **Response (HTTP 200):**
```json
{
  "status": "success",
  "message": "URL redirect Google OAuth berhasil dibuat.",
  "url": "https://accounts.google.com/o/oauth2/v2/auth?client_id=...&redirect_uri=...&scope=openid+profile+email"
}
```

- **Contoh Pemanggilan FE (JavaScript):**
```javascript
async function loginWithGoogle() {
  const res = await fetch('http://localhost:8000/api/v1/auth/google/redirect');
  const data = await res.json();
  if (data.status === 'success') {
    window.location.href = data.url;
  }
}
```

---

#### 4.1.2 POST `/api/v1/auth/google/callback`
Menukar authorization `code` atau `token` dari Google OAuth untuk diverifikasi dan mendapatkan personal access token Sanctum.

- **Auth:** Publik
- **Request Body (JSON):**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `code` | string | Kondisional | Authorization code dari query parameter redirect Google |
  | `token` / `access_token` | string | Kondisional | Access token dari SDK Google langsung (opsi alternatif) |
  | `redirect_uri` | string | Tidak | Wajib sama jika saat request redirect menyertakan custom redirect_uri |

  > *Catatan: Wajib menyertakan salah satu antara `code` atau `token`.*

- **Contoh Request Body:**
```json
{
  "code": "4/0AeanS0b...",
  "redirect_uri": "http://localhost:3000/auth/callback"
}
```

- **Response Sukses (HTTP 200):**
```json
{
  "status": "success",
  "message": "Autentikasi SSO UGM berhasil.",
  "token": "1|qXyZ89ABC...plainTextToken",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Budi Santoso",
    "email": "budi.santoso@mail.ugm.ac.id",
    "google_id_sub": "109876543210987654321",
    "user_role": "student",
    "role": "student",
    "role_label": "Mahasiswa",
    "avatar_link": "https://lh3.googleusercontent.com/a/...",
    "status": "active",
    "is_bypass": false,
    "student_profile": {
      "id": 1,
      "nim": "23/512345/SV/22001",
      "is_bypass": false,
      "role_1_id": null,
      "role_2_id": null,
      "team_id": null,
      "portfolio_link": null
    }
  }
}
```

- **Kemungkinan Error:**
  - `400 Bad Request`: Token/Code Google invalid atau kadaluarsa.
  - `403 Forbidden`: Email bukan domain `@mail.ugm.ac.id`, email belum diverifikasi Google, atau email tidak ada di whitelist KRS aktif.
  - `422 Unprocessable Entity`: Parameter `code` maupun `token` kosong.

---

#### 4.1.3 GET `/api/v1/auth/me`
Mengambil data profil pengguna yang sedang login berdasarkan Bearer Token.

- **Auth:** `Bearer Token` (`auth:sanctum`)
- **Response (HTTP 200):**
```json
{
  "status": "success",
  "message": "Data profil pengguna yang sedang login berhasil diambil.",
  "data": {
    "id": 1,
    "name": "Budi Santoso",
    "email": "budi.santoso@mail.ugm.ac.id",
    "user_role": "student",
    "role": "student",
    "role_label": "Mahasiswa",
    "avatar_link": "https://lh3.googleusercontent.com/...",
    "status": "active",
    "is_bypass": false,
    "student_profile": {
      "id": 1,
      "nim": "23/512345/SV/22001",
      "is_bypass": false,
      "role_1_id": 1,
      "role_2_id": 2
    }
  }
}
```

---

#### 4.1.4 POST `/api/v1/auth/logout`
Mencabut / membatalkan token personal access Sanctum saat ini.

- **Auth:** `Bearer Token` (`auth:sanctum`)
- **Response (HTTP 200):**
```json
{
  "status": "success",
  "message": "Berhasil logout, token telah dihapus."
}
```

---

### 4.2 Modul Pengelolaan Whitelist KRS & Pengguna

Modul ini dikhususkan bagi Dosen Pengampu dan Master Admin untuk mengelola hak masuk pengguna ke dalam sistem.

---

#### 4.2.1 POST `/api/v1/admin/lecturers`
Menginisiasi email dosen pengampu ke sistem dan memberikan hak akses `lecturer` atau `admin`.

- **Auth:** `Bearer Token` (`role:admin`)
- **Request Body (Single):**
```json
{
  "email": "dosen@ugm.ac.id",
  "name": "Dr. Nama Dosen, S.Kom., M.Cs.",
  "role": "lecturer"
}
```
- **Request Body (Bulk):**
```json
{
  "lecturers": [
    { "email": "dosen1@ugm.ac.id", "name": "Dosen Satu", "role": "lecturer" },
    { "email": "dosen2@ugm.ac.id", "name": "Dosen Dua", "role": "admin" }
  ]
}
```
- **Response (HTTP 201):**
```json
{
  "status": "success",
  "message": "Dosen Pengampu berhasil diinisiasi.",
  "data": { ... }
}
```

---

#### 4.2.2 GET `/api/v1/users`
Mengambil daftar pengguna dengan fitur pencarian, filter role, filter bypass, dan paginasi.

- **Auth:** `Bearer Token` (`role:lecturer,admin`)
- **Query Parameters:**
  | Parameter | Tipe | Default | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `role` / `user_role` | string | - | Filter role (`mahasiswa`, `student`, `dosen`, `admin`, dll.) |
  | `status` | string | - | Filter status (`active`, `inactive`) |
  | `search` / `q` | string | - | Cari berdasarkan nama, email, atau NIM mahasiswa |
  | `is_bypass` | boolean | - | Filter khusus mahasiswa bypass GEMASTIK / LIDM |
  | `all` | boolean | `false` | Jika `true`, kembalikan seluruh data tanpa paginasi |
  | `per_page` | integer | `15` | Jumlah data per halaman |
  | `page` | integer | `1` | Nomor halaman aktif |

- **Response (HTTP 200):**
```json
{
  "status": "success",
  "message": "Daftar seluruh pengguna berhasil diambil.",
  "data": [
    {
      "id": 1,
      "name": "Budi Santoso",
      "email": "budi@mail.ugm.ac.id",
      "user_role": "student",
      "role": "student",
      "role_label": "Mahasiswa",
      "status": "active",
      "is_bypass": false,
      "student_profile": {
        "nim": "23/512345/SV/22001",
        "is_bypass": false
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

---

#### 4.2.3 POST `/api/v1/users/whitelist`
Mendaftarkan stakeholder (mahasiswa KRS, asisten, dosen, praktisi) secara manual (mengetik satu atau banyak).

- **Auth:** `Bearer Token` (`role:lecturer,admin`)
- **Request Body (Single):**
```json
{
  "email": "mahasiswa1@mail.ugm.ac.id",
  "name": "Budi Santoso",
  "user_role": "mahasiswa",
  "nim": "23/512345/SV/22001",
  "is_bypass": false
}
```
- **Request Body (Bulk):**
```json
{
  "users": [
    { "email": "mhs1@mail.ugm.ac.id", "name": "Budi", "nim": "23/512001/SV/1", "user_role": "mahasiswa" },
    { "email": "asprak@ugm.ac.id", "name": "Siti", "user_role": "asisten" }
  ]
}
```
*(Key pembungkus array yang didukung: `users`, `whitelist`, atau `stakeholders`)*

- **Response (HTTP 201):**
```json
{
  "status": "success",
  "message": "Data pengguna berhasil dimasukkan ke dalam daftar whitelist.",
  "data": { ... }
}
```

---

#### 4.2.4 POST `/api/v1/users/whitelist/import`
Mengimpor data mahasiswa KRS dalam jumlah besar melalui berkas CSV atau Excel (.xlsx, .xls, .csv, .txt).

- **Auth:** `Bearer Token` (`role:lecturer,admin`)
- **Headers:** `Content-Type: multipart/form-data`
- **Form Data:**
  - `file`: Berkas spreadsheet (Maks. 10MB)
- **Struktur Kolom Berkas yang Didukung:**
  Sistem secara fleksibel mengenali kolom:
  - `email` / `alamat email` / `e-mail` / `mail`
  - `nim` / `nomor induk mahasiswa` / `no_mhs`
  - `name` / `nama` / `nama mahasiswa` / `nama lengkap`
- **Response (HTTP 201):**
```json
{
  "status": "success",
  "message": "Import selesai. Berhasil memasukkan 120 mahasiswa dari KRS ke tabel users dan student_profiles.",
  "total_imported": 120,
  "errors": []
}
```

---

#### 4.2.5 DELETE `/api/v1/users/{id}`
Menghapus pengguna dari sistem / whitelist.

- **Auth:** `Bearer Token` (`role:lecturer,admin`)
- **Proteksi Integritas Data:**
  Jika mahasiswa telah memiliki peran yang terkunci (`is_locked = true` pada tabel `student_role_evaluations`) atau telah berada di dalam kelompok yang terkunci (`is_locked = true` pada tabel `teams`), permintaan penghapusan **akan ditolak demi keamanan**.
- **Response Sukses (HTTP 200):**
```json
{
  "status": "success",
  "message": "Email pengguna berhasil dihapus dari daftar whitelist."
}
```
- **Response Terkunci (HTTP 422):**
```json
{
  "status": "error",
  "message": "Akses ditolak. Pengguna tidak dapat dihapus dari whitelist karena peran (role) sudah dikunci."
}
```

---

### 4.3 Modul Tim Eksklusif (GEMASTIK / LIDM)

#### 4.3.1 POST `/api/v1/teams/exclusive`
Mendaftarkan satu kelompok utuh dari jalur lomba/kompetisi eksternal (GEMASTIK, LIDM Software, dll).
Sistem secara otomatis:
1. Membuat record `Team` dengan tipe `external` (atau `internal`) dan langsung mengunci tim (`is_locked = true`).
2. Menghubungkan Dosen Pembimbing (`supervisor`).
3. Mengubah mahasiswa anggota tim menjadi status **Bypass** (`is_bypass = true`) dan mengaitkannya ke `team_id`.
4. Membuat record `Project` untuk tim tersebut.

- **Auth:** `Bearer Token` (`role:lecturer,admin`)
- **Request Body (JSON):**
```json
{
  "team_name": "Tim GEMASTIK Software Divisi 1",
  "team_type": "external",
  "supervisor_id": 2,
  "project_associate": "Puspresnas GEMASTIK 2026",
  "project_description": "Aplikasi Sistem Deteksi Cerdas",
  "members": [
    {
      "email": "ketua@mail.ugm.ac.id",
      "name": "Ketua Tim",
      "nim": "23/510001/SV/10001",
      "role_id": 1
    },
    {
      "email": "anggota1@mail.ugm.ac.id",
      "name": "Anggota Frontend",
      "nim": "23/510002/SV/10002",
      "role_id": 3
    },
    {
      "email": "anggota2@mail.ugm.ac.id",
      "name": "Anggota Backend",
      "nim": "23/510003/SV/10003",
      "role_id": 4
    }
  ]
}
```
*(Bisa juga menyertakan array sederhana `student_ids: [1, 2, 3]` atau `emails: ["a@mail.ugm.ac.id", ...]`)*

- **Response (HTTP 201):**
```json
{
  "status": "success",
  "message": "Tim eksklusif jalur GEMASTIK / LIDM Software berhasil didaftarkan.",
  "data": {
    "id": 10,
    "team_type": "external",
    "is_locked": true,
    "supervisor": 2,
    "project": {
      "id": 5,
      "project_name": "Tim GEMASTIK Software Divisi 1",
      "project_associate": "Puspresnas GEMASTIK 2026"
    },
    "members": [ ... ]
  }
}
```

---

### 4.4 Modul Konfigurasi Sistem (App Settings)

#### 4.4.1 GET `/api/v1/settings`
Mengambil konfigurasi sistem aktif dari tabel `app_settings` (apakah pilihan role ke-2 wajib/opsional, status pendaftaran, jadwal timeline, dsb).

- **Auth:** `Bearer Token` (`auth:sanctum` - semua peran yang login)
- **Response (HTTP 200):**
```json
{
  "status": "success",
  "message": "Parameter konfigurasi sistem berhasil diambil.",
  "data": {
    "is_role_2_required": false,
    "is_role_2_required_label": "opsional",
    "registration_timeline": "1 Okt 2026 - 15 Okt 2026",
    "registration_terms": "Mahasiswa wajib terdaftar pada KRS aktif",
    "registration_status": "open",
    "academic_year": "2026/2027",
    "all_settings": {
      "is_role_2_required": "false",
      "registration_status": "open"
    }
  }
}
```

---

#### 4.4.2 PUT `/api/v1/settings`
Memperbarui konfigurasi sistem (toggle kewajiban role 2, tanggal pendaftaran, dsb).

- **Auth:** `Bearer Token` (`role:lecturer,admin`)
- **Request Body (JSON):**
```json
{
  "is_role_2_required": true,
  "registration_timeline": "10 Nov 2026 - 25 Nov 2026",
  "registration_status": "open",
  "academic_year": "2026/2027"
}
```
*(Nilai `is_role_2_required` menerima boolean `true`/`false` atau string `"wajib"`/`"opsional"`)*

- **Response (HTTP 200):**
Mengembalikan data konfigurasi terbaru sama seperti response `GET /api/v1/settings`.

---

### 4.5 Modul Master Data Roles & Skillset

#### 4.5.1 GET `/api/v1/roles`
Mengambil daftar 4 peran proyek (PM, UI/UX, FE, BE) lengkap dengan seluruh daftar keahlian (*skills*) yang terkait.

- **Auth:** `Bearer Token` (`auth:sanctum` - semua peran yang login)
- **Response (HTTP 200):**
```json
{
  "status": "success",
  "message": "Daftar roles dan skillset berhasil diambil.",
  "data": [
    {
      "id": 1,
      "code": "PM",
      "name": "Project Manager",
      "description": "Bertanggung jawab atas manajemen proyek...",
      "skills": [
        { "id": 1, "role_id": 1, "name": "Scrum / Agile" },
        { "id": 2, "role_id": 1, "name": "Trello / Jira" }
      ]
    },
    {
      "id": 2,
      "code": "UIUX",
      "name": "UI/UX Designer",
      "skills": [
        { "id": 3, "role_id": 2, "name": "Figma" },
        { "id": 4, "role_id": 2, "name": "Design System" }
      ]
    },
    { "id": 3, "code": "FE", "name": "Frontend Developer", "skills": [ ... ] },
    { "id": 4, "code": "BE", "name": "Backend Developer", "skills": [ ... ] }
  ]
}
```

---

#### 4.5.2 POST `/api/v1/skills`
Menambahkan item keahlian baru ke dalam role tertentu.

- **Auth:** `Bearer Token` (`role:lecturer,admin`)
- **Request Body:**
```json
{
  "role_id": 3,
  "name": "Next.js 15 / React Server Components"
}
```
*(Bisa juga menggunakan `role_code`: `"FE"`, `"BE"`, `"UIUX"`, `"PM"`)*

- **Response (HTTP 201):**
```json
{
  "status": "success",
  "message": "Item skillset berhasil ditambahkan ke role.",
  "data": {
    "id": 15,
    "role_id": 3,
    "name": "Next.js 15 / React Server Components"
  }
}
```

---

#### 4.5.3 PUT/PATCH `/api/v1/skills/{id}`
Memperbarui nama atau role dari item keahlian.

- **Auth:** `Bearer Token` (`role:lecturer,admin`)
- **Request Body:**
```json
{
  "name": "Tailwind CSS v4"
}
```
- **Response (HTTP 200):**
```json
{
  "status": "success",
  "message": "Item skillset berhasil diperbarui.",
  "data": { "id": 15, "role_id": 3, "name": "Tailwind CSS v4" }
}
```

---

#### 4.5.4 DELETE `/api/v1/skills/{id}`
Menghapus item keahlian dari sistem.

- **Auth:** `Bearer Token` (`role:lecturer,admin`)
- **Response (HTTP 200):**
```json
{
  "status": "success",
  "message": "Item skillset berhasil dihapus."
}
```

---

### 4.6 Modul Pendaftaran & Form Profiling Mahasiswa

Mahasiswa mengisi form pilihan peran dan mencentang keahlian yang dikuasai. Skor dihitung secara otomatis saat form disubmit melalui rumus pembobotan resmi.

---

#### 4.6.1 GET `/api/v1/student/profiling-config`
Memeriksa kondisi akun mahasiswa yang sedang login:
- Apakah berstatus **Bypass** (jalur GEMASTIK/LIDM).
- Apakah role 2 berstatus **Wajib** / **Opsional** / **Disabled**.
- Apakah form profiling telah **Dikunci** oleh dosen pengampu.
- Mengembalikan data isian profil yang tersimpan sebelumnya & master data pilihan roles/skills.

- **Auth:** `Bearer Token` (`role:student,admin`)
- **Response (HTTP 200):**
```json
{
  "status": "success",
  "message": "Konfigurasi form profiling berhasil diambil.",
  "data": {
    "config": {
      "is_bypass": false,
      "is_role_2_required": true,
      "allow_role_2": true,
      "role_2_status": "required",
      "is_locked": false
    },
    "current_profile": {
      "nim": "23/512345/SV/22001",
      "role_1_id": 4,
      "role_2_id": 3,
      "portfolio_link": "https://github.com/budi",
      "is_bypass": false,
      "selected_skill_ids": [10, 11, 14]
    },
    "roles": [ ... ]
  }
}
```

---

#### 4.6.2 POST `/api/v1/student/profile`
Menyimpan pengisian form profiling dan **langsung menghitung skor rekomendasi peran** secara otomatis.

- **Auth:** `Bearer Token` (`role:student,admin`)
- **Request Body (JSON):**
```json
{
  "nim": "23/512345/SV/22001",
  "role_1_id": 4,
  "role_2_id": 3,
  "skill_ids": [8, 9, 10, 12],
  "portfolio_link": "https://github.com/budi/my-portfolio"
}
```

- **Aturan Validasi & Logika Bisnis:**
  1. Jika status form telah dikunci (`is_locked = true`), pengiriman ditolak dengan **HTTP 422**.
  2. `role_1_id` dan `role_2_id` **tidak boleh bernilai sama** (HTTP 422).
  3. Jika mahasiswa `is_bypass = true`, pilihan `role_2_id` otomatis diabaikan (*force null*).
  4. Jika sistem mewajibkan role 2 (`is_role_2_required = true`) dan mahasiswa bukan bypass, `role_2_id` **wajib diisi**.
  5. **Formula Perhitungan Skor:**
     $$\text{Skor Peran} = (\text{Nilai Keahlian} \times 70\%) + (\text{Nilai Prioritas} \times 30\%)$$
     - **Nilai Keahlian (0 - 100):** $\frac{\text{Skill Relevan yang Dipilih}}{\text{Total Skill pada Role Tersebut}} \times 100$
     - **Nilai Prioritas Pilihan 1:** Bobot $100\% \times 30\% = 30\text{ poin}$
     - **Nilai Prioritas Pilihan 2:** Bobot $50\% \times 30\% = 15\text{ poin}$
     - `recommended_role_id` diberikan kepada role dengan skor tertinggi.

- **Response (HTTP 200):**
```json
{
  "status": "success",
  "message": "Profil dan keahlian mahasiswa berhasil disimpan dan dievaluasi.",
  "data": {
    "profile": {
      "id": 1,
      "user_id": 1,
      "nim": "23/512345/SV/22001",
      "is_bypass": false,
      "role_1_id": 4,
      "role_2_id": 3,
      "portfolio_link": "https://github.com/budi/my-portfolio",
      "selected_skill_ids": [8, 9, 10, 12]
    },
    "evaluation": {
      "id": 1,
      "user_id": 1,
      "score_role_1": 82.50,
      "score_role_2": 57.00,
      "recommended_score": 82.50,
      "recommended_role_id": 4,
      "recommended_role": {
        "id": 4,
        "code": "BE",
        "name": "Backend Developer"
      },
      "final_role_id": 4,
      "final_role": {
        "id": 4,
        "code": "BE",
        "name": "Backend Developer"
      },
      "is_overridden": false,
      "is_locked": false
    }
  }
}
```

---

### 4.7 Modul Pengumuman Kelompok Mahasiswa (My Team)

#### 4.7.1 GET `/api/v1/student/my-team`
Menampilkan pengumuman tim kelompok mahasiswa, termasuk posisi/peran final mahasiswa, Dosen Pembimbing, detail Proyek, dan daftar seluruh rekan kelompok beserta perannya.

- **Auth:** `Bearer Token` (`role:student,admin`)

- **Kondisi 1: Mahasiswa Belum Memiliki Kelompok:**
```json
{
  "status": "success",
  "message": "Anda belum terdaftar dalam kelompok atau pengumuman kelompok belum dirilis.",
  "data": {
    "has_team": false,
    "final_role": {
      "id": 4,
      "code": "BE",
      "name": "Backend Developer"
    },
    "team": null
  }
}
```

- **Kondisi 2: Mahasiswa Sudah Terdaftar di Kelompok:**
```json
{
  "status": "success",
  "message": "Informasi tim dan pengumuman peran berhasil dimuat.",
  "data": {
    "has_team": true,
    "final_role": {
      "id": 4,
      "code": "BE",
      "name": "Backend Developer"
    },
    "team": {
      "id": 1,
      "team_type": "internal",
      "is_locked": true,
      "supervisor": {
        "id": 2,
        "name": "Ir. Dinar Nugroho Pratomo, M.Cs.",
        "email": "dinar@ugm.ac.id",
        "role": "lecturer",
        "role_label": "Dosen Pengampu"
      },
      "project": {
        "id": 1,
        "name": "Sistem Informasi Rekam Medis",
        "description": "Pengembangan aplikasi web rekam medis klinik",
        "associate": "RS Akademik UGM",
        "owner": {
          "id": 1,
          "name": "Budi Santoso",
          "email": "budi@mail.ugm.ac.id"
        }
      },
      "members": [
        {
          "id": 1,
          "user_id": 1,
          "name": "Budi Santoso",
          "email": "budi@mail.ugm.ac.id",
          "nim": "23/512345/SV/22001",
          "portfolio_link": "https://github.com/budi",
          "final_role": {
            "id": 4,
            "code": "BE",
            "name": "Backend Developer"
          }
        },
        {
          "id": 2,
          "user_id": 5,
          "name": "Siti Rahma",
          "email": "siti@mail.ugm.ac.id",
          "nim": "23/512346/SV/22002",
          "portfolio_link": "https://figma.com/@siti",
          "final_role": {
            "id": 2,
            "code": "UIUX",
            "name": "UI/UX Designer"
          }
        }
      ]
    }
  }
}
```

---

### 4.8 Endpoint Kompatibilitas / Legacy Alias

Untuk menjaga kompatibilitas mundur dengan tugas pengujian terdahulu, rute-rute berikut tetap aktif dan memiliki fungsionalitas yang identik:

| Endpoint Alias | Method | Mengarah ke Controller | Keterangan |
| :--- | :--- | :--- | :--- |
| `/api/v1/stakeholders` | GET | `UserController@index` | Alias daftar pengguna |
| `/api/v1/stakeholders` | POST | `UserController@storeWhitelist` | Alias input whitelist |
| `/api/v1/stakeholders/{user}` | GET | `StakeholderController@show` | Menampilkan detail 1 pengguna |
| `/api/v1/stakeholders/{user}` | DELETE | `UserController@destroy` | Hapus stakeholder |
| `/api/v1/stakeholders/{user}/role` | PUT/PATCH | `StakeholderController@assignRole` | Ubah role via URL ID |
| `/api/v1/stakeholders/assign-role` | POST | `StakeholderController@assignRole` | Ubah role via Request Body Email |

---

## 5. Contoh Integrasi Frontend (Axios Instance & Interceptor)

Berikut adalah contoh implementasi berkas `src/services/api.js` pada project Frontend (React / Vue / Next.js):

```javascript
import axios from 'axios';

// 1. Buat Axios Instance
const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// 2. Request Interceptor: Otomatis lampirkan token jika tersimpan
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// 3. Response Interceptor: Tangani sesi habis / token expired
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      if (window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);

export default api;
```

### Contoh Penggunaan pada Komponen Form Profiling:
```javascript
import api from './services/api';

// 1. Load data konfigurasi & peran saat form dibuka
export async function loadProfilingPage() {
  const res = await api.get('/student/profiling-config');
  return res.data.data;
}

// 2. Kirim isian profiling
export async function submitStudentProfile(formData) {
  try {
    const res = await api.post('/student/profile', {
      nim: formData.nim,
      role_1_id: formData.role1Id,
      role_2_id: formData.role2Id || null,
      skill_ids: formData.selectedSkills,
      portfolio_link: formData.portfolioLink,
    });
    return res.data;
  } catch (error) {
    throw error.response?.data || error.message;
  }
}

// 3. Muat pengumuman kelompok
export async function fetchMyTeam() {
  const res = await api.get('/student/my-team');
  return res.data.data;
}
```

---

## 6. Panduan Testing & Debugging Backend (BE)

### 6.1 Menjalankan Server & Environment PHP 8.3

Aplikasi ini mewajibkan **PHP >= 8.3**. Pada Laragon (Windows), gunakan binary PHP 8.3 secara eksplisit:

```powershell
# Jalankan Local Dev Server
& "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" artisan serve --port=8000
```

---

### 6.2 Menjalankan Automated Feature Tests (PHPUnit)

Seluruh modul (Autentikasi SSO, Whitelist KRS, Master Settings, Roles/Skills, dan Student Profiling) telah memiliki test suite komprehensif sebanyak **63 skenario test dengan 310 assertions**.

#### Menjalankan Seluruh Test:
```powershell
& "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" vendor/bin/phpunit --testdox
```

#### Menjalankan File Test Tertentu:
```powershell
# 1. Test Autentikasi SSO UGM:
& "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" vendor/bin/phpunit tests/Feature/AuthSSOTest.php

# 2. Test Manajemen Whitelist KRS & Tim Eksklusif:
& "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" vendor/bin/phpunit tests/Feature/UserManagementWhitelistTest.php

# 3. Test Pengaturan Sistem & Master Roles/Skills:
& "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" vendor/bin/phpunit tests/Feature/SystemSettingsAndRolesTest.php

# 4. Test Form Profiling & Scoring Engine Mahasiswa:
& "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" vendor/bin/phpunit tests/Feature/StudentProfilingTest.php
```

---

### 6.3 Web Interactive Tester & Token Generator Dev

Untuk mempermudah pengujian frontend dan backend secara visual, telah disediakan antarmuka web interaktif bawaan:

1. **API Web Tester Interaktif:**  
   Buka browser di: [http://localhost:8000/](http://localhost:8000/)  
   Memungkinkan pengujian seluruh endpoint, simulasi SSO callback, pemilihan akun, dan eksekusi request langsung dari UI.

2. **Endpoint Generate Token Dev Cepat (Tanpa OAuth):**  
   Saat mode `APP_ENV=local`, Anda dapat menghasilkan token Sanctum instan tanpa login Google:
   ```powershell
   curl -X POST "http://localhost:8000/dev/generate-token" `
     -H "Content-Type: application/json" `
     -d '{"email":"dosen.test@mail.ugm.ac.id","create_if_missing":true,"name":"Dosen Tester"}'
   ```
   *Response akan langsung memberikan token Sanctum yang valid untuk pengujian API.*

---

### 6.4 Debugging Cepat via Laravel Tinker

Buka shell Tinker:
```powershell
& "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" artisan tinker
```

#### Contoh Perintah Berguna:
```php
// 1. Buat Dosen Admin dan dapatkan token instan:
$lecturer = App\Models\User::firstOrCreate(
    ['email' => 'dosen@ugm.ac.id'],
    ['name' => 'Dosen Utama', 'user_role' => App\Enums\UserRole::Lecturer, 'status' => 'active']
);
$token = $lecturer->createToken('test-token')->plainTextToken;
echo "Token Dosen: " . $token;

// 2. Cek status toggle kewajiban role 2:
App\Models\AppSetting::where('key', 'is_role_2_required')->first();

// 3. Cek hasil evaluasi profiling mahasiswa:
App\Models\StudentRoleEvaluation::with(['recommendedRole', 'finalRole'])->get();
```

---

### 6.5 Kumpulan Perintah Pengujian Manual cURL

#### 1. Dapatkan URL Login Google OAuth:
```powershell
curl -X GET "http://localhost:8000/api/v1/auth/google/redirect" -H "Accept: application/json"
```

#### 2. Ambil Profil User yang Sedang Login (`/me`):
```powershell
curl -X GET "http://localhost:8000/api/v1/auth/me" `
  -H "Accept: application/json" `
  -H "Authorization: Bearer <TOKEN_ANDA>"
```

#### 3. Inisiasi Dosen Pengampu:
```powershell
curl -X POST "http://localhost:8000/api/v1/admin/lecturers" `
  -H "Content-Type: application/json" `
  -H "Accept: application/json" `
  -H "Authorization: Bearer <TOKEN_ADMIN>" `
  -d '{"email":"dosen.pengampu@ugm.ac.id","name":"Dr. Dosen, M.Kom.","role":"lecturer"}'
```

#### 4. Daftarkan Mahasiswa ke Whitelist:
```powershell
curl -X POST "http://localhost:8000/api/v1/users/whitelist" `
  -H "Content-Type: application/json" `
  -H "Accept: application/json" `
  -H "Authorization: Bearer <TOKEN_DOSEN>" `
  -d '{"email":"mhs.baru@mail.ugm.ac.id","name":"Budi Santoso","nim":"23/512345/SV/22001","user_role":"mahasiswa"}'
```

#### 5. Daftarkan Tim Eksklusif GEMASTIK:
```powershell
curl -X POST "http://localhost:8000/api/v1/teams/exclusive" `
  -H "Content-Type: application/json" `
  -H "Accept: application/json" `
  -H "Authorization: Bearer <TOKEN_DOSEN>" `
  -d '{"team_name":"Tim Alpha GEMASTIK","emails":["mhs1@mail.ugm.ac.id","mhs2@mail.ugm.ac.id"]}'
```

#### 6. Update Konfigurasi Toggle Role 2 Wajib:
```powershell
curl -X PUT "http://localhost:8000/api/v1/settings" `
  -H "Content-Type: application/json" `
  -H "Accept: application/json" `
  -H "Authorization: Bearer <TOKEN_DOSEN>" `
  -d '{"is_role_2_required":true}'
```

#### 7. Mahasiswa Mengisi Form Profiling:
```powershell
curl -X POST "http://localhost:8000/api/v1/student/profile" `
  -H "Content-Type: application/json" `
  -H "Accept: application/json" `
  -H "Authorization: Bearer <TOKEN_MAHASISWA>" `
  -d '{"nim":"23/512345/SV/22001","role_1_id":4,"role_2_id":3,"skill_ids":[1,2,5]}'
```

#### 8. Mahasiswa Melihat Pengumuman Kelompok:
```powershell
curl -X GET "http://localhost:8000/api/v1/student/my-team" `
  -H "Accept: application/json" `
  -H "Authorization: Bearer <TOKEN_MAHASISWA>"
```

---

### 6.6 Troubleshooting & Solusi Kendala Umum

| Masalah / Error | Penyebab | Solusi |
| :--- | :--- | :--- |
| `Platform Check: require PHP >= 8.3.0` | CLI Windows memanggil PHP versi lama di PATH. | Gunakan path langsung PHP 8.3: `"C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe"`. |
| `403: Akses ditolak, hanya email domain @mail.ugm.ac.id` | Akun Google bukan akun resmi mahasiswa/dosen UGM. | Gunakan email yang berakhiran `@mail.ugm.ac.id`. |
| `403: Akses ditolak, email tidak terdaftar pada KRS aktif` | Email belum ada di tabel `users` atau statusnya bukan `active`. | Masukkan email ke whitelist melalui `POST /api/v1/users/whitelist` atau fitur Import Excel. |
| `403: Akses ditolak. Anda tidak memiliki izin untuk mengakses resource ini` | Token yang digunakan tidak memiliki peran yang diizinkan oleh rute (misal mahasiswa mencoba akses rute dosen). | Gunakan akun dengan peran yang sesuai (`lecturer` atau `admin`). |
| `422: Pilihan role 1 dan role 2 tidak boleh sama` | Mahasiswa memilih role yang identik pada pilihan 1 dan 2. | Pilih dua peran yang berbeda di form profiling. |
| `422: Pengguna tidak dapat dihapus karena role sudah dikunci` | Mahasiswa sudah memiliki hasil evaluasi atau tim dengan status `is_locked = true`. | Buka kunci (*unlock*) terlebih dahulu oleh dosen pengampu sebelum data dapat dihapus. |

---
*Dokumentasi ini dibuat dan diselaraskan secara otomatis dengan codebase PAD-BE terkini. Seluruh fitur telah terverifikasi melalui automated test suite.*
