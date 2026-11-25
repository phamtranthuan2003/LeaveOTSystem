<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->input('year', now()->year);

        $holidays = Holiday::whereYear('holiday_date', $year)
            ->orderBy('holiday_date')
            ->get();

        $editing = $request->filled('edit') ? Holiday::find($request->integer('edit')) : null;
        $years = range(now()->year + 1, now()->year - 2);

        return view('admin.holidays.index', compact('holidays', 'year', 'editing', 'years'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $holiday = Holiday::create($data);

        AuditLogger::log('holiday.create', 'holidays', "Thêm ngày lễ {$holiday->name}", $holiday);

        return redirect()->route('admin.holidays.index', ['year' => $holiday->holiday_date->year])
            ->with('success', "Đã thêm ngày lễ {$holiday->name}.");
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $holiday->update($this->validated($request, $holiday));

        AuditLogger::log('holiday.update', 'holidays', "Cập nhật ngày lễ {$holiday->name}", $holiday);

        return redirect()->route('admin.holidays.index', ['year' => $holiday->holiday_date->year])
            ->with('success', 'Đã cập nhật ngày lễ.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $name = $holiday->name;
        $holiday->delete();

        AuditLogger::log('holiday.delete', 'holidays', "Xoá ngày lễ {$name}");

        return back()->with('success', "Đã xoá ngày lễ {$name}.");
    }

    protected function validated(Request $request, ?Holiday $holiday = null): array
    {
        return $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'holiday_date' => ['required', 'date', Rule::unique('holidays', 'holiday_date')->ignore($holiday?->id)],
            'description'  => ['nullable', 'string', 'max:255'],
        ], [
            'holiday_date.unique' => 'Ngày này đã được khai báo là ngày lễ.',
        ]);
    }
}
