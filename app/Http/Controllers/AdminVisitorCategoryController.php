<?php

namespace App\Http\Controllers;

use App\Models\VisitorCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminVisitorCategoryController extends Controller
{
    public function index(): View
    {
        $categories = VisitorCategory::orderBy('created_at', 'desc')->get();

        return view('admin.configurations.categories', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:visitor_categories,code'],
            'description' => ['nullable', 'string', 'max:500'],
            'badge_color' => ['required', 'string', 'max:30'],
            'entrance_fee' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = Str::slug($validated['name']);
        } else {
            $validated['code'] = Str::slug($validated['code']);
        }

        $validated['is_active'] = $request->has('is_active') ? true : false;

        VisitorCategory::create($validated);

        return redirect()
            ->route('admin.configurations.categories.index')
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
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = Str::slug($validated['name']);
        } else {
            $validated['code'] = Str::slug($validated['code']);
        }

        $validated['is_active'] = $request->has('is_active') ? true : false;

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
}
