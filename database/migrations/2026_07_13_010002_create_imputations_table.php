<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('imputations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courrier_id')->constrained('courriers')->cascadeOnDelete();
            $table->enum('niveau', ['dg', 'direction']);
            $table->foreignId('entite_source_id')->nullable()->constrained('entites')->nullOnDelete();
            $table->json('mentions')->nullable();
            $table->text('observations')->nullable();
            $table->string('signataire_nom')->nullable();
            $table->string('scan_path')->nullable();
            $table->foreignId('saisi_par')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imputations');
    }
};
