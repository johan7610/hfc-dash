<?php

namespace App\Services\Articles;

use App\Models\ArticlePool;
use App\Models\Presentation;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ArticleMatcherService
{
    /**
     * Suggest the most relevant articles from the pool for a presentation.
     *
     * Excludes articles already attached to this presentation (matched by URL).
     * Excludes articles older than 6 months.
     *
     * @return Collection<int, ArticlePool>  Scored and ranked, with a `match_score` attribute
     */
    public function suggest(Presentation $presentation, int $limit = 5): Collection
    {
        $cutoff = Carbon::now()->subMonths(6);

        // URLs already attached to this presentation — exclude them
        $attachedUrls = $presentation->articles()->pluck('url')->toArray();

        $query = ArticlePool::where(function ($q) use ($cutoff) {
            $q->where('published_at', '>=', $cutoff)
              ->orWhereNull('published_at');
        });

        $articles = $query->get();

        // Extract presentation context for matching
        $suburb       = mb_strtolower(trim($presentation->suburb ?? ''));
        $propertyType = mb_strtolower(trim($presentation->property_type ?? ''));

        // Score each article
        $scored = $articles->map(function (ArticlePool $article) use ($suburb, $propertyType, $attachedUrls) {
            // Skip already-attached
            if (in_array($article->url, $attachedUrls, true)) {
                return null;
            }

            $score = $this->scoreArticle($article, $suburb, $propertyType);

            if ($score <= 0) {
                return null;
            }

            $article->setAttribute('match_score', $score);

            return $article;
        })
        ->filter()
        ->sortByDesc('match_score')
        ->take($limit)
        ->values();

        return $scored;
    }

    /**
     * Score a single article against presentation context.
     */
    private function scoreArticle(ArticlePool $article, string $suburb, string $propertyType): int
    {
        $tags  = $article->tags_json ?? [];
        $score = 0;

        // Exact suburb match (+10)
        if ($suburb !== '' && !empty($tags['suburbs'])) {
            foreach ($tags['suburbs'] as $tagSuburb) {
                if (mb_strtolower($tagSuburb) === $suburb) {
                    $score += 10;
                    break;
                }
            }
        }

        // Region match (+5)
        if (!empty($tags['regions'])) {
            $score += 5;
        }

        // Property type match (+3)
        if ($propertyType !== '' && !empty($tags['property_types'])) {
            foreach ($tags['property_types'] as $tagType) {
                if (mb_strtolower($tagType) === $propertyType) {
                    $score += 3;
                    break;
                }
            }
        }

        // Financial/market topic (+2)
        if (!empty($tags['topics'])) {
            $score += 2;
        }

        // Recency bonus
        if ($article->published_at) {
            $daysAgo = $article->published_at->diffInDays(now());
            if ($daysAgo <= 7) {
                $score += 3;
            } elseif ($daysAgo <= 30) {
                $score += 2;
            } elseif ($daysAgo <= 90) {
                $score += 1;
            }
        }

        // Minimum relevance: at least a recency or topic score to surface
        // Articles with zero tags still get recency bonus, which is fine
        return $score;
    }
}
