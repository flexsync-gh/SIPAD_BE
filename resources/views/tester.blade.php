<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PAD Backend - API Testing Playground (Modul B - D)</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ugm: {
                            blue: '#003366',
                            gold: '#FFCC00',
                            dark: '#002244'
                        }
                    }
                }
            }
        }
    </script>
    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        pre code { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        .tab-active { border-bottom-color: #003366; color: #003366; font-weight: 700; background-color: #f8fafc; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex flex-col font-sans" x-data="apiTester()" x-init="initApp()" x-cloak>

    <!-- Top Header -->
    @if(isset($oauthCode) && $oauthCode)
    <div class="bg-emerald-50 border-b border-emerald-200 px-4 py-2 text-xs text-emerald-800 text-center font-mono">
        Google OAuth Code terdeteksi: <strong>{{ $oauthCode }}</strong>
    </div>
    @endif
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Brand / Title -->
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-ugm-blue text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        PAD
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-base font-bold text-slate-900 leading-tight">PAD API Tester & Playground</h1>
                            <span class="text-3xs bg-ugm-blue/10 text-ugm-blue font-bold px-2 py-0.5 rounded-full border border-ugm-blue/20">Fase 1 & 2 (Modul B - D)</span>
                        </div>
                        <p class="text-xs text-slate-500">Pengujian Modul B (Pengguna & Tim), Modul C (Konfigurasi & Skill), Modul D (Profiling)</p>
                    </div>
                </div>

                <!-- API Prefix & Active Persona / Token Status -->
                <div class="flex items-center gap-3">
                    <!-- API Prefix Toggle -->
                    <div class="hidden sm:flex items-center bg-slate-100 rounded-lg p-1 border border-slate-200 text-xs">
                        <span class="text-slate-500 px-1.5 font-medium">Prefix:</span>
                        <button type="button" @click="apiPrefix = '/api'" :class="apiPrefix === '/api' ? 'bg-white text-ugm-blue font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'" class="px-2 py-0.5 rounded transition text-2xs">
                            /api
                        </button>
                        <button type="button" @click="apiPrefix = '/api/v1'" :class="apiPrefix === '/api/v1' ? 'bg-white text-ugm-blue font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'" class="px-2 py-0.5 rounded transition text-2xs">
                            /api/v1
                        </button>
                    </div>

                    <!-- Token Status Badge -->
                    <div class="flex items-center gap-2">
                        <template x-if="bearerToken">
                            <div class="flex items-center gap-1.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs px-2.5 py-1.5 rounded-lg font-medium">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="font-mono text-2xs" x-text="activeUser ? activeUser.name + ' (' + activeUser.user_role + ')' : bearerToken.substring(0, 10) + '...'"></span>
                                <button type="button" @click="copyToClipboard(bearerToken, 'Token disalin!')" title="Salin Token" class="text-emerald-700 hover:text-emerald-900 ml-1 text-xs">📋</button>
                                <button type="button" @click="clearToken()" title="Hapus Token" class="text-rose-600 hover:text-rose-800 font-bold ml-1 text-xs">✕</button>
                            </div>
                        </template>
                        <template x-if="!bearerToken">
                            <div class="flex items-center gap-1.5 bg-slate-100 border border-slate-200 text-slate-500 text-xs px-2.5 py-1.5 rounded-lg">
                                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                <span class="text-2xs">No Token</span>
                            </div>
                        </template>

                        <!-- Quick Persona Dropdown Button -->
                        <div class="relative" x-data="{ openPersona: false }">
                            <button type="button" @click="openPersona = !openPersona" class="text-xs bg-slate-800 hover:bg-slate-900 text-white px-3 py-1.5 rounded-lg transition font-medium flex items-center gap-1.5 shadow-xs">
                                <span>👤 Ganti Persona</span>
                                <span class="text-3xs">▼</span>
                            </button>
                            <div x-show="openPersona" @click.away="openPersona = false" class="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-xl border border-slate-200 p-2 z-50 text-xs space-y-1">
                                <div class="px-2 py-1 text-3xs font-bold text-slate-400 uppercase tracking-wider">Pilih Persona untuk Test Token:</div>
                                <button type="button" @click="loginAsPersona('dosen.pengampu@mail.ugm.ac.id', 'lecturer', 'Dr. Dosen Pengampu, M.T.', false); openPersona = false" class="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-purple-50 text-purple-900 flex items-center justify-between">
                                    <span>👨‍🏫 <strong>Dosen Pengampu</strong></span>
                                    <span class="text-3xs bg-purple-100 px-1.5 py-0.5 rounded text-purple-700 font-bold">Lecturer</span>
                                </button>
                                <button type="button" @click="loginAsPersona('admin.master@mail.ugm.ac.id', 'admin', 'Master Admin PAD', false); openPersona = false" class="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-emerald-50 text-emerald-900 flex items-center justify-between">
                                    <span>👑 <strong>Master Admin</strong></span>
                                    <span class="text-3xs bg-emerald-100 px-1.5 py-0.5 rounded text-emerald-700 font-bold">Admin</span>
                                </button>
                                <button type="button" @click="loginAsPersona('mhs.reguler@mail.ugm.ac.id', 'student', 'Mahasiswa Reguler', false); openPersona = false" class="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-sky-50 text-sky-900 flex items-center justify-between">
                                    <span>🎓 <strong>Mahasiswa Reguler</strong></span>
                                    <span class="text-3xs bg-sky-100 px-1.5 py-0.5 rounded text-sky-700 font-bold">KRS Aktif</span>
                                </button>
                                <button type="button" @click="loginAsPersona('mhs.gemastik@mail.ugm.ac.id', 'student', 'Mahasiswa Jalur GEMASTIK', true); openPersona = false" class="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-amber-50 text-amber-900 flex items-center justify-between">
                                    <span>🚀 <strong>Mahasiswa GEMASTIK</strong></span>
                                    <span class="text-3xs bg-amber-100 px-1.5 py-0.5 rounded text-amber-800 font-bold">Bypass</span>
                                </button>
                                <button type="button" @click="loginAsPersona('asisten.lab@mail.ugm.ac.id', 'assistant', 'Asisten Praktikum Lab', false); openPersona = false" class="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-teal-50 text-teal-900 flex items-center justify-between">
                                    <span>🤝 <strong>Asisten Praktikum</strong></span>
                                    <span class="text-3xs bg-teal-100 px-1.5 py-0.5 rounded text-teal-700 font-bold">Assistant</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs for Modul B, C, D -->
            <nav class="flex space-x-2 border-t border-slate-100 overflow-x-auto text-xs py-1" aria-label="Tabs">
                <!-- Modul B -->
                <button type="button" @click="activeTab = 'modul-b'" :class="activeTab === 'modul-b' ? 'tab-active border-b-2 text-ugm-blue' : 'border-b-2 border-transparent text-slate-600 hover:text-slate-900'" class="py-2.5 px-3 whitespace-nowrap rounded-t-lg transition flex items-center gap-1.5">
                    <span>👥</span>
                    <span><strong>Modul B:</strong> Pengguna, Whitelist KRS & Tim Eksklusif</span>
                </button>

                <!-- Modul C -->
                <button type="button" @click="activeTab = 'modul-c'; fetchSystemSettings(); fetchMasterRoles();" :class="activeTab === 'modul-c' ? 'tab-active border-b-2 text-ugm-blue' : 'border-b-2 border-transparent text-slate-600 hover:text-slate-900'" class="py-2.5 px-3 whitespace-nowrap rounded-t-lg transition flex items-center gap-1.5">
                    <span>⚙️</span>
                    <span><strong>Modul C:</strong> Konfigurasi Sistem & Master Data Role/Skill</span>
                </button>

                <!-- Modul D -->
                <button type="button" @click="activeTab = 'modul-d'; fetchProfilingConfig();" :class="activeTab === 'modul-d' ? 'tab-active border-b-2 text-ugm-blue' : 'border-b-2 border-transparent text-slate-600 hover:text-slate-900'" class="py-2.5 px-3 whitespace-nowrap rounded-t-lg transition flex items-center gap-1.5">
                    <span>📝</span>
                    <span><strong>Modul D:</strong> Pendaftaran & Form Profiling Mahasiswa</span>
                </button>

                <!-- Universal Console -->
                <button type="button" @click="activeTab = 'console'" :class="activeTab === 'console' ? 'tab-active border-b-2 text-ugm-blue' : 'border-b-2 border-transparent text-slate-600 hover:text-slate-900'" class="py-2.5 px-3 whitespace-nowrap rounded-t-lg transition flex items-center gap-1.5 text-slate-500">
                    <span>⚡</span>
                    <span>Universal Console</span>
                </button>
            </nav>
        </div>
    </header>

    <!-- Main Content Grid -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex-1 w-full grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left / Primary Area: Feature Test Panel (7 cols on lg) -->
        <section class="lg:col-span-7 space-y-6">

            <!-- ======================================================== -->
            <!-- MODUL B: PENGELOLAAN PENGGUNA, WHITELIST & TIM EKSKLUSIF  -->
            <!-- ======================================================== -->
            <div x-show="activeTab === 'modul-b'" class="space-y-6">

                <!-- Modul B Header Card -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                            <span>👥</span> B. Modul Pengelolaan Pengguna, Whitelist KRS & Tim Eksklusif
                        </h2>
                        <p class="text-xs text-slate-500">Proses 1.0 — Fase 1: Inisiasi dosen, pengelolaan whitelist KRS, import file Excel/CSV, dan pendaftaran tim eksklusif</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="modulBSection = 'users'" :class="modulBSection === 'users' ? 'bg-ugm-blue text-white font-bold' : 'bg-slate-100 text-slate-700'" class="text-2xs px-2.5 py-1.5 rounded-lg transition">
                            Daftar Users
                        </button>
                        <button type="button" @click="modulBSection = 'lecturers'" :class="modulBSection === 'lecturers' ? 'bg-ugm-blue text-white font-bold' : 'bg-slate-100 text-slate-700'" class="text-2xs px-2.5 py-1.5 rounded-lg transition">
                            Inisiasi Dosen
                        </button>
                        <button type="button" @click="modulBSection = 'whitelist'" :class="modulBSection === 'whitelist' ? 'bg-ugm-blue text-white font-bold' : 'bg-slate-100 text-slate-700'" class="text-2xs px-2.5 py-1.5 rounded-lg transition">
                            Input Whitelist
                        </button>
                        <button type="button" @click="modulBSection = 'import'" :class="modulBSection === 'import' ? 'bg-ugm-blue text-white font-bold' : 'bg-slate-100 text-slate-700'" class="text-2xs px-2.5 py-1.5 rounded-lg transition">
                            Import KRS
                        </button>
                        <button type="button" @click="modulBSection = 'exclusive'" :class="modulBSection === 'exclusive' ? 'bg-ugm-blue text-white font-bold' : 'bg-slate-100 text-slate-700'" class="text-2xs px-2.5 py-1.5 rounded-lg transition">
                            Tim Eksklusif
                        </button>
                    </div>
                </div>

                <!-- SUBSECTION B1: DAFTAR USERS (GET /api/users & DELETE /api/users/{id}) -->
                <div x-show="modulBSection === 'users'" class="space-y-4">
                    <!-- Filter & Search -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-800">Endpoint: <code class="text-ugm-blue font-mono font-semibold">GET /api/users</code> & <code class="text-rose-600 font-mono font-semibold">DELETE /api/users/{id}</code></span>
                            <span class="text-3xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-mono">Akses: Dosen, Master Admin</span>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <div class="flex-1 relative">
                                <input type="text" x-model="userFilters.search" @input.debounce.400ms="fetchUsers(1)" placeholder="Cari nama, email, atau NIM..." class="w-full bg-slate-50 border border-slate-300 rounded-lg pl-8 pr-3 py-1.5 text-xs focus:ring-1 focus:ring-ugm-blue focus:outline-none">
                                <span class="absolute left-2.5 top-2 text-slate-400 text-xs">🔍</span>
                            </div>
                            <select x-model="userFilters.user_role" @change="fetchUsers(1)" class="bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                                <option value="">Semua Role</option>
                                <option value="student">Mahasiswa</option>
                                <option value="lecturer">Dosen</option>
                                <option value="admin">Admin</option>
                                <option value="assistant">Asisten</option>
                                <option value="practitioner">Praktisi</option>
                            </select>
                            <select x-model="userFilters.status" @change="fetchUsers(1)" class="bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                                <option value="">Semua Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="archived">Archived</option>
                            </select>
                            <select x-model="userFilters.is_bypass" @change="fetchUsers(1)" class="bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                                <option value="">Semua Jalur</option>
                                <option value="true">Bypass (GEMASTIK)</option>
                                <option value="false">Reguler</option>
                            </select>
                            <button type="button" @click="fetchUsers(usersMeta.current_page)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                🔄 Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Users Table -->
                    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
                        <div class="px-4 py-2.5 border-b border-slate-100 flex items-center justify-between text-xs bg-slate-50 font-medium">
                            <span x-text="'Total: ' + usersMeta.total + ' pengguna terdaftar'"></span>
                            <span x-text="'Hal ' + (usersMeta.current_page || 1) + ' dari ' + (usersMeta.last_page || 1)"></span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="bg-slate-50 text-slate-600 uppercase font-semibold border-b border-slate-200 text-3xs">
                                    <tr>
                                        <th class="py-2.5 px-3">Nama & Email</th>
                                        <th class="py-2.5 px-3">Role</th>
                                        <th class="py-2.5 px-3">Status</th>
                                        <th class="py-2.5 px-3">NIM / Bypass</th>
                                        <th class="py-2.5 px-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-if="loadingUsers">
                                        <tr>
                                            <td colspan="5" class="py-8 text-center text-slate-400">
                                                <div class="inline-block animate-spin mr-1">⏳</div> Memuat daftar pengguna...
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-if="!loadingUsers && usersList.length === 0">
                                        <tr>
                                            <td colspan="5" class="py-8 text-center text-slate-500">
                                                Tidak ada data pengguna ditemukan.
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-for="u in usersList" :key="u.id">
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="py-2 px-3">
                                                <div class="font-bold text-slate-900" x-text="u.name || '(Belum isi nama)'"></div>
                                                <div class="font-mono text-2xs text-slate-500" x-text="u.email"></div>
                                            </td>
                                            <td class="py-2 px-3">
                                                <span :class="getRoleBadgeClass(u.user_role)" class="px-2 py-0.5 rounded font-bold text-3xs uppercase" x-text="u.user_role"></span>
                                            </td>
                                            <td class="py-2 px-3">
                                                <span :class="u.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'" class="px-2 py-0.5 rounded text-3xs font-medium" x-text="u.status"></span>
                                            </td>
                                            <td class="py-2 px-3">
                                                <template x-if="u.student_profile">
                                                    <div>
                                                        <span class="font-mono text-2xs" x-text="u.student_profile.nim || '-'"></span>
                                                        <template x-if="u.student_profile.is_bypass">
                                                            <span class="ml-1 bg-amber-100 text-amber-900 text-3xs px-1.5 py-0.2 rounded font-bold">BYPASS</span>
                                                        </template>
                                                    </div>
                                                </template>
                                                <template x-if="!u.student_profile">
                                                    <span class="text-slate-400 text-3xs">-</span>
                                                </template>
                                            </td>
                                            <td class="py-2 px-3 text-right space-x-1 whitespace-nowrap">
                                                <!-- Switch Token to this user -->
                                                <button type="button" @click="generateDevTokenForUser(u.email)" title="Ganti Token ke user ini" class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded text-3xs font-bold transition">
                                                    🔑 Login As
                                                </button>
                                                <!-- Delete User -->
                                                <button type="button" @click="deleteUser(u)" title="Hapus dari whitelist (DELETE /api/users/{id})" class="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded text-3xs font-bold transition">
                                                    Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination -->
                        <div class="px-4 py-2 border-t border-slate-100 flex items-center justify-between text-xs bg-slate-50">
                            <button type="button" :disabled="usersMeta.current_page <= 1" @click="fetchUsers(usersMeta.current_page - 1)" class="px-2.5 py-1 bg-white border border-slate-300 rounded hover:bg-slate-50 disabled:opacity-40 transition text-2xs">
                                &larr; Prev
                            </button>
                            <span class="text-slate-500 text-2xs" x-text="'Halaman ' + usersMeta.current_page + ' / ' + usersMeta.last_page"></span>
                            <button type="button" :disabled="usersMeta.current_page >= usersMeta.last_page" @click="fetchUsers(usersMeta.current_page + 1)" class="px-2.5 py-1 bg-white border border-slate-300 rounded hover:bg-slate-50 disabled:opacity-40 transition text-2xs">
                                Next &rarr;
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SUBSECTION B2: INISIASI DOSEN (POST /api/admin/lecturers) -->
                <div x-show="modulBSection === 'lecturers'" class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-4">
                    <div class="border-b border-slate-200 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">Inisiasi Dosen Pengampu & Berikan Hak Akses Dosen/Admin</h3>
                            <p class="text-xs text-slate-500">Endpoint: <code class="text-ugm-blue font-mono font-semibold">POST /api/admin/lecturers</code> (Akses: Master Admin)</p>
                        </div>
                        <div class="flex bg-slate-100 p-0.5 rounded-lg text-xs">
                            <button type="button" @click="lecturerFormMode = 'single'" :class="lecturerFormMode === 'single' ? 'bg-white font-bold text-ugm-blue shadow-xs' : 'text-slate-600'" class="px-3 py-1 rounded">Single</button>
                            <button type="button" @click="lecturerFormMode = 'batch'" :class="lecturerFormMode === 'batch' ? 'bg-white font-bold text-ugm-blue shadow-xs' : 'text-slate-600'" class="px-3 py-1 rounded">Batch</button>
                        </div>
                    </div>

                    <!-- Single Form -->
                    <template x-if="lecturerFormMode === 'single'">
                        <form @submit.prevent="submitSingleLecturer()" class="space-y-3 text-xs">
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Email Dosen Pengampu <span class="text-rose-500">*</span></label>
                                <input type="email" x-model="singleLecturer.email" required placeholder="dosen.pengampu@mail.ugm.ac.id" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-medium text-slate-700 mb-1">Nama Lengkap (Opsional)</label>
                                    <input type="text" x-model="singleLecturer.name" placeholder="Dr. Ir. Budi Santoso, M.T." class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                                </div>
                                <div>
                                    <label class="block font-medium text-slate-700 mb-1">Role yang Diberikan</label>
                                    <select x-model="singleLecturer.role" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                                        <option value="admin">Full Admin</option>
                                        <option value="lecturer">Dosen Pengampu</option>
                                    </select>
                                </div>
                            </div>
                            <div class="pt-2 flex justify-end">
                                <button type="submit" :disabled="loadingApi" class="bg-ugm-blue hover:bg-ugm-dark text-white px-4 py-2 rounded-lg font-bold transition text-xs shadow-xs disabled:opacity-50">
                                    Inisiasi Dosen Pengampu (POST)
                                </button>
                            </div>
                        </form>
                    </template>

                    <!-- Batch Form -->
                    <template x-if="lecturerFormMode === 'batch'">
                        <form @submit.prevent="submitBatchLecturers()" class="space-y-3 text-xs">
                            <div class="space-y-2 max-h-56 overflow-y-auto">
                                <template x-for="(l, idx) in batchLecturers" :key="idx">
                                    <div class="flex items-center gap-2 bg-slate-50 p-2 rounded-lg border border-slate-200">
                                        <span class="text-slate-400 font-mono text-2xs w-4" x-text="idx + 1"></span>
                                        <input type="email" x-model="l.email" required placeholder="email.dosen@mail.ugm.ac.id" class="flex-1 bg-white border border-slate-300 rounded px-2.5 py-1 text-xs">
                                        <input type="text" x-model="l.name" placeholder="Nama Lengkap" class="flex-1 bg-white border border-slate-300 rounded px-2.5 py-1 text-xs">
                                        <select x-model="l.role" class="bg-white border border-slate-300 rounded px-2 py-1 text-xs">
                                            <option value="admin">Admin</option>
                                            <option value="lecturer">Dosen</option>
                                        </select>
                                        <button type="button" @click="batchLecturers.splice(idx, 1)" :disabled="batchLecturers.length <= 1" class="text-rose-500 hover:text-rose-700 p-1 disabled:opacity-30">✕</button>
                                    </div>
                                </template>
                            </div>
                            <div class="flex items-center justify-between pt-2">
                                <button type="button" @click="batchLecturers.push({ email: '', name: '', role: 'admin' })" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded text-xs font-medium">
                                    + Tambah Baris
                                </button>
                                <button type="submit" :disabled="loadingApi" class="bg-ugm-blue hover:bg-ugm-dark text-white px-4 py-2 rounded-lg font-bold transition text-xs shadow-xs disabled:opacity-50">
                                    Inisiasi Batch Dosen (POST)
                                </button>
                            </div>
                        </form>
                    </template>
                </div>

                <!-- SUBSECTION B3: INPUT MANUAL WHITELIST (POST /api/users/whitelist) -->
                <div x-show="modulBSection === 'whitelist'" class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-4">
                    <div class="border-b border-slate-200 pb-3">
                        <h3 class="font-bold text-sm text-slate-900">Input Manual Whitelist Stakeholder</h3>
                        <p class="text-xs text-slate-500">Endpoint: <code class="text-ugm-blue font-mono font-semibold">POST /api/users/whitelist</code> (Akses: Dosen)</p>
                    </div>

                    <form @submit.prevent="submitManualWhitelist()" class="space-y-3 text-xs">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Email Stakeholder <span class="text-rose-500">*</span></label>
                                <input type="email" x-model="manualWhitelist.email" required placeholder="target@mail.ugm.ac.id" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue">
                            </div>
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">User Role <span class="text-rose-500">*</span></label>
                                <select x-model="manualWhitelist.user_role" required class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                                    <option value="student">Mahasiswa (KRS Aktif)</option>
                                    <option value="assistant">Asisten Praktikum (Asprak)</option>
                                    <option value="practitioner">Praktisi Industri</option>
                                    <option value="lecturer">Dosen Pengampu</option>
                                    <option value="admin">Full Admin</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Nama Lengkap</label>
                                <input type="text" x-model="manualWhitelist.name" placeholder="Nama lengkap" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                            </div>
                            <div x-show="manualWhitelist.user_role === 'student'">
                                <label class="block font-medium text-slate-700 mb-1">NIM Mahasiswa</label>
                                <input type="text" x-model="manualWhitelist.nim" placeholder="21/123456/TK/12345" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                            </div>
                        </div>

                        <div x-show="manualWhitelist.user_role === 'student'" class="pt-1">
                            <label class="flex items-center gap-2 cursor-pointer bg-slate-50 p-2 rounded-lg border border-slate-200">
                                <input type="checkbox" x-model="manualWhitelist.is_bypass" class="rounded text-ugm-blue">
                                <span class="text-slate-700"><strong>Tandai sebagai Jalur Bypass (GEMASTIK/LIDM)</strong> — Tidak mengikuti pemilihan role ke-2 maupun pengacakan tim reguler</span>
                            </label>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit" :disabled="loadingApi" class="bg-ugm-blue hover:bg-ugm-dark text-white px-5 py-2 rounded-lg font-bold transition text-xs shadow-xs disabled:opacity-50">
                                Simpan ke Whitelist (POST)
                            </button>
                        </div>
                    </form>
                </div>

                <!-- SUBSECTION B4: IMPORT ENGINE CSV/EXCEL (POST /api/users/whitelist/import) -->
                <div x-show="modulBSection === 'import'" class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-4">
                    <div class="border-b border-slate-200 pb-3">
                        <h3 class="font-bold text-sm text-slate-900">Import Engine CSV/Excel KRS (Maatwebsite)</h3>
                        <p class="text-xs text-slate-500">Endpoint: <code class="text-ugm-blue font-mono font-semibold">POST /api/users/whitelist/import</code> (Akses: Dosen, Master Admin)</p>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 p-3 rounded-lg text-xs text-blue-900 space-y-1">
                        <p class="font-semibold">Format file CSV/Excel yang didukung:</p>
                        <p class="text-2xs">Harus memiliki kolom header: <code class="bg-white px-1.5 py-0.5 rounded border border-blue-300">email</code>, <code class="bg-white px-1.5 py-0.5 rounded border border-blue-300">nim</code>, dan <code class="bg-white px-1.5 py-0.5 rounded border border-blue-300">nama</code> (atau name). Engine akan otomatis memasukkan data ke tabel users dan inisiasi student_profiles.</p>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Pilih File CSV / Excel (.xlsx, .xls, .csv)</label>
                            <input type="file" id="krsImportFile" accept=".csv, .xlsx, .xls" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs">
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <button type="button" @click="generateSampleCsvAndUpload()" class="text-ugm-blue hover:underline text-2xs font-semibold">
                                💡 Download / Buat File CSV Contoh Otomatis
                            </button>
                            <button type="button" @click="submitImportWhitelist()" :disabled="loadingApi" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-lg font-bold transition text-xs shadow-xs disabled:opacity-50">
                                Upload & Import Whitelist (POST)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SUBSECTION B5: TIM EKSKLUSIF GEMASTIK / LIDM (POST /api/teams/exclusive) -->
                <div x-show="modulBSection === 'exclusive'" class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-4">
                    <div class="border-b border-slate-200 pb-3">
                        <h3 class="font-bold text-sm text-slate-900">Daftarkan Tim Eksklusif (Jalur GEMASTIK / LIDM Software)</h3>
                        <p class="text-xs text-slate-500">Endpoint: <code class="text-ugm-blue font-mono font-semibold">POST /api/teams/exclusive</code> (Akses: Dosen)</p>
                    </div>

                    <form @submit.prevent="submitExclusiveTeam()" class="space-y-4 text-xs">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Nama Tim Eksklusif <span class="text-rose-500">*</span></label>
                                <input type="text" x-model="exclusiveTeam.team_name" required placeholder="GEMASTIK XVI Software Development Team" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue">
                            </div>
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Nama Proyek</label>
                                <input type="text" x-model="exclusiveTeam.project_name" placeholder="Smart Agro-Vision Monitoring" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                            </div>
                        </div>

                        <!-- Anggota Tim -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="font-bold text-slate-700">Daftar Anggota Tim (Otomatis is_bypass = true & bound to team):</label>
                                <button type="button" @click="exclusiveTeam.members.push({ email: '', name: '', nim: '', role_id: 1 })" class="text-2xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2.5 py-1 rounded">
                                    + Tambah Anggota
                                </button>
                            </div>
                            <div class="space-y-2 max-h-48 overflow-y-auto">
                                <template x-for="(m, mIdx) in exclusiveTeam.members" :key="mIdx">
                                    <div class="grid grid-cols-4 gap-2 bg-slate-50 p-2 rounded-lg border border-slate-200 items-center">
                                        <input type="email" x-model="m.email" required placeholder="email.mhs@mail.ugm.ac.id" class="bg-white border border-slate-300 rounded px-2 py-1 text-xs">
                                        <input type="text" x-model="m.name" placeholder="Nama Mhs" class="bg-white border border-slate-300 rounded px-2 py-1 text-xs">
                                        <input type="text" x-model="m.nim" placeholder="NIM Mhs" class="bg-white border border-slate-300 rounded px-2 py-1 text-xs">
                                        <div class="flex items-center gap-1">
                                            <select x-model="m.role_id" class="bg-white border border-slate-300 rounded px-2 py-1 text-xs flex-1">
                                                <option value="1">PM</option>
                                                <option value="2">UIUX</option>
                                                <option value="3">FE</option>
                                                <option value="4">BE</option>
                                            </select>
                                            <button type="button" @click="exclusiveTeam.members.splice(mIdx, 1)" :disabled="exclusiveTeam.members.length <= 1" class="text-rose-500 hover:text-rose-700 text-xs px-1 disabled:opacity-30">✕</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit" :disabled="loadingApi" class="bg-ugm-blue hover:bg-ugm-dark text-white px-5 py-2 rounded-lg font-bold transition text-xs shadow-xs disabled:opacity-50">
                                Daftarkan Tim Eksklusif (POST)
                            </button>
                        </div>
                    </form>
                </div>

            </div>

            <!-- ======================================================== -->
            <!-- MODUL C: KONFIGURASI SISTEM & MASTER DATA ROLE/SKILL     -->
            <!-- ======================================================== -->
            <div x-show="activeTab === 'modul-c'" class="space-y-6">

                <!-- Modul C Header Card -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                            <span>⚙️</span> C. Modul Konfigurasi Sistem & Master Data Role/Skill
                        </h2>
                        <p class="text-xs text-slate-500">Proses 2.0 — Fase 1: Konfigurasi toggle kewajiban role ke-2, jadwal pendaftaran, serta master data 4 roles & skills</p>
                    </div>
                    <button type="button" @click="fetchSystemSettings(); fetchMasterRoles();" class="text-2xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg font-medium transition">
                        🔄 Muat Ulang Data
                    </button>
                </div>

                <!-- CARD C1: KONFIGURASI SISTEM (GET & PUT /api/settings) -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-5 space-y-4">
                    <div class="border-b border-slate-200 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">1. Konfigurasi Sistem & Pendaftaran</h3>
                            <p class="text-xs text-slate-500">Endpoints: <code class="text-ugm-blue font-mono font-semibold">GET /api/settings</code> & <code class="text-emerald-700 font-mono font-semibold">PUT /api/settings</code></p>
                        </div>
                        <span class="text-3xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-mono">Akses: Dosen</span>
                    </div>

                    <form @submit.prevent="updateSystemSettings()" class="space-y-4 text-xs">
                        <!-- Toggle is_role_2_required -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 flex items-center justify-between">
                            <div>
                                <span class="font-bold text-slate-900 block text-xs">Kewajiban Pilihan Role ke-2 (is_role_2_required)</span>
                                <span class="text-slate-500 text-2xs">Jika diaktifkan (Wajib), seluruh mahasiswa reguler wajib memilih Role ke-2 saat profiling</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span :class="settingsForm.is_role_2_required ? 'text-ugm-blue font-bold' : 'text-slate-400 font-normal'" class="text-xs">
                                    <span x-text="settingsForm.is_role_2_required ? 'WAJIB' : 'OPSIONAL'"></span>
                                </span>
                                <button type="button" @click="settingsForm.is_role_2_required = !settingsForm.is_role_2_required" :class="settingsForm.is_role_2_required ? 'bg-ugm-blue' : 'bg-slate-300'" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none">
                                    <span :class="settingsForm.is_role_2_required ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Other Setting Fields -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Jadwal / Timeline Pendaftaran</label>
                                <input type="text" x-model="settingsForm.registration_timeline" placeholder="Periode Pendaftaran: 1 - 14 November 2026" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue">
                            </div>
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Tahun Ajaran / Semester</label>
                                <input type="text" x-model="settingsForm.academic_year" placeholder="2026/2027 Ganjil" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue">
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Info Syarat & Ketentuan Pendaftaran</label>
                            <textarea x-model="settingsForm.registration_terms" rows="2" placeholder="Syarat pendaftaran..." class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue"></textarea>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit" :disabled="loadingApi" class="bg-ugm-blue hover:bg-ugm-dark text-white px-5 py-2 rounded-lg font-bold transition text-xs shadow-xs disabled:opacity-50">
                                Simpan Perubahan Pengaturan (PUT)
                            </button>
                        </div>
                    </form>
                </div>

                <!-- CARD C2: MASTER DATA ROLES & SKILLSETS (GET /api/roles, POST /api/skills, PUT/DELETE /api/skills/{id}) -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-5 space-y-4">
                    <div class="border-b border-slate-200 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">2. Master Data 4 Roles & Skillsets</h3>
                            <p class="text-xs text-slate-500">Endpoints: <code class="text-ugm-blue font-mono font-semibold">GET /api/roles</code>, <code class="text-emerald-700 font-mono font-semibold">POST /api/skills</code>, <code class="text-amber-700 font-mono font-semibold">PUT /api/skills/{id}</code>, <code class="text-rose-600 font-mono font-semibold">DELETE /api/skills/{id}</code></p>
                        </div>
                        <button type="button" @click="showAddSkillModal = true" class="bg-ugm-blue hover:bg-ugm-dark text-white px-3 py-1.5 rounded-lg text-xs font-bold transition shadow-xs">
                            + Tambah Skill Baru
                        </button>
                    </div>

                    <!-- 4 Roles Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <template x-for="r in masterRoles" :key="r.id">
                            <div class="bg-slate-50 rounded-xl border border-slate-200 p-3.5 space-y-2.5">
                                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-7 h-7 rounded bg-ugm-blue text-white font-mono font-bold flex items-center justify-center text-xs" x-text="r.code"></span>
                                        <div>
                                            <div class="font-bold text-slate-900 text-xs" x-text="r.name"></div>
                                            <div class="text-3xs text-slate-400 font-mono" x-text="'Role ID: ' + r.id"></div>
                                        </div>
                                    </div>
                                    <span class="text-3xs bg-white text-slate-600 px-2 py-0.5 rounded-full border border-slate-200 font-semibold" x-text="(r.skills?.length || 0) + ' skills'"></span>
                                </div>

                                <!-- Skill Items List -->
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="s in r.skills" :key="s.id">
                                        <div class="bg-white border border-slate-200 rounded-md px-2 py-1 text-2xs flex items-center gap-1.5 shadow-2xs group">
                                            <span class="text-slate-700" x-text="s.name"></span>
                                            <!-- Edit Skill Inline -->
                                            <button type="button" @click="openEditSkillModal(s, r.id)" title="Ubah Nama Skill (PUT)" class="text-sky-600 hover:text-sky-800 opacity-60 group-hover:opacity-100 text-3xs font-bold">
                                                ✎
                                            </button>
                                            <!-- Delete Skill -->
                                            <button type="button" @click="deleteSkill(s)" title="Hapus Skill (DELETE)" class="text-rose-500 hover:text-rose-700 opacity-60 group-hover:opacity-100 text-3xs font-bold">
                                                ✕
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

            </div>

            <!-- ======================================================== -->
            <!-- MODUL D: PENDAFTARAN & FORM PROFILING MAHASISWA          -->
            <!-- ======================================================== -->
            <div x-show="activeTab === 'modul-d'" class="space-y-6">

                <!-- Modul D Header Card -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                            <span>📝</span> D. Modul Pendaftaran & Form Profiling Mahasiswa
                        </h2>
                        <p class="text-xs text-slate-500">Proses 3.0 — Fase 2: Pengisian form profiling mahasiswa, pengecekan bypass & kewajiban role 2, auto scoring, dan pengumuman tim</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="modulDSection = 'form'" :class="modulDSection === 'form' ? 'bg-ugm-blue text-white font-bold' : 'bg-slate-100 text-slate-700'" class="text-2xs px-2.5 py-1.5 rounded-lg transition">
                            Form Profiling
                        </button>
                        <button type="button" @click="modulDSection = 'my-team'; fetchStudentMyTeam();" :class="modulDSection === 'my-team' ? 'bg-ugm-blue text-white font-bold' : 'bg-slate-100 text-slate-700'" class="text-2xs px-2.5 py-1.5 rounded-lg transition">
                            Pengumuman Tim Saya
                        </button>
                    </div>
                </div>

                <!-- SUBSECTION D1: FORM PROFILING MAHASISWA (GET /api/student/profiling-config & POST /api/student/profile) -->
                <div x-show="modulDSection === 'form'" class="bg-white rounded-xl border border-slate-200 shadow-xs p-5 space-y-4">
                    <div class="border-b border-slate-200 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">Formulir Profiling & Pemilihan Role Mahasiswa</h3>
                            <p class="text-xs text-slate-500">Endpoints: <code class="text-ugm-blue font-mono font-semibold">GET /api/student/profiling-config</code> & <code class="text-emerald-700 font-mono font-semibold">POST /api/student/profile</code></p>
                        </div>
                        <button type="button" @click="fetchProfilingConfig()" class="text-2xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg font-medium transition">
                            🔄 Cek Profiling Config
                        </button>
                    </div>

                    <!-- Live Config Badges -->
                    <div class="bg-slate-50 border border-slate-200 p-3 rounded-xl flex items-center justify-between text-xs flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-slate-500 text-2xs">Status Mahasiswa:</span>
                            <span :class="profilingConfigData.is_bypass ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-sky-100 text-sky-900 border border-sky-300'" class="px-2 py-0.5 rounded text-3xs font-bold uppercase" x-text="profilingConfigData.is_bypass ? 'Jalur Bypass (GEMASTIK / LIDM)' : 'Mahasiswa Reguler'"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-slate-500 text-2xs">Role ke-2:</span>
                            <span :class="profilingConfigData.is_bypass ? 'bg-slate-200 text-slate-600' : (profilingConfigData.is_role_2_required ? 'bg-rose-100 text-rose-800 border border-rose-300 font-bold' : 'bg-emerald-100 text-emerald-800 border border-emerald-300')" class="px-2 py-0.5 rounded text-3xs" x-text="profilingConfigData.is_bypass ? 'Dinonaktifkan (Null)' : (profilingConfigData.is_role_2_required ? 'Wajib Dipilih' : 'Opsional')"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-slate-500 text-2xs">Status Kunci:</span>
                            <span :class="profilingConfigData.is_locked ? 'bg-rose-100 text-rose-800 font-bold' : 'bg-emerald-100 text-emerald-800 font-medium'" class="px-2 py-0.5 rounded text-3xs" x-text="profilingConfigData.is_locked ? 'Terkunci (Locked)' : 'Terbuka (Editable)'"></span>
                        </div>
                    </div>

                    <form @submit.prevent="submitStudentProfile()" class="space-y-4 text-xs">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <!-- NIM -->
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">NIM Mahasiswa <span class="text-rose-500">*</span></label>
                                <input type="text" x-model="profilingForm.nim" required placeholder="21/473829/SV/19283" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue">
                            </div>

                            <!-- Role 1 -->
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Pilihan Role ke-1 <span class="text-rose-500">*</span></label>
                                <select x-model="profilingForm.role_1_id" required class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue font-bold text-ugm-blue">
                                    <option value="">-- Pilih Role 1 --</option>
                                    <template x-for="r in masterRoles" :key="r.id">
                                        <option :value="r.id" x-text="r.code + ' - ' + r.name"></option>
                                    </template>
                                </select>
                            </div>

                            <!-- Role 2 -->
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">
                                    Pilihan Role ke-2
                                    <span x-show="!profilingConfigData.is_bypass && profilingConfigData.is_role_2_required" class="text-rose-500">* (Wajib)</span>
                                    <span x-show="profilingConfigData.is_bypass" class="text-slate-400 font-normal">(Dinonaktifkan)</span>
                                </label>
                                <select x-model="profilingForm.role_2_id" :disabled="profilingConfigData.is_bypass" :required="!profilingConfigData.is_bypass && profilingConfigData.is_role_2_required" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue disabled:opacity-50 disabled:bg-slate-200">
                                    <option value="">-- Pilih Role 2 (Opsional / Wajib) --</option>
                                    <template x-for="r in masterRoles" :key="r.id">
                                        <option :value="r.id" x-text="r.code + ' - ' + r.name"></option>
                                    </template>
                                </select>
                                <div x-show="profilingForm.role_1_id && profilingForm.role_2_id && profilingForm.role_1_id == profilingForm.role_2_id" class="text-rose-600 text-3xs font-bold mt-1">
                                    ⚠️ Role 1 dan Role 2 tidak boleh sama!
                                </div>
                            </div>
                        </div>

                        <!-- Portfolio Link -->
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Tautan Portofolio (GitHub, Behance, Figma, Drive, dsb.)</label>
                            <input type="url" x-model="profilingForm.portfolio_link" placeholder="https://github.com/username atau https://behance.net/..." class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue">
                        </div>

                        <!-- Skills Checkboxes grouped by Role -->
                        <div>
                            <label class="block font-bold text-slate-800 mb-2">Checklist Keahlian / Skillset yang Dikuasai:</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-64 overflow-y-auto pr-1">
                                <template x-for="r in masterRoles" :key="r.id">
                                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-1.5">
                                        <div class="font-bold text-2xs text-slate-700 flex items-center justify-between border-b border-slate-200 pb-1">
                                            <span x-text="r.code + ' - ' + r.name"></span>
                                        </div>
                                        <div class="space-y-1">
                                            <template x-for="s in r.skills" :key="s.id">
                                                <label class="flex items-center gap-2 cursor-pointer hover:bg-white p-1 rounded transition text-2xs">
                                                    <input type="checkbox" :value="s.id" x-model="profilingForm.skill_ids" class="rounded text-ugm-blue focus:ring-0">
                                                    <span class="text-slate-800" x-text="s.name"></span>
                                                </label>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="pt-2 flex items-center justify-between border-t border-slate-100">
                            <span class="text-2xs text-slate-500" x-text="profilingForm.skill_ids.length + ' skill dipilih'"></span>
                            <button type="submit" :disabled="loadingApi || profilingConfigData.is_locked" class="bg-ugm-blue hover:bg-ugm-dark text-white px-5 py-2 rounded-lg font-bold transition text-xs shadow-xs disabled:opacity-50">
                                Simpan Profiling & Hitung Skor (POST)
                            </button>
                        </div>
                    </form>
                </div>

                <!-- SUBSECTION D2: PENGUMUMAN TIM SAYA (GET /api/student/my-team) -->
                <div x-show="modulDSection === 'my-team'" class="bg-white rounded-xl border border-slate-200 shadow-xs p-5 space-y-4">
                    <div class="border-b border-slate-200 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">Pengumuman Posisi Final & Kelompok Mahasiswa</h3>
                            <p class="text-xs text-slate-500">Endpoint: <code class="text-ugm-blue font-mono font-semibold">GET /api/student/my-team</code> (Akses: Mahasiswa)</p>
                        </div>
                        <button type="button" @click="fetchStudentMyTeam()" class="text-2xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg font-medium transition">
                            🔄 Refresh Status Tim
                        </button>
                    </div>

                    <template x-if="!myTeamData.has_team">
                        <div class="bg-amber-50 border border-amber-200 p-4 rounded-xl text-xs text-amber-900 space-y-2">
                            <div class="font-bold flex items-center gap-1.5">
                                <span>📢</span> Belum Terdaftar dalam Tim / Pengumuman Belum Dirilis
                            </div>
                            <p>Mahasiswa saat ini belum memiliki kelompok atau dosen pengampu belum menjalankan/mengunci hasil pengelompokan tim.</p>
                            <template x-if="myTeamData.final_role">
                                <div class="mt-2 pt-2 border-t border-amber-200 flex items-center gap-2">
                                    <span class="font-semibold">Rekomendasi / Posisi Sementara:</span>
                                    <span class="bg-amber-200 text-amber-900 px-2 py-0.5 rounded font-mono font-bold" x-text="myTeamData.final_role.code + ' (' + myTeamData.final_role.name + ')'"></span>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="myTeamData.has_team && myTeamData.team">
                        <div class="space-y-4 text-xs">
                            <!-- Team Banner -->
                            <div class="bg-slate-900 text-white p-4 rounded-xl space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-3xs uppercase tracking-wider text-amber-400 font-bold" x-text="'Kelompok #' + myTeamData.team.id + ' (' + myTeamData.team.team_type + ')'"></span>
                                    <span class="bg-emerald-500 text-white px-2 py-0.5 rounded text-3xs font-bold" x-text="myTeamData.final_role?.code || 'Anggota'"></span>
                                </div>
                                <h4 class="text-base font-bold text-white" x-text="myTeamData.team.project?.project_name || myTeamData.team.team_name || 'Tim Proyek PAD'"></h4>
                                <div class="text-slate-300 text-2xs flex items-center gap-4">
                                    <span>Pembimbing (Supervisor): <strong class="text-white" x-text="myTeamData.team.supervisor?.name || 'Belum ditentukan'"></strong></span>
                                </div>
                            </div>

                            <!-- Teammates -->
                            <div>
                                <h5 class="font-bold text-slate-800 mb-2">Daftar Anggota Kelompok:</h5>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <template x-for="mem in (myTeamData.team.members || [])" :key="mem.id">
                                        <div class="bg-slate-50 border border-slate-200 p-2.5 rounded-lg flex items-center justify-between">
                                            <div>
                                                <div class="font-bold text-slate-900" x-text="mem.name"></div>
                                                <div class="text-3xs text-slate-500 font-mono" x-text="mem.email"></div>
                                            </div>
                                            <span class="bg-ugm-blue text-white px-2 py-0.5 rounded text-3xs font-mono font-bold" x-text="mem.final_role?.code || mem.role || '-'"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

            </div>

            <!-- ======================================================== -->
            <!-- UNIVERSAL API CONSOLE TAB                                -->
            <!-- ======================================================== -->
            <div x-show="activeTab === 'console'" class="bg-white rounded-xl border border-slate-200 shadow-xs p-5 space-y-4">
                <div class="border-b border-slate-200 pb-3 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">Universal API Playground</h3>
                        <p class="text-xs text-slate-500">Kirim custom HTTP request ke endpoint manapun</p>
                    </div>
                </div>

                <form @submit.prevent="executeConsoleRequest()" class="space-y-3 text-xs">
                    <div class="flex gap-2">
                        <select x-model="consoleReq.method" class="w-28 bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 font-bold text-xs focus:ring-1 focus:ring-ugm-blue">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                            <option value="PATCH">PATCH</option>
                            <option value="DELETE">DELETE</option>
                        </select>
                        <input type="text" x-model="consoleReq.path" placeholder="/users" class="flex-1 bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 font-mono text-xs focus:ring-1 focus:ring-ugm-blue">
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="flex items-center gap-1.5 cursor-pointer text-slate-700">
                            <input type="checkbox" x-model="consoleReq.useAuth" class="rounded text-ugm-blue">
                            <span>Sertakan Bearer Token</span>
                        </label>
                    </div>

                    <div x-show="['POST', 'PUT', 'PATCH', 'DELETE'].includes(consoleReq.method)">
                        <label class="block font-medium text-slate-700 mb-1">Request Body (JSON):</label>
                        <textarea x-model="consoleReq.body" rows="5" placeholder="{&#10;  &#34;key&#34;: &#34;value&#34;&#10;}" class="w-full bg-slate-900 text-emerald-400 font-mono text-xs p-3 rounded-lg border border-slate-800 focus:outline-none"></textarea>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" :disabled="loadingApi" class="bg-ugm-blue hover:bg-ugm-dark text-white px-5 py-2 rounded-lg font-bold transition text-xs shadow-xs disabled:opacity-50">
                            Kirim Request (Execute)
                        </button>
                    </div>
                </form>
            </div>

        </section>

        <!-- Right / Secondary Area: Live Response & Logs Inspector (5 cols on lg) -->
        <section class="lg:col-span-5 space-y-6">

            <!-- Active Response Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden sticky top-24">
                <div class="px-4 py-3 bg-slate-900 text-white flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-xs uppercase tracking-wider text-slate-300">Live API Response</span>
                        <template x-if="lastResponse.status">
                            <span :class="getStatusBadgeClass(lastResponse.status)" class="px-2 py-0.5 rounded text-2xs font-bold font-mono" x-text="lastResponse.status + ' ' + (lastResponse.statusText || '')"></span>
                        </template>
                    </div>
                    <div class="flex items-center gap-2">
                        <template x-if="lastResponse.duration">
                            <span class="text-2xs font-mono text-slate-400" x-text="lastResponse.duration + ' ms'"></span>
                        </template>
                        <template x-if="lastResponse.data">
                            <button type="button" @click="copyToClipboard(JSON.stringify(lastResponse.data, null, 2), 'JSON disalin!')" title="Salin JSON" class="text-xs text-slate-400 hover:text-white transition">
                                📋 Salin
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Last Request Details Pill -->
                <div class="px-4 py-2 bg-slate-800 border-t border-slate-700 text-2xs font-mono text-slate-300 flex items-center justify-between">
                    <div class="truncate mr-2">
                        <span class="font-bold text-amber-400" x-text="lastResponse.method || 'IDLE'"></span>
                        <span class="text-slate-400" x-text="lastResponse.url || '-'"></span>
                    </div>
                    <span class="text-slate-500" x-text="lastResponse.timestamp || ''"></span>
                </div>

                <!-- Body / JSON Display -->
                <div class="p-3 bg-slate-950 max-h-[500px] overflow-y-auto">
                    <template x-if="loadingApi">
                        <div class="py-12 text-center text-slate-400 text-xs font-mono">
                            <div class="animate-spin text-lg mb-2">⏳</div>
                            Mengirim request ke API...
                        </div>
                    </template>
                    <template x-if="!loadingApi && !lastResponse.data">
                        <div class="py-12 text-center text-slate-500 text-xs">
                            Belum ada request yang dieksekusi.<br>Pilih salah satu endpoint atau ganti persona untuk mengetes.
                        </div>
                    </template>
                    <template x-if="!loadingApi && lastResponse.data">
                        <pre class="text-emerald-400 font-mono text-2xs leading-relaxed overflow-x-auto"><code x-text="JSON.stringify(lastResponse.data, null, 2)"></code></pre>
                    </template>
                </div>

                <!-- Response History Drawer -->
                <div class="p-3 bg-white border-t border-slate-200">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-2xs font-bold text-slate-600 uppercase">Riwayat Panggilan (Last 5):</span>
                        <button type="button" @click="requestHistory = []" class="text-3xs text-rose-600 hover:underline">Clear History</button>
                    </div>
                    <div class="space-y-1 max-h-36 overflow-y-auto">
                        <template x-if="requestHistory.length === 0">
                            <div class="text-slate-400 text-3xs py-1">Belum ada riwayat.</div>
                        </template>
                        <template x-for="(hist, idx) in requestHistory" :key="idx">
                            <div class="flex items-center justify-between text-2xs p-1.5 rounded hover:bg-slate-50 border border-transparent hover:border-slate-200 cursor-pointer font-mono" @click="lastResponse = hist">
                                <div class="flex items-center gap-1.5 truncate">
                                    <span :class="getStatusBadgeClass(hist.status)" class="px-1 py-0.2 rounded text-3xs font-bold" x-text="hist.status"></span>
                                    <span class="font-bold text-slate-700" x-text="hist.method"></span>
                                    <span class="text-slate-500 truncate" x-text="hist.url"></span>
                                </div>
                                <span class="text-3xs text-slate-400 ml-2" x-text="hist.duration + 'ms'"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

        </section>

    </main>

    <!-- ======================================================== -->
    <!-- MODALS: Add Skill & Edit Skill                           -->
    <!-- ======================================================== -->

    <!-- Modal: Tambah Skill (POST /api/skills) -->
    <div x-show="showAddSkillModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden" @click.away="showAddSkillModal = false">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Tambah Item Skillset Baru</h3>
                    <p class="text-xs text-slate-500 font-mono">POST /api/skills</p>
                </div>
                <button type="button" @click="showAddSkillModal = false" class="text-slate-400 hover:text-slate-700 text-base">✕</button>
            </div>

            <form @submit.prevent="submitAddSkill()" class="p-5 space-y-3 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Pilih Role <span class="text-rose-500">*</span></label>
                    <select x-model="newSkill.role_id" required class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                        <template x-for="r in masterRoles" :key="r.id">
                            <option :value="r.id" x-text="r.code + ' - ' + r.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Nama Skillset <span class="text-rose-500">*</span></label>
                    <input type="text" x-model="newSkill.name" required placeholder="contoh: Docker & Containerization" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                </div>
                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="showAddSkillModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium transition">
                        Batal
                    </button>
                    <button type="submit" :disabled="loadingApi" class="px-4 py-2 bg-ugm-blue hover:bg-ugm-dark text-white rounded-lg font-bold transition shadow-xs disabled:opacity-50">
                        Tambah Skill (POST)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Skill (PUT /api/skills/{id}) -->
    <div x-show="showEditSkillModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden" @click.away="showEditSkillModal = false">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Edit Item Skillset</h3>
                    <p class="text-xs text-slate-500 font-mono" x-text="'PUT /api/skills/' + editSkillData.id"></p>
                </div>
                <button type="button" @click="showEditSkillModal = false" class="text-slate-400 hover:text-slate-700 text-base">✕</button>
            </div>

            <form @submit.prevent="submitEditSkill()" class="p-5 space-y-3 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Role</label>
                    <select x-model="editSkillData.role_id" required class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                        <template x-for="r in masterRoles" :key="r.id">
                            <option :value="r.id" x-text="r.code + ' - ' + r.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Nama Skillset <span class="text-rose-500">*</span></label>
                    <input type="text" x-model="editSkillData.name" required class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                </div>
                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="showEditSkillModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium transition">
                        Batal
                    </button>
                    <button type="submit" :disabled="loadingApi" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg font-bold transition shadow-xs disabled:opacity-50">
                        Update Skill (PUT)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notification Toast -->
    <div x-show="toast.show" x-transition class="fixed bottom-5 right-5 z-50 bg-slate-900 text-white text-xs px-4 py-2.5 rounded-xl shadow-lg border border-slate-700 flex items-center gap-2">
        <span x-text="toast.message"></span>
    </div>

    <!-- Main Alpine.js Logic -->
    <script>
        function apiTester() {
            return {
                apiPrefix: '/api',
                activeTab: 'modul-b',
                bearerToken: localStorage.getItem('pad_test_bearer_token') || '',
                activeUser: null,
                loadingApi: false,

                // Toast
                toast: { show: false, message: '' },
                showToast(msg) {
                    this.toast.message = msg;
                    this.toast.show = true;
                    setTimeout(() => { this.toast.show = false; }, 3500);
                },

                // Last Response / Inspector
                lastResponse: {
                    status: null,
                    statusText: '',
                    duration: null,
                    method: '',
                    url: '',
                    timestamp: '',
                    data: null,
                },
                requestHistory: [],

                // MODUL B STATE
                modulBSection: 'users',
                usersList: [],
                usersMeta: { current_page: 1, last_page: 1, total: 0, per_page: 15 },
                userFilters: { search: '', user_role: '', status: '', is_bypass: '', per_page: 15 },
                loadingUsers: false,

                // B2: Inisiasi Dosen
                lecturerFormMode: 'single',
                singleLecturer: { email: '', name: '', role: 'admin' },
                batchLecturers: [
                    { email: 'dosen.koordinator@mail.ugm.ac.id', name: 'Dr. Dosen Koordinator', role: 'admin' },
                    { email: 'dosen.anggota@mail.ugm.ac.id', name: 'Ir. Dosen Anggota, Ph.D.', role: 'lecturer' }
                ],

                // B3: Manual Whitelist
                manualWhitelist: { email: '', user_role: 'student', name: '', nim: '', is_bypass: false },

                // B5: Exclusive Team
                exclusiveTeam: {
                    team_name: 'GEMASTIK Software Team ' + Math.floor(Math.random() * 900 + 100),
                    project_name: 'Smart Vision Platform',
                    members: [
                        { email: 'mhs1.gemastik@mail.ugm.ac.id', name: 'Budi Leader', nim: '21/470001/SV/19001', role_id: 1 },
                        { email: 'mhs2.gemastik@mail.ugm.ac.id', name: 'Siti UIUX', nim: '21/470002/SV/19002', role_id: 2 }
                    ]
                },

                // MODUL C STATE
                settingsForm: {
                    is_role_2_required: false,
                    registration_timeline: '',
                    registration_terms: '',
                    registration_status: 'open',
                    academic_year: '2026/2027'
                },
                masterRoles: [],
                showAddSkillModal: false,
                newSkill: { role_id: 1, name: '' },
                showEditSkillModal: false,
                editSkillData: { id: null, role_id: 1, name: '' },

                // MODUL D STATE
                modulDSection: 'form',
                profilingConfigData: { is_bypass: false, is_role_2_required: false, is_locked: false },
                profilingForm: {
                    nim: '',
                    role_1_id: '',
                    role_2_id: '',
                    portfolio_link: '',
                    skill_ids: []
                },
                myTeamData: { has_team: false, final_role: null, team: null },

                // Console
                consoleReq: { method: 'GET', path: '/users', useAuth: true, body: '' },

                initApp() {
                    if (this.bearerToken) {
                        this.fetchActiveProfile();
                    } else {
                        // Auto-login as default Dosen for smooth experience
                        this.loginAsPersona('dosen.pengampu@mail.ugm.ac.id', 'lecturer', 'Dr. Dosen Pengampu, M.T.', false);
                    }
                    this.fetchUsers(1);
                    this.fetchSystemSettings();
                    this.fetchMasterRoles();
                },

                // Headers
                getHeaders(includeAuth = true) {
                    const headers = {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    };
                    if (includeAuth && this.bearerToken) {
                        headers['Authorization'] = 'Bearer ' + this.bearerToken;
                    }
                    return headers;
                },

                // Base Make Request
                async makeRequest(method, endpoint, body = null, isFormData = false) {
                    this.loadingApi = true;
                    const startTime = performance.now();
                    const fullUrl = endpoint.startsWith('http') ? endpoint : (endpoint.startsWith('/api') ? endpoint : this.apiPrefix + (endpoint.startsWith('/') ? endpoint : '/' + endpoint));

                    try {
                        const headers = isFormData ? {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            ...(this.bearerToken ? { 'Authorization': 'Bearer ' + this.bearerToken } : {})
                        } : this.getHeaders(true);

                        const options = {
                            method: method.toUpperCase(),
                            headers: headers
                        };

                        if (body && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
                            options.body = isFormData ? body : (typeof body === 'string' ? body : JSON.stringify(body));
                        }

                        const res = await fetch(fullUrl, options);
                        const endTime = performance.now();
                        const duration = Math.round(endTime - startTime);

                        let data = null;
                        const contentType = res.headers.get('content-type');
                        if (contentType && contentType.includes('application/json')) {
                            data = await res.json();
                        } else {
                            data = { raw: await res.text() };
                        }

                        this.lastResponse = {
                            status: res.status,
                            statusText: res.statusText,
                            duration: duration,
                            method: method.toUpperCase(),
                            url: fullUrl,
                            timestamp: new Date().toLocaleTimeString(),
                            data: data
                        };

                        this.requestHistory.unshift({ ...this.lastResponse });
                        if (this.requestHistory.length > 10) this.requestHistory.pop();

                        return { res, data };
                    } catch (err) {
                        const endTime = performance.now();
                        this.lastResponse = {
                            status: 0,
                            statusText: 'Client Error',
                            duration: Math.round(endTime - startTime),
                            method: method.toUpperCase(),
                            url: fullUrl,
                            timestamp: new Date().toLocaleTimeString(),
                            data: { error: err.message }
                        };
                        return { res: null, data: { error: err.message } };
                    } finally {
                        this.loadingApi = false;
                    }
                },

                // Persona switcher
                async loginAsPersona(email, role, name, isBypass = false) {
                    try {
                        const res = await fetch('/dev/generate-token', {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                email: email,
                                user_role: role,
                                name: name,
                                is_bypass: isBypass,
                                create_if_missing: true
                            })
                        });
                        const data = await res.json();
                        if (res.ok && data?.token) {
                            this.setToken(data.token);
                            this.activeUser = data.user;
                            this.showToast('Login sebagai ' + name + ' (' + role + ')');
                            if (this.activeTab === 'modul-d') {
                                this.fetchProfilingConfig();
                            }
                        }
                    } catch (e) {
                        this.showToast('Error persona: ' + e.message);
                    }
                },

                async fetchActiveProfile() {
                    const { res, data } = await this.makeRequest('GET', '/api/auth/me');
                    if (res && res.ok && data?.data) {
                        this.activeUser = data.data;
                    }
                },

                setToken(t) {
                    this.bearerToken = t;
                    localStorage.setItem('pad_test_bearer_token', t);
                },

                clearToken() {
                    this.bearerToken = '';
                    this.activeUser = null;
                    localStorage.removeItem('pad_test_bearer_token');
                    this.showToast('Token dibersihkan.');
                },

                copyToClipboard(text, msg) {
                    navigator.clipboard.writeText(text).then(() => this.showToast(msg));
                },

                // ==========================================
                // MODUL B FUNCTIONS
                // ==========================================
                async fetchUsers(page = 1) {
                    this.loadingUsers = true;
                    let url = this.apiPrefix + '/users?page=' + page;
                    if (this.userFilters.search) url += '&search=' + encodeURIComponent(this.userFilters.search);
                    if (this.userFilters.user_role) url += '&user_role=' + encodeURIComponent(this.userFilters.user_role);
                    if (this.userFilters.status) url += '&status=' + encodeURIComponent(this.userFilters.status);
                    if (this.userFilters.is_bypass) url += '&is_bypass=' + encodeURIComponent(this.userFilters.is_bypass);
                    if (this.userFilters.per_page) url += '&per_page=' + this.userFilters.per_page;

                    const { res, data } = await this.makeRequest('GET', url);
                    this.loadingUsers = false;
                    if (res && res.ok && data) {
                        this.usersList = data.data || [];
                        this.usersMeta = data.meta || { current_page: 1, last_page: 1, total: this.usersList.length };
                    }
                },

                async deleteUser(user) {
                    if (!confirm(`Hapus pengguna "${user.name || user.email}" dari whitelist?`)) return;

                    const { res, data } = await this.makeRequest('DELETE', this.apiPrefix + '/users/' + user.id);
                    if (res && res.ok) {
                        this.showToast('Pengguna berhasil dihapus dari whitelist!');
                        this.fetchUsers(this.usersMeta.current_page);
                    } else {
                        this.showToast('Gagal hapus: ' + (data?.message || 'Error'));
                    }
                },

                async generateDevTokenForUser(email) {
                    await this.loginAsPersona(email, 'student', email);
                },

                async submitSingleLecturer() {
                    const { res, data } = await this.makeRequest('POST', this.apiPrefix + '/admin/lecturers', this.singleLecturer);
                    if (res && (res.status === 201 || res.status === 200)) {
                        this.showToast('Dosen pengampu berhasil diinisiasi!');
                        this.singleLecturer = { email: '', name: '', role: 'admin' };
                        this.fetchUsers(1);
                    } else {
                        this.showToast('Error: ' + (data?.message || 'Gagal inisiasi'));
                    }
                },

                async submitBatchLecturers() {
                    const payload = { lecturers: this.batchLecturers };
                    const { res, data } = await this.makeRequest('POST', this.apiPrefix + '/admin/lecturers', payload);
                    if (res && (res.status === 201 || res.status === 200)) {
                        this.showToast('Batch dosen pengampu berhasil diinisiasi!');
                        this.fetchUsers(1);
                    } else {
                        this.showToast('Error: ' + (data?.message || 'Gagal inisiasi batch'));
                    }
                },

                async submitManualWhitelist() {
                    const payload = { ...this.manualWhitelist };
                    if (payload.user_role !== 'student') {
                        delete payload.nim;
                        delete payload.is_bypass;
                    }
                    const { res, data } = await this.makeRequest('POST', this.apiPrefix + '/users/whitelist', payload);
                    if (res && (res.status === 201 || res.status === 200)) {
                        this.showToast('Pengguna berhasil didaftarkan ke whitelist!');
                        this.manualWhitelist = { email: '', user_role: 'student', name: '', nim: '', is_bypass: false };
                        this.fetchUsers(1);
                    } else {
                        this.showToast('Error: ' + (data?.message || 'Gagal whitelist'));
                    }
                },

                generateSampleCsvAndUpload() {
                    const rnd = Math.floor(Math.random() * 800 + 100);
                    const csvContent = "email,nim,name\n" +
                        `mhs.krs${rnd}_1@mail.ugm.ac.id,21/${rnd}001/SV/19001,Mhs KRS ${rnd} A\n` +
                        `mhs.krs${rnd}_2@mail.ugm.ac.id,21/${rnd}002/SV/19002,Mhs KRS ${rnd} B\n`;

                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const file = new File([blob], `krs_sample_${rnd}.csv`, { type: 'text/csv' });

                    const dt = new DataTransfer();
                    dt.items.add(file);
                    document.getElementById('krsImportFile').files = dt.files;
                    this.showToast('File contoh "krs_sample_' + rnd + '.csv" siap di-import!');
                },

                async submitImportWhitelist() {
                    const fileInput = document.getElementById('krsImportFile');
                    if (!fileInput.files || fileInput.files.length === 0) {
                        this.showToast('Pilih file CSV atau Excel terlebih dahulu!');
                        return;
                    }
                    const formData = new FormData();
                    formData.append('file', fileInput.files[0]);

                    const { res, data } = await this.makeRequest('POST', this.apiPrefix + '/users/whitelist/import', formData, true);
                    if (res && (res.status === 201 || res.status === 200)) {
                        this.showToast('Import berhasil! ' + (data?.message || ''));
                        fileInput.value = '';
                        this.fetchUsers(1);
                    } else {
                        this.showToast('Gagal import: ' + (data?.message || 'Error'));
                    }
                },

                async submitExclusiveTeam() {
                    const { res, data } = await this.makeRequest('POST', this.apiPrefix + '/teams/exclusive', this.exclusiveTeam);
                    if (res && (res.status === 201 || res.status === 200)) {
                        this.showToast('Tim eksklusif GEMASTIK berhasil didaftarkan!');
                        this.fetchUsers(1);
                    } else {
                        this.showToast('Error tim eksklusif: ' + (data?.message || 'Gagal'));
                    }
                },

                // ==========================================
                // MODUL C FUNCTIONS
                // ==========================================
                async fetchSystemSettings() {
                    const { res, data } = await this.makeRequest('GET', this.apiPrefix + '/settings');
                    if (res && res.ok && data?.data) {
                        this.settingsForm = {
                            is_role_2_required: data.data.is_role_2_required,
                            registration_timeline: data.data.registration_timeline || '',
                            registration_terms: data.data.registration_terms || '',
                            registration_status: data.data.registration_status || 'open',
                            academic_year: data.data.academic_year || '2026/2027'
                        };
                    }
                },

                async updateSystemSettings() {
                    const { res, data } = await this.makeRequest('PUT', this.apiPrefix + '/settings', this.settingsForm);
                    if (res && res.ok) {
                        this.showToast('Pengaturan sistem berhasil diperbarui!');
                        this.fetchSystemSettings();
                    } else {
                        this.showToast('Gagal update settings: ' + (data?.message || 'Error'));
                    }
                },

                async fetchMasterRoles() {
                    const { res, data } = await this.makeRequest('GET', this.apiPrefix + '/roles');
                    if (res && res.ok && data?.data) {
                        this.masterRoles = data.data;
                    }
                },

                async submitAddSkill() {
                    const { res, data } = await this.makeRequest('POST', this.apiPrefix + '/skills', this.newSkill);
                    if (res && (res.status === 201 || res.status === 200)) {
                        this.showToast('Skill baru berhasil ditambahkan!');
                        this.showAddSkillModal = false;
                        this.newSkill.name = '';
                        this.fetchMasterRoles();
                    } else {
                        this.showToast('Gagal tambah skill: ' + (data?.message || 'Error'));
                    }
                },

                openEditSkillModal(skill, roleId) {
                    this.editSkillData = { id: skill.id, role_id: roleId, name: skill.name };
                    this.showEditSkillModal = true;
                },

                async submitEditSkill() {
                    const { res, data } = await this.makeRequest('PUT', this.apiPrefix + '/skills/' + this.editSkillData.id, {
                        name: this.editSkillData.name,
                        role_id: this.editSkillData.role_id
                    });
                    if (res && res.ok) {
                        this.showToast('Skill berhasil diperbarui!');
                        this.showEditSkillModal = false;
                        this.fetchMasterRoles();
                    } else {
                        this.showToast('Gagal update skill: ' + (data?.message || 'Error'));
                    }
                },

                async deleteSkill(skill) {
                    if (!confirm(`Hapus skill "${skill.name}"?`)) return;

                    const { res, data } = await this.makeRequest('DELETE', this.apiPrefix + '/skills/' + skill.id);
                    if (res && res.ok) {
                        this.showToast('Skill berhasil dihapus!');
                        this.fetchMasterRoles();
                    } else {
                        this.showToast('Gagal hapus: ' + (data?.message || 'Error'));
                    }
                },

                // ==========================================
                // MODUL D FUNCTIONS
                // ==========================================
                async fetchProfilingConfig() {
                    const { res, data } = await this.makeRequest('GET', this.apiPrefix + '/student/profiling-config');
                    if (res && res.ok && data?.data) {
                        this.profilingConfigData = data.data.config || {};
                        if (data.data.roles) {
                            this.masterRoles = data.data.roles;
                        }
                        if (data.data.current_profile) {
                            const cp = data.data.current_profile;
                            this.profilingForm.nim = cp.nim || this.profilingForm.nim;
                            this.profilingForm.role_1_id = cp.role_1_id || '';
                            this.profilingForm.role_2_id = cp.role_2_id || '';
                            this.profilingForm.portfolio_link = cp.portfolio_link || '';
                            this.profilingForm.skill_ids = cp.selected_skill_ids || [];
                        }
                    }
                },

                async submitStudentProfile() {
                    if (this.profilingForm.role_1_id && this.profilingForm.role_2_id && this.profilingForm.role_1_id == this.profilingForm.role_2_id) {
                        this.showToast('Pilihan Role 1 dan Role 2 tidak boleh sama!');
                        return;
                    }

                    const payload = {
                        nim: this.profilingForm.nim,
                        role_1_id: parseInt(this.profilingForm.role_1_id),
                        role_2_id: this.profilingForm.role_2_id ? parseInt(this.profilingForm.role_2_id) : null,
                        portfolio_link: this.profilingForm.portfolio_link,
                        skill_ids: this.profilingForm.skill_ids.map(id => parseInt(id))
                    };

                    const { res, data } = await this.makeRequest('POST', this.apiPrefix + '/student/profile', payload);
                    if (res && (res.status === 201 || res.status === 200)) {
                        this.showToast('Profil dan keahlian berhasil disimpan & dihitung skornya!');
                        this.fetchProfilingConfig();
                    } else {
                        this.showToast('Gagal simpan: ' + (data?.message || 'Error'));
                    }
                },

                async fetchStudentMyTeam() {
                    const { res, data } = await this.makeRequest('GET', this.apiPrefix + '/student/my-team');
                    if (res && res.ok && data?.data) {
                        this.myTeamData = data.data;
                    }
                },

                // ==========================================
                // UNIVERSAL CONSOLE
                // ==========================================
                async executeConsoleRequest() {
                    let body = null;
                    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(this.consoleReq.method) && this.consoleReq.body.trim()) {
                        try {
                            body = JSON.parse(this.consoleReq.body);
                        } catch (e) {
                            this.showToast('Error JSON syntax pada Request Body!');
                            return;
                        }
                    }
                    await this.makeRequest(this.consoleReq.method, this.consoleReq.path, body);
                },

                // Badge Helper
                getRoleBadgeClass(role) {
                    switch (role) {
                        case 'admin': return 'bg-emerald-100 text-emerald-800 border border-emerald-300';
                        case 'lecturer': return 'bg-purple-100 text-purple-800 border border-purple-300';
                        case 'student': return 'bg-sky-100 text-sky-800 border border-sky-300';
                        case 'assistant': return 'bg-teal-100 text-teal-800 border border-teal-300';
                        case 'practitioner': return 'bg-amber-100 text-amber-800 border border-amber-300';
                        default: return 'bg-slate-100 text-slate-700';
                    }
                },

                getStatusBadgeClass(status) {
                    if (!status) return 'bg-slate-700 text-slate-300';
                    if (status >= 200 && status < 300) return 'bg-emerald-500 text-white';
                    if (status >= 300 && status < 400) return 'bg-sky-500 text-white';
                    if (status === 401 || status === 403) return 'bg-amber-500 text-white';
                    if (status >= 400 && status < 500) return 'bg-rose-500 text-white';
                    return 'bg-purple-600 text-white';
                }
            };
        }
    </script>
</body>
</html>