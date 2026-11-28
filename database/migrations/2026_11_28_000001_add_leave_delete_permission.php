<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::updateOrCreate(
            ['slug' => 'leave.delete'],
            ['name' => 'Xoá vĩnh viễn đơn nghỉ phép', 'group' => 'Nghỉ phép']
        );

        $adminId = Role::where('slug', 'admin')->value('id');

        if ($adminId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $adminId,
                'permission_id' => $permission->id,
            ]);
        }
    }

    public function down(): void
    {
        Permission::where('slug', 'leave.delete')->delete();
    }
};