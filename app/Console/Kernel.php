<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\ReleaseExpiredHolds; 

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        $schedule->command(ReleaseExpiredHolds::class)
                 ->everyMinute()
                 ->withoutOverlapping();
    }
    
    protected function commands()
    {
        $this->load(__DIR__.'/Commands'); 

        require base_path('routes/console.php');
    }
}