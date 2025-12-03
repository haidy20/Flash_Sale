<?php

namespace App\Console\Commands;

use App\Models\Hold;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ReleaseExpiredHolds extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'holds:release-expired';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Checks for and releases expired active holds back to product stock.';

    public function handle()
    {
        $this->info('Starting release of expired holds...');
        $expiredHolds = Hold::where('status', 'active')
            ->where('expires_at', '<', Carbon::now())
            ->get();

        if ($expiredHolds->isEmpty()) {
            $this->info('No expired holds found.');
            return Command::SUCCESS;
        }

        $this->info(sprintf('Found %d expired holds to process.', $expiredHolds->count()));
        $holdsByProduct = $expiredHolds->groupBy('product_id');

        foreach ($holdsByProduct as $productId => $holds) {
            $totalReleasedQty = $holds->sum('qty');

            try {
                DB::transaction(function () use ($productId, $holds, $totalReleasedQty) {

                    $product = Product::lockForUpdate()->find($productId);
                    if (!$product) {
                        return;
                    }
                    Hold::whereIn('id', $holds->pluck('id'))->update(['status' => 'expired']);

                    $this->comment("Released {$totalReleasedQty} units for Product ID: {$productId}");
                });
            } catch (\Exception $e) {
                $this->error("Failed to release holds for Product ID {$productId}: " . $e->getMessage());
            }
        }

        $this->info('Expired holds release process completed.');
        return Command::SUCCESS;
    }
}
