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
        $hash = (string) $request->query('hash', '');
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $hash)) {
            return redirect()->route('dashboard')->with('error', 'Linkvertise did not return a valid completion token. Complete the sponsor task first.');
        }

        $user = $request->user();
        $hashFingerprint = hash('sha256', $hash);
        if (DB::table('linkvertise_reward_claims')->where('hash_fingerprint', $hashFingerprint)->exists()) {
            return redirect()->route('dashboard')->with('error', 'This Linkvertise completion has already been claimed.');
        }

        $dailyLimit = max(1, (int) Settings::get('linkvertise_daily_limit', 1));
        $reward = max(0, (float) Settings::get('linkvertise_reward_credits', 10));
        $token = trim((string) Settings::get('linkvertise_anti_bypass_token', ''));
        if ($reward <= 0 || $token === '') {
            return redirect()->route('dashboard')->with('error', 'Linkvertise rewards are not fully configured. Contact an administrator.');
        }

        $alreadyClaimedToday = CreditTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'linkvertise_reward')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
        if ($alreadyClaimedToday >= $dailyLimit) {
            return redirect()->route('dashboard')->with('error', 'You have reached today’s Linkvertise reward limit.');
        }

        try {
            $verifyUrl = 'https://publisher.linkvertise.com/api/v1/anti_bypassing?' . http_build_query([
                'token' => $token,
                'hash' => $hash,
            ], '', '&', PHP_QUERY_RFC3986);
            $response = Http::timeout(8)->post($verifyUrl);
            $verified = strtoupper(trim($response->body())) === 'TRUE';
            if (!$response->successful() || !$verified) {
                return redirect()->route('dashboard')->with('error', 'Linkvertise could not verify the completed task. No credits were awarded.');
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
            return redirect()->route('dashboard')->with('success', 'Linkvertise task verified. ' . number_format($reward, 2) . ' credits added to your balance.');
        } catch (Throwable $e) {
            if ($e instanceof QueryException && DB::table('linkvertise_reward_claims')->where('hash_fingerprint', $hashFingerprint)->exists()) {
                return redirect()->route('dashboard')->with('error', 'This Linkvertise completion has already been claimed.');
            }
            // Do not log the exception text: connection exceptions may include the
            // Linkvertise token that is carried in the verification URL query.
            logger()->warning('Linkvertise reward verification failed.', [
                'user_id' => $user->id,
                'exception_class' => get_class($e),
            ]);
            return redirect()->route('dashboard')->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'Could not verify the Linkvertise task. Please try again later.');
        }
    }

    private function assertEnabled(): void
    {
        abort_unless(Settings::enabled('linkvertise_enabled'), 404);
    }
}
