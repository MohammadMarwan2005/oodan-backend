<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BackupResource;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function show(Request $request, string $id): BackupResource
    {
        $backup = $request->user()->backups()
            ->whereKey(strtolower($id))
            ->whereNull('deleted_at')
            ->firstOrFail();

        return new BackupResource($backup);
    }
}
