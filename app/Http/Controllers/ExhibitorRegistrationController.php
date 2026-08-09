<?php

namespace App\Http\Controllers;

use App\Models\ExhibitorProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ExhibitorRegistrationController extends Controller
{
    private const PACKAGES = [
        'designer' => 6,
        'standard' => 4,
        'mini' => 2,
    ];

    public function show(ExhibitorProfile $exhibitor): View|RedirectResponse
    {
        if (! $this->isAuthenticated($exhibitor, request())) {
            return view('exhibitor.login', compact('exhibitor'));
        }

        if ($exhibitor->registered_at) {
            return redirect()->route('exhibitor.dashboard', $exhibitor);
        }

        return view('exhibitor.registration', compact('exhibitor'));
    }

    public function authenticate(Request $request, ExhibitorProfile $exhibitor): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = $exhibitor->user;
        if (! $user || strtolower((string) $user->status) !== 'active'
            || ! hash_equals(strtolower((string) $user->username), strtolower(trim($validated['username'])))
            || ! Hash::check($validated['password'], $user->password)) {
            return back()->withInput($request->only('username'))->withErrors([
                'username' => 'The username or password is incorrect.',
            ]);
        }

        $request->session()->put('exhibitor_profile_id', $exhibitor->id);
        $request->session()->regenerate();

        return redirect()->route('exhibitor.registration.show', $exhibitor);
    }

    public function store(Request $request, ExhibitorProfile $exhibitor): RedirectResponse
    {
        $this->ensureAuthenticated($exhibitor, $request);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'ngja_file_number' => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'name_board' => ['required', 'string', 'max:150'],
            'package' => ['required', 'in:designer,standard,mini'],
        ]);

        $exhibitor->update([
            ...$validated,
            'member_limit' => self::PACKAGES[$validated['package']],
            'registered_at' => now(),
        ]);

        return redirect()->route('exhibitor.dashboard', $exhibitor)->with('status', 'Exhibitor profile saved. You can now add members.');
    }

    public function dashboard(Request $request, ExhibitorProfile $exhibitor): View|RedirectResponse
    {
        $this->ensureAuthenticated($exhibitor, $request);
        if (! $exhibitor->registered_at) {
            return redirect()->route('exhibitor.registration.show', $exhibitor);
        }

        $exhibitor->load(['members' => fn ($query) => $query->latest()]);

        return view('exhibitor.dashboard', compact('exhibitor'));
    }

    private function isAuthenticated(ExhibitorProfile $exhibitor, Request $request): bool
    {
        return (int) $request->session()->get('exhibitor_profile_id') === $exhibitor->id;
    }

    private function ensureAuthenticated(ExhibitorProfile $exhibitor, Request $request): void
    {
        abort_unless($this->isAuthenticated($exhibitor, $request), 403);
    }
}
