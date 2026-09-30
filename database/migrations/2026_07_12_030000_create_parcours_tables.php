<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('formations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee_debut');
            $table->unsignedTinyInteger('mois_debut')->nullable();
            $table->unsignedSmallInteger('annee_fin')->nullable();
            $table->unsignedTinyInteger('mois_fin')->nullable();
            $table->string('domaine');        // Domaine d'études
            $table->string('etablissement');  // École / Nom de l'établissement
            $table->string('ville')->nullable();
            $table->timestamps();
        });

        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee_debut');
            $table->unsignedTinyInteger('mois_debut')->nullable();
            $table->unsignedSmallInteger('annee_fin')->nullable();
            $table->unsignedTinyInteger('mois_fin')->nullable();
            $table->string('activite');   // Activité / Profession
            $table->string('employeur');  // Entreprise / Employeur
            $table->string('ville')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiences');
        Schema::dropIfExists('formations');
    }
};
