<?php

namespace App\Console\Commands;

use App\Models\LeaveType;
use App\Services\BalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class AccruePaidPersonalLeave extends Command
{
    protected $signature = 'leave:accrue-personal {--month= : Kỳ cộng ngày (định dạng Y-m), mặc định tháng hiện tại}';

    protected $description = 'Cộng 1 ngày nghỉ việc riêng có lương vào quỹ phép của nhân sự mỗi đầu tháng';

    public function handle(BalanceService $balances): int
    {
        $type = LeaveType::where('code', 'CL')->where('active', true)->where('deduct_balance', true)->first();

        if (! $type) {
            $this->error('Không tìm thấy loại nghỉ "Nghỉ việc riêng có lương" (mã CL) đang hoạt động.');

            return self::FAILURE;
        }

        $month = $this->option('month')
            ? CarbonImmutable::createFromFormat('!Y-m', $this->option('month'))
            : CarbonImmutable::now()->startOfMonth();

        if (! $month) {
            $this->error('Kỳ không hợp lệ, cần định dạng Y-m (ví dụ: 2026-12).');

            return self::FAILURE;
        }

        $accrued = $balances->accrueMonthly($type, $month);

        $this->info(sprintf('Đã cộng 1 ngày "%s" cho %d nhân sự (kỳ %s).', $type->name, $accrued, $month->format('Y-m')));

        return self::SUCCESS;
    }
}
