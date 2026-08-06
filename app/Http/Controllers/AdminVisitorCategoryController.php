<?php

namespace App\Http\Controllers;

use App\Models\VisitorCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminVisitorCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = VisitorCategory::orderBy('created_at', 'desc')->get();
        $selectedCategory = $request->filled('category')
            ? $categories->firstWhere('id', (int) $request->input('category'))
            : null;

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
