<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SocialAccount;
use App\Services\Composer\MentionSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ComposerMentionController extends Controller
{
    public function index(Request $request, MentionSuggestionService $mentions): JsonResponse
    {
        $validated = $request->validate([
            'platform'          => 'required|string|in:twitter,linkedin,facebook,instagram,threads',
            'q'                 => 'required|string|min:1|max:50',
            'social_account_id' => 'nullable|integer|exists:social_accounts,id',
        ]);

        $user = Auth::user();
        if ($user === null) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated.'], 401);
        }

        try {
            $account = $this->resolveSocialAccount(
                $user->id,
                $validated['platform'],
                $validated['social_account_id'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        try {
            $suggestions = $mentions->search($account, $validated['platform'], $validated['q']);
        } catch (\Throwable $e) {
            Log::warning('Composer mention search failed', [
                'user_id'  => $user->id,
                'platform' => $validated['platform'],
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'success'     => true,
                'suggestions' => [],
            ]);
        }

        return response()->json([
            'success'     => true,
            'suggestions' => $suggestions,
        ]);
    }

    private function resolveSocialAccount(int $userId, string $platform, ?int $accountId): SocialAccount
    {
        $query = SocialAccount::query()
            ->where('user_id', $userId)
            ->where('platform', $platform)
            ->active();

        if ($accountId !== null) {
            $account = $query->where('id', $accountId)->first();
        } else {
            $account = $query->first();
        }

        if ($account === null) {
            throw new InvalidArgumentException('No active connected account for this platform.');
        }

        if ($account->access_token === null || trim((string) $account->access_token) === '') {
            throw new InvalidArgumentException('This account needs to be reconnected before mention search works.');
        }

        return $account;
    }
}
