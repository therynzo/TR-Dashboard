<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
use App\Models\RedeemCode;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RedeemController extends Controller
{
    public function redeem(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $user = $request->user();

        try {
            DB::transaction(function () use ($data, $user) {
                $code = RedeemCode::query()->where('code', strtoupper(trim($data['code'])))->lockForUpdate()->first();
                if (!$code) {
                    throw new RuntimeException('That redeem code was not found.');
                }
                if ($code->expires_at && $code->expires_at->isPast()) {
                    throw new RuntimeException('That redeem code has expired.');
                }
                if ($code->uses >= $code->max_uses) {
                    throw new RuntimeException('That redeem code has already reached its redemption limit.');
                }
                if ($code->redeemers()->where('users.id', $user->id)->exists()) {
                    throw new RuntimeException('You have already redeemed that code.');
                }
                if ((float) $code->credits <= 0 && (int) $code->slot_bonus <= 0) {
                    throw new RuntimeException('This code does not contain a credit or slot reward.');
                }

                $lockedUser = $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $lockedUser->credits = round((float) $lockedUser->credits + (float) $code->credits, 2);
                $lockedUser->server_slots = (int) $lockedUser->server_slots + (int) $code->slot_bonus;
                $lockedUser->save();
                $code->redeemers()->attach($lockedUser->id);
                $code->increment('uses');

                if ((float) $code->credits > 0) {
                    CreditTransaction::create([
                        'user_id' => $lockedUser->id,
                        'amount' => $code->credits,
                        'balance_after' => $lockedUser->credits,
                        'type' => 'redeem',
                        'description' => 'Redeemed code ' . $code->code,
                    ]);
                }
            });

            ActivityLogger::record($user->id, $user->username, 'code_redeemed', 'Redeemed a reward code.', $request);
            return back()->with('success', 'Code redeemed successfully. Your account rewards have been added.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', $e instanceof RuntimeException ? $e->getMessage() : 'Could not redeem this code. Try again or contact support.');
        }
    }
}
