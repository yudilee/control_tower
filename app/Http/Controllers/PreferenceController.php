<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PreferenceController extends Controller
{
    /**
     * Save column visibility preferences
     */
    public function storeColumns(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $table = $request->input('table', 'jobs'); // Default to jobs
        $columns = $request->input('columns', []);
        $widths = $request->input('widths', []);
        $order = $request->input('order', []);
        $sort = $request->input('sort', '');
        $dir = $request->input('dir', 'desc');

        $prefs = [
            'columns' => $columns,
            'widths' => $widths,
            'order' => $order,
            'sort' => $sort,
            'dir' => $dir,
        ];

        // Store based on table type
        switch($table) {
            case 'booking':
                $user->booking_preferences = $prefs;
                break;
            case 'pdi':
                $user->pdi_preferences = $prefs;
                break;
            case 'towing':
                $user->towing_preferences = $prefs;
                break;
            case 'vehicle':
                $user->vehicle_preferences = $prefs;
                break;
            case 'customer':
                $user->customer_preferences = $prefs;
                break;
            case 'uninvoiced':
                $user->uninvoiced_preferences = $prefs;
                break;
            default:
                $user->column_preferences = $prefs;
        }

        $user->save();

        return response()->json(['success' => true]);
    }
}
