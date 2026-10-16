<?php

namespace App\Http\Resources;

use App\Models\Backup;
use Illuminate\Http\Request;

/**
 * Backup metadata plus its payload (an ExportFile document).
 *
 * @mixin Backup
 */
class BackupResource extends BackupMetaResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Decode to objects so empty JSON objects stay `{}` on the way out.
        return parent::toArray($request) + ['payload' => json_decode($this->payload)];
    }
}
