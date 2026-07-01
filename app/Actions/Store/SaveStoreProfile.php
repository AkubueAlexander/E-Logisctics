<?php
namespace App\Actions\Store;

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SaveStoreProfile
{
    public function execute(User $manager, array $data, ?Store $store = null, ?UploadedFile $logo = null): Store
    {
        return DB::transaction(function () use ($manager, $data, $store, $logo) {

            // If no store is passed, we create a new one. Otherwise, we use the specific store provided.
            $store = $store ?? new Store();

            if ($logo) {
                if ($store->logo_url) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $store->logo_url));
                }
                $path = $logo->store('stores/logos', 'public');
                $data['logo_url'] = Storage::url($path);
            }

            $store->fill([
                'name' => $data['name'],
                'address' => $data['address'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'operating_hours' => $data['operating_hours'] ?? $store->operating_hours,
                'logo_url' => $data['logo_url'] ?? $store->logo_url
            ]);

            $store->save();

            // Link this manager to the store if the relationship doesn't exist yet
            if (!$store->users()->where('users.id', $manager->id)->exists()) {
                $store->users()->attach($manager->id, ['role' => 'manager']);
            }

            // Sync global tags
            if (isset($data['global_categories'])) {
                $store->globalCategories()->sync($data['global_categories']);
            }

            return $store->load('globalCategories');
        });
    }
}
