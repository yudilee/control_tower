# Fix: Uninvoiced Report Franchise Filter Returns 0 Jobs

## Root Cause Analysis

After investigating the codebase, I found that the franchise filter logic itself is **correct** — both [`ReportController::uninvoiced()`](app/Http/Controllers/ReportController.php:39) and the view's filter form treat PC and CV identically. The same `$query->where('franchise', $request->franchise)` is used for both values.

### The Real Bug: Multi-Select Empty-Value Filter Interference

The issue is in the **multi-select fields** for `service_advisor[]` and `foreman[]` in the form.

**How it happens:**

1. The form's [`service_advisor[]`](resources/views/reports/uninvoiced.blade.php:466) and [`foreman[]`](resources/views/reports/uninvoiced.blade.php:480) multi-selects have `<option value="">All SA</option>` / `<option value="">All Foreman</option>`.

2. On initial page load, these "All" options are **selected** because `empty(request('service_advisor'))` is `true`.

3. When the user submits the filter form (e.g., selecting "PC" franchise), the browser sends `service_advisor[]=` and `foreman[]=` as empty-string array values.

4. In Laravel 11's [`filled()`](vendor/laravel/framework/src/Illuminate/Support/Traits/InteractsWithData.php:104) method, an array `['']` is **not** considered empty (`isEmptyString` returns `false` because `is_array` is `true`), so `$request->filled('service_advisor')` returns `true`.

5. The controller then executes:
   ```php
   $query->whereIn('service_advisor', [''])   // matches NOTHING
   ```

6. This `whereIn` with empty string **zeroes out all results** regardless of franchise selection.

### Summary of Findings

| Component | Status | Details |
|-----------|--------|---------|
| `ReportController::uninvoiced()` line 39-41 | ✅ Correct | `$query->where('franchise', $request->franchise)` works for both PC and CV |
| View franchise dropdown | ✅ Correct | Values "PC" and "CV" are used correctly |
| View multi-select SA/Foreman fields | ❌ Bug | Empty-value options submit as `['']` causing `whereIn` filter to match nothing |
| Laravel 11 `filled()` behavior | ✅ As-designed | `['']` is considered "filled" per Laravel's logic |
| Database schema | ✅ Correct | `franchise` is `ENUM('PC', 'CV')` with proper values |

## Fix Plan

### Fix 1: Filter out empty values from multi-select arrays in the controller

In [`ReportController::uninvoiced()`](app/Http/Controllers/ReportController.php:45), modify the `service_advisor` and `foreman` handling to filter out empty strings from the arrays before passing them to `whereIn`.

**Current code (lines 45-68):**
```php
if ($request->filled('service_advisor') || $request->filled('foreman')) {
    $query->where(function($q) use ($request) {
        if ($request->filled('service_advisor')) {
            $sa = $request->service_advisor;
            if (is_array($sa)) {
                $q->whereIn('service_advisor', $sa);
            } else {
                $q->where('service_advisor', $sa);
            }
        }
        
        if ($request->filled('foreman')) {
            $fm = $request->foreman;
            // ...
        }
    });
}
```

**Fix:** Filter empty values from arrays before using them:
```php
if ($request->filled('service_advisor') || $request->filled('foreman')) {
    $query->where(function($q) use ($request) {
        if ($request->filled('service_advisor')) {
            $sa = $request->service_advisor;
            if (is_array($sa)) {
                $sa = array_filter($sa, fn($v) => $v !== null && $v !== '');
                if (!empty($sa)) {
                    $q->whereIn('service_advisor', $sa);
                }
            } else {
                $q->where('service_advisor', $sa);
            }
        }
        
        if ($request->filled('foreman')) {
            $fm = $request->foreman;
            if (is_array($fm)) {
                $fm = array_filter($fm, fn($v) => $v !== null && $v !== '');
            }
            // ... rest of logic
        }
    });
}
```

### Fix 2: Apply the same fix to `exportUninvoiced()` method

The same issue exists in [`exportUninvoiced()`](app/Http/Controllers/ReportController.php:709) at lines 709-732, which has identical multi-select array handling.

### Fix 3: Apply the same fix to the view's summary stats block

The view's summary stats section at [`resources/views/reports/uninvoiced.blade.php`](resources/views/reports/uninvoiced.blade.php:38) also uses `request('service_advisor')` and `request('foreman')` directly. While these are just for display counts, they should also be updated for consistency.

### Fix 4 (Optional): Remove "All" options from multi-selects

An alternative approach is to remove the `<option value="">All SA</option>` and `<option value="">All Foreman</option>` entries from the multi-select fields, since multi-selects don't typically have "All" options. Instead, the placeholder text or label can indicate "Select SA(s) to filter".

This is optional but would prevent the empty-value issue at the form level.

## Files to Modify

1. [`app/Http/Controllers/ReportController.php`](app/Http/Controllers/ReportController.php)
   - Lines 45-68: `uninvoiced()` method - filter empty values from service_advisor/foreman arrays
   - Lines 709-732: `exportUninvoiced()` method - same fix

2. [`resources/views/reports/uninvoiced.blade.php`](resources/views/reports/uninvoiced.blade.php)
   - Lines 38-53: Summary stats - add array_filter for consistency
   - (Optional) Remove empty-value "All" options from multi-selects

## Testing Steps

1. Navigate to the Uninvoiced Jobs Report
2. Select "PC" franchise and click Filter → should show PC jobs
3. Select "CV" franchise and click Filter → should show CV jobs
4. Try filtering with both franchise AND specific SA/Foreman selections
5. Test the export feature with franchise filters
