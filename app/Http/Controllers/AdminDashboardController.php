<?php

namespace App\Http\Controllers;

use App\Models\VerifiedVisitor;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total' => VerifiedVisitor::count(),
            'today' => VerifiedVisitor::whereDate('verified_at', today())->count(),
            'checked_in' => VerifiedVisitor::where('checkin_status', true)->count(),
            'checked_out' => VerifiedVisitor::where('checkin_status', false)->count(),
        ];

        $recentVisitors = VerifiedVisitor::latest()->limit(8)->get();

        return view('admin.dashboard', compact('stats', 'recentVisitors'));
    }
}
