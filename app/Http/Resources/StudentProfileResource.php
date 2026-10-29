<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nim' => $this->nim,
            'is_bypass' => (bool) $this->is_bypass,
            'role_1_id' => $this->role_1_id,
            'role_2_id' => $this->role_2_id,
            'team_id' => $this->team_id,
            'portfolio_link' => $this->portfolio_link,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
