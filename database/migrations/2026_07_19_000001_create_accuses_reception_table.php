<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accuses_reception', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courrier_id')->constrained('courriers')->cascadeOnDelete();
            $table->foreignId('entite_id')->constrained('entites')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
            $table->unique(['courrier_id', 'entite_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accuses_reception');
    }
};
