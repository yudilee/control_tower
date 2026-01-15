<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Vehicle;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Vehicle lookup API for job creation, booking, and towing forms
Route::get('/vehicles/lookup', function (Request $request) {
    $plate = $request->query('plate');
    
    if (empty($plate)) {
        return response()->json(['found' => false]);
    }
    
    // Normalize plate: remove spaces and search case-insensitive
    $normalizedPlate = strtoupper(preg_replace('/\s+/', '', $plate));
    
    $vehicle = Vehicle::whereRaw('UPPER(REPLACE(plate_number, " ", "")) = ?', [$normalizedPlate])
        ->first();
    
    if ($vehicle) {
        return response()->json([
            'found' => true,
            'model' => $vehicle->model,
            'customer_name' => $vehicle->customer_name,
            'vin' => $vehicle->vin,
        ]);
    }
    
    return response()->json(['found' => false]);
});

// Customer address lookup API for job detail page
Route::get('/customers/lookup-address', function (Request $request) {
    $customerId = $request->query('customer_id');
    $customerName = $request->query('customer_name');
    $plate = $request->query('plate');
    
    $customer = null;
    
    // Try to find by customer_id first
    if (!empty($customerId)) {
        $customer = \App\Models\Customer::where('id', $customerId)
            ->orWhere('account_no', $customerId)
            ->first();
    }
    
    // Then try by customer_name
    if (!$customer && !empty($customerName)) {
        $customer = \App\Models\Customer::where('name', 'LIKE', '%' . $customerName . '%')
            ->first();
    }
    
    // Then try via vehicle plate
    if (!$customer && !empty($plate)) {
        $vehicle = \App\Models\Vehicle::where('plate_number', $plate)->first();
        if ($vehicle && $vehicle->customer_id) {
            $customer = $vehicle->customer;
        }
    }
    
    if ($customer) {
        // Build full address from address fields
        $addressParts = array_filter([
            $customer->address,
            $customer->address_1,
            $customer->address_2,
            $customer->address_3,
            $customer->address_4,
            $customer->address_5,
        ]);
        
        $fullAddress = !empty($addressParts) ? implode(', ', $addressParts) : null;
        
        return response()->json([
            'found' => true,
            'address' => $fullAddress,
            'name' => $customer->name,
            'phone' => $customer->phone ?? $customer->phone_1,
        ]);
    }
    
    return response()->json(['found' => false, 'message' => 'Customer not found']);
});
