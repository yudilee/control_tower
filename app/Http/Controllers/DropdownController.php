<?php

namespace App\Http\Controllers;

use App\Models\DropdownOption;
use Illuminate\Http\Request;

class DropdownController extends Controller
{
    /**
     * Display dropdown options management
     */
    public function index(Request $request)
    {
        $types = DropdownOption::getTypes();
        $currentType = $request->input('type', 'work_status');
        
        $options = DropdownOption::where('type', $currentType)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return view('admin.dropdowns.index', compact('types', 'currentType', 'options'));
    }

    /**
     * Show form for creating new option
     */
    public function create(Request $request)
    {
        $types = DropdownOption::getTypes();
        $currentType = $request->input('type', 'work_status');
        $colors = $this->getColors();
        $icons = $this->getIcons();
        
        return view('admin.dropdowns.form', compact('types', 'currentType', 'colors', 'icons'));
    }

    /**
     * Store new option
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:' . implode(',', array_keys(DropdownOption::getTypes())),
            'value' => 'required|string|max:100',
            'label' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'required|string|max:20',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Auto-generate value from label if not provided or same as label
        $validated['value'] = strtolower(str_replace(' ', '_', $validated['value']));
        $validated['is_active'] = $request->boolean('is_active', true);

        DropdownOption::create($validated);

        return redirect()->route('admin.dropdowns.index', ['type' => $validated['type']])
            ->with('success', 'Option added successfully.');
    }

    /**
     * Show form for editing option
     */
    public function edit(DropdownOption $dropdown)
    {
        $types = DropdownOption::getTypes();
        $currentType = $dropdown->type;
        $colors = $this->getColors();
        $icons = $this->getIcons();
        
        return view('admin.dropdowns.form', compact('dropdown', 'types', 'currentType', 'colors', 'icons'));
    }

    /**
     * Update option
     */
    public function update(Request $request, DropdownOption $dropdown)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:' . implode(',', array_keys(DropdownOption::getTypes())),
            'value' => 'required|string|max:100',
            'label' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'required|string|max:20',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['value'] = strtolower(str_replace(' ', '_', $validated['value']));
        $validated['is_active'] = $request->boolean('is_active', true);

        $dropdown->update($validated);

        return redirect()->route('admin.dropdowns.index', ['type' => $validated['type']])
            ->with('success', 'Option updated successfully.');
    }

    /**
     * Delete option
     */
    public function destroy(DropdownOption $dropdown)
    {
        $type = $dropdown->type;
        $dropdown->delete();

        return redirect()->route('admin.dropdowns.index', ['type' => $type])
            ->with('success', 'Option deleted successfully.');
    }

    /**
     * Update sort order via AJAX
     */
    public function updateOrder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|integer|exists:dropdown_options,id',
            'order.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->order as $item) {
            DropdownOption::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Available colors
     */
    private function getColors(): array
    {
        return [
            'primary' => 'Primary (Blue)',
            'secondary' => 'Secondary (Gray)',
            'success' => 'Success (Green)',
            'danger' => 'Danger (Red)',
            'warning' => 'Warning (Yellow)',
            'info' => 'Info (Cyan)',
            'dark' => 'Dark',
            'light' => 'Light',
            'purple' => 'Purple',
            'pink' => 'Pink',
            'indigo' => 'Indigo',
            'orange' => 'Orange',
            'cyan' => 'Cyan (Bright)',
            'lime' => 'Lime',
            'maroon' => 'Maroon',
            'navy' => 'Navy',
            'olive' => 'Olive',
            'magenta' => 'Magenta',
            'coral' => 'Coral',
            'gold' => 'Gold',
            'mint' => 'Mint',
            'slate' => 'Slate',
            'khaki' => 'Khaki',
            'lavender' => 'Lavender',
        ];
    }

    /**
     * Common icons
     */
    private function getIcons(): array
    {
        return [
            '' => '(No Icon)',
            'hourglass-split' => 'Hourglass',
            'play-circle' => 'Play',
            'gear' => 'Gear',
            'hand-thumbs-up' => 'Thumbs Up',
            'check2-circle' => 'Check Circle',
            'pause-circle' => 'Pause',
            'x-circle' => 'X Circle',
            'clock' => 'Clock',
            'cash' => 'Cash',
            'credit-card' => 'Credit Card',
            'bank' => 'Bank',
            'person' => 'Person',
            'tools' => 'Tools',
            'wrench' => 'Wrench',
            'building' => 'Building',
            'grid' => 'Grid',
            'box' => 'Box',
            'truck' => 'Truck',
            'car-front' => 'Car',
        ];
    }
}
