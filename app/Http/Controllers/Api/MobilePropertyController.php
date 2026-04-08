<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MobilePropertyController extends Controller
{
    // ── GET /api/mobile/properties ───────────────────────────────
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $properties = Property::visibleTo($user)
            ->orderByDesc('updated_at')
            ->get([
                'id', 'title', 'address', 'street_number', 'street_name',
                'suburb', 'city', 'complex_name', 'unit_number',
                'beds', 'baths', 'garages', 'status', 'property_type',
                'category', 'listing_type', 'price', 'agent_id',
                'gallery_images_json', 'updated_at',
            ])
            ->map(fn (Property $p) => [
                'id'            => $p->id,
                'address'       => $p->buildDisplayAddress(),
                'beds'          => $p->beds,
                'baths'         => $p->baths,
                'garages'       => $p->garages,
                'status'        => $p->status,
                'property_type' => $p->property_type,
                'category'      => $p->category,
                'listing_type'  => $p->listing_type,
                'price'         => $p->price,
                'price_display' => $p->formattedPrice(),
                'thumbnail'     => ($p->gallery_images_json ?? [])[0] ?? null,
                'updated_at'    => $p->updated_at?->toIso8601String(),
            ]);

        return response()->json(['properties' => $properties]);
    }

    // ── GET /api/mobile/properties/{id} ─────────────────────────
    public function show(Request $request, Property $property): JsonResponse
    {
        $this->authorizeProperty($request->user(), $property);

        return response()->json([
            'property' => $this->fullPropertyResponse($property),
        ]);
    }

    // ── POST /api/mobile/properties ─────────────────────────────
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'street_number' => 'nullable|string|max:20',
            'street_name'   => 'nullable|string|max:255',
            'address'       => 'nullable|string|max:500',
            'suburb'        => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:255',
            'complex_name'  => 'nullable|string|max:255',
            'unit_number'   => 'nullable|string|max:50',
            'beds'          => 'nullable|integer|min:0|max:50',
            'baths'         => 'nullable|integer|min:0|max:50',
            'garages'       => 'nullable|integer|min:0|max:20',
            'size_m2'       => 'nullable|numeric|min:0',
            'erf_size_m2'   => 'nullable|numeric|min:0',
            'property_type' => 'nullable|string|max:100',
            'category'      => 'nullable|string|max:100',
            'listing_type'  => 'nullable|string|max:100',
            'mandate_type'  => 'nullable|string|max:100',
            'price'         => 'nullable|integer|min:0',
            'description'   => 'nullable|string|max:10000',
            'features'      => 'nullable|array',
            'features.*'    => 'string|max:255',
            'status'        => 'nullable|string|max:50',
        ]);

        $data['agent_id']   = $user->id;
        $data['branch_id']  = $user->effectiveBranchId();
        $data['agency_id']  = $user->agency_id ?? null;

        if (isset($data['features'])) {
            $data['features_json'] = $data['features'];
            unset($data['features']);
        }

        $property = Property::create($data);
        $property->refresh();

        return response()->json([
            'property' => $this->fullPropertyResponse($property),
        ], 201);
    }

    // ── PUT /api/mobile/properties/{id} ─────────────────────────
    public function update(Request $request, Property $property): JsonResponse
    {
        $this->authorizeProperty($request->user(), $property);

        $data = $request->validate([
            'street_number' => 'nullable|string|max:20',
            'street_name'   => 'nullable|string|max:255',
            'address'       => 'nullable|string|max:500',
            'suburb'        => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:255',
            'complex_name'  => 'nullable|string|max:255',
            'unit_number'   => 'nullable|string|max:50',
            'beds'          => 'nullable|integer|min:0|max:50',
            'baths'         => 'nullable|integer|min:0|max:50',
            'garages'       => 'nullable|integer|min:0|max:20',
            'size_m2'       => 'nullable|numeric|min:0',
            'erf_size_m2'   => 'nullable|numeric|min:0',
            'property_type' => 'nullable|string|max:100',
            'category'      => 'nullable|string|max:100',
            'listing_type'  => 'nullable|string|max:100',
            'mandate_type'  => 'nullable|string|max:100',
            'price'         => 'nullable|integer|min:0',
            'description'   => 'nullable|string|max:10000',
            'features'      => 'nullable|array',
            'features.*'    => 'string|max:255',
            'status'        => 'nullable|string|max:50',
        ]);

        if (isset($data['features'])) {
            $data['features_json'] = $data['features'];
            unset($data['features']);
        }

        $property->update($data);
        $property->refresh();

        return response()->json([
            'property' => $this->fullPropertyResponse($property),
        ]);
    }

    // ── POST /api/mobile/properties/{id}/images ─────────────────
    public function uploadImage(Request $request, Property $property): JsonResponse
    {
        $this->authorizeProperty($request->user(), $property);

        $request->validate([
            'image'    => 'required|image|max:10240',
            'room_tag' => 'nullable|string|max:100',
        ]);

        $file = $request->file('image');
        $path = $file->store("properties/{$property->id}", 'public');
        $url  = Storage::url($path);

        // Append to flat gallery list
        $gallery   = $property->gallery_images_json ?? [];
        $gallery[] = $url;
        $property->gallery_images_json = $gallery;

        // Tag into category if room_tag provided
        $roomTag = $request->input('room_tag');
        if ($roomTag) {
            $cats  = $property->gallery_categories_json ?? ['categories' => [], 'unsorted' => []];
            $found = false;

            foreach ($cats['categories'] as &$cat) {
                if ($cat['name'] === $roomTag) {
                    $cat['images'][] = $url;
                    $found = true;
                    break;
                }
            }
            unset($cat);

            if (! $found) {
                $cats['categories'][] = ['name' => $roomTag, 'images' => [$url]];
            }

            $property->gallery_categories_json = $cats;
        } else {
            // No tag — add to unsorted
            $cats = $property->gallery_categories_json ?? ['categories' => [], 'unsorted' => []];
            $cats['unsorted'][] = $url;
            $property->gallery_categories_json = $cats;
        }

        $property->saveQuietly();

        return response()->json([
            'message'   => 'Image uploaded.',
            'url'       => $url,
            'room_tag'  => $roomTag,
        ], 201);
    }

    // ── Response helpers ───────────────────────────────────────
    private function fullPropertyResponse(Property $property): array
    {
        $galleryImages = $property->gallery_images_json ?? [];

        return [
            'id'              => $property->id,
            'title'           => $property->title,
            'address'         => $property->buildDisplayAddress(),
            'street_number'   => $property->street_number,
            'street_name'     => $property->street_name,
            'suburb'          => $property->suburb,
            'city'            => $property->city,
            'complex_name'    => $property->complex_name,
            'unit_number'     => $property->unit_number,
            'beds'            => $property->beds,
            'baths'           => $property->baths,
            'garages'         => $property->garages,
            'size_m2'         => $property->size_m2,
            'erf_size_m2'     => $property->erf_size_m2,
            'status'          => $property->status,
            'property_type'   => $property->property_type,
            'category'        => $property->category,
            'listing_type'    => $property->listing_type,
            'mandate_type'    => $property->mandate_type,
            'price'           => $property->price,
            'price_display'   => $property->formattedPrice(),
            'description'     => $property->description,
            'features'        => $property->features_json ?? [],
            'gallery_images'  => $galleryImages,
            'gallery_categories' => $this->buildGalleryCategories($property),
            'thumbnail'       => $galleryImages[0] ?? null,
            'agent_id'        => $property->agent_id,
            'agent_name'      => $property->agent?->name,
            'published_at'    => $property->published_at?->toIso8601String(),
            'updated_at'      => $property->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Transform the internal gallery_categories_json (array of {name, images})
     * into the mobile-friendly format: { "categories": { "Kitchen": [...], "Lounge": [...] } }
     */
    private function buildGalleryCategories(Property $property): array
    {
        $raw = $property->gallery_categories_json ?? ['categories' => [], 'unsorted' => []];
        $mapped = [];

        foreach ($raw['categories'] ?? [] as $cat) {
            $mapped[$cat['name']] = $cat['images'] ?? [];
        }

        return ['categories' => (object) $mapped];
    }

    // ── Authorization ───────────────────────────────────────────
    private function authorizeProperty(User $user, Property $property): void
    {
        $scope = PermissionService::getDataScope($user, 'properties');

        if ($scope === 'all') return;
        if ($scope === 'branch' && (int) $property->branch_id === (int) $user->effectiveBranchId()) return;
        if ($scope === 'own' && (int) $property->agent_id === (int) $user->id) return;

        abort(403, 'You do not have access to this property.');
    }
}
