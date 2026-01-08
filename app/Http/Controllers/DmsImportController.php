<?php

namespace App\Http\Controllers;

use App\Services\DmsImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DmsImportController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (auth()->user()->role !== 'admin') {
                abort(403, 'Only administrators can access DMS import.');
            }
            return $next($request);
        });
    }

    /**
     * Show import page
     */
    public function index()
    {
        return view('admin.dms-import.index');
    }

    /**
     * Import customers from Excel
     */
    public function importCustomers(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xls,xlsx|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $path = $file->storeAs('temp', 'customer_import_' . time() . '.' . $file->getClientOriginalExtension());
            $fullPath = storage_path('app/' . $path);

            $service = new DmsImportService();
            $results = $service->importCustomers($fullPath);

            // Clean up temp file
            Storage::delete($path);

            return redirect()->route('admin.dms-import.index')
                ->with('success', "Customer import complete: {$results['created']} created, {$results['updated']} updated")
                ->with('import_results', $results);

        } catch (\Exception $e) {
            return redirect()->route('admin.dms-import.index')
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Import vehicles from Excel
     */
    public function importVehicles(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xls,xlsx|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $path = $file->storeAs('temp', 'vehicle_import_' . time() . '.' . $file->getClientOriginalExtension());
            $fullPath = storage_path('app/' . $path);

            $service = new DmsImportService();
            $results = $service->importVehicles($fullPath);

            // Clean up temp file
            Storage::delete($path);

            return redirect()->route('admin.dms-import.index')
                ->with('success', "Vehicle import complete: {$results['created']} created, {$results['updated']} updated")
                ->with('import_results', $results);

        } catch (\Exception $e) {
            return redirect()->route('admin.dms-import.index')
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
