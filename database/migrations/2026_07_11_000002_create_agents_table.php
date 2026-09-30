<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('prenoms');
            $table->string('noms');
            $table->string('matricule')->nullable()->unique();
            $table->string('profession')->nullable();
            $table->string('fonction')->nullable();
            $table->foreignId('direction_id')->constrained('directions');
            $table->enum('statut', ['fonctionnaire', 'police', 'contractuel_pav', 'autre'])->default('autre');
            $table->decimal('solde_conge_jours', 6, 1)->default(0);
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('date_naissance')->nullable();
            $table->date('date_prise_service')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
