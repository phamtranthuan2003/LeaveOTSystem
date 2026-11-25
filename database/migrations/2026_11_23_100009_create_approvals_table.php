<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedTinyInteger('step');
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->string('approver_role')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'skipped'])->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id']);
            $table->index(['approver_id', 'status']);
            $table->unique(['approvable_type', 'approvable_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
