<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Branch;
use Illuminate\Support\Str;

/**
 * Phase 9c-3 rebuild — public renderer for an agency's (or branch's)
 * privacy policy. Token-only credential; no auth, no agency middleware.
 * 404 when token doesn't match, when published_at is null, or when the
 * resolved markdown is empty.
 */
final class PrivacyPolicyController extends Controller
{
    public function show(string $token)
    {
        // Try branch first — a branch override token always wins over the
        // agency token if they ever collide (the unique constraints on
        // both columns make collision impossible, but the order makes the
        // resolution deterministic).
        $branch = Branch::where('privacy_policy_token', $token)->first();
        if ($branch) {
            abort_unless($branch->privacy_policy_published_at !== null, 404);
            $markdown = $branch->effectivePrivacyPolicyMarkdown();
            abort_if(empty($markdown), 404);
            $agency = $branch->agency;
            $logoPath = $branch->logo_path ?: $agency?->logo_path;
            $title = $branch->name ?: $agency?->name;
            $lastUpdated = $branch->privacy_policy_published_at;
            return $this->renderView($markdown, $title, $agency, $logoPath, $lastUpdated);
        }

        $agency = Agency::where('privacy_policy_token', $token)->first();
        if ($agency) {
            abort_unless($agency->privacy_policy_published_at !== null, 404);
            $markdown = (string) ($agency->privacy_policy_markdown ?? '');
            abort_if($markdown === '', 404);
            return $this->renderView(
                $markdown,
                $agency->name,
                $agency,
                $agency->logo_path,
                $agency->privacy_policy_published_at,
            );
        }

        abort(404);
    }

    private function renderView(string $markdown, ?string $title, ?Agency $agency, ?string $logoPath, $lastUpdated)
    {
        $renderedHtml = (string) Str::markdown($markdown);
        return view('public.privacy-policy', [
            'title'        => $title ?: 'Privacy Policy',
            'agency'       => $agency,
            'logoUrl'      => $logoPath ? asset('storage/' . $logoPath) : null,
            'renderedHtml' => $renderedHtml,
            'lastUpdated'  => $lastUpdated,
        ]);
    }
}
