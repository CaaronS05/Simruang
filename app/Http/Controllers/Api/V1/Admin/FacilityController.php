<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facility\StoreFacilityRequest;
use App\Http\Requests\Facility\UpdateFacilityRequest;
use App\Http\Resources\FacilityResource;
use App\Models\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FacilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'condition' => ['nullable', 'in:good,damaged,maintenance'],
            'is_active' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'in:name,global_stock,created_at'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $facilities = Facility::query()
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($validated['condition'] ?? null, fn ($query, string $condition) => $query->where('condition', $condition))
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy($validated['sort_by'] ?? 'created_at', $validated['sort_direction'] ?? 'desc')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => FacilityResource::collection($facilities)->resolve(),
            'meta' => [
                'current_page' => $facilities->currentPage(),
                'last_page' => $facilities->lastPage(),
                'per_page' => $facilities->perPage(),
                'total' => $facilities->total(),
            ],
            'links' => [
                'first' => $facilities->url(1),
                'last' => $facilities->url($facilities->lastPage()),
                'prev' => $facilities->previousPageUrl(),
                'next' => $facilities->nextPageUrl(),
            ],
        ]);
    }

    public function store(StoreFacilityRequest $request): JsonResponse
    {
        $data = $request->safe()->except('photo');
        $data['photo_path'] = $request->file('photo')?->store('facilities', 'public');

        $facility = Facility::query()->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Fasilitas berhasil dibuat.',
            'data' => new FacilityResource($facility),
        ], 201);
    }

    public function show(Facility $facility): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => new FacilityResource($facility),
        ]);
    }

    public function update(UpdateFacilityRequest $request, Facility $facility): JsonResponse
    {
        $data = $request->safe()->except('photo');
        $oldPhotoPath = $facility->photo_path;
        $newPhotoPath = $request->file('photo')?->store('facilities', 'public');

        if ($newPhotoPath) {
            $data['photo_path'] = $newPhotoPath;
        }

        DB::transaction(function () use ($facility, $data): void {
            $facility->update($data);
        });

        if ($newPhotoPath && $oldPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fasilitas berhasil diperbarui.',
            'data' => new FacilityResource($facility->refresh()),
        ]);
    }

    public function destroy(Facility $facility): JsonResponse
    {
        if ($facility->rooms()->exists() || $facility->bookings()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Fasilitas sudah digunakan. Ubah is_active menjadi false.',
                'errors' => (object) [],
            ], 409);
        }

        $photoPath = $facility->photo_path;

        DB::transaction(function () use ($facility): void {
            $facility->delete();
        });

        if ($photoPath) {
            Storage::disk('public')->delete($photoPath);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fasilitas berhasil dihapus.',
            'data' => (object) [],
        ]);
    }
}
