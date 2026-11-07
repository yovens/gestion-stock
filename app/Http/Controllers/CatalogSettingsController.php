<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Catégories et unités : petites listes gérées sur une seule page chacune. */
class CatalogSettingsController extends Controller
{
    // ------------------------------------------------------------ catégories

    public function categories(): View
    {
        return view('catalog.categories', [
            'categories' => Category::with(['children' => fn ($q) => $q->withCount('products')->orderBy('name')])
                ->withCount('products')->whereNull('parent_id')->orderBy('name')->get(),
            'parents' => Category::whereNull('parent_id')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $this->validateCategory($request);
        Category::create($data + ['slug' => $this->uniqueSlug($data['name'])]);

        return back()->with('success', "Catégorie « {$data['name']} » ajoutée.");
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validateCategory($request, $category);
        if (($data['parent_id'] ?? null) && $category->children()->exists()) {
            return back()->with('error', 'Une catégorie qui a des sous-catégories ne peut pas devenir sous-catégorie.');
        }
        $category->update($data);

        return back()->with('success', 'Catégorie mise à jour.');
    }

    public function destroyCategory(Category $category): RedirectResponse
    {
        if ($category->products()->withTrashed()->exists() || $category->children()->exists()) {
            return back()->with('error', 'Cette catégorie contient des produits ou des sous-catégories : désactivez-la plutôt.');
        }
        $category->delete();

        return back()->with('success', 'Catégorie supprimée.');
    }

    // ---------------------------------------------------------------- unités

    public function units(): View
    {
        return view('catalog.units', [
            'units' => Unit::withCount(['products as base_count', 'productUnits as pack_count'])->orderBy('name')->get(),
        ]);
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        Unit::create($this->validateUnit($request));

        return back()->with('success', 'Unité ajoutée.');
    }

    public function updateUnit(Request $request, Unit $unit): RedirectResponse
    {
        $unit->update($this->validateUnit($request, $unit));

        return back()->with('success', 'Unité mise à jour.');
    }

    public function destroyUnit(Unit $unit): RedirectResponse
    {
        if ($unit->products()->withTrashed()->exists() || $unit->productUnits()->exists()) {
            return back()->with('error', 'Cette unité est utilisée par des produits.');
        }
        $unit->delete();

        return back()->with('success', 'Unité supprimée.');
    }

    // ------------------------------------------------------------------------

    private function validateCategory(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->whereNull('parent_id'),
                Rule::notIn(array_filter([$category?->id]))],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => ['boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function validateUnit(Request $request, ?Unit $unit = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('units', 'name')->ignore($unit?->id)],
            'abbreviation' => ['required', 'string', 'max:15'],
            'allows_decimal' => ['boolean'],
        ]);
        $data['allows_decimal'] = $request->boolean('allows_decimal');

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'categorie';
        $slug = $base;
        for ($i = 2; Category::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
