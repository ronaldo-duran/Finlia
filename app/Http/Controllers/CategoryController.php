<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * Muestra las categorías globales (read-only) y las personales del hogar activo.
     */
    public function index(Request $request): View
    {
        $householdId = active_household_id();

        $categories = Category::forHousehold($householdId)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('categories.index', [
            'categories' => $categories,
            'activeHouseholdId' => $householdId,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        active_household()->categories()->create($request->validatedData());

        return redirect()
            ->route('categories.index')
            ->with('status', __('Categoría creada.'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validatedData());

        return redirect()
            ->route('categories.index')
            ->with('status', __('Categoría actualizada.'));
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('status', __('Categoría ":name" eliminada.', ['name' => $category->name]));
    }
}
