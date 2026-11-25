<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('ot_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('hours', 5, 2)->default(0);
            $table->enum('ot_type', ['weekday', 'weekend', 'holiday'])->default('weekday');
            $table->decimal('multiplier', 3, 1)->default(1.5);
            $table->string('project')->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->unsignedTinyInteger('total_steps')->default(0);
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('ot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
