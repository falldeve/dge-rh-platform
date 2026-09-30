<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('courriers', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->string('objet');
            $table->string('expediteur');
            $table->date('date_arrivee');
            $table->date('date_depart')->nullable();
            $table->string('scan_path')->nullable();
            $table->foreignId('enregistre_par')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courriers');
    }
};
