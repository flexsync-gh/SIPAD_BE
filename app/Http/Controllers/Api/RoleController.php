<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Get list of roles (PM, UIUX, FE, BE) with their associated skills.
     */
    public function index(Request $request): JsonResponse
    {
        $roles = Role::with('skills')->orderBy('id')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar roles dan skillset berhasil diambil.',
            'data' => RoleResource::collection($roles),
        ]);
    }
}
