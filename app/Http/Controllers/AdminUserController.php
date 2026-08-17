<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('created_at', 'desc')->get();

        $availablePages = [
            'Dashboard' => 'Dashboard',
            'Visitors' => 'Visitors',
            'Attendance Summary' => 'Attendance - Summary',
            'Attendance Detail' => 'Attendance - Detail',
            'Revenue Summary' => 'Revenue - Summary',
            'Revenue Detail' => 'Revenue - Detail',
            'Event Configurations' => 'Event Configurations',
            'Occupancy Limit' => 'Occupancy Limit',
            'Schedule Manager' => 'Schedule Manager',
            'Visitor Categories' => 'Visitor Categories',
            'Receipt Manager' => 'Receipt Manager',
            'Users & Access' => 'Users & Access',
        ];

        return view('admin.configurations.users', compact('users', 'availablePages'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:100', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'in:active,suspended'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $validated['permissions'] = $request->input('permissions', []);
        $validated['password'] = Hash::make($validated['password']);

        User::create($validated);

        return back()->with('status', 'User account created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:100', 'alpha_dash', 'unique:users,username,' . $user->id],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'in:active,suspended'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $validated['permissions'] = $request->input('permissions', []);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return back()->with('status', 'User account updated successfully.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $newStatus = $user->status === 'active' ? 'suspended' : 'active';
        $user->update(['status' => $newStatus]);

        return back()->with('status', 'User account status updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $user->delete();

        if ($request->session()->get('superadmin_authenticated', false)) {
            return redirect()->route('superadmin.dashboard')->with('status', 'User account removed successfully.');
        }

        return back()->with('status', 'User account removed successfully.');
    }
}
