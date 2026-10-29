<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemSettingController extends Controller
{
    /**
     * Get system configuration parameters from app_settings.
     */
    public function index(Request $request): JsonResponse
    {
        $allSettings = AppSetting::all()->pluck('value', 'key')->toArray();

        $rawRole2 = $allSettings['is_role_2_required'] ?? $allSettings['is_role_2_mandatory'] ?? 'false';
        $isRole2Required = in_array(strtolower((string) $rawRole2), ['true', '1', 'wajib', 'yes'], true);

        return response()->json([
            'status' => 'success',
            'message' => 'Parameter konfigurasi sistem berhasil diambil.',
            'data' => [
                'is_role_2_required' => $isRole2Required,
                'is_role_2_required_label' => $isRole2Required ? 'wajib' : 'opsional',
                'registration_timeline' => $allSettings['registration_timeline'] ?? null,
                'registration_terms' => $allSettings['registration_terms'] ?? null,
                'registration_status' => $allSettings['registration_status'] ?? 'open',
                'academic_year' => $allSettings['academic_year'] ?? null,
                'all_settings' => $allSettings,
            ],
        ]);
    }

    /**
     * Update system configuration parameters (is_role_2_required toggle, timeline, terms).
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'is_role_2_required' => ['nullable'],
            'is_role_2_mandatory' => ['nullable'],
            'registration_timeline' => ['nullable', 'string', 'max:255'],
            'registration_terms' => ['nullable', 'string', 'max:255'],
            'registration_status' => ['nullable', 'string', 'max:50'],
            'academic_year' => ['nullable', 'string', 'max:50'],
            'settings' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($request) {
            // 1. Toggle is_role_2_required (wajib / opsional)
            if ($request->has('is_role_2_required') || $request->has('is_role_2_mandatory')) {
                $raw = $request->input('is_role_2_required') ?? $request->input('is_role_2_mandatory');
                $isRequired = is_bool($raw)
                    ? $raw
                    : in_array(strtolower(trim((string) $raw)), ['true', '1', 'wajib', 'yes'], true);

                $strValue = $isRequired ? 'true' : 'false';

                AppSetting::setValue('is_role_2_required', $strValue);
                AppSetting::setValue('is_role_2_mandatory', $strValue);
            }

            // 2. Timeline / jadwal pendaftaran
            if ($request->has('registration_timeline')) {
                AppSetting::setValue('registration_timeline', (string) $request->input('registration_timeline'));
            }

            // 3. Info syarat pendaftaran
            if ($request->has('registration_terms')) {
                AppSetting::setValue('registration_terms', (string) $request->input('registration_terms'));
            }

            // 4. Status pendaftaran & tahun akademik
            if ($request->has('registration_status')) {
                AppSetting::setValue('registration_status', (string) $request->input('registration_status'));
            }

            if ($request->has('academic_year')) {
                AppSetting::setValue('academic_year', (string) $request->input('academic_year'));
            }

            // 5. Batch custom settings jika disediakan
            if ($request->has('settings') && is_array($request->input('settings'))) {
                foreach ((array) $request->input('settings') as $k => $v) {
                    AppSetting::setValue((string) $k, is_scalar($v) ? (string) $v : json_encode($v));
                }
            }
        });

        return $this->index($request);
    }
}
