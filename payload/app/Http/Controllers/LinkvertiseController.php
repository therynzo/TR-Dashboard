<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
use App\Support\ActivityLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\QueryException;
use Throwable;

class LinkvertiseController extends Controller
{
    public function start(Request $request): RedirectResponse
    {
        $this->assertEnabled();
        $url = trim((string) Settings::get('linkvertise_url', ''));
        abort_unless(filter_var($url, FILTER_VALIDATE_URL) && str_starts_with(strtolower($url), 'https://'), 503, 'The Linkvertise destination is not configured.');
        return redirect()->away($url);
    }

    public function complete(Request $request): RedirectResponse
    {
        $this->assertEnabled();
        $hash = trim((string) $request->query('hash', ''));
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $hash)) {
            logger()->notice('Linkvertise return did not include a valid completion hash.', [
                'user_id' => $request->user()->id,
                'hash_present' => $hash !== '',
                'hash_length' => strlen($hash),
            ]);
            return redirect()->route('credits')->with('error', 'Linkvertise returned without a valid 64-character completion hash. In Linkvertise, make sure your published item is a Target Link (not a Paste Link) and its target URL is exactly the Verified completion target URL shown in Admin → Linkvertise Rewards. Then complete the task again.');
        }

        $user = $request->user();
        $hashFingerprint = hash('sha256', $hash);
        if (DB::table('linkvertise_reward_claims')->where('hash_fingerprint', $hashFingerprint)->exists()) {
            return redirect()->route('credits')->with('error', 'This Linkvertise completion has already been claimed.');
        }

        $dailyLimit = max(1, (int) Settings::get('linkvertise_daily_limit', 1));
        $reward = max(0, (float) Settings::get('linkvertise_reward_credits', 10));
        $token = trim((string) Settings::get('linkvertise_anti_bypass_token', ''));
        if ($reward <= 0 || $token === '') {
            return redirect()->route('credits')->with('error', 'Linkvertise rewards are not fully configured. Contact an administrator.');
        }

        $alreadyClaimedToday = CreditTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'linkvertise_reward')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
        if ($alreadyClaimedToday >= $dailyLimit) {
            return redirect()->route('credits')->with('error', 'You have reached today’s Linkvertise reward limit.');
        }

        try {
            $verifyUrl = 'https://publisher.linkvertise.com/api/v1/anti_bypassing?' . http_build_query([
                'token' => $token,
                'hash' => $hash,
            ], '', '&', PHP_QUERY_RFC3986);
            $response = Http::connectTimeout(3)->timeout(6)->post($verifyUrl);
            // Linkvertise documents plain-text TRUE/FALSE responses. Normalize whitespace
            // and optional quotes so formatting does not turn a valid response into failure.
            $verificationBody = strtoupper(trim(trim($response->body()), " \t\n\r\0\x0B\"'"));

            if (!$response->successful()) {
                logger()->warning('Linkvertise verification endpoint returned an HTTP error.', [
                    'user_id' => $user->id,
                    'http_status' => $response->status(),
                ]);
                $message = $response->status() === 401 || $response->status() === 403
                    ? 'Linkvertise rejected the anti-bypass token. Re-copy the current token from Linkvertise Settings → Anti-Bypassing, save it in TR Dashboard, and retry.'
                    : 'Linkvertise verification could not be reached (HTTP ' . $response->status() . '). No credits were awarded; please retry shortly.';
                return redirect()->route('credits')->with('error', $message);
            }

            if ($verificationBody !== 'TRUE') {
                logger()->notice('Linkvertise rejected a completion hash.', [
                    'user_id' => $user->id,
                    'verification_result' => mb_substr($verificationBody, 0, 32),
                ]);
                if (str_contains($verificationBody, 'TOKEN')) {
                    $message = 'Linkvertise rejected the anti-bypass token. Copy the current token from Linkvertise Settings → Anti-Bypassing and save it in TR Dashboard.';
                } elseif ($verificationBody === 'FALSE' || str_contains($verificationBody, 'HASH')) {
                    $message = 'Linkvertise could not find this completion hash. Confirm your Linkvertise item is a Target Link (not a Paste Link), its target URL exactly matches the Verified completion target URL in TR Dashboard, and anti-bypassing is enabled. Hashes expire quickly, so retry from the Credits page.';
                } else {
                    $message = 'Linkvertise did not verify this completion (' . mb_substr($verificationBody ?: 'empty response', 0, 60) . '). Check the saved anti-bypass token and Target Link settings. No credits were awarded.';
                }
                return redirect()->route('credits')->with('error', $message);
            }

            DB::transaction(function () use ($user, $reward, $hashFingerprint, $dailyLimit) {
                // Store a unique fingerprint of the provider hash before awarding credits.
                // A unique DB index prevents a concurrent/replayed callback from paying twice.
                if (DB::table('linkvertise_reward_claims')->where('hash_fingerprint', $hashFingerprint)->exists()) {
                    throw new \RuntimeException('This Linkvertise completion has already been claimed.');
                }
                DB::table('linkvertise_reward_claims')->insert([
                    'user_id' => $user->id,
                    'hash_fingerprint' => $hashFingerprint,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $locked = $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $count = CreditTransaction::query()->where('user_id', $locked->id)->where('type', 'linkvertise_reward')->where('created_at', '>=', now()->startOfDay())->count();
                if ($count >= $dailyLimit) {
                    throw new \RuntimeException('Daily reward limit reached.');
                }
                $locked->credits = round((float) $locked->credits + $reward, 2);
                $locked->save();
                CreditTransaction::create([
                    'user_id' => $locked->id,
                    'amount' => $reward,
                    'balance_after' => $locked->credits,
                    'type' => 'linkvertise_reward',
                    'description' => 'Verified Linkvertise reward ' . substr($hashFingerprint, 0, 24),
                ]);
            });

            ActivityLogger::record($user->id, $user->username, 'linkvertise_reward', 'Received a verified Linkvertise reward.', $request);
            return redirect()->route('credits')->with('success', 'Linkvertise task verified. ' . number_format($reward, 2) . ' credits added to your balance.');
        } catch (Throwable $e) {
            if ($e instanceof QueryException && DB::table('linkvertise_reward_claims')->where('hash_fingerprint', $hashFingerprint)->exists()) {
                return redirect()->route('credits')->with('error', 'This Linkvertise completion has already been claimed.');
            }
            // Do not log the exception text: connection exceptions may include the
            // Linkvertise token that is carried in the verification URL query.
            logger()->warning('Linkvertise reward verification failed.', [
                'user_id' => $user->id,
                'exception_class' => get_class($e),
            ]);
            return redirect()->route('credits')->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'Could not verify the Linkvertise task. Please try again later.');
        }
    }

    private function assertEnabled(): void
    {
        abort_unless(Settings::enabled('linkvertise_enabled'), 404);
    }
}
