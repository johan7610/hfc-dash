<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactMatch;
use App\Models\ContactMatchFeedback;
use App\Models\Property;
use App\Models\User;
use App\Services\Matching\MatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileCoreMatchController extends Controller
{
    public function __construct(protected MatchingService $matching) {}

    // GET /api/mobile/core-matches
    // Grouped by contact, only matches the current user owns.
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $matches = ContactMatch::with(['contact.type', 'feedback'])
            ->whereHas('contact')
            ->where('created_by_user_id', $user->id)
            ->orderByRaw("FIELD(status,'active','paused','fulfilled','expired')")
            ->latest()
            ->get();

        $grouped = $matches->groupBy('contact_id')->map(function ($items) {
            $contact = $items->first()->contact;
            return [
                'contact' => [
                    'id'        => $contact->id,
                    'full_name' => trim($contact->first_name . ' ' . $contact->last_name),
                    'phone'     => $contact->phone,
                    'email'     => $contact->email,
                    'type'      => $contact->type?->name,
                ],
                'matches' => $items->map(fn (ContactMatch $m) => $this->shapeMatch($m))->values(),
            ];
        })->values();

        return response()->json(['groups' => $grouped]);
    }

    // GET /api/mobile/core-matches/{match}
    // Match details + result properties + per-property feedback + hidden flag.
    public function show(Request $request, ContactMatch $match): JsonResponse
    {
        $this->authorizeMatch($request->user(), $match);
        $match->load(['contact.type', 'feedback']);

        $properties = $this->matching->propertiesForMatch($match, ['include_hidden' => true]);
        $feedback   = $match->feedback->keyBy('property_id');

        $results = $properties->map(function (Property $p) use ($match, $feedback) {
            $fb = $feedback->get($p->id);
            return [
                'id'            => $p->id,
                'address'       => $p->buildDisplayAddress(),
                'suburb'        => $p->suburb,
                'beds'          => $p->beds,
                'baths'         => $p->baths,
                'garages'       => $p->garages,
                'price'         => $p->price,
                'price_display' => $p->formattedPrice(),
                'thumbnail'     => ($p->gallery_images_json ?? [])[0] ?? null,
                'hidden'        => $match->isPropertyHidden($p->id),
                'reaction'      => $fb?->reaction,        // 'interested' | 'not_interested' | 'saved' | null
                'reaction_note' => $fb?->note,
            ];
        })->values();

        return response()->json([
            'match'   => $this->shapeMatch($match, full: true),
            'contact' => [
                'id'        => $match->contact->id,
                'full_name' => trim($match->contact->first_name . ' ' . $match->contact->last_name),
                'phone'     => $match->contact->phone,
                'email'     => $match->contact->email,
                'type'      => $match->contact->type?->name,
            ],
            'results' => $results,
        ]);
    }

    // PUT /api/mobile/core-matches/{match}
    public function update(Request $request, ContactMatch $match): JsonResponse
    {
        $this->authorizeMatch($request->user(), $match);

        $data = $request->validate([
            'name'          => 'sometimes|nullable|string|max:120',
            'listing_type'  => 'sometimes|required|in:sale,rental',
            'category'      => 'sometimes|nullable|string|max:100',
            'property_type' => 'sometimes|nullable|string|max:100',
            'price_min'     => 'sometimes|nullable|integer|min:0',
            'price_max'     => 'sometimes|nullable|integer|min:0',
            'beds_min'      => 'sometimes|nullable|integer|min:0|max:20',
            'baths_min'     => 'sometimes|nullable|integer|min:0|max:20',
            'garages_min'   => 'sometimes|nullable|integer|min:0|max:20',
            'suburb'        => 'sometimes|nullable|string|max:150',
            'suburbs'       => 'sometimes|nullable|array',
            'suburbs.*'     => 'string|max:150',
            'must_have_features'   => 'sometimes|nullable|array',
            'must_have_features.*' => 'string|max:60',
            'notes'         => 'sometimes|nullable|string|max:500',
        ]);

        $match->update($data);

        return response()->json(['match' => $this->shapeMatch($match->fresh(['contact.type', 'feedback']), full: true)]);
    }

    // PATCH /api/mobile/core-matches/{match}/status
    public function setStatus(Request $request, ContactMatch $match): JsonResponse
    {
        $this->authorizeMatch($request->user(), $match);
        $data = $request->validate([
            'status' => 'required|in:active,paused,fulfilled,expired',
        ]);
        $match->update($data);
        return response()->json(['status' => $match->status]);
    }

    // POST /api/mobile/core-matches/{match}/hide/{property}
    // Toggles hidden flag for that property within this match.
    public function toggleHide(Request $request, ContactMatch $match, int $property): JsonResponse
    {
        $this->authorizeMatch($request->user(), $match);
        $match->toggleHiddenProperty($property);

        return response()->json([
            'property_id' => $property,
            'hidden'      => $match->isPropertyHidden($property),
        ]);
    }

    // DELETE /api/mobile/core-matches/{match}
    public function destroy(Request $request, ContactMatch $match): JsonResponse
    {
        $this->authorizeMatch($request->user(), $match);
        $match->delete();
        return response()->json(['deleted' => true]);
    }

    // ── helpers ─────────────────────────────────────────────────
    private function authorizeMatch(User $user, ContactMatch $match): void
    {
        abort_unless($match->created_by_user_id === $user->id, 403, 'Not your match.');
    }

    private function shapeMatch(ContactMatch $m, bool $full = false): array
    {
        $base = [
            'id'            => $m->id,
            'contact_id'    => $m->contact_id,
            'name'          => $m->name,
            'status'        => $m->status,
            'listing_type'  => $m->listing_type,
            'category'      => $m->category,
            'property_type' => $m->property_type,
            'price_min'     => $m->price_min,
            'price_max'     => $m->price_max,
            'beds_min'      => $m->beds_min,
            'baths_min'     => $m->baths_min,
            'garages_min'   => $m->garages_min,
            'suburb'        => $m->suburb,
            'suburbs'       => $m->suburbs,
            'feedback_summary' => [
                'interested'     => $m->feedback->where('reaction', ContactMatchFeedback::REACTION_INTERESTED)->count(),
                'not_interested' => $m->feedback->where('reaction', ContactMatchFeedback::REACTION_NOT_INTERESTED)->count(),
                'saved'          => $m->feedback->where('reaction', ContactMatchFeedback::REACTION_SAVED)->count(),
            ],
            'updated_at'    => $m->updated_at?->toIso8601String(),
        ];

        if (!$full) return $base;

        return $base + [
            'must_have_features' => $m->must_have_features,
            'notes'              => $m->notes,
            'hidden_property_ids'=> $m->hidden_property_ids ?? [],
            'share_url'          => $m->sharedUrl(),
        ];
    }
}
