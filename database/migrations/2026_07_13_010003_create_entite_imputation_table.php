<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entite_imputation', function (Blueprint $table) {
            $table->foreignId('imputation_id')->constrained('imputations')->cascadeOnDelete();
            $table->foreignId('entite_id')->constrained('entites')->cascadeOnDelete();
            $table->primary(['imputation_id', 'entite_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entite_imputation');
    }
};
