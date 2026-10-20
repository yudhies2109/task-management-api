<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            // Pengguna yang memiliki task.
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // Nilai yang diizinkan akan diperiksa melalui validasi request.
            $table->string('status')->default('todo');
            $table->string('priority')->default('medium');

            // Tenggat task bersifat opsional.
            $table->date('due_date')->nullable();

            $table->timestampsTz();

            // Menyimpan waktu penghapusan agar task dapat dipulihkan.
            $table->softDeletesTz();

            // Membantu pencarian task berdasarkan pemilik dan status.
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
