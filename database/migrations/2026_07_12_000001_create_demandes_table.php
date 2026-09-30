<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->enum('type', ['conge_annuel', 'permission', 'ordre_mission']);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->unsignedInteger('nb_jours');
            $table->text('motif')->nullable();
            $table->enum('statut', ['brouillon', 'soumise', 'validee_chef', 'validee_rh', 'refusee', 'emise'])->default('brouillon');
            $table->string('piece_jointe_path')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes');
    }
};
