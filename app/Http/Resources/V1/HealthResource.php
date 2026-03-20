<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HealthResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource['status'],
            'app' => $this->resource['app'],
            'api_version' => $this->resource['api_version'],
            'timestamp' => $this->resource['timestamp'],
        ];
    }
}
