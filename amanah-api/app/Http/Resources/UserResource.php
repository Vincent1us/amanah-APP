<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'email_verified' => $this->email_verified_at !== null,
            'phone_verified' => $this->phone_verified_at !== null,
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
