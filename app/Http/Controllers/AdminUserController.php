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

        return view('admin.configurations.users', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'in:active,suspended'],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        User::create($validated);

        return redirect()
            ->route('admin.configurations.users.index')
            ->with('status', 'User account created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'in:active,suspended'],
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()
            ->route('admin.configurations.users.index')
            ->with('status', 'User account updated successfully.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $newStatus = $user->status === 'active' ? 'suspended' : 'active';
        $user->update(['status' => $newStatus]);

        return redirect()
            ->route('admin.configurations.users.index')
            ->with('status', 'User account status updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (User::count() <= 1) {
            return redirect()
                ->route('admin.configurations.users.index')
                ->withErrors(['user' => 'Cannot remove the last remaining administrator account.']);
        }

        $user->delete();

        return redirect()
            ->route('admin.configurations.users.index')
            ->with('status', 'User account removed successfully.');
    }
}
