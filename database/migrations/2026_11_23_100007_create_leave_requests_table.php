<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('day_type', ['full', 'morning', 'afternoon'])->default('full');
            $table->decimal('total_days', 5, 1)->default(1);
            $table->text('reason');
            $table->foreignId('handover_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('attachment')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->unsignedTinyInteger('total_steps')->default(0);
            $table->boolean('balance_deducted')->default(false);
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
