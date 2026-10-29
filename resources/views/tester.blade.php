<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PAD Backend - API Testing Playground</title>
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
        .tab-active { border-bottom-color: #003366; color: #003366; font-weight: 600; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex flex-col font-sans" x-data="apiTester()" x-init="initApp()" x-cloak>

    <!-- Top Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Brand / Title -->
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-ugm-blue text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        PAD
                    </div>
                    <div>
                        <h1 class="text-base font-bold text-slate-900 leading-tight">PAD API Tester & Playground</h1>
                        <p class="text-xs text-slate-500">Platform Proyek Akhir D4 — Simple API Testing Dashboard</p>
                    </div>
                </div>

                <!-- API Prefix Switcher & Active Token Info -->
                <div class="flex items-center gap-3">
                    <!-- API Prefix Toggle -->
                    <div class="flex items-center bg-slate-100 rounded-lg p-1 border border-slate-200 text-xs">
                        <span class="text-slate-500 px-2 font-medium">Prefix:</span>
                        <button type="button" @click="apiPrefix = '/api/v1'" :class="apiPrefix === '/api/v1' ? 'bg-white text-ugm-blue font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900'" class="px-2.5 py-1 rounded transition">
                            v1 (/api/v1)
                        </button>
                        <button type="button" @click="apiPrefix = '/api'" :class="apiPrefix === '/api' ? 'bg-white text-ugm-blue font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900'" class="px-2.5 py-1 rounded transition">
                            Root (/api)
                        </button>
                    </div>

                    <!-- Token Status Badge -->
                    <div class="flex items-center gap-2">
                        <template x-if="bearerToken">
                            <div class="flex items-center gap-1.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs px-3 py-1.5 rounded-full font-medium">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span x-text="'Token: ' + bearerToken.substring(0, 8) + '...'"></span>
                                <button type="button" @click="copyToClipboard(bearerToken, 'Token disalin!')" title="Salin Token" class="text-emerald-700 hover:text-emerald-900 ml-1">
                                    📋
                                </button>
                                <button type="button" @click="clearToken()" title="Hapus Token" class="text-rose-600 hover:text-rose-800 font-bold ml-1">
                                    ✕
                                </button>
                            </div>
                        </template>
                        <template x-if="!bearerToken">
                            <div class="flex items-center gap-1.5 bg-slate-100 border border-slate-200 text-slate-600 text-xs px-3 py-1.5 rounded-full">
                                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                <span>No Sanctum Token</span>
                            </div>
                        </template>

                        <!-- Quick Dev Token Button -->
                        <button type="button" @click="showDevTokenModal = true" class="text-xs bg-slate-800 hover:bg-slate-900 text-white px-3 py-1.5 rounded-lg transition font-medium flex items-center gap-1">
                            <span>⚡</span> Dev Token
                        </button>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <nav class="flex space-x-6 border-t border-slate-100 overflow-x-auto text-sm" aria-label="Tabs">
                <button type="button" @click="activeTab = 'stakeholders'" :class="activeTab === 'stakeholders' ? 'tab-active border-b-2 font-semibold' : 'border-b-2 border-transparent text-slate-600 hover:text-slate-900'" class="py-3 px-1 whitespace-nowrap transition flex items-center gap-2">
                    <span>👥</span> Stakeholders & Whitelist
                </button>
                <button type="button" @click="activeTab = 'lecturer'" :class="activeTab === 'lecturer' ? 'tab-active border-b-2 font-semibold' : 'border-b-2 border-transparent text-slate-600 hover:text-slate-900'" class="py-3 px-1 whitespace-nowrap transition flex items-center gap-2">
                    <span>🎓</span> Inisiasi Dosen (Admin)
                </button>
                <button type="button" @click="activeTab = 'auth'" :class="activeTab === 'auth' ? 'tab-active border-b-2 font-semibold' : 'border-b-2 border-transparent text-slate-600 hover:text-slate-900'" class="py-3 px-1 whitespace-nowrap transition flex items-center gap-2">
                    <span>🔐</span> Auth & SSO Google
                </button>
                <button type="button" @click="activeTab = 'console'" :class="activeTab === 'console' ? 'tab-active border-b-2 font-semibold' : 'border-b-2 border-transparent text-slate-600 hover:text-slate-900'" class="py-3 px-1 whitespace-nowrap transition flex items-center gap-2">
                    <span>⚡</span> API Console (Custom)
                </button>
            </nav>
        </div>
    </header>

    <!-- Main Content Grid -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex-1 w-full grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left / Primary Area: Feature Test Panel (7 cols on lg) -->
        <section class="lg:col-span-7 space-y-6">

            <!-- ========================================== -->
            <!-- TAB 1: STAKEHOLDERS & WHITELIST            -->
            <!-- ========================================== -->
            <div x-show="activeTab === 'stakeholders'" class="space-y-6">

                <!-- Actions & Filter Bar -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs space-y-3">
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                        <div class="flex-1 relative">
                            <input type="text" x-model="stakeholderFilters.search" @input.debounce.400ms="fetchStakeholders(1)" placeholder="Cari nama atau email..." class="w-full bg-slate-50 border border-slate-300 rounded-lg pl-9 pr-3 py-1.5 text-xs focus:ring-2 focus:ring-ugm-blue focus:outline-none">
                            <span class="absolute left-3 top-2 text-slate-400 text-xs">🔍</span>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <button type="button" @click="showAddStakeholderModal = true" class="text-xs bg-ugm-blue hover:bg-ugm-dark text-white px-3 py-1.5 rounded-lg font-medium transition flex items-center gap-1 shadow-xs">
                                <span>+</span> Tambah Whitelist
                            </button>
                            <button type="button" @click="showAssignRoleModal = true" class="text-xs bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg font-medium transition">
                                🔄 Assign Role by Email
                            </button>
                            <button type="button" @click="fetchStakeholders(stakeholdersMeta.current_page)" title="Refresh Data" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 p-1.5 rounded-lg transition">
                                🔄
                            </button>
                        </div>
                    </div>

                    <!-- Filter Dropdowns -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 border-t border-slate-100 text-xs">
                        <div>
                            <label class="block text-slate-500 mb-1 font-medium">Role:</label>
                            <select x-model="stakeholderFilters.role" @change="fetchStakeholders(1)" class="w-full bg-white border border-slate-300 rounded-md py-1 px-2 text-xs focus:outline-none focus:ring-1 focus:ring-ugm-blue">
                                <option value="">Semua Role</option>
                                <option value="student">Mahasiswa (student)</option>
                                <option value="lecturer">Dosen (lecturer)</option>
                                <option value="admin">Admin (admin)</option>
                                <option value="assistant">Asisten (assistant)</option>
                                <option value="practitioner">Praktisi (practitioner)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1 font-medium">Status:</label>
                            <select x-model="stakeholderFilters.status" @change="fetchStakeholders(1)" class="w-full bg-white border border-slate-300 rounded-md py-1 px-2 text-xs focus:outline-none focus:ring-1 focus:ring-ugm-blue">
                                <option value="">Semua Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1 font-medium">Bypass KRS:</label>
                            <select x-model="stakeholderFilters.is_bypass" @change="fetchStakeholders(1)" class="w-full bg-white border border-slate-300 rounded-md py-1 px-2 text-xs focus:outline-none focus:ring-1 focus:ring-ugm-blue">
                                <option value="">Semua</option>
                                <option value="true">Bypass (true)</option>
                                <option value="false">Non-Bypass (false)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1 font-medium">Per Page:</label>
                            <select x-model="stakeholderFilters.per_page" @change="fetchStakeholders(1)" class="w-full bg-white border border-slate-300 rounded-md py-1 px-2 text-xs focus:outline-none focus:ring-1 focus:ring-ugm-blue">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="25">25</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Stakeholders Table -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-sm text-slate-800">Daftar Stakeholder Terdaftar</span>
                            <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-semibold" x-text="stakeholdersMeta.total + ' data'"></span>
                        </div>
                        <div class="text-xs text-slate-500" x-text="'Halaman ' + (stakeholdersMeta.current_page || 1) + ' dari ' + (stakeholdersMeta.last_page || 1)"></div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-slate-50 text-slate-600 uppercase font-semibold border-b border-slate-200">
                                <tr>
                                    <th class="py-2.5 px-3">User</th>
                                    <th class="py-2.5 px-3">Role</th>
                                    <th class="py-2.5 px-3">Status</th>
                                    <th class="py-2.5 px-3">NIM / Bypass</th>
                                    <th class="py-2.5 px-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-if="loadingStakeholders">
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-slate-400">
                                            <div class="inline-block animate-spin mr-2">⏳</div> Memuat data stakeholder...
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="!loadingStakeholders && stakeholdersList.length === 0">
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-slate-500">
                                            Belum ada data stakeholder ditemukan. Tambahkan whitelist atau ubah filter pencarian.
                                        </td>
                                    </tr>
                                </template>
                                <template x-for="item in stakeholdersList" :key="item.id">
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-2.5 px-3">
                                            <div class="font-medium text-slate-900" x-text="item.name || '(Tanpa Nama)'"></div>
                                            <div class="text-slate-500 text-2xs font-mono" x-text="item.email"></div>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <span :class="getRoleBadgeClass(item.user_role)" class="px-2 py-0.5 rounded-md font-medium text-2xs" x-text="item.user_role"></span>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <span :class="item.status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'" class="px-2 py-0.5 rounded-md font-medium text-2xs" x-text="item.status"></span>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <template x-if="item.student_profile">
                                                <div>
                                                    <span class="font-mono" x-text="item.student_profile.nim || '-'"></span>
                                                    <template x-if="item.student_profile.is_bypass">
                                                        <span class="ml-1 bg-amber-100 text-amber-800 text-3xs px-1.5 py-0.2 rounded font-bold">BYPASS</span>
                                                    </template>
                                                </div>
                                            </template>
                                            <template x-if="!item.student_profile">
                                                <span class="text-slate-400">-</span>
                                            </template>
                                        </td>
                                        <td class="py-2.5 px-3 text-right space-x-1 whitespace-nowrap">
                                            <!-- Detail Button -->
                                            <button type="button" @click="getStakeholderDetail(item.id)" title="Detail (GET)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded transition text-2xs">
                                                Detail
                                            </button>
                                            <!-- Edit Role Button -->
                                            <button type="button" @click="openEditRoleModal(item)" title="Ubah Role (PUT)" class="px-2 py-1 bg-sky-50 hover:bg-sky-100 text-sky-700 rounded transition text-2xs font-medium">
                                                Role
                                            </button>
                                            <!-- Issue Token / Login As -->
                                            <button type="button" @click="generateDevTokenForUser(item.email)" title="Buat Token Sanctum untuk User ini" class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded transition text-2xs font-medium">
                                                🔑 Token
                                            </button>
                                            <!-- Delete Button -->
                                            <button type="button" @click="deleteStakeholder(item)" title="Hapus (DELETE)" class="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded transition text-2xs font-medium">
                                                Hapus
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Footer -->
                    <div class="px-4 py-2.5 border-t border-slate-100 flex items-center justify-between text-xs bg-slate-50">
                        <button type="button" :disabled="stakeholdersMeta.current_page <= 1" @click="fetchStakeholders(stakeholdersMeta.current_page - 1)" class="px-3 py-1 bg-white border border-slate-300 rounded hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition">
                            &larr; Sebelumnya
                        </button>
                        <span class="text-slate-600" x-text="'Halaman ' + (stakeholdersMeta.current_page || 1) + ' dari ' + (stakeholdersMeta.last_page || 1)"></span>
                        <button type="button" :disabled="stakeholdersMeta.current_page >= stakeholdersMeta.last_page" @click="fetchStakeholders(stakeholdersMeta.current_page + 1)" class="px-3 py-1 bg-white border border-slate-300 rounded hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition">
                            Berikutnya &rarr;
                        </button>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- TAB 2: INISIASI DOSEN (ADMIN)              -->
            <!-- ========================================== -->
            <div x-show="activeTab === 'lecturer'" class="space-y-6">
                <!-- Info Box -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-900 leading-relaxed">
                    <strong class="font-bold flex items-center gap-1 mb-1">
                        <span>ℹ️</span> Fitur Inisiasi Dosen Pengampu (Proses 1.0 & 2.0)
                    </strong>
                    Menginisiasi alamat email dosen pengampu sebagai <strong>Full Admin</strong> dengan status <strong>active</strong>. Dapat dilakukan secara satuan (single) atau sekaligus (batch).
                </div>

                <!-- Single vs Batch Tabs -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                        <h3 class="font-bold text-sm text-slate-800">Form Inisiasi Dosen Pengampu</h3>
                        <div class="flex rounded-md bg-slate-100 p-0.5 text-xs">
                            <button type="button" @click="lecturerMode = 'single'" :class="lecturerMode === 'single' ? 'bg-white text-ugm-blue font-semibold shadow-xs' : 'text-slate-600'" class="px-3 py-1 rounded transition">
                                Single
                            </button>
                            <button type="button" @click="lecturerMode = 'batch'" :class="lecturerMode === 'batch' ? 'bg-white text-ugm-blue font-semibold shadow-xs' : 'text-slate-600'" class="px-3 py-1 rounded transition">
                                Batch (Banyak)
                            </button>
                        </div>
                    </div>

                    <!-- Single Lecturer Form -->
                    <template x-if="lecturerMode === 'single'">
                        <form @submit.prevent="submitSingleLecturer()" class="space-y-3 text-xs">
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Email Dosen Pengampu <span class="text-rose-500">*</span></label>
                                <input type="email" x-model="singleLecturer.email" required placeholder="contoh: dosen.pengampu@mail.ugm.ac.id" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-ugm-blue focus:outline-none">
                            </div>
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Nama Lengkap (Opsional)</label>
                                <input type="text" x-model="singleLecturer.name" placeholder="contoh: Dr. Ir. Budi Santoso, M.T." class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-ugm-blue focus:outline-none">
                            </div>
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Route Endpoint:</label>
                                <div class="flex gap-4 text-xs">
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" value="admin/initiate-lecturer" x-model="lecturerEndpoint">
                                        <span>/admin/initiate-lecturer</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" value="initiate-lecturer" x-model="lecturerEndpoint">
                                        <span>/initiate-lecturer</span>
                                    </label>
                                </div>
                            </div>
                            <div class="pt-2 flex justify-end">
                                <button type="submit" :disabled="loadingApi" class="bg-ugm-blue hover:bg-ugm-dark text-white px-4 py-2 rounded-lg font-medium transition text-xs flex items-center gap-1.5 shadow-xs disabled:opacity-50">
                                    <span x-show="loadingApi">⏳</span> Inisiasi Dosen Satuan (POST)
                                </button>
                            </div>
                        </form>
                    </template>

                    <!-- Batch Lecturer Form -->
                    <template x-if="lecturerMode === 'batch'">
                        <form @submit.prevent="submitBatchLecturer()" class="space-y-3 text-xs">
                            <div class="space-y-2">
                                <template x-for="(item, index) in batchLecturers" :key="index">
                                    <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                                        <span class="text-slate-400 font-mono text-2xs w-4" x-text="index + 1"></span>
                                        <input type="email" x-model="item.email" required placeholder="email@mail.ugm.ac.id *" class="flex-1 bg-white border border-slate-300 rounded px-2.5 py-1 text-xs focus:ring-1 focus:ring-ugm-blue focus:outline-none">
                                        <input type="text" x-model="item.name" placeholder="Nama Lengkap (opsional)" class="flex-1 bg-white border border-slate-300 rounded px-2.5 py-1 text-xs focus:ring-1 focus:ring-ugm-blue focus:outline-none">
                                        <button type="button" @click="removeBatchLecturerRow(index)" :disabled="batchLecturers.length <= 1" class="text-rose-500 hover:text-rose-700 p-1 disabled:opacity-30">
                                            ✕
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <div class="flex items-center justify-between pt-2">
                                <div class="flex gap-2">
                                    <button type="button" @click="addBatchLecturerRow()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded text-xs font-medium transition">
                                        + Tambah Baris
                                    </button>
                                    <button type="button" @click="fillSampleBatchLecturers()" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-1.5 rounded text-xs transition">
                                        Isi Contoh Data
                                    </button>
                                </div>
                                <button type="submit" :disabled="loadingApi" class="bg-ugm-blue hover:bg-ugm-dark text-white px-4 py-2 rounded-lg font-medium transition text-xs flex items-center gap-1.5 shadow-xs disabled:opacity-50">
                                    <span x-show="loadingApi">⏳</span> Inisiasi Batch Dosen (POST)
                                </button>
                            </div>
                        </form>
                    </template>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- TAB 3: AUTH & SSO GOOGLE                   -->
            <!-- ========================================== -->
            <div x-show="activeTab === 'auth'" class="space-y-6">

                <!-- Alert if redirected with OAuth code -->
                @if(isset($oauthCode) && $oauthCode)
                <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 p-4 rounded-xl text-xs space-y-2">
                    <div class="font-bold text-sm flex items-center gap-1.5">
                        <span>🎉</span> Google OAuth Authorization Code Diterima!
                    </div>
                    <p>Kode otorisasi dari Google berhasil ditangkap: <code class="font-mono bg-white px-1.5 py-0.5 rounded border border-emerald-200 text-emerald-700">{{ Str::limit($oauthCode, 35) }}</code></p>
                    <button type="button" @click="googleAuthCallback.code = '{{ $oauthCode }}'; submitGoogleCallback()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded font-medium transition">
                        Tukarkan Code Ini Menjadi Token Sanctum Sekarang &rarr;
                    </button>
                </div>
                @endif

                @if(isset($oauthError) && $oauthError)
                <div class="bg-rose-50 border border-rose-300 text-rose-800 p-4 rounded-xl text-xs">
                    <strong class="font-bold">OAuth Error:</strong> {{ $oauthError }}
                </div>
                @endif

                <!-- Card 1: Google Redirect URL -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                        <div>
                            <h3 class="font-bold text-sm text-slate-800">1. Generate Google OAuth Redirect URL</h3>
                            <p class="text-xs text-slate-500">Endpoint: <code class="text-ugm-blue font-mono">GET /auth/google/redirect</code></p>
                        </div>
                        <span class="text-xs bg-sky-50 text-sky-700 font-mono px-2 py-0.5 rounded">GET</span>
                    </div>

                    <form @submit.prevent="getGoogleRedirectUrl()" class="space-y-3 text-xs">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Custom Redirect URI (Opsional)</label>
                            <input type="text" x-model="googleRedirectUri" placeholder="http://localhost:8000/auth/google/callback" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-ugm-blue focus:outline-none">
                            <span class="text-2xs text-slate-400">Default mengacu pada konfigurasi GOOGLE_REDIRECT_URI di .env</span>
                        </div>
                        <div class="flex items-center justify-between pt-1">
                            <button type="submit" :disabled="loadingApi" class="bg-ugm-blue hover:bg-ugm-dark text-white px-4 py-2 rounded-lg font-medium transition text-xs flex items-center gap-1.5 shadow-xs disabled:opacity-50">
                                <span x-show="loadingApi">⏳</span> Dapatkan URL Redirect Google
                            </button>
                            <template x-if="googleGeneratedUrl">
                                <a :href="googleGeneratedUrl" target="_blank" class="bg-amber-400 hover:bg-amber-500 text-slate-900 font-bold px-4 py-2 rounded-lg transition text-xs flex items-center gap-1.5 shadow-xs">
                                    <span>🌐</span> Buka Login Google (Tab Baru) &rarr;
                                </a>
                            </template>
                        </div>
                    </form>
                </div>

                <!-- Card 2: Google Callback -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                        <div>
                            <h3 class="font-bold text-sm text-slate-800">2. Google OAuth Callback (Code / Token)</h3>
                            <p class="text-xs text-slate-500">Endpoint: <code class="text-ugm-blue font-mono">POST /auth/google/callback</code></p>
                        </div>
                        <span class="text-xs bg-emerald-50 text-emerald-700 font-mono px-2 py-0.5 rounded">POST</span>
                    </div>

                    <form @submit.prevent="submitGoogleCallback()" class="space-y-3 text-xs">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Authorization Code dari Google</label>
                            <input type="text" x-model="googleAuthCallback.code" placeholder="4/0A... (kode yang didapat setelah redirect)" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-ugm-blue focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Access Token Google (Alternatif jika tidak ada code)</label>
                            <input type="text" x-model="googleAuthCallback.token" placeholder="ya29.a0... (access token Google OAuth)" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-ugm-blue focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Redirect URI (Wajib sama dengan saat request redirect)</label>
                            <input type="text" x-model="googleAuthCallback.redirect_uri" placeholder="http://localhost:8000/auth/google/callback" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-ugm-blue focus:outline-none">
                        </div>
                        <div class="pt-1 flex justify-end">
                            <button type="submit" :disabled="loadingApi" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-medium transition text-xs flex items-center gap-1.5 shadow-xs disabled:opacity-50">
                                <span x-show="loadingApi">⏳</span> Kirim Callback & Ambil Token Sanctum
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Card 3 & 4: Auth Me & Logout -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Me Card -->
                    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-xs text-slate-800">3. Profil Pengguna (/auth/me)</h4>
                            <span class="text-3xs bg-sky-50 text-sky-700 font-mono px-1.5 py-0.5 rounded">GET</span>
                        </div>
                        <p class="text-2xs text-slate-500">Memerlukan Bearer token aktif. Mengambil data user yang sedang login beserta student profile.</p>
                        <button type="button" @click="fetchAuthMe()" :disabled="!bearerToken || loadingApi" class="w-full bg-slate-800 hover:bg-slate-900 text-white py-2 rounded-lg font-medium transition text-xs disabled:opacity-40">
                            Cek /auth/me
                        </button>
                    </div>

                    <!-- Logout Card -->
                    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-xs text-slate-800">4. Logout (/auth/logout)</h4>
                            <span class="text-3xs bg-emerald-50 text-emerald-700 font-mono px-1.5 py-0.5 rounded">POST</span>
                        </div>
                        <p class="text-2xs text-slate-500">Memerlukan Bearer token aktif. Menghapus current Personal Access Token Sanctum.</p>
                        <button type="button" @click="submitLogout()" :disabled="!bearerToken || loadingApi" class="w-full bg-rose-600 hover:bg-rose-700 text-white py-2 rounded-lg font-medium transition text-xs disabled:opacity-40">
                            Logout & Revoke Token
                        </button>
                    </div>
                </div>

            </div>

            <!-- ========================================== -->
            <!-- TAB 4: UNIVERSAL API CONSOLE (CUSTOM)      -->
            <!-- ========================================== -->
            <div x-show="activeTab === 'console'" class="space-y-6">
                <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                        <div>
                            <h3 class="font-bold text-sm text-slate-800">Universal API Playground</h3>
                            <p class="text-xs text-slate-500">Uji coba endpoint bebas dengan HTTP Method, Header, dan JSON Body</p>
                        </div>
                    </div>

                    <!-- Endpoint Presets -->
                    <div>
                        <label class="block text-2xs font-semibold text-slate-500 uppercase mb-1">Preset Endpoint Siap Pakai:</label>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" @click="applyPreset('GET', '/auth/google/redirect', '')" class="text-3xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 rounded">
                                GET /auth/google/redirect
                            </button>
                            <button type="button" @click="applyPreset('GET', '/auth/me', '', true)" class="text-3xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 rounded">
                                GET /auth/me (Auth)
                            </button>
                            <button type="button" @click="applyPreset('POST', '/auth/logout', '', true)" class="text-3xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 rounded">
                                POST /auth/logout (Auth)
                            </button>
                            <button type="button" @click="applyPreset('GET', '/stakeholders', '')" class="text-3xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 rounded">
                                GET /stakeholders
                            </button>
                            <button type="button" @click="applyPreset('POST', '/stakeholders', JSON.stringify({ email: 'mhs@mail.ugm.ac.id', role: 'student', name: 'Contoh Mhs', nim: '21/123456/TK/12345', is_bypass: false }, null, 2))" class="text-3xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 rounded">
                                POST /stakeholders (Single)
                            </button>
                            <button type="button" @click="applyPreset('POST', '/admin/initiate-lecturer', JSON.stringify({ email: 'dosen@mail.ugm.ac.id', name: 'Nama Dosen' }, null, 2))" class="text-3xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 rounded">
                                POST /admin/initiate-lecturer
                            </button>
                            <button type="button" @click="applyPreset('POST', '/stakeholders/assign-role', JSON.stringify({ email: 'mhs@mail.ugm.ac.id', role: 'assistant' }, null, 2))" class="text-3xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 rounded">
                                POST /stakeholders/assign-role
                            </button>
                        </div>
                    </div>

                    <!-- Console Form -->
                    <form @submit.prevent="executeConsoleRequest()" class="space-y-3 text-xs">
                        <div class="flex gap-2">
                            <select x-model="consoleReq.method" class="w-28 bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 font-bold text-xs focus:ring-1 focus:ring-ugm-blue">
                                <option value="GET">GET</option>
                                <option value="POST">POST</option>
                                <option value="PUT">PUT</option>
                                <option value="PATCH">PATCH</option>
                                <option value="DELETE">DELETE</option>
                            </select>
                            <div class="flex-1 relative">
                                <input type="text" x-model="consoleReq.path" placeholder="/stakeholders" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 font-mono text-xs focus:ring-2 focus:ring-ugm-blue focus:outline-none">
                            </div>
                        </div>

                        <!-- Include Bearer Checkbox -->
                        <div class="flex items-center gap-2">
                            <label class="flex items-center gap-1.5 cursor-pointer text-slate-700">
                                <input type="checkbox" x-model="consoleReq.useAuth" class="rounded text-ugm-blue">
                                <span>Sertakan Sanctum Bearer Token</span>
                            </label>
                            <span class="text-slate-400">|</span>
                            <span class="text-2xs text-slate-500" x-text="bearerToken ? 'Token terdeteksi (' + bearerToken.substring(0, 10) + '...)' : 'Tidak ada token'"></span>
                        </div>

                        <!-- JSON Body -->
                        <div x-show="['POST', 'PUT', 'PATCH', 'DELETE'].includes(consoleReq.method)">
                            <div class="flex items-center justify-between mb-1">
                                <label class="font-medium text-slate-700">Request Body (JSON):</label>
                                <button type="button" @click="formatConsoleJson()" class="text-2xs text-ugm-blue hover:underline">
                                    Prettify JSON
                                </button>
                            </div>
                            <textarea x-model="consoleReq.body" rows="6" placeholder="{&#10;  &#34;key&#34;: &#34;value&#34;&#10;}" class="w-full bg-slate-900 text-emerald-400 font-mono text-xs p-3 rounded-lg border border-slate-800 focus:outline-none focus:ring-2 focus:ring-ugm-blue"></textarea>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit" :disabled="loadingApi" class="bg-ugm-blue hover:bg-ugm-dark text-white px-5 py-2 rounded-lg font-semibold transition text-xs flex items-center gap-1.5 shadow-xs disabled:opacity-50">
                                <span x-show="loadingApi">⏳</span> Kirim Request (Execute)
                            </button>
                        </div>
                    </form>
                </div>
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
                            Belum ada request yang dieksekusi.<br>Pilih salah satu aksi atau klik tombol untuk mengetes API.
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
    <!-- MODALS: Add Stakeholder, Edit Role, Assign by Email, Dev -->
    <!-- ======================================================== -->

    <!-- Modal 1: Add Stakeholder (Single or Batch) -->
    <div x-show="showAddStakeholderModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-xl overflow-hidden" @click.away="showAddStakeholderModal = false">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Tambah Stakeholder ke Whitelist</h3>
                    <p class="text-xs text-slate-500">Endpoint: <code class="text-ugm-blue font-mono">POST /stakeholders</code></p>
                </div>
                <button type="button" @click="showAddStakeholderModal = false" class="text-slate-400 hover:text-slate-700 text-base">✕</button>
            </div>

            <!-- Single or Batch Selector -->
            <div class="p-5 space-y-4">
                <div class="flex rounded-lg bg-slate-100 p-1 text-xs">
                    <button type="button" @click="newStakeholderMode = 'single'" :class="newStakeholderMode === 'single' ? 'bg-white font-semibold text-ugm-blue shadow-xs' : 'text-slate-600'" class="flex-1 py-1.5 rounded transition text-center">
                        Single Stakeholder
                    </button>
                    <button type="button" @click="newStakeholderMode = 'batch'" :class="newStakeholderMode === 'batch' ? 'bg-white font-semibold text-ugm-blue shadow-xs' : 'text-slate-600'" class="flex-1 py-1.5 rounded transition text-center">
                        Batch (Banyak Data)
                    </button>
                </div>

                <!-- Single Form -->
                <template x-if="newStakeholderMode === 'single'">
                    <form @submit.prevent="submitSingleStakeholder()" class="space-y-3 text-xs">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Email <span class="text-rose-500">*</span></label>
                            <input type="email" x-model="singleStakeholder.email" required placeholder="nama@mail.ugm.ac.id" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Role <span class="text-rose-500">*</span></label>
                                <select x-model="singleStakeholder.role" required class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue">
                                    <option value="student">Mahasiswa (student)</option>
                                    <option value="lecturer">Dosen (lecturer)</option>
                                    <option value="admin">Admin (admin)</option>
                                    <option value="assistant">Asisten (assistant)</option>
                                    <option value="practitioner">Praktisi (practitioner)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Nama Lengkap</label>
                                <input type="text" x-model="singleStakeholder.name" placeholder="Nama lengkap" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3" x-show="singleStakeholder.role === 'student'">
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">NIM (Khusus Mahasiswa)</label>
                                <input type="text" x-model="singleStakeholder.nim" placeholder="21/123456/TK/12345" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue">
                            </div>
                            <div class="flex items-center pt-5">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" x-model="singleStakeholder.is_bypass" class="rounded text-ugm-blue">
                                    <span class="font-medium text-slate-700">Bypass KRS</span>
                                </label>
                            </div>
                        </div>
                        <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                            <button type="button" @click="showAddStakeholderModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium transition">
                                Batal
                            </button>
                            <button type="submit" :disabled="loadingApi" class="px-4 py-2 bg-ugm-blue hover:bg-ugm-dark text-white rounded-lg font-medium transition shadow-xs disabled:opacity-50">
                                Simpan Stakeholder
                            </button>
                        </div>
                    </form>
                </template>

                <!-- Batch Form -->
                <template x-if="newStakeholderMode === 'batch'">
                    <form @submit.prevent="submitBatchStakeholders()" class="space-y-3 text-xs">
                        <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                            <template x-for="(item, index) in batchStakeholders" :key="index">
                                <div class="p-2.5 bg-slate-50 rounded-lg border border-slate-200 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-2xs text-slate-500" x-text="'Stakeholder #' + (index + 1)"></span>
                                        <button type="button" @click="removeBatchStakeholderRow(index)" :disabled="batchStakeholders.length <= 1" class="text-rose-500 hover:text-rose-700 text-xs disabled:opacity-30">✕</button>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="email" x-model="item.email" required placeholder="email@mail.ugm.ac.id *" class="bg-white border border-slate-300 rounded px-2 py-1 text-xs">
                                        <select x-model="item.role" required class="bg-white border border-slate-300 rounded px-2 py-1 text-xs">
                                            <option value="student">Mahasiswa</option>
                                            <option value="lecturer">Dosen</option>
                                            <option value="admin">Admin</option>
                                            <option value="assistant">Asisten</option>
                                            <option value="practitioner">Praktisi</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2">
                                        <input type="text" x-model="item.name" placeholder="Nama" class="bg-white border border-slate-300 rounded px-2 py-1 text-xs">
                                        <input type="text" x-model="item.nim" placeholder="NIM" class="bg-white border border-slate-300 rounded px-2 py-1 text-xs">
                                        <label class="flex items-center gap-1.5 cursor-pointer text-2xs">
                                            <input type="checkbox" x-model="item.is_bypass" class="rounded text-ugm-blue">
                                            <span>Bypass</span>
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                            <div class="flex gap-2">
                                <button type="button" @click="addBatchStakeholderRow()" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs font-medium">
                                    + Tambah Baris
                                </button>
                                <button type="button" @click="fillSampleBatchStakeholders()" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded text-xs">
                                    Isi Contoh
                                </button>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="showAddStakeholderModal = false" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs">
                                    Batal
                                </button>
                                <button type="submit" :disabled="loadingApi" class="px-4 py-1.5 bg-ugm-blue hover:bg-ugm-dark text-white rounded text-xs font-medium shadow-xs disabled:opacity-50">
                                    Simpan Batch
                                </button>
                            </div>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>

    <!-- Modal 2: Edit Stakeholder Role (by ID) -->
    <div x-show="showEditRoleModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden" @click.away="showEditRoleModal = false">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Update Role Stakeholder</h3>
                    <p class="text-xs text-slate-500 font-mono" x-text="editRoleTarget.email"></p>
                </div>
                <button type="button" @click="showEditRoleModal = false" class="text-slate-400 hover:text-slate-700 text-base">✕</button>
            </div>

            <form @submit.prevent="submitEditRole()" class="p-5 space-y-3 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Pilih Role Baru <span class="text-rose-500">*</span></label>
                    <select x-model="editRoleTarget.role" required class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-ugm-blue">
                        <option value="student">Mahasiswa (student)</option>
                        <option value="lecturer">Dosen (lecturer)</option>
                        <option value="admin">Admin (admin)</option>
                        <option value="assistant">Asisten (assistant)</option>
                        <option value="practitioner">Praktisi (practitioner)</option>
                    </select>
                </div>
                <div x-show="editRoleTarget.role === 'student'" class="space-y-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">NIM (Mahasiswa)</label>
                        <input type="text" x-model="editRoleTarget.nim" placeholder="21/123456/TK/12345" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="editRoleTarget.is_bypass" class="rounded text-ugm-blue">
                        <span class="font-medium text-slate-700">Bypass KRS Status</span>
                    </label>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="showEditRoleModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium transition">
                        Batal
                    </button>
                    <button type="submit" :disabled="loadingApi" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg font-medium transition shadow-xs disabled:opacity-50">
                        Update Role (PUT)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 3: Assign Role by Email -->
    <div x-show="showAssignRoleModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden" @click.away="showAssignRoleModal = false">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Assign Role via Email</h3>
                    <p class="text-xs text-slate-500">Endpoint: <code class="text-ugm-blue font-mono">POST /stakeholders/assign-role</code></p>
                </div>
                <button type="button" @click="showAssignRoleModal = false" class="text-slate-400 hover:text-slate-700 text-base">✕</button>
            </div>

            <form @submit.prevent="submitAssignRoleByEmail()" class="p-5 space-y-3 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Email Stakeholder <span class="text-rose-500">*</span></label>
                    <input type="email" x-model="assignRoleByEmailData.email" required placeholder="target@mail.ugm.ac.id" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Target Role <span class="text-rose-500">*</span></label>
                    <select x-model="assignRoleByEmailData.role" required class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                        <option value="student">Mahasiswa (student)</option>
                        <option value="lecturer">Dosen (lecturer)</option>
                        <option value="admin">Admin (admin)</option>
                        <option value="assistant">Asisten (assistant)</option>
                        <option value="practitioner">Praktisi (practitioner)</option>
                    </select>
                </div>
                <div x-show="assignRoleByEmailData.role === 'student'" class="space-y-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">NIM (Opsional)</label>
                        <input type="text" x-model="assignRoleByEmailData.nim" placeholder="21/123456/TK/12345" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="assignRoleByEmailData.is_bypass" class="rounded text-ugm-blue">
                        <span class="font-medium text-slate-700">Bypass KRS Status</span>
                    </label>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="showAssignRoleModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium transition">
                        Batal
                    </button>
                    <button type="submit" :disabled="loadingApi" class="px-4 py-2 bg-ugm-blue hover:bg-ugm-dark text-white rounded-lg font-medium transition shadow-xs disabled:opacity-50">
                        Kirim Perubahan Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 4: Quick Dev Token Generator -->
    <div x-show="showDevTokenModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden" @click.away="showDevTokenModal = false">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">⚡ Dev Sanctum Token Generator</h3>
                    <p class="text-xs text-slate-500">Buat Personal Access Token instan untuk testing lokal</p>
                </div>
                <button type="button" @click="showDevTokenModal = false" class="text-slate-400 hover:text-slate-700 text-base">✕</button>
            </div>

            <div class="p-5 space-y-4 text-xs">
                <!-- Select Existing User -->
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Pilih User Terdaftar:</label>
                    <div class="flex gap-2">
                        <select x-model="selectedDevUserEmail" class="flex-1 bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                            <option value="">-- Pilih dari database --</option>
                            <template x-for="u in devUsersList" :key="u.id">
                                <option :value="u.email" x-text="u.email + ' (' + u.user_role + ')'"></option>
                            </template>
                        </select>
                        <button type="button" @click="fetchDevUsers()" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-slate-600">🔄</button>
                    </div>
                </div>

                <div class="relative flex py-1 items-center">
                    <div class="flex-grow border-t border-slate-200"></div>
                    <span class="flex-shrink mx-2 text-3xs text-slate-400 uppercase">atau ketik manual</span>
                    <div class="flex-grow border-t border-slate-200"></div>
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Email User:</label>
                    <input type="email" x-model="manualDevEmail" placeholder="contoh: dosen.pengampu@mail.ugm.ac.id" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs">
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" x-model="createIfMissing" class="rounded text-ugm-blue">
                    <span class="text-slate-600">Buat user admin baru jika email belum ada di database</span>
                </label>

                <div class="pt-2 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="showDevTokenModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium transition">
                        Tutup
                    </button>
                    <button type="button" @click="generateDevTokenAction()" :disabled="loadingApi" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition shadow-xs disabled:opacity-50">
                        Buat Token & Pasang di Header
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Toast -->
    <div x-show="toast.show" x-transition class="fixed bottom-5 right-5 z-50 bg-slate-900 text-white text-xs px-4 py-2.5 rounded-xl shadow-lg border border-slate-700 flex items-center gap-2">
        <span x-text="toast.message"></span>
    </div>

    <!-- Alpine.js Main Controller Script -->
    <script>
        function apiTester() {
            return {
                // Config & State
                apiPrefix: '/api/v1',
                activeTab: '{{ $initialTab ?? "stakeholders" }}',
                bearerToken: localStorage.getItem('pad_test_bearer_token') || '',
                loadingApi: false,
                loadingStakeholders: false,

                // Toast
                toast: { show: false, message: '' },
                showToast(msg) {
                    this.toast.message = msg;
                    this.toast.show = true;
                    setTimeout(() => { this.toast.show = false; }, 3500);
                },

                // Last Response / Log
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

                // Modals
                showAddStakeholderModal: false,
                showEditRoleModal: false,
                showAssignRoleModal: false,
                showDevTokenModal: false,

                // Stakeholders Tab
                stakeholdersList: [],
                stakeholdersMeta: { current_page: 1, last_page: 1, total: 0, per_page: 15 },
                stakeholderFilters: { search: '', role: '', status: '', is_bypass: '', per_page: '15' },

                // New Stakeholder Form
                newStakeholderMode: 'single',
                singleStakeholder: { email: '', role: 'student', name: '', nim: '', is_bypass: false },
                batchStakeholders: [
                    { email: 'mhs1@mail.ugm.ac.id', role: 'student', name: 'Mhs 1', nim: '21/111111/TK/11111', is_bypass: false },
                    { email: 'asisten1@mail.ugm.ac.id', role: 'assistant', name: 'Asisten 1', nim: '', is_bypass: false }
                ],

                // Edit Role Target
                editRoleTarget: { id: null, email: '', role: 'student', nim: '', is_bypass: false },

                // Assign Role by Email
                assignRoleByEmailData: { email: '', role: 'student', nim: '', is_bypass: false },

                // Lecturer Tab
                lecturerMode: 'single',
                lecturerEndpoint: 'admin/initiate-lecturer',
                singleLecturer: { email: '', name: '' },
                batchLecturers: [
                    { email: 'dosen.koordinator@mail.ugm.ac.id', name: 'Prof. Dosen Koordinator' },
                    { email: 'dosen.anggota@mail.ugm.ac.id', name: 'Dr. Dosen Anggota' }
                ],

                // Google Auth Tab
                googleRedirectUri: window.location.origin + '/auth/google/callback',
                googleGeneratedUrl: '',
                googleAuthCallback: {
                    code: '{{ $oauthCode ?? "" }}',
                    token: '',
                    redirect_uri: window.location.origin + '/auth/google/callback'
                },

                // Universal Console
                consoleReq: {
                    method: 'GET',
                    path: '/stakeholders',
                    useAuth: false,
                    body: ''
                },

                // Dev Token Modal
                devUsersList: [],
                selectedDevUserEmail: '',
                manualDevEmail: '',
                createIfMissing: true,

                // Init
                initApp() {
                    this.fetchStakeholders(1);
                    this.fetchDevUsers();
                    if ('{{ $oauthCode ?? "" }}') {
                        this.showToast('OAuth Authorization Code terdeteksi!');
                    }
                },

                // Utility: Headers builder
                getHeaders(includeAuth = false) {
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

                // Utility: Base API caller with timing & logging
                async makeRequest(method, endpoint, body = null, requireAuth = false) {
                    this.loadingApi = true;
                    const startTime = performance.now();
                    const fullUrl = endpoint.startsWith('http') ? endpoint : (endpoint.startsWith('/api') ? endpoint : this.apiPrefix + (endpoint.startsWith('/') ? endpoint : '/' + endpoint));

                    try {
                        const options = {
                            method: method.toUpperCase(),
                            headers: this.getHeaders(requireAuth || !!this.bearerToken)
                        };
                        if (body && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
                            options.body = typeof body === 'string' ? body : JSON.stringify(body);
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
                            statusText: 'Network / Client Error',
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

                // ==========================================
                // STAKEHOLDERS ACTIONS
                // ==========================================
                async fetchStakeholders(page = 1) {
                    this.loadingStakeholders = true;
                    let url = this.apiPrefix + '/stakeholders?page=' + page;
                    if (this.stakeholderFilters.per_page) url += '&per_page=' + this.stakeholderFilters.per_page;
                    if (this.stakeholderFilters.search) url += '&search=' + encodeURIComponent(this.stakeholderFilters.search);
                    if (this.stakeholderFilters.role) url += '&role=' + encodeURIComponent(this.stakeholderFilters.role);
                    if (this.stakeholderFilters.status) url += '&status=' + encodeURIComponent(this.stakeholderFilters.status);
                    if (this.stakeholderFilters.is_bypass) url += '&is_bypass=' + encodeURIComponent(this.stakeholderFilters.is_bypass);

                    const { res, data } = await this.makeRequest('GET', url);
                    this.loadingStakeholders = false;

                    if (res && res.ok && data) {
                        this.stakeholdersList = data.data || [];
                        this.stakeholdersMeta = data.meta || { current_page: 1, last_page: 1, total: this.stakeholdersList.length };
                    }
                },

                async getStakeholderDetail(id) {
                    const { res, data } = await this.makeRequest('GET', this.apiPrefix + '/stakeholders/' + id);
                    if (res && res.ok) {
                        this.showToast('Data stakeholder #' + id + ' berhasil diambil!');
                    }
                },

                openEditRoleModal(item) {
                    this.editRoleTarget = {
                        id: item.id,
                        email: item.email,
                        role: item.user_role || 'student',
                        nim: item.student_profile?.nim || '',
                        is_bypass: item.student_profile?.is_bypass || false
                    };
                    this.showEditRoleModal = true;
                },

                async submitEditRole() {
                    const payload = {
                        role: this.editRoleTarget.role
                    };
                    if (this.editRoleTarget.role === 'student') {
                        if (this.editRoleTarget.nim) payload.nim = this.editRoleTarget.nim;
                        payload.is_bypass = this.editRoleTarget.is_bypass;
                    }

                    const { res, data } = await this.makeRequest('PUT', this.apiPrefix + '/stakeholders/' + this.editRoleTarget.id + '/role', payload);
                    if (res && res.ok) {
                        this.showToast('Role berhasil diperbarui!');
                        this.showEditRoleModal = false;
                        this.fetchStakeholders(this.stakeholdersMeta.current_page);
                        this.fetchDevUsers();
                    } else {
                        this.showToast('Gagal update role: ' + (data?.message || 'Error'));
                    }
                },

                async submitAssignRoleByEmail() {
                    const payload = {
                        email: this.assignRoleByEmailData.email,
                        role: this.assignRoleByEmailData.role
                    };
                    if (this.assignRoleByEmailData.role === 'student') {
                        if (this.assignRoleByEmailData.nim) payload.nim = this.assignRoleByEmailData.nim;
                        payload.is_bypass = this.assignRoleByEmailData.is_bypass;
                    }

                    const { res, data } = await this.makeRequest('POST', this.apiPrefix + '/stakeholders/assign-role', payload);
                    if (res && res.ok) {
                        this.showToast('Role via email berhasil diperbarui!');
                        this.showAssignRoleModal = false;
                        this.fetchStakeholders(this.stakeholdersMeta.current_page);
                        this.fetchDevUsers();
                    } else {
                        this.showToast('Gagal assign role: ' + (data?.message || 'Error'));
                    }
                },

                async deleteStakeholder(item) {
                    if (!confirm(`Apakah Anda yakin ingin menghapus stakeholder "${item.name || item.email}"?`)) return;

                    const { res, data } = await this.makeRequest('DELETE', this.apiPrefix + '/stakeholders/' + item.id);
                    if (res && res.ok) {
                        this.showToast('Stakeholder berhasil dihapus!');
                        this.fetchStakeholders(this.stakeholdersMeta.current_page);
                        this.fetchDevUsers();
                    } else {
                        this.showToast('Gagal menghapus: ' + (data?.message || 'Error'));
                    }
                },

                async submitSingleStakeholder() {
                    const payload = {
                        email: this.singleStakeholder.email,
                        role: this.singleStakeholder.role,
                        name: this.singleStakeholder.name || null
                    };
                    if (this.singleStakeholder.role === 'student') {
                        if (this.singleStakeholder.nim) payload.nim = this.singleStakeholder.nim;
                        payload.is_bypass = this.singleStakeholder.is_bypass;
                    }

                    const { res, data } = await this.makeRequest('POST', this.apiPrefix + '/stakeholders', payload);
                    if (res && (res.status === 201 || res.status === 200)) {
                        this.showToast('Stakeholder berhasil ditambahkan ke whitelist!');
                        this.showAddStakeholderModal = false;
                        this.singleStakeholder = { email: '', role: 'student', name: '', nim: '', is_bypass: false };
                        this.fetchStakeholders(1);
                        this.fetchDevUsers();
                    } else {
                        this.showToast('Error: ' + (data?.message || 'Gagal menyimpan'));
                    }
                },

                addBatchStakeholderRow() {
                    this.batchStakeholders.push({ email: '', role: 'student', name: '', nim: '', is_bypass: false });
                },

                removeBatchStakeholderRow(idx) {
                    if (this.batchStakeholders.length > 1) this.batchStakeholders.splice(idx, 1);
                },

                fillSampleBatchStakeholders() {
                    this.batchStakeholders = [
                        { email: 'mhs1.' + Date.now() + '@mail.ugm.ac.id', role: 'student', name: 'Mahasiswa Satu', nim: '21/470001/SV/19001', is_bypass: false },
                        { email: 'asprak.' + Date.now() + '@mail.ugm.ac.id', role: 'assistant', name: 'Asisten Lab', nim: '20/460002/SV/18002', is_bypass: false },
                        { email: 'praktisi.' + Date.now() + '@mail.ugm.ac.id', role: 'practitioner', name: 'Praktisi PT Maju', nim: '', is_bypass: false }
                    ];
                },

                async submitBatchStakeholders() {
                    const payload = {
                        stakeholders: this.batchStakeholders
                    };

                    const { res, data } = await this.makeRequest('POST', this.apiPrefix + '/stakeholders', payload);
                    if (res && (res.status === 201 || res.status === 200)) {
                        this.showToast('Batch stakeholder berhasil disimpan!');
                        this.showAddStakeholderModal = false;
                        this.fetchStakeholders(1);
                        this.fetchDevUsers();
                    } else {
                        this.showToast('Error: ' + (data?.message || 'Gagal menyimpan batch'));
                    }
                },

                // ==========================================
                // LECTURER ACTIONS
                // ==========================================
                async submitSingleLecturer() {
                    const endpoint = this.apiPrefix + '/' + this.lecturerEndpoint;
                    const payload = {
                        email: this.singleLecturer.email,
                        name: this.singleLecturer.name || null
                    };

                    const { res, data } = await this.makeRequest('POST', endpoint, payload);
                    if (res && (res.status === 201 || res.status === 200)) {
                        this.showToast('Dosen pengampu berhasil diinisiasi sebagai Full Admin!');
                        this.singleLecturer = { email: '', name: '' };
                        this.fetchDevUsers();
                    } else {
                        this.showToast('Error: ' + (data?.message || 'Gagal inisiasi'));
                    }
                },

                addBatchLecturerRow() {
                    this.batchLecturers.push({ email: '', name: '' });
                },

                removeBatchLecturerRow(idx) {
                    if (this.batchLecturers.length > 1) this.batchLecturers.splice(idx, 1);
                },

                fillSampleBatchLecturers() {
                    const rnd = Math.floor(Math.random() * 1000);
                    this.batchLecturers = [
                        { email: `dosen.utama${rnd}@mail.ugm.ac.id`, name: 'Dr. Dosen Utama, M.Kom.' },
                        { email: `dosen.pendamping${rnd}@mail.ugm.ac.id`, name: 'Ir. Dosen Pendamping, Ph.D.' }
                    ];
                },

                async submitBatchLecturer() {
                    const endpoint = this.apiPrefix + '/' + this.lecturerEndpoint;
                    const payload = {
                        lecturers: this.batchLecturers
                    };

                    const { res, data } = await this.makeRequest('POST', endpoint, payload);
                    if (res && (res.status === 201 || res.status === 200)) {
                        this.showToast('Batch dosen pengampu berhasil diinisiasi!');
                        this.fetchDevUsers();
                    } else {
                        this.showToast('Error: ' + (data?.message || 'Gagal inisiasi'));
                    }
                },

                // ==========================================
                // AUTH & SSO ACTIONS
                // ==========================================
                async getGoogleRedirectUrl() {
                    let endpoint = this.apiPrefix + '/auth/google/redirect';
                    if (this.googleRedirectUri) {
                        endpoint += '?redirect_uri=' + encodeURIComponent(this.googleRedirectUri);
                    }

                    const { res, data } = await this.makeRequest('GET', endpoint);
                    if (res && res.ok && data?.url) {
                        this.googleGeneratedUrl = data.url;
                        this.showToast('URL Google OAuth berhasil dibuat!');
                    } else {
                        this.showToast('Error: ' + (data?.message || 'Gagal mengambil URL'));
                    }
                },

                async submitGoogleCallback() {
                    const endpoint = this.apiPrefix + '/auth/google/callback';
                    const payload = {};
                    if (this.googleAuthCallback.code) payload.code = this.googleAuthCallback.code;
                    if (this.googleAuthCallback.token) payload.token = this.googleAuthCallback.token;
                    if (this.googleAuthCallback.redirect_uri) payload.redirect_uri = this.googleAuthCallback.redirect_uri;

                    const { res, data } = await this.makeRequest('POST', endpoint, payload);
                    if (res && res.ok && data?.token) {
                        this.setToken(data.token);
                        this.showToast('Login SSO berhasil! Sanctum token aktif.');
                    } else {
                        this.showToast('Login gagal: ' + (data?.message || 'Cek console/response'));
                    }
                },

                async fetchAuthMe() {
                    const { res, data } = await this.makeRequest('GET', this.apiPrefix + '/auth/me', null, true);
                    if (res && res.ok) {
                        this.showToast('Profil pengguna (/me) berhasil diambil!');
                    } else {
                        this.showToast('Error /me: ' + (data?.message || 'Token tidak valid / expired'));
                    }
                },

                async submitLogout() {
                    const { res, data } = await this.makeRequest('POST', this.apiPrefix + '/auth/logout', null, true);
                    if (res && res.ok) {
                        this.showToast('Berhasil logout! Token dihapus.');
                        this.clearToken();
                    } else {
                        this.showToast('Logout gagal: ' + (data?.message || 'Error'));
                    }
                },

                // ==========================================
                // UNIVERSAL CONSOLE
                // ==========================================
                applyPreset(method, path, body = '', useAuth = false) {
                    this.consoleReq.method = method;
                    this.consoleReq.path = path;
                    this.consoleReq.body = body;
                    this.consoleReq.useAuth = useAuth;
                },

                formatConsoleJson() {
                    try {
                        if (this.consoleReq.body.trim()) {
                            const parsed = JSON.parse(this.consoleReq.body);
                            this.consoleReq.body = JSON.stringify(parsed, null, 2);
                        }
                    } catch (e) {
                        this.showToast('Format JSON tidak valid!');
                    }
                },

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

                    await this.makeRequest(this.consoleReq.method, this.consoleReq.path, body, this.consoleReq.useAuth);
                },

                // ==========================================
                // DEV TOKEN ACTIONS
                // ==========================================
                async fetchDevUsers() {
                    try {
                        const res = await fetch('/dev/users');
                        if (res.ok) {
                            const data = await res.json();
                            this.devUsersList = data.data || [];
                        }
                    } catch (e) {}
                },

                async generateDevTokenAction() {
                    const targetEmail = this.selectedDevUserEmail || this.manualDevEmail;
                    if (!targetEmail) {
                        this.showToast('Pilih atau masukkan email user!');
                        return;
                    }

                    await this.generateDevTokenForUser(targetEmail);
                    this.showDevTokenModal = false;
                },

                async generateDevTokenForUser(email) {
                    this.loadingApi = true;
                    try {
                        const res = await fetch('/dev/generate-token', {
                            method: 'POST',
                            headers: this.getHeaders(),
                            body: JSON.stringify({
                                email: email,
                                create_if_missing: this.createIfMissing
                            })
                        });

                        const data = await res.json();
                        if (res.ok && data?.token) {
                            this.setToken(data.token);
                            this.showToast('Sanctum token dibuat untuk ' + email);
                            this.lastResponse = {
                                status: res.status,
                                statusText: res.statusText,
                                duration: 15,
                                method: 'POST',
                                url: '/dev/generate-token',
                                timestamp: new Date().toLocaleTimeString(),
                                data: data
                            };
                            this.fetchDevUsers();
                        } else {
                            this.showToast('Gagal: ' + (data?.message || 'Error'));
                        }
                    } catch (e) {
                        this.showToast('Error dev token: ' + e.message);
                    } finally {
                        this.loadingApi = false;
                    }
                },

                setToken(token) {
                    this.bearerToken = token;
                    localStorage.setItem('pad_test_bearer_token', token);
                },

                clearToken() {
                    this.bearerToken = '';
                    localStorage.removeItem('pad_test_bearer_token');
                    this.showToast('Token dihapus dari tester.');
                },

                copyToClipboard(text, successMsg) {
                    navigator.clipboard.writeText(text).then(() => {
                        this.showToast(successMsg);
                    }).catch(() => {
                        this.showToast('Gagal menyalin text.');
                    });
                },

                // Helpers for badge classes
                getRoleBadgeClass(role) {
                    switch (role) {
                        case 'admin': return 'bg-emerald-100 text-emerald-800 border border-emerald-300';
                        case 'lecturer': return 'bg-purple-100 text-purple-800 border border-purple-300';
                        case 'student': return 'bg-sky-100 text-sky-800 border border-sky-300';
                        case 'assistant': return 'bg-amber-100 text-amber-800 border border-amber-300';
                        case 'practitioner': return 'bg-teal-100 text-teal-800 border border-teal-300';
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