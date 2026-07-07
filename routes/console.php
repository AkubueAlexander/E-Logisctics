<?php

use Illuminate\Support\Facades\Schedule;

// Automatically execute the telemetry flush pipeline every 10 minutes
Schedule::command('telemetry:flush --chunk=1000')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
