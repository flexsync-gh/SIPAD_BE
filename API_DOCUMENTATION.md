# 📚 Dokumentasi Lengkap API PAD-BE (Project Agent Delivery)

Dokumentasi ini mencakup seluruh antarmuka pemrograman aplikasi (API) yang tersedia pada backend PAD-BE. Disusun untuk mempermudah integrasi **Frontend (FE)** serta memandu proses debugging, simulasi, dan pengujian oleh **Backend (BE)**.

---

## 📑 Daftar Isi
1. [Ringkasan & Lingkungan (Environment)](#1-ringkasan--lingkungan-environment)
2. [Format Standar Response & Error Handling](#2-format-standar-response--error-handling)
3. [Daftar Peran Pengguna (User Roles & Aliases)](#3-daftar-peran-pengguna-user-roles--aliases)
4. [Dokumentasi Endpoint & Panduan Frontend (FE)](#4-dokumentasi-endpoint--panduan-frontend-fe)
   - [4.1 Modul Autentikasi & SSO UGM](#41-modul-autentikasi--sso-ugm)
     - [GET `/api/v1/auth/google/redirect`](#411-get-apiv1authgoogleredirect)
     - [POST `/api/v1/auth/google/callback`](#412-post-apiv1authgooglecallback)
     - [GET `/api/v1/auth/me`](#413-get-apiv1authme)
     - [POST `/api/v1/auth/logout`](#414-post-apiv1authlogout)
   - [4.2 Modul Inisiasi Admin & Dosen Pengampu](#42-modul-inisiasi-admin--dosen-pengampu)
     - [POST `/api/v1/admin/initiate-lecturer`](#421-post-apiv1admininitiate-lecturer)
   - [4.3 Modul Manajemen Stakeholder (Whitelist KRS & Akun)](#43-modul-manajemen-stakeholder-whitelist-krs--akun)
     - [GET `/api/v1/stakeholders`](#431-get-apiv1stakeholders)
     - [POST `/api/v1/stakeholders`](#432-post-apiv1stakeholders)
     - [GET `/api/v1/stakeholders/{user}`](#433-get-apiv1stakeholdersuser)
     - [PUT/PATCH `/api/v1/stakeholders/{user}/role`](#434-putpatch-apiv1stakeholdersuserrole)
     - [POST `/api/v1/stakeholders/assign-role`](#435-post-apiv1stakeholdersassign-role)
     - [DELETE `/api/v1/stakeholders/{user}`](#436-delete-apiv1stakeholdersuser)
5. [Contoh Integrasi Frontend (Axios Instance & Interceptor)](#5-contoh-integrasi-frontend-axios-instance--interceptor)
6. [Panduan Testing & Debugging Backend (BE)](#6-panduan-testing--debugging-backend-be)
   - [6.1 Menjalankan Server & Environment PHP 8.3](#61-menjalankan-server--environment-php-83)
   - [6.2 Menjalankan Automated Feature Test (PHPUnit)](#62-menjalankan-automated-feature-test-phpunit)
   - [6.3 Debugging Menggunakan Laravel Tinker](#63-debugging-menggunakan-laravel-tinker)
   - [6.4 Pengujian Manual Menggunakan cURL](#64-pengujian-manual-menggunakan-curl)
   - [6.5 Troubleshooting Error yang Sering Ditemui](#65-troubleshooting-error-yang-sering-ditemui)

---

## 1. Ringkasan & Lingkungan (Environment)

### Base URL
- **Local (Artisan Serve):** `http://localhost:8000`
- **Local (Laragon Virtual Host):** `http://pad-be.test`
- **Production / Staging:** Sesuai URL server yang ditunjuk

### API Versioning & Routing Alias
Seluruh route didaftarkan dengan dua alias:
1. **Versi Direkomendasikan:** `/api/v1/...` (contoh: `/api/v1/auth/me`)
2. **Direct Root Alias:** `/api/...` (contoh: `/api/auth/me`)

> **Rekomendasi untuk Frontend:** Selalu gunakan prefix `/api/v1/` untuk stabilitas versi dan masa depan integrasi.

### Header Standar Request
Kecuali ditentukan lain, setiap request wajib menyertakan header:
```http
Accept: application/json
Content-Type: application/json
```
Untuk endpoint yang dilindungi (protected routes):
```http
Authorization: Bearer <personal_access_token>
```

---

## 2. Format Standar Response & Error Handling

### Response Berhasil (Success)
```json
{
  "status": "success",
  "message": "Pesan keberhasilan aksi.",
  "data": { ... }
}
```

### Response Validasi Gagal (HTTP 422)
```json
{
  "message": "Bidang email stakeholder wajib diisi.",
  "errors": {
    "email": [
      "Bidang email stakeholder wajib diisi."
    ]
  }
}
```

### Response Error Logika / Izin (HTTP 400 / 403 / 404)
```json
{
  "status": "error",
  "message": "Deskripsi alasan mengapa akses atau aksi ditolak."
}
```

### Response Belum Login / Token Invalid (HTTP 401)
```json
{
  "message": "Unauthenticated."
}
```

---

## 3. Daftar Peran Pengguna (User Roles & Aliases)

Sistem menggunakan Enum `App\Enums\UserRole` yang mendukung normalisasi input nama peran baik dalam Bahasa Indonesia maupun Bahasa Inggris:

| Nilai Database (Value) | Label Tampilan | Input Alias yang Diterima (Case-Insensitive) |
| :--- | :--- | :--- |
| `student` | Mahasiswa | `student`, `mahasiswa` |
| `lecturer` | Dosen Pengampu | `lecturer`, `dosen`, `dosen_pengampu` |
| `admin` | Admin (Full) | `admin`, `full_admin`, `master_admin`, `full admin` |
| `assistant` | Asisten Praktikum | `assistant`, `asisten`, `asprak`, `asisten_praktikum`, `asisten praktikum` |
| `practitioner` | Praktisi Industri | `practitioner`, `praktisi`, `praktisi_industri`, `praktisi industri` |

---

## 4. Dokumentasi Endpoint & Panduan Frontend (FE)

### 4.1 Modul Autentikasi & SSO UGM

Sistem autentikasi menggunakan alur **Google OAuth 2.0 (SSO UGM)**.
Aturan validasi autentikasi:
1. Akun Google harus terverifikasi (`email_verified = true`).
2. Domain email Google harus berakhiran `@mail.ugm.ac.id`.
3. Email harus sudah terdaftar sebelumnya di database sistem (whitelist KRS) dengan `status = 'active'`.

---

#### 4.1.1 GET `/api/v1/auth/google/redirect`
Mendapatkan URL otorisasi Google OAuth untuk diarahkan ke user browser.

- **Auth Required:** Tidak (Public)
- **Query Parameters:**
  | Parameter | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `redirect_uri` | string | Tidak | URL callback FE jika dinamis/berbeda dari default `.env` |

- **Response Sukses (HTTP 200):**
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
  const response = await fetch('http://localhost:8000/api/v1/auth/google/redirect');
  const result = await response.json();
  if (result.status === 'success') {
    // Redirect browser ke halaman login Google
    window.location.href = result.url;
  }
}
```

---

#### 4.1.2 POST `/api/v1/auth/google/callback`
Menukar authorization `code` atau `access_token` dari Google untuk verifikasi dan menghasilkan Personal Access Token (Sanctum).

- **Auth Required:** Tidak (Public)
- **Request Body (JSON):**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `code` | string | Kondisional | Authorization code dari query parameter callback Google |
  | `token` / `access_token` | string | Kondisional | Access token OAuth (jika FE menggunakan Google Identity Services SDK langsung) |
  | `redirect_uri` | string | Tidak | Harus sama dengan redirect_uri saat request URL sebelumnya jika dipakai |

  > *Catatan: Wajib mengirim salah satu antara `code` atau `token`.*

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
      "portfolio_link": null,
      "created_at": "2026-10-23T03:30:00.000000Z",
      "updated_at": "2026-10-23T03:30:00.000000Z"
    },
    "created_at": "2026-10-23T03:30:00.000000Z",
    "updated_at": "2026-10-23T03:30:00.000000Z"
  }
}
```

- **Kemungkinan Response Error:**
  - `400 Bad Request`: Token / Code Google kadaluarsa atau tidak valid.
  - `403 Forbidden`: Email belum diverifikasi Google, atau bukan `@mail.ugm.ac.id`, atau belum didaftarkan di whitelist KRS.
  - `422 Unprocessable Entity`: Parameter `code` maupun `token` tidak disertakan.

- **Contoh Pemanggilan FE (React / Vue Component Callback):**
```javascript
// Dijalankan di halaman /auth/callback setelah redirect dari Google
async function handleGoogleCallback() {
  const urlParams = new URLSearchParams(window.location.search);
  const code = urlParams.get('code');

  try {
    const res = await axios.post('http://localhost:8000/api/v1/auth/google/callback', {
      code: code,
      redirect_uri: window.location.origin + window.location.pathname
    });

    const { token, user } = res.data;
    localStorage.setItem('auth_token', token);
    localStorage.setItem('user', JSON.stringify(user));
    
    // Redirect ke dashboard
    window.location.href = '/dashboard';
  } catch (error) {
    alert(error.response?.data?.message || 'Gagal login SSO.');
  }
}
```

---

#### 4.1.3 GET `/api/v1/auth/me`
Mengambil data profil pengguna yang sedang terautentikasi berdasarkan Bearer Token.

- **Auth Required:** Ya (`Bearer <token>`)
- **Response Sukses (HTTP 200):**
```json
{
  "status": "success",
  "message": "Data profil pengguna yang sedang login berhasil diambil.",
  "data": {
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
      "portfolio_link": null,
      "created_at": "2026-10-23T03:30:00.000000Z",
      "updated_at": "2026-10-23T03:30:00.000000Z"
    },
    "created_at": "2026-10-23T03:30:00.000000Z",
    "updated_at": "2026-10-23T03:30:00.000000Z"
  }
}
```

---

#### 4.1.4 POST `/api/v1/auth/logout`
Mencabut / menghapus personal access token yang sedang aktif.

- **Auth Required:** Ya (`Bearer <token>`)
- **Response Sukses (HTTP 200):**
```json
{
  "status": "success",
  "message": "Berhasil logout, token telah dihapus."
}
```

- **Contoh Pemanggilan FE:**
```javascript
async function logout() {
  await api.post('/auth/logout');
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
  window.location.href = '/login';
}
```

---

### 4.2 Modul Inisiasi Admin & Dosen Pengampu

#### 4.2.1 POST `/api/v1/admin/initiate-lecturer`
Menginisiasi email dosen pengampu ke sistem sebagai **Full Admin** (`user_role = 'admin'`, `status = 'active'`). Mendukung inisiasi satuan maupun bulk (banyak sekaligus).

- **Route Aliases:**
  - `/api/v1/admin/initiate-lecturer`
  - `/api/admin/initiate-lecturer`
  - `/api/v1/initiate-lecturer`
  - `/api/initiate-lecturer`
- **Auth Required:** Publik / Admin Setup

##### Opsi A: Inisiasi Single Lecturer
- **Request Body:**
```json
{
  "email": "dinar@ugm.ac.id",
  "name": "Ir. Dinar Nugroho Pratomo, S.Kom., M.IM., M.Cs."
}
```
- **Response Sukses (HTTP 201):**
```json
{
  "status": "success",
  "message": "Email dosen pengampu berhasil diinisiasi sebagai full admin.",
  "data": {
    "id": 2,
    "name": "Ir. Dinar Nugroho Pratomo, S.Kom., M.IM., M.Cs.",
    "email": "dinar@ugm.ac.id",
    "google_id_sub": null,
    "user_role": "admin",
    "role": "admin",
    "role_label": "Admin (Full)",
    "avatar_link": null,
    "status": "active",
    "is_bypass": false,
    "student_profile": null,
    "created_at": "2026-10-23T03:35:00.000000Z",
    "updated_at": "2026-10-23T03:35:00.000000Z"
  }
}
```

##### Opsi B: Inisiasi Bulk Lecturers
- **Request Body:**
```json
{
  "lecturers": [
    {
      "email": "dinar@ugm.ac.id",
      "name": "Ir. Dinar Nugroho Pratomo, S.Kom., M.IM., M.Cs."
    },
    {
      "email": "iwan@ugm.ac.id",
      "name": "Ahmad Iwan Fadli, S.Kom., M.Eng."
    }
  ]
}
```
- **Response Sukses (HTTP 201):**
```json
{
  "status": "success",
  "message": "2 dosen pengampu berhasil diinisiasi sebagai full admin.",
  "data": [
    {
      "id": 2,
      "name": "Ir. Dinar Nugroho Pratomo, S.Kom., M.IM., M.Cs.",
      "email": "dinar@ugm.ac.id",
      "user_role": "admin",
      "role": "admin",
      "role_label": "Admin (Full)",
      "status": "active"
    },
    {
      "id": 3,
      "name": "Ahmad Iwan Fadli, S.Kom., M.Eng.",
      "email": "iwan@ugm.ac.id",
      "user_role": "admin",
      "role": "admin",
      "role_label": "Admin (Full)",
      "status": "active"
    }
  ]
}
```

---

### 4.3 Modul Manajemen Stakeholder (Whitelist KRS & Akun)

Modul ini digunakan untuk input whitelist mahasiswa yang mengambil KRS, asisten praktikum, praktisi industri, dan dosen.

---

#### 4.3.1 GET `/api/v1/stakeholders`
Mengambil daftar stakeholder terdaftar lengkap dengan paginasi dan filter.

- **Auth Required:** Tidak (atau disesuaikan dengan middleware admin di masa depan)
- **Query Parameters:**
  | Parameter | Tipe | Default | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `role` | string | - | Filter role (`mahasiswa`, `student`, `dosen`, `asisten`, `praktisi`, `admin`) |
  | `status` | string | - | Filter status user (`active`, `inactive`, dll.) |
  | `search` | string | - | Pencarian berdasarkan kata kunci pada `name` atau `email` |
  | `is_bypass` | boolean | - | `true` atau `false` (hanya mahasiswa dengan status bypass) |
  | `per_page` | integer | `15` | Jumlah data per halaman |
  | `page` | integer | `1` | Nomor halaman aktif |

- **Contoh Request URL:**
  `GET /api/v1/stakeholders?role=mahasiswa&search=budi&page=1&per_page=10`

- **Response Sukses (HTTP 200):**
```json
{
  "status": "success",
  "message": "Daftar stakeholder berhasil diambil.",
  "data": [
    {
      "id": 1,
      "name": "Budi Santoso",
      "email": "budi.santoso@mail.ugm.ac.id",
      "google_id_sub": null,
      "user_role": "student",
      "role": "student",
      "role_label": "Mahasiswa",
      "avatar_link": null,
      "status": "active",
      "is_bypass": true,
      "student_profile": {
        "id": 1,
        "nim": "23/512345/SV/22001",
        "is_bypass": true,
        "role_1_id": null,
        "role_2_id": null,
        "team_id": null,
        "portfolio_link": null,
        "created_at": "2026-10-23T03:30:00.000000Z",
        "updated_at": "2026-10-23T03:30:00.000000Z"
      },
      "created_at": "2026-10-23T03:30:00.000000Z",
      "updated_at": "2026-10-23T03:30:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 10,
    "total": 1
  }
}
```

---

#### 4.3.2 POST `/api/v1/stakeholders`
Menambahkan data stakeholder baru (atau memperbarui jika email sudah ada). Mendukung format **Single** atau **Bulk**.

- **Auth Required:** Tidak (atau Admin)

##### Opsi A: Single Stakeholder
- **Request Body (JSON):**
```json
{
  "email": "mahasiswa1@mail.ugm.ac.id",
  "name": "Budi Santoso",
  "role": "mahasiswa",
  "nim": "23/512345/SV/22001",
  "is_bypass": true
}
```
*Catatan:* Kolom `nim` dan `is_bypass` hanya diproses jika `role` dinormalisasi ke `student`.

- **Response Sukses (HTTP 201):**
```json
{
  "status": "success",
  "message": "Data stakeholder berhasil disimpan ke dalam whitelist sistem.",
  "data": {
    "id": 1,
    "name": "Budi Santoso",
    "email": "mahasiswa1@mail.ugm.ac.id",
    "user_role": "student",
    "role": "student",
    "role_label": "Mahasiswa",
    "status": "active",
    "is_bypass": true,
    "student_profile": {
      "id": 1,
      "nim": "23/512345/SV/22001",
      "is_bypass": true
    }
  }
}
```

##### Opsi B: Bulk Stakeholders
- **Request Body (JSON):**
```json
{
  "stakeholders": [
    {
      "email": "mhs1@mail.ugm.ac.id",
      "role": "mahasiswa",
      "name": "Budi Santoso",
      "nim": "23/512345/SV/22001",
      "is_bypass": true
    },
    {
      "email": "asprak@ugm.ac.id",
      "role": "asisten",
      "name": "Asisten Praktikum 1"
    },
    {
      "email": "mentor@tokopedia.com",
      "role": "praktisi",
      "name": "Praktisi Senior"
    }
  ]
}
```
- **Response Sukses (HTTP 201):**
```json
{
  "status": "success",
  "message": "3 stakeholder berhasil didata ke dalam sistem.",
  "data": [
    { "id": 1, "email": "mhs1@mail.ugm.ac.id", "role": "student", "status": "active" },
    { "id": 2, "email": "asprak@ugm.ac.id", "role": "assistant", "status": "active" },
    { "id": 3, "email": "mentor@tokopedia.com", "role": "practitioner", "status": "active" }
  ]
}
```

---

#### 4.3.3 GET `/api/v1/stakeholders/{user}`
Mengambil detail satu data stakeholder berdasarkan ID pengguna (`user.id`).

- **Auth Required:** Tidak (atau Admin)
- **URL Parameter:** `{user}` = Integer User ID (contoh: `/api/v1/stakeholders/1`)
- **Response Sukses (HTTP 200):**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "Budi Santoso",
    "email": "mahasiswa1@mail.ugm.ac.id",
    "user_role": "student",
    "role": "student",
    "role_label": "Mahasiswa",
    "status": "active",
    "is_bypass": true,
    "student_profile": {
      "id": 1,
      "nim": "23/512345/SV/22001",
      "is_bypass": true
    }
  }
}
```
- **Error (HTTP 404):** Jika User ID tidak ditemukan.

---

#### 4.3.4 PUT/PATCH `/api/v1/stakeholders/{user}/role`
Mengubah peran (role) pengguna berdasarkan User ID.

- **Auth Required:** Tidak (atau Admin)
- **URL Parameter:** `{user}` = Integer User ID (contoh: `/api/v1/stakeholders/1/role`)
- **Request Body (JSON):**
```json
{
  "role": "asisten"
}
```
*Jika role diubah menjadi mahasiswa, dapat menyertakan `nim` dan `is_bypass`:*
```json
{
  "role": "mahasiswa",
  "nim": "23/512345/SV/22001",
  "is_bypass": false
}
```
- **Response Sukses (HTTP 200):**
```json
{
  "status": "success",
  "message": "Peran stakeholder berhasil diperbarui menjadi Asisten Praktikum.",
  "data": {
    "id": 1,
    "email": "mahasiswa1@mail.ugm.ac.id",
    "user_role": "assistant",
    "role": "assistant",
    "role_label": "Asisten Praktikum",
    "status": "active"
  }
}
```

---

#### 4.3.5 POST `/api/v1/stakeholders/assign-role`
Mengubah peran (role) pengguna langsung berdasarkan alamat email yang ada di request body.

- **Auth Required:** Tidak (atau Admin)
- **Request Body (JSON):**
```json
{
  "email": "mahasiswa1@mail.ugm.ac.id",
  "role": "praktisi"
}
```
- **Response Sukses (HTTP 200):**
```json
{
  "status": "success",
  "message": "Peran stakeholder berhasil diperbarui menjadi Praktisi Industri.",
  "data": {
    "id": 1,
    "email": "mahasiswa1@mail.ugm.ac.id",
    "user_role": "practitioner",
    "role": "practitioner",
    "role_label": "Praktisi Industri",
    "status": "active"
  }
}
```
- **Kemungkinan Error:**
  - `400 Bad Request`: Email tidak dikirim.
  - `404 Not Found`: Pengguna dengan email tersebut belum terdaftar di sistem.
  - `422 Unprocessable Entity`: Format role tidak valid.

---

#### 4.3.6 DELETE `/api/v1/stakeholders/{user}`
Menghapus stakeholder dari sistem.

- **Auth Required:** Tidak (atau Admin)
- **URL Parameter:** `{user}` = Integer User ID (contoh: `/api/v1/stakeholders/1`)
- **Response Sukses (HTTP 200):**
```json
{
  "status": "success",
  "message": "Stakeholder berhasil dihapus dari sistem."
}
```
- **Error (HTTP 404):** Jika ID tidak ditemukan.

---

## 5. Contoh Integrasi Frontend (Axios Instance & Interceptor)

Disarankan bagi pengembang Frontend untuk membuat satu berkas HTTP Client (misal `src/services/api.js`):

```javascript
import axios from 'axios';

// 1. Buat Axios Instance
const api = axios.create({
  baseURL: process.env.REACT_APP_API_URL || 'http://localhost:8000/api/v1',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// 2. Request Interceptor: Otomatis cantumkan Bearer Token
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

// 3. Response Interceptor: Tangani Token Expired (401)
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;
```

### Contoh Pemakaian dalam Komponen:
```javascript
import api from './services/api';

// Ambil profil user saat ini
export async function fetchMyProfile() {
  const response = await api.get('/auth/me');
  return response.data.data;
}

// Ambil daftar stakeholder mahasiswa dengan filter
export async function getStudents(searchTerm = '', page = 1) {
  const response = await api.get('/stakeholders', {
    params: {
      role: 'mahasiswa',
      search: searchTerm,
      page: page,
      per_page: 15,
    },
  });
  return response.data;
}

// Input data mahasiswa baru
export async function registerStudent(payload) {
  const response = await api.post('/stakeholders', {
    email: payload.email,
    name: payload.name,
    role: 'mahasiswa',
    nim: payload.nim,
    is_bypass: payload.isBypass || false,
  });
  return response.data;
}
```

---

## 6. Panduan Testing & Debugging Backend (BE)

### 6.1 Menjalankan Server & Environment PHP 8.3

Proyek ini membutuhkan **PHP >= 8.3**. Jika menggunakan Laragon pada Windows, gunakan path binary PHP 8.3:

```powershell
# Jalankan development server pada port 8000
& "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" artisan serve --port=8000

# Atau buat alias powershell di terminal:
function php83 { & "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" $args }
php83 artisan serve
```

---

### 6.2 Menjalankan Automated Feature Test (PHPUnit)

Seluruh logika autentikasi dan stakeholder management telah dilengkapi dengan unit/feature test otomatis:
- `tests/Feature/AuthSSOTest.php`
- `tests/Feature/StakeholderManagementTest.php`

#### Menjalankan Semua Test:
```powershell
& "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" vendor/bin/phpunit --testdox
```

#### Menjalankan File Test Tertentu:
```powershell
# Jalankan test autentikasi SSO UGM saja:
& "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" vendor/bin/phpunit tests/Feature/AuthSSOTest.php

# Jalankan test manajemen stakeholder saja:
& "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" vendor/bin/phpunit tests/Feature/StakeholderManagementTest.php
```

#### Menjalankan Method Test Tertentu:
```powershell
& "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" vendor/bin/phpunit --filter=test_can_initiate_single_lecturer_as_full_admin
```

---

### 6.3 Debugging Menggunakan Laravel Tinker

Tinker sangat berguna untuk membuat dummy user, mengecek token, atau memverifikasi whitelist secara instan tanpa perlu mock OAuth.

Jalankan Tinker:
```powershell
& "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" artisan tinker
```

Contoh snippet bermanfaat di dalam Tinker:

#### 1. Mendaftarkan User Whitelist Mahasiswa untuk Uji Coba SSO:
```php
$user = App\Models\User::firstOrCreate(
    ['email' => 'testing.mhs@mail.ugm.ac.id'],
    [
        'name' => 'Testing Mahasiswa',
        'user_role' => App\Enums\UserRole::Student,
        'status' => 'active',
    ]
);
App\Models\StudentProfile::firstOrCreate(
    ['user_id' => $user->id],
    ['nim' => '23/999999/SV/99999', 'is_bypass' => false]
);
```

#### 2. Generate Personal Access Token Sanctum Secara Manual:
```php
$user = App\Models\User::where('email', 'testing.mhs@mail.ugm.ac.id')->first();
$token = $user->createToken('dev-debug-token')->plainTextToken;
echo "Bearer Token: " . $token;
```
*(Token yang dicetak dapat langsung ditempelkan ke Postman / cURL pada header `Authorization: Bearer <token>`)*

#### 3. Cek Jumlah Stakeholder Berdasarkan Role:
```php
App\Models\User::selectRaw('user_role, count(*) as total')->groupBy('user_role')->get();
```

---

### 6.4 Pengujian Manual Menggunakan cURL

Gunakan perintah cURL berikut di PowerShell / Command Prompt untuk pengujian endpoint secara langsung:

#### 1. Dapatkan Google Redirect URL:
```powershell
curl -X GET "http://localhost:8000/api/v1/auth/google/redirect" -H "Accept: application/json"
```

#### 2. Inisiasi Dosen Pengampu Sebagai Admin:
```powershell
curl -X POST "http://localhost:8000/api/v1/admin/initiate-lecturer" `
  -H "Content-Type: application/json" `
  -H "Accept: application/json" `
  -d '{"email":"dosen@ugm.ac.id","name":"Dr. Nama Dosen, M.Kom."}'
```

#### 3. Tambah Mahasiswa ke Whitelist:
```powershell
curl -X POST "http://localhost:8000/api/v1/stakeholders" `
  -H "Content-Type: application/json" `
  -H "Accept: application/json" `
  -d '{"email":"mhs1@mail.ugm.ac.id","name":"Budi Santoso","role":"mahasiswa","nim":"23/512345/SV/22001","is_bypass":true}'
```

#### 4. Lihat Daftar Stakeholder:
```powershell
curl -X GET "http://localhost:8000/api/v1/stakeholders?role=student" -H "Accept: application/json"
```

#### 5. Ambil Profil User yang Sedang Login (`/me`):
```powershell
curl -X GET "http://localhost:8000/api/v1/auth/me" `
  -H "Accept: application/json" `
  -H "Authorization: Bearer <GANTI_DENGAN_TOKEN_SANCTUM>"
```

#### 6. Logout / Cabut Token:
```powershell
curl -X POST "http://localhost:8000/api/v1/auth/logout" `
  -H "Accept: application/json" `
  -H "Authorization: Bearer <GANTI_DENGAN_TOKEN_SANCTUM>"
```

---

### 6.5 Troubleshooting Error yang Sering Ditemui

| Gejala Error | Penyebab Utama | Solusi |
| :--- | :--- | :--- |
| `Composer detected issues in your platform: Your Composer dependencies require a PHP version ">= 8.3.0". You are running 8.2.12.` | Terminal memanggil `php.exe` bawaan PATH lama (PHP 8.2). | Gunakan path lengkap PHP 8.3: `"C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe"` atau ganti PHP version di menu GUI Laragon (Klik Kanan > PHP > Version > 8.3). |
| `403: Akses ditolak, hanya email dengan domain @mail.ugm.ac.id yang diizinkan untuk login.` | User login Google menggunakan akun gmail biasa (`@gmail.com`). | Pastikan login menggunakan akun resmi institusi UGM (`@mail.ugm.ac.id`). |
| `403: Akses ditolak, email tidak terdaftar pada KRS aktif.` | Email berdomain UGM valid, namun belum didaftarkan pada whitelist tabel `users` atau statusnya bukan `active`. | Daftarkan email tersebut terlebih dahulu melalui endpoint `POST /api/v1/stakeholders` atau inisiasi via tinker. |
| `401: Unauthenticated.` | Request ke `/auth/me` atau `/auth/logout` tidak menyertakan header `Authorization: Bearer <token>` atau token sudah dicabut/kadaluarsa. | Periksa apakah token tersimpan di storage client dan header dikirim dengan format `Bearer <token>`. |
| `CORS Error pada Browser FE` | Browser memblokir permintaan lintas domain (misal FE di `localhost:3000` dan BE di `localhost:8000`). | Pastikan `config/cors.php` mengizinkan origin FE atau tambahkan origin `localhost:3000` / `localhost:5173` ke `allowed_origins`. |

---
Dokumentasi ini terus diperbarui seiring penambahan modul baru di PAD-BE. Untuk pertanyaan lebih lanjut, hubungi tim Backend PAD.
