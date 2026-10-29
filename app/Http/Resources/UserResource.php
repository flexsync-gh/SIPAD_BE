<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleEnum = $this->user_role instanceof UserRole
            ? $this->user_role
            : ($this->user_role ? UserRole::tryFrom((string) $this->user_role) : null);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'google_id_sub' => $this->google_id_sub,
            'user_role' => $roleEnum?->value ?? (string) $this->user_role,
            'role' => $roleEnum?->value ?? (string) $this->user_role,
            'role_label' => $roleEnum?->label(),
            'avatar_link' => $this->avatar_link,
            'status' => $this->status,
            'is_bypass' => (bool) ($this->studentProfile?->is_bypass ?? false),
            'student_profile' => new StudentProfileResource($this->whenLoaded('studentProfile')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
