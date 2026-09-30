<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entites', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('nom');
            $table->enum('type', ['direction', 'service', 'division']);
            $table->foreignId('parent_id')->nullable()->constrained('entites')->nullOnDelete();
            $table->foreignId('chef_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('secretaire_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entites');
    }
};
