<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class FlushTelemetryQueue extends Command
{
    protected $signature = 'telemetry:flush';
    protected $description = 'Atomically offload high-velocity driver GPS tracking data from Redis to PostgreSQL.';

    public function handle(): int
    {
        $mainKey = 'telemetry:breadcrumbs';
        $processingKey = 'telemetry:breadcrumbs:processing';

        // 1. Guard against empty queues
        if (!Redis::exists($mainKey)) {
            $this->info('No breadcrumbs to flush.');
            return Command::SUCCESS;
        }

        // 2. Atomic Rotation: Isolates current logs so incoming writes don't get lost
        Redis::rename($mainKey, $processingKey);

        // 3. Retrieve all strings from our isolated snapshot array
        $payloads = Redis::lrange($processingKey, 0, -1);

        // Delete the processing key instantly from memory
        Redis::del($processingKey);

        if (empty($payloads)) {
            return Command::SUCCESS;
        }

        // 4. Batch Compilation
        $bindings = [];
        $queryValues = [];

        foreach ($payloads as $rawJson) {
            $data = json_decode($rawJson, true);
            if (!$data) continue;

            // Compile parameterized raw layout values for PostGIS insertion
            $queryValues[] = '(?, ?, ST_GeomFromText(?))';

            $bindings[] = $data['order_id'];
            $bindings[] = $data['recorded_at'];
            $bindings[] = "POINT({$data['longitude']} {$data['latitude']})"; // PostGIS requires (Lng Lat) layout
        }

        if (empty($queryValues)) {
            return Command::SUCCESS;
        }

        // 5. Atomic PostgreSQL Write Chunk Frame
        $rawSql = "INSERT INTO driver_telemetry_breadcrumbs (order_id, recorded_at, coordinates) VALUES " . implode(', ', $queryValues);

        DB::transaction(function () use ($rawSql, $bindings) {
            DB::insert($rawSql, $bindings);
        });

        $this->info("Successfully flushed " . count($queryValues) . " telemetric coordinates into PostgreSQL.");
        return Command::SUCCESS;
    }
}
