<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SkillResource;
use App\Models\Role;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    /**
     * Store a new skill item for a specific role.
     */
    public function store(Request $request): JsonResponse
    {
        $roleId = $request->input('role_id');

        if (! $roleId && $request->filled('role_code')) {
            $role = Role::where('code', strtoupper((string) $request->input('role_code')))->first();
            if ($role) {
                $roleId = $role->id;
            }
        }

        $request->merge(['role_id' => $roleId]);

        $validated = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'name' => ['required', 'string', 'max:100'],
        ], [
            'role_id.required' => 'Role ID atau Role Code wajib ditentukan.',
            'role_id.exists' => 'Role yang dipilih tidak ditemukan dalam sistem.',
            'name.required' => 'Nama skill wajib diisi.',
            'name.max' => 'Nama skill maksimal 100 karakter.',
        ]);

        $skill = Skill::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Item skillset berhasil ditambahkan ke role.',
            'data' => new SkillResource($skill),
        ], 201);
    }

    /**
     * Update an existing skill item.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $skill = Skill::find($id);

        if (! $skill) {
            return response()->json([
                'status' => 'error',
                'message' => 'Skill tidak ditemukan.',
            ], 404);
        }

        $roleId = $request->input('role_id');
        if (! $roleId && $request->filled('role_code')) {
            $role = Role::where('code', strtoupper((string) $request->input('role_code')))->first();
            if ($role) {
                $roleId = $role->id;
            }
        }
        if ($roleId) {
            $request->merge(['role_id' => $roleId]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'role_id' => ['sometimes', 'required', 'exists:roles,id'],
        ], [
            'name.required' => 'Nama skill wajib diisi.',
            'name.max' => 'Nama skill maksimal 100 karakter.',
            'role_id.exists' => 'Role yang dipilih tidak ditemukan.',
        ]);

        $skill->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Item skillset berhasil diperbarui.',
            'data' => new SkillResource($skill),
        ]);
    }

    /**
     * Remove an existing skill item.
     */
    public function destroy(int|string $id): JsonResponse
    {
        $skill = Skill::find($id);

        if (! $skill) {
            return response()->json([
                'status' => 'error',
                'message' => 'Skill tidak ditemukan.',
            ], 404);
        }

        $skill->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Item skillset berhasil dihapus.',
        ]);
    }
}
