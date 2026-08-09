<?php

namespace App\Http\Controllers;

use App\Models\VisitorCategory;
use App\Models\VerifiedVisitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminVisitorCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = VisitorCategory::query()
            ->withCount('visitors')
            ->orderBy('created_at', 'desc')
            ->get();
        $selectedCategory = $request->filled('category')
            ? $categories->firstWhere('id', (int) $request->input('category'))
            : null;

        $selectedCategory?->load([
            'visitors' => fn ($query) => $query->latest(),
        ]);

        return view('admin.configurations.categories', compact('categories', 'selectedCategory'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:visitor_categories,code'],
            'description' => ['nullable', 'string', 'max:500'],
            'badge_color' => ['required', 'string', 'max:30'],
            'entrance_fee' => ['required', 'numeric', 'min:0'],
            'access_schedule' => ['nullable', 'array'],
            'access_schedule.*.date' => ['required_with:access_schedule', 'date'],
            'access_schedule.*.from' => ['required_with:access_schedule', 'date_format:H:i'],
            'access_schedule.*.to' => ['required_with:access_schedule', 'date_format:H:i', 'after:access_schedule.*.from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = Str::slug($validated['name']);
        } else {
            $validated['code'] = Str::slug($validated['code']);
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['access_schedule'] = $this->normaliseSchedule($validated['access_schedule'] ?? []);

        $category = VisitorCategory::create($validated);

        return redirect()
            ->route('admin.configurations.categories.index', ['category' => $category->id])
            ->with('status', 'Visitor Category created successfully.');
    }

    public function update(Request $request, VisitorCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:visitor_categories,code,' . $category->id],
            'description' => ['nullable', 'string', 'max:500'],
            'badge_color' => ['required', 'string', 'max:30'],
            'entrance_fee' => ['required', 'numeric', 'min:0'],
            'access_schedule' => ['nullable', 'array'],
            'access_schedule.*.date' => ['required_with:access_schedule', 'date'],
            'access_schedule.*.from' => ['required_with:access_schedule', 'date_format:H:i'],
            'access_schedule.*.to' => ['required_with:access_schedule', 'date_format:H:i', 'after:access_schedule.*.from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = Str::slug($validated['name']);
        } else {
            $validated['code'] = Str::slug($validated['code']);
        }

        $validated['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : $category->is_active;
        $validated['access_schedule'] = $this->normaliseSchedule($validated['access_schedule'] ?? []);

        $category->update($validated);

        return redirect()
            ->route('admin.configurations.categories.index')
            ->with('status', 'Visitor Category updated successfully.');
    }

    public function toggleActive(VisitorCategory $category): RedirectResponse
    {
        $category->update(['is_active' => !$category->is_active]);

        return redirect()
            ->route('admin.configurations.categories.index')
            ->with('status', 'Visitor Category status updated.');
    }

    /**
     * Issue a gate pass for a person who belongs to a pre-configured category
     * (for example staff, exhibitor, sponsor or VIP).
     */
    public function storeMember(Request $request, VisitorCategory $category): RedirectResponse
    {
        abort_unless($category->is_active, 422, 'Inactive categories cannot receive new members.');

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'company' => ['nullable', 'string', 'max:150'],
            'occupation' => ['nullable', 'string', 'max:100'],
        ]);

        $member = VerifiedVisitor::create([
            'verification_id' => (string) Str::uuid(),
            'visitor_category_id' => $category->id,
            'full_name' => $validated['full_name'],
            'full_name_latin' => $validated['full_name'],
            'email' => $validated['email'] ?? null,
            'mobile_number' => $validated['mobile_number'] ?? null,
            'company' => $validated['company'] ?? null,
            'occupation' => $validated['occupation'] ?? null,
            'category' => $category->name,
            'entrance_fee' => $category->entrance_fee,
            'payment_status' => 'paid',
            'registration_status' => 'registered',
            'verified_at' => now(),
            'identity_reviewed_at' => now(),
            'face_verification_status' => 'not_required',
            'ocr_provider' => 'category_member',
        ]);

        return redirect()
            ->route('admin.visitors.badge', $member)
            ->with('status', "{$validated['full_name']} was added to {$category->name}. Print or save the QR pass.");
    }

    public function destroy(VisitorCategory $category): RedirectResponse
    {
        $category->delete();

        return redirect()
            ->route('admin.configurations.categories.index')
            ->with('status', 'Visitor Category removed.');
    }

    private function normaliseSchedule(array $schedule): array
    {
        return collect($schedule)
            ->filter(fn ($slot) => !empty($slot['date']) && !empty($slot['from']) && !empty($slot['to']))
            ->values()
            ->all();
    }
}
