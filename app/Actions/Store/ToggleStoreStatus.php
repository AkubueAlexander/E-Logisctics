<?php

namespace App\Actions\Store;

use App\Models\Store;
use Illuminate\Support\Facades\Cache;

class ToggleStoreStatus
{
    /**
     * Update the operational status of a store.
     */
    public function execute(Store $store, bool $is_active): Store
    {
        $store->update([
            'is_active' => $is_active,
        ]);

        // Tech Lead Tip: Evict this store from the geofenced cache instantly
        // so customer apps stop seeing it in their nearby store feeds.
        Cache::forget("store_profile:{$store->id}");

        return $store;
    }
}
