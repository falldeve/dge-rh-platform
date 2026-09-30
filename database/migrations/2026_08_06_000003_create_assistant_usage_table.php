<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('annee_mois', 7); // YYYY-MM
            $table->unsignedInteger('credits_consommes')->default(0);
            $table->unsignedBigInteger('jetons_input')->default(0);
            $table->unsignedBigInteger('jetons_output')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'annee_mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_usage');
    }
};
