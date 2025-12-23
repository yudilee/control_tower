<?php

namespace App\Console\Commands;

use App\Models\DismissedDuplicateGroup;
use App\Models\DuplicateCustomerGroup;
use App\Models\Job;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FindCustomerDuplicates extends Command
{
    protected $signature = 'customers:find-duplicates {--force : Force recalculation}';
    protected $description = 'Find duplicate customer names and store in database for fast retrieval';

    public function handle(): int
    {
        $this->info('Scanning for duplicate customer names...');

        // Get dismissed hashes to skip
        $dismissedHashes = DuplicateCustomerGroup::where('status', 'dismissed')
            ->pluck('group_hash')
            ->merge(DismissedDuplicateGroup::pluck('group_hash'))
            ->unique()
            ->toArray();

        // Get all unique customer names
        $allNames = DB::table(
            DB::raw("(
                SELECT DISTINCT customer_name as name FROM vehicles WHERE customer_name IS NOT NULL AND customer_name != ''
                UNION
                SELECT DISTINCT customer_name as name FROM jobs WHERE customer_name IS NOT NULL AND customer_name != ''
            ) as customers")
        )
        ->orderBy('name')
        ->pluck('name')
        ->toArray();

        $this->info("Found " . count($allNames) . " unique customer names.");

        $duplicateGroups = [];
        $processed = [];
        $total = count($allNames);
        $count = 0;

        foreach ($allNames as $name1) {
            $count++;
            if ($count % 100 === 0) {
                $this->info("Processing: {$count}/{$total}...");
            }

            if (in_array($name1, $processed)) {
                continue;
            }

            $similar = [$name1];
            $normalized1 = $this->normalizeForComparison($name1);

            foreach ($allNames as $name2) {
                if ($name1 === $name2 || in_array($name2, $processed)) {
                    continue;
                }

                $normalized2 = $this->normalizeForComparison($name2);
                
                $levenshtein = levenshtein($normalized1, $normalized2);
                $maxLen = max(strlen($normalized1), strlen($normalized2));
                $similarity = $maxLen > 0 ? (1 - $levenshtein / $maxLen) * 100 : 0;

                similar_text($normalized1, $normalized2, $percentSimilar);

                if (($similarity > 90 && $percentSimilar > 85) || ($similarity > 85 && $percentSimilar > 90)) {
                    $similar[] = $name2;
                    $processed[] = $name2;
                }
            }

            $processed[] = $name1;

            // Only include groups with 2+ names
            if (count($similar) >= 2) {
                $groupHash = DuplicateCustomerGroup::generateHash($similar);
                
                // Skip if this group was previously dismissed
                if (in_array($groupHash, $dismissedHashes)) {
                    continue;
                }

                // Get counts and SOURCE for each name
                $entries = [];
                $dmsSourceCount = 0;
                $userSourceCount = 0;

                foreach ($similar as $n) {
                    $source = $this->detectDuplicateSource($n);
                    
                    if ($source === 'dms_import') {
                        $dmsSourceCount++;
                    } else {
                        $userSourceCount++;
                    }

                    $entries[] = [
                        'name' => $n,
                        'job_count' => Job::where('customer_name', $n)->count(),
                        'vehicle_count' => Vehicle::where('customer_name', $n)->count(),
                        'source' => $source,
                        'source_label' => $this->getSourceLabel($source),
                    ];
                }

                $classification = $dmsSourceCount >= 2 ? 'DMS_ISSUE' : 'USER_MISTAKE';

                $duplicateGroups[] = [
                    'group_hash' => $groupHash,
                    'names' => $similar,
                    'entries' => $entries,
                    'classification' => $classification,
                    'dms_count' => $dmsSourceCount,
                    'user_count' => $userSourceCount,
                    'status' => 'pending',
                ];
            }
        }

        if (empty($duplicateGroups)) {
            $this->info('No new duplicate groups found.');
            // Clear old pending entries
            DuplicateCustomerGroup::pending()->delete();
            return Command::SUCCESS;
        }

        // Clear old pending entries and insert new
        DuplicateCustomerGroup::pending()->delete();

        foreach ($duplicateGroups as $group) {
            DuplicateCustomerGroup::updateOrCreate(
                ['group_hash' => $group['group_hash']],
                [
                    'names' => $group['names'],
                    'entries' => $group['entries'],
                    'classification' => $group['classification'],
                    'dms_count' => $group['dms_count'],
                    'user_count' => $group['user_count'],
                    'status' => 'pending',
                ]
            );
        }

        $this->info("Found " . count($duplicateGroups) . " duplicate groups.");
        return Command::SUCCESS;
    }

    /**
     * Normalize name for comparison
     */
    private function normalizeForComparison(string $name): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9\s]/i', ' ', trim($name))));
    }

    /**
     * Detect the source of a customer name
     */
    private function detectDuplicateSource(string $name): string
    {
        // Check if from invoiced/uninvoiced (DMS data)
        $hasInvoicedJob = Job::where('customer_name', $name)
            ->where('status', 'invoiced')
            ->exists();
        
        $hasUninvoicedJob = Job::where('customer_name', $name)
            ->where('status', 'uninvoiced')
            ->exists();

        if ($hasInvoicedJob || $hasUninvoicedJob) {
            return 'dms_import';
        }

        return 'user_entry';
    }

    /**
     * Get human readable source label
     */
    private function getSourceLabel(string $source): string
    {
        return match($source) {
            'dms_import' => 'DMS (Invoice/Uninvoiced)',
            default => 'User Entry/Progress',
        };
    }
}
