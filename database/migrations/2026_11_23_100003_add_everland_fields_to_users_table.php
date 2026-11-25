<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_code', 30)->nullable()->unique()->after('id');
            $table->foreignId('role_id')->nullable()->after('password')->constrained('roles')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('role_id')->constrained('departments')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
            $table->string('position')->nullable()->after('manager_id');
            $table->string('phone', 30)->nullable()->after('position');
            $table->string('avatar')->nullable()->after('phone');
            $table->date('join_date')->nullable()->after('avatar');
            $table->date('date_of_birth')->nullable()->after('join_date');
            $table->string('address')->nullable()->after('date_of_birth');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['manager_id']);
            $table->dropColumn([
                'employee_code', 'role_id', 'department_id', 'manager_id', 'position',
                'phone', 'avatar', 'join_date', 'date_of_birth', 'address', 'status',
            ]);
        });
    }
};
