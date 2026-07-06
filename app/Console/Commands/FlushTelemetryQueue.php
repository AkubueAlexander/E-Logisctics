<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class FlushTelemetryQueue extends Command
{
    protected $signature = 'telemetry:flush {--chunk=1000}';
    protected $description = 'Atomically offload high-velocity driver GPS tracking data from Redis to PostgreSQL without data loss risk.';

    public function handle(): int
    {
        $mainKey = 'telemetry:breadcrumbs';
        $chunkSize = (int) $this->option('chunk');

        // 1. Check if there is anything to do without locking resources
        if (!Redis::exists($mainKey)) {
            $this->info('No breadcrumbs to flush.');
            return Command::SUCCESS;
        }

        $batch = [];
        
        // 2. Safely pop a finite chunk of logs out of Redis. 
        // This allows multiple Kubernetes worker pods to safely pull from the same queue simultaneously.
        for ($i = 0; $i < $chunkSize; $i++) {
            $rawJson = Redis::lpop($mainKey);
            if (!$rawJson) {
                break; // Queue is fully drained
            }
            
            // Handle double-encoded nested JSON edge cases safely
            $data = json_decode($rawJson, true);
            if (is_string($data)) {
                $data = json_decode($data, true);
            }

            if (!isset($data['order_id'], $data['latitude'], $data['longitude'], $data['recorded_at'])) {
                continue; // Skip corrupted frames
            }

            $batch[] = $data;
        }

        if (empty($batch)) {
            return Command::SUCCESS;
        }

        // 3. Compile parameterized layout statements
        $queryValues = [];
        $bindings = [];

        foreach ($batch as $record) {
            // Note: 4326 represents the standard spatial SRID for WGS 84 GPS coordinates
            $queryValues[] = '(?, ?, ST_GeomFromText(?, 4326))';
            
            $bindings[] = $record['order_id'];
            $bindings[] = $record['recorded_at'];
            $bindings[] = "POINT({$record['longitude']} {$record['latitude']})";
        }

        // 4. Atomic Database Write Frame
       
        $rawSql = "INSERT INTO driver_telemetry_breadcrumbs (order_id, created_at, coordinates) VALUES " . implode(', ', $queryValues);

        try {
            DB::transaction(function () use ($rawSql, $bindings) {
                DB::insert($rawSql, $bindings);
            });

            $this->info("Successfully flushed " . count($batch) . " tracking coordinates into PostgreSQL.");
        } catch (Throwable $e) {
            $this->error("Database write failure: " . $e->getMessage());

            // 5. THE FAIL-SAFE: If PostgreSQL goes down, push items back to Redis instantly.
            // This prevents your core application from losing a single trace of historical distance.
            foreach ($batch as $failedRecord) {
                Redis::rpush($mainKey, json_encode($failedRecord));
            }

            $this->warn("Re-queued " . count($batch) . " entries back into Redis memory buffer.");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}