<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class SuperAdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()->latest()->get();

        $availablePages = [
            'Dashboard' => 'Dashboard',
            'Visitors' => 'Visitors',
            'Event Configurations' => 'Event Configurations',
            'Occupancy Limit' => 'Occupancy Limit',
            'Visitor Categories' => 'Visitor Categories',
            'Users & Access' => 'Users & Access',
        ];

        return view('superadmin.dashboard', [
            'users' => $users,
            'availablePages' => $availablePages,
        ]);
    }
}
