<?php

namespace App\Http\Controllers;

use App\Models\ExhibitorProfile;
use App\Models\User;
use App\Models\VerifiedVisitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminExhibitorController extends Controller
{
    public function index(): View
    {
        $exhibitors = $this->exhibitors();

        return view('admin.exhibitors.index', compact('exhibitors'));
    }

    public function directory(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $exhibitors = $this->exhibitors($search);
        $selectedExhibitor = $request->filled('exhibitor')
            ? $exhibitors->firstWhere('id', $request->integer('exhibitor'))
            : null;

        return view('admin.exhibitors.directory', compact('exhibitors', 'search', 'selectedExhibitor'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:100', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:10', 'max:255'],
        ]);

        $user = User::create([
            'name' => 'Exhibitor '.$validated['username'],
            // The exhibitor completes its business email on the registration form.
            // A reserved internal address keeps the existing non-null users.email
            // constraint intact without exposing a made-up contact address.
            'email' => $validated['username'].'@exhibitor.local',
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'role' => 'Exhibitor',
            'status' => 'active',
            'permissions' => [],
        ]);

        $exhibitor = ExhibitorProfile::create([
            'user_id' => $user->id,
            'registration_token' => Str::random(64),
        ]);

        return redirect()
            ->route('admin.exhibitors.index')
            ->with('status', 'Exhibitor profile created. Share the registration link and credentials below.')
            ->with('new_exhibitor_id', $exhibitor->id)
            ->with('new_exhibitor_password', $validated['password']);
    }

    public function destroyMember(Request $request, int $exhibitorId, VerifiedVisitor $member): RedirectResponse
    {
        $exhibitor = ExhibitorProfile::findOrFail($exhibitorId);
        abort_unless((int) $member->exhibitor_profile_id === $exhibitor->id, 404);

        $paths = collect([$member->photo_path, $member->back_photo_path, $member->selfie_path])
            ->filter()
            ->map(fn ($path) => str_replace('\\', '/', trim($path)));

        // Also find any files from an older registration flow without allowing
        // member ID 1 to match files that belong to member ID 10.
        $searchIds = array_filter([$member->verification_id, (string) $member->id]);
        foreach (['local', 'public'] as $diskName) {
            foreach (Storage::disk($diskName)->allFiles('verified-visitors') as $file) {
                $normalized = str_replace('\\', '/', $file);
                $belongsToMember = collect($searchIds)->contains(
                    fn ($searchId) => preg_match('/^'.preg_quote((string) $searchId, '/').'(?:[._-]|$)/', basename($normalized)) === 1
                );

                if ($belongsToMember) {
                    $paths->push($normalized);
                }
            }
        }

        $validPaths = $paths
            ->filter()
            ->map(fn ($path) => str_replace('\\', '/', trim($path)))
            ->filter(fn ($path) => str_starts_with($path, 'verified-visitors/') && ! str_contains($path, '..'))
            ->unique()
            ->values();

        $failedDeletes = collect();
        foreach ($validPaths as $path) {
            foreach (['local', 'public'] as $diskName) {
                $disk = Storage::disk($diskName);

                try {
                    if ($disk->exists($path) && (! $disk->delete($path) || $disk->exists($path))) {
                        $failedDeletes->push("{$diskName}:{$path}");
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                    $failedDeletes->push("{$diskName}:{$path}");
                }
            }
        }

        $directoryParameters = array_filter([
            'search' => trim((string) $request->query('search', '')),
            'exhibitor' => $exhibitor->id,
        ]);

        if ($failedDeletes->isNotEmpty()) {
            return redirect()
                ->route('admin.exhibitors.directory', $directoryParameters)
                ->withErrors(['delete' => 'The member was not deleted because one or more identity photos could not be removed. Please retry or check storage permissions.']);
        }

        $member->gateLogs()->delete();
        $member->delete();

        return redirect()
            ->route('admin.exhibitors.directory', $directoryParameters)
            ->with('status', 'Exhibitor member deleted successfully.');
    }

    private function exhibitors(string $search = '')
    {
        return ExhibitorProfile::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('company_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('username', 'like', "%{$search}%"));
                });
            })
            ->with(['user', 'members' => fn ($query) => $query->latest()])
            ->withCount('members')
            ->latest()
            ->get();
    }
}
