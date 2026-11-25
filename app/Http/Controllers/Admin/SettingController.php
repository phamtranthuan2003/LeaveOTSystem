<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SettingController extends Controller
{
    protected array $fields = [
        'Thông tin công ty' => [
            'company_name'    => ['label' => 'Tên công ty', 'type' => 'text', 'rules' => ['required', 'string', 'max:255']],
            'company_address' => ['label' => 'Địa chỉ trụ sở', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
            'company_phone'   => ['label' => 'Hotline', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:30']],
            'company_email'   => ['label' => 'Email liên hệ', 'type' => 'email', 'rules' => ['nullable', 'email', 'max:255']],
        ],
        'Chính sách nghỉ phép' => [
            'work_start'            => ['label' => 'Giờ bắt đầu làm việc', 'type' => 'time', 'rules' => ['required', 'date_format:H:i']],
            'work_end'              => ['label' => 'Giờ kết thúc làm việc', 'type' => 'time', 'rules' => ['required', 'date_format:H:i']],
            'annual_leave_default'  => ['label' => 'Phép năm mặc định (ngày)', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0', 'max:60']],
            'max_carry_over'        => ['label' => 'Số ngày phép tối đa được chuyển năm', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0', 'max:30']],
        ],
        'Hệ số làm thêm giờ (OT)' => [
            'ot_rate_weekday' => ['label' => 'Hệ số OT ngày thường', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:1', 'max:5']],
            'ot_rate_weekend' => ['label' => 'Hệ số OT cuối tuần', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:1', 'max:5']],
            'ot_rate_holiday' => ['label' => 'Hệ số OT ngày lễ', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:1', 'max:5']],
        ],
    ];

    public function index(): View
    {
        $settings = Setting::all()->keyBy('key');
        $groups = $this->fields;

        return view('admin.settings.index', compact('settings', 'groups'));
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        $attributes = [];

        foreach ($this->fields as $fields) {
            foreach ($fields as $key => $config) {
                $rules[$key] = $config['rules'];
                $attributes[$key] = $config['label'];
            }
        }

        $data = $request->validate($rules, [], $attributes);

        foreach ($this->fields as $group => $fields) {
            foreach ($fields as $key => $config) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $data[$key] ?? null, 'group' => $group, 'label' => $config['label']]
                );
            }
        }

        Cache::forget('app_settings');

        AuditLogger::log('settings.update', 'settings', 'Cập nhật cấu hình hệ thống');

        return back()->with('success', 'Đã lưu cấu hình hệ thống.');
    }
}
