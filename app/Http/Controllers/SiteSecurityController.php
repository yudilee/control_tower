<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class SiteSecurityController extends Controller
{
    public function unlock(Request $request)
    {
        $password = $request->input('password');

        if ($password === 'b4RgP9Em@d85') {
            Session::put('site_unlocked', true);
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Incorrect password'], 401);
    }
}
