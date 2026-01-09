<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerAlias;
use App\Models\CustomerSummary;
use App\Models\Job;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshCustomerSummaries extends Command
{
    protected $signature = 'customers:refresh-summaries';
    protected $description = 'Refresh the customer summaries cache table';

    public function handle(): int
    {
        $this->info('Refreshing customer summaries...');

        // Get all unique customer names from jobs and vehicles
        $namesFromActivity = DB::table(
            DB::raw("(
                SELECT DISTINCT customer_name as name FROM vehicles WHERE customer_name IS NOT NULL AND customer_name != ''
                UNION
                SELECT DISTINCT customer_name as name FROM jobs WHERE customer_name IS NOT NULL AND customer_name != ''
            ) as customers")
        )->pluck('name')->toArray();

        // Get all customer names from DMS-imported customers table
        $dmsCustomers = Customer::whereNotNull('name')
            ->where('name', '!=', '')
            ->get();

        $this->info("Found " . count($namesFromActivity) . " customers from jobs/vehicles.");
        $this->info("Found " . $dmsCustomers->count() . " DMS-imported customers.");

        // Cache customer lookups for efficiency
        $customersByName = $dmsCustomers->keyBy(fn($c) => strtoupper(trim($c->name)));
        
        $aliasMap = CustomerAlias::with('customer')
            ->get()
            ->keyBy(fn($a) => strtoupper(trim($a->alias_name)));

        // Process customers with job/vehicle activity
        $processedNames = [];
        $batch = [];
        $batchSize = 100;
        
        $bar = $this->output->createProgressBar(count($namesFromActivity) + $dmsCustomers->count());
        $bar->start();

        foreach ($namesFromActivity as $name) {
            $vehicleCount = Vehicle::where('customer_name', $name)->count();
            $uninvoicedCount = Job::where('customer_name', $name)->where('status', 'uninvoiced')->count();
            $invoicedCount = Job::where('customer_name', $name)->where('status', 'invoiced')->count();
            $totalSales = Job::where('customer_name', $name)->where('status', 'invoiced')->sum('inv_ppn_meterai') ?? 0;
            $estimatedSales = Job::where('customer_name', $name)->where('status', 'uninvoiced')->sum('total_sales') ?? 0;

            // Try to find linked customer
            $normalizedName = strtoupper(trim($name));
            $customer = $customersByName->get($normalizedName);
            
            // Try alias if no direct match
            if (!$customer && $aliasMap->has($normalizedName)) {
                $customer = $aliasMap->get($normalizedName)->customer;
            }

            $batch[] = [
                'name' => $name,
                'customer_id' => $customer?->id,
                'dms_magic' => $customer?->dms_magic,
                'email' => $customer?->email,
                'phone' => $customer?->phone ?? $customer?->phone_1,
                'company_name' => $customer?->company_name,
                'vehicle_count' => $vehicleCount,
                'job_count' => $uninvoicedCount + $invoicedCount,
                'uninvoiced_count' => $uninvoicedCount,
                'invoiced_count' => $invoicedCount,
                'total_sales' => $totalSales,
                'estimated_sales' => $estimatedSales,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            $processedNames[strtoupper(trim($name))] = true;

            if (count($batch) >= $batchSize) {
                $this->upsertBatch($batch);
                $batch = [];
            }

            $bar->advance();
        }

        // Now add DMS customers who don't have any job/vehicle activity yet
        $dmsOnlyCount = 0;
        foreach ($dmsCustomers as $customer) {
            $normalizedName = strtoupper(trim($customer->name));
            
            // Skip if already processed
            if (isset($processedNames[$normalizedName])) {
                $bar->advance();
                continue;
            }
            
            $batch[] = [
                'name' => $customer->name,
                'customer_id' => $customer->id,
                'dms_magic' => $customer->dms_magic,
                'email' => $customer->email,
                'phone' => $customer->phone ?? $customer->phone_1,
                'company_name' => $customer->company_name,
                'vehicle_count' => 0,
                'job_count' => 0,
                'uninvoiced_count' => 0,
                'invoiced_count' => 0,
                'total_sales' => 0,
                'estimated_sales' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            $processedNames[$normalizedName] = true;
            $dmsOnlyCount++;

            if (count($batch) >= $batchSize) {
                $this->upsertBatch($batch);
                $batch = [];
            }

            $bar->advance();
        }

        // Insert remaining
        if (!empty($batch)) {
            $this->upsertBatch($batch);
        }

        $bar->finish();
        $this->newLine();
        
        $totalCount = CustomerSummary::count();
        $linkedCount = CustomerSummary::whereNotNull('customer_id')->count();
        $this->info("Customer summaries refreshed!");
        $this->info("Total: {$totalCount} | DMS Linked: {$linkedCount} | DMS-only (no activity): {$dmsOnlyCount}");

        return Command::SUCCESS;
    }

    private function upsertBatch(array $batch): void
    {
        foreach ($batch as $item) {
            CustomerSummary::updateOrCreate(
                ['name' => $item['name']],
                $item
            );
        }
    }
}


