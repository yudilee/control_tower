<?php

namespace App\Console\Commands;

use App\Helpers\CustomerNameHelper;
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

        // Clear existing summaries for fresh rebuild
        CustomerSummary::truncate();

        // Get all DMS customers
        $dmsCustomers = Customer::whereNotNull('name')
            ->where('name', '!=', '')
            ->get()
            ->keyBy('id');

        $this->info("Found " . $dmsCustomers->count() . " DMS-imported customers.");

        // Build lookup maps for name matching
        $customersByNormalizedName = [];
        $customersByExactName = [];
        foreach ($dmsCustomers as $customer) {
            $normalized = CustomerNameHelper::normalize($customer->name);
            $exact = strtoupper(trim($customer->name));
            
            if ($customer->title) {
                $withTitle = strtoupper(trim($customer->title . ' ' . $customer->name));
                $customersByExactName[$withTitle] = $customer;
            }
            
            $customersByNormalizedName[$normalized] = $customer;
            $customersByExactName[$exact] = $customer;
        }
        
        // Build alias map
        $aliasMap = [];
        foreach (CustomerAlias::with('customer')->get() as $alias) {
            $normalized = CustomerNameHelper::normalize($alias->alias_name);
            $exact = strtoupper(trim($alias->alias_name));
            $aliasMap[$normalized] = $alias->customer;
            $aliasMap[$exact] = $alias->customer;
        }

        // Get all unique customer names from jobs and vehicles
        $namesFromActivity = DB::table(
            DB::raw("(
                SELECT DISTINCT customer_name as name FROM vehicles WHERE customer_name IS NOT NULL AND customer_name != ''
                UNION
                SELECT DISTINCT customer_name as name FROM jobs WHERE customer_name IS NOT NULL AND customer_name != ''
            ) as customers")
        )->pluck('name')->toArray();

        $this->info("Found " . count($namesFromActivity) . " unique names from jobs/vehicles.");

        // Build aggregated data by customer_id
        // Key: customer_id (or 'unlinked_' + name for unlinked)
        $aggregated = [];
        
        $bar = $this->output->createProgressBar(count($namesFromActivity));
        $bar->start();

        foreach ($namesFromActivity as $name) {
            // Find linked customer
            $exactName = strtoupper(trim($name));
            $normalizedName = CustomerNameHelper::normalize($name);
            
            $customer = $customersByExactName[$exactName] 
                ?? $customersByNormalizedName[$normalizedName] 
                ?? $aliasMap[$exactName] 
                ?? $aliasMap[$normalizedName] 
                ?? null;

            // Get stats for this name
            $vehicleCount = Vehicle::where('customer_name', $name)->count();
            $uninvoicedCount = Job::where('customer_name', $name)->where('status', 'uninvoiced')->count();
            $invoicedCount = Job::where('customer_name', $name)->where('status', 'invoiced')->count();
            $totalSales = (float) Job::where('customer_name', $name)->where('status', 'invoiced')->sum('inv_ppn_meterai');
            $estimatedSales = (float) Job::where('customer_name', $name)->where('status', 'uninvoiced')->sum('total_sales');

            if ($customer) {
                // Aggregate by customer_id
                $key = 'customer_' . $customer->id;
                
                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'name' => $customer->title ? ($customer->title . ' ' . $customer->name) : $customer->name,
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
                        'name_variations' => [],
                    ];
                }
                
                // Aggregate stats
                $aggregated[$key]['vehicle_count'] += $vehicleCount;
                $aggregated[$key]['job_count'] += $uninvoicedCount + $invoicedCount;
                $aggregated[$key]['uninvoiced_count'] += $uninvoicedCount;
                $aggregated[$key]['invoiced_count'] += $invoicedCount;
                $aggregated[$key]['total_sales'] += $totalSales;
                $aggregated[$key]['estimated_sales'] += $estimatedSales;
                $aggregated[$key]['name_variations'][] = $name;
            } else {
                // Unlinked - keep separate entry
                $key = 'unlinked_' . md5($name);
                $aggregated[$key] = [
                    'name' => $name,
                    'customer_id' => null,
                    'dms_magic' => null,
                    'email' => null,
                    'phone' => null,
                    'company_name' => null,
                    'vehicle_count' => $vehicleCount,
                    'job_count' => $uninvoicedCount + $invoicedCount,
                    'uninvoiced_count' => $uninvoicedCount,
                    'invoiced_count' => $invoicedCount,
                    'total_sales' => $totalSales,
                    'estimated_sales' => $estimatedSales,
                    'name_variations' => [$name],
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Add DMS customers without activity
        $processedCustomerIds = [];
        foreach ($aggregated as $key => $data) {
            if ($data['customer_id']) {
                $processedCustomerIds[$data['customer_id']] = true;
            }
        }

        $dmsOnlyCount = 0;
        foreach ($dmsCustomers as $customer) {
            if (isset($processedCustomerIds[$customer->id])) {
                continue;
            }
            
            $key = 'customer_' . $customer->id;
            $aggregated[$key] = [
                'name' => $customer->title ? ($customer->title . ' ' . $customer->name) : $customer->name,
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
                'name_variations' => [],
            ];
            $dmsOnlyCount++;
        }

        // Insert all summaries
        $this->info("Inserting " . count($aggregated) . " customer summaries...");
        
        $batch = [];
        foreach ($aggregated as $data) {
            unset($data['name_variations']); // Don't store in DB
            $data['created_at'] = now();
            $data['updated_at'] = now();
            $batch[] = $data;
            
            if (count($batch) >= 100) {
                CustomerSummary::insert($batch);
                $batch = [];
            }
        }
        
        if (!empty($batch)) {
            CustomerSummary::insert($batch);
        }

        $totalCount = CustomerSummary::count();
        $linkedCount = CustomerSummary::whereNotNull('customer_id')->count();
        $unlinkedCount = CustomerSummary::whereNull('customer_id')->count();
        
        $this->info("Customer summaries refreshed!");
        $this->info("Total: {$totalCount} | DMS Linked: {$linkedCount} | Unlinked: {$unlinkedCount} | DMS-only: {$dmsOnlyCount}");

        return Command::SUCCESS;
    }
}



