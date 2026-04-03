<?php

declare(strict_types=1);

namespace App\Services\Composer;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches @mention candidates from each network’s APIs (backend-only; never exposes tokens to the browser).
 * Availability depends on app permissions and each provider’s API — some networks return few or no results.
 */
class MentionSuggestionService
{
    /**
     * @return list<array{username: string, name: string}>
     */
    public function search(SocialAccount $account, string $platform, string $query): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }

        return match ($platform) {
            'twitter'   => $this->twitterSuggestions($account, $q),
            'linkedin'  => $this->linkedinSuggestions($account, $q),
            'facebook'  => $this->facebookGraphSearch($account, $q, ['page', 'user']),
            'instagram' => $this->facebookGraphSearch($account, $q, ['page', 'user']),
            'threads'   => $this->threadsSuggestions($account, $q),
            default     => [],
        };
    }

    /**
     * @return list<array{username: string, name: string}>
     */
    private function twitterSuggestions(SocialAccount $account, string $q): array
    {
        $response = Http::withToken($account->access_token)
            ->timeout(15)
            ->acceptJson()
            ->get('https://api.twitter.com/1.1/users/search.json', [
                'q'     => $q,
                'count' => 10,
            ]);

        if (! $response->successful()) {
            Log::warning('Twitter users/search failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [];
        }

        $users = $response->json();
        if (! is_array($users)) {
            return [];
        }

        $out = [];
        foreach ($users as $u) {
            if (! is_array($u)) {
                continue;
            }
            $sn = $u['screen_name'] ?? null;
            if (! is_string($sn) || $sn === '') {
                continue;
            }
            $out[] = [
                'username' => $sn,
                'name'     => is_string($u['name'] ?? null) && $u['name'] !== '' ? $u['name'] : $sn,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{username: string, name: string}>
     */
    private function linkedinSuggestions(SocialAccount $account, string $q): array
    {
        $response = Http::withToken($account->access_token)
            ->timeout(15)
            ->acceptJson()
            ->withHeaders([
                'X-Restli-Protocol-Version' => '2.0.0',
                'LinkedIn-Version'          => '202401',
            ])
            ->get('https://api.linkedin.com/v2/people', [
                'q'        => 'peopleSearch',
                'keywords' => $q,
                'count'    => 10,
            ]);

        if (! $response->successful()) {
            Log::warning('LinkedIn people search failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [];
        }

        $elements = $response->json('elements');
        if (! is_array($elements)) {
            return [];
        }

        $out = [];
        foreach ($elements as $el) {
            if (! is_array($el)) {
                continue;
            }
            $fn = $this->linkedinLocalizedName($el['firstName'] ?? null);
            $ln = $this->linkedinLocalizedName($el['lastName'] ?? null);
            $name = trim($fn . ' ' . $ln);
            $vanity = $el['vanityName'] ?? null;
            if (is_string($vanity) && $vanity !== '') {
                $handle = $vanity;
            } else {
                $id = $el['id'] ?? null;
                if (! is_string($id) && ! is_numeric($id)) {
                    continue;
                }
                $handle = 'user_' . preg_replace('/\D/', '', (string) $id);
            }
            $out[] = [
                'username' => $handle,
                'name'     => $name !== '' ? $name : $handle,
            ];
        }

        return $out;
    }

    private function linkedinLocalizedName(mixed $node): string
    {
        if (is_string($node)) {
            return $node;
        }
        if (! is_array($node)) {
            return '';
        }
        if (isset($node['localized']) && is_array($node['localized'])) {
            $loc = $node['localized'];
            $v = reset($loc);

            return is_string($v) ? $v : '';
        }

        $v = reset($node);

        return is_string($v) ? $v : '';
    }

    /**
     * Meta Graph search (used for Facebook; Instagram tokens often work on graph.facebook.com for search).
     *
     * @param list<string> $types
     * @return list<array{username: string, name: string}>
     */
    private function facebookGraphSearch(SocialAccount $account, string $q, array $types): array
    {
        $seen = [];
        $out  = [];

        foreach ($types as $type) {
            $response = Http::withToken($account->access_token)
                ->timeout(15)
                ->acceptJson()
                ->get('https://graph.facebook.com/v21.0/search', [
                    'q'    => $q,
                    'type' => $type,
                ]);

            if (! $response->successful()) {
                continue;
            }

            $data = $response->json('data');
            if (! is_array($data)) {
                continue;
            }

            foreach ($data as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = $row['name'] ?? '';
                if (! is_string($name) || $name === '') {
                    continue;
                }
                $username = $row['username'] ?? $row['id'] ?? null;
                if (! is_string($username) && ! is_numeric($username)) {
                    $username = preg_replace('/\s+/', '', $name);
                }
                $username = is_string($username) ? $username : (string) $username;
                $key      = strtolower($username);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[]      = [
                    'username' => $username,
                    'name'     => $name,
                ];
                if (count($out) >= 10) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array{username: string, name: string}>
     */
    private function threadsSuggestions(SocialAccount $account, string $q): array
    {
        $response = Http::withToken($account->access_token)
            ->timeout(15)
            ->acceptJson()
            ->get('https://graph.threads.net/v1.0/search', [
                'q'    => $q,
                'type' => 'user',
            ]);

        if ($response->successful()) {
            return $this->mapMetaSearchData($response->json('data'));
        }

        return $this->facebookGraphSearch($account, $q, ['user', 'page']);
    }

    /**
     * @return list<array{username: string, name: string}>
     */
    private function mapMetaSearchData(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = $row['name'] ?? '';
            if (! is_string($name) || $name === '') {
                continue;
            }
            $username = $row['username'] ?? $row['id'] ?? null;
            if (! is_string($username) && ! is_numeric($username)) {
                continue;
            }
            $username = (string) $username;
            $out[]      = [
                'username' => $username,
                'name'     => $name,
            ];
            if (count($out) >= 10) {
                break;
            }
        }

        return $out;
    }
}
