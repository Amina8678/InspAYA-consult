<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricingPlan;
use Illuminate\Http\Request;

class PricingPlanController extends Controller
{
    public function index()
    {
        return view('admin.pricing.index', ['items' => PricingPlan::orderBy('order')->get()]);
    }

    public function create()
    {
        return view('admin.pricing.form', ['item' => new PricingPlan()]);
    }

    public function store(Request $request)
    {
        PricingPlan::create($this->validated($request));
        return redirect()->route('admin.pricing.index')->with('status', 'Plan created.');
    }

    public function edit(PricingPlan $pricing)
    {
        return view('admin.pricing.form', ['item' => $pricing]);
    }

    public function update(Request $request, PricingPlan $pricing)
    {
        $pricing->update($this->validated($request));
        return redirect()->route('admin.pricing.index')->with('status', 'Plan updated.');
    }

    public function destroy(PricingPlan $pricing)
    {
        $pricing->delete();
        return back()->with('status', 'Plan deleted.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'price'    => ['required', 'string', 'max:50'],
            'features' => ['nullable', 'string'],
            'order'    => ['nullable', 'integer'],
        ]);

        $data['features'] = collect(explode("\n", $data['features'] ?? ''))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        return $data;
    }
}
