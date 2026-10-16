<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\SettingResource;
use App\Http\Resources\ShotResource;
use App\Services\Devices;
use App\Services\Sync\SyncPull;
use App\Services\Sync\SyncPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(private Devices $devices) {}

    public function push(Request $request, SyncPush $push): JsonResponse
    {
        $result = $push->handle($request->user(), $request->json()->all());

        $this->devices->touch($request->user(), $request);

        return response()->json($result);
    }

    public function pull(Request $request, SyncPull $pull): JsonResponse
    {
        $data = $request->validate([
            'since' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'between:1,500'],
        ]);

        $page = $pull->handle($request->user(), (int) ($data['since'] ?? 0), (int) ($data['limit'] ?? 500));

        $this->devices->touch($request->user(), $request, $page['next_since']);

        return response()->json([
            'shots' => ShotResource::collection($page['items']['shots']),
            'categories' => CategoryResource::collection($page['items']['categories']),
            'settings' => SettingResource::collection($page['items']['settings']),
            'backups' => [],
            'next_since' => $page['next_since'],
            'has_more' => $page['has_more'],
        ]);
    }
}
