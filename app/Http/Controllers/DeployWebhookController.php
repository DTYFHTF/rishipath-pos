<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeployWebhookController extends Controller
{
    public function deploy(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $ref = (string) json_decode($request->getContent(), true)['ref'] ?? 'main';
        $allowedBranches = ['main', 'deploy-clean'];

        if (! in_array($ref, $allowedBranches, true)) {
            Log::warning('deploy-webhook: rejected unknown ref', ['ref' => $ref]);

            return response()->json(['error' => 'Unknown branch'], 422);
        }

        $script = base_path('scripts/deploy.sh');

        if (! is_file($script)) {
            Log::error("deploy-webhook: script not found at {$script}");

            return response()->json(['error' => 'Deploy script missing'], 500);
        }

        $logFile = storage_path('logs/deploy-'.date('Y-m-d-His').'.log');

        // nohup + </dev/null detaches the process so it outlives this HTTP request
        exec(sprintf(
            'DEPLOY_BRANCH=%s nohup bash %s > %s 2>&1 </dev/null &',
            escapeshellarg($ref),
            escapeshellarg($script),
            escapeshellarg($logFile)
        ));

        Log::info('deploy-webhook: deploy triggered', ['ref' => $ref, 'log' => $logFile]);

        return response()->json(['status' => 'deploy triggered', 'log' => basename($logFile)]);
    }

    public function assets(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $tarball = storage_path('app/assets-upload.tar.gz');
        file_put_contents($tarball, $request->getContent());

        // Extract public/build into the public directory
        $exitCode = null;
        exec(sprintf(
            'tar -xzf %s -C %s 2>&1',
            escapeshellarg($tarball),
            escapeshellarg(public_path())
        ), $output, $exitCode);

        @unlink($tarball);

        if ($exitCode !== 0) {
            Log::error('deploy-webhook: asset extraction failed', ['output' => $output]);

            return response()->json(['error' => 'Extraction failed', 'detail' => implode("\n", $output)], 500);
        }

        Log::info('deploy-webhook: assets updated');

        return response()->json(['status' => 'assets updated']);
    }

    private function verifySignature(Request $request): bool
    {
        $secret = env('DEPLOY_WEBHOOK_SECRET', '');

        if (empty($secret)) {
            Log::error('deploy-webhook: DEPLOY_WEBHOOK_SECRET not configured');

            return false;
        }

        $signature = $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('deploy-webhook: signature mismatch', ['ip' => $request->ip()]);

            return false;
        }

        return true;
    }
}
