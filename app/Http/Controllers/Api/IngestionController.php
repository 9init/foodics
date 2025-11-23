<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Webhook\IngestionManager;
use Illuminate\Http\JsonResponse;

class IngestionController extends Controller
{
    public function __construct(
        private readonly IngestionManager $ingestionManager
    ) {
    }

    public function pauseIngestion(): JsonResponse
    {
        $this->ingestionManager->pause();

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook ingestion paused - jobs will be held in queue',
        ]);
    }

    public function resumeIngestion(): JsonResponse
    {
        $this->ingestionManager->resume();

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook ingestion resumed - queued jobs will now be processed',
        ]);
    }

    public function ingestionStatus(): JsonResponse
    {
        $isPaused = $this->ingestionManager->isPaused();
        return response()->json([
            'status' => $isPaused ? 'paused' : 'active',
            'paused' => $isPaused,
        ]);
    }
}
