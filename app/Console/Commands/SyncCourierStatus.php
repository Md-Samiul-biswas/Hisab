<?php

namespace App\Console\Commands;

use App\Http\Controllers\PathaoCourierController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class SyncCourierStatus extends Command
{
    protected $signature = 'courier:sync-status';
    protected $description = 'Automatically fetch and sync parcel delivery statuses from Pathao Courier';

    public function handle(): int
    {
        $this->info('Starting Pathao courier auto-status & parcel sync...');
        
        $controller = new PathaoCourierController();

        // 1. Import all latest customer parcels from Pathao
        $this->info('Fetching parcels from Pathao...');
        $importResponse = $controller->importAllParcels(new Request());
        $importData = $importResponse->getData(true);
        if (!empty($importData['success'])) {
            $count = $importData['total_synced'] ?? 0;
            $this->info("Imported/Synced {$count} parcels from Pathao.");
        } else {
            $this->warn('Parcel import warning: ' . ($importData['error'] ?? 'None'));
        }

        // 2. Sync real-time delivery status for active consignments
        $this->info('Syncing active parcel statuses...');
        $response = $controller->syncStatuses(new Request());
        $data = $response->getData(true);

        if (!empty($data['success'])) {
            $updated = $data['updated_count'] ?? 0;
            $this->info("Successfully synced! {$updated} parcel statuses updated.");
            return Command::SUCCESS;
        }

        $this->error('Courier status sync failed: ' . ($data['error'] ?? 'Unknown error'));
        return Command::SUCCESS;
    }
}
