<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->foreignId('rubrique_id')->nullable()->constrained('rubriques')->nullOnDelete();
            $table->enum('type', ['loi', 'decret', 'reglement', 'rapport', 'circulaire', 'guide', 'archive', 'ordonnance', 'autre'])->default('autre');
            $table->string('reference')->nullable();
            $table->date('date_document')->nullable();
            $table->text('resume')->nullable();
            $table->string('mots_cles')->nullable();
            $table->enum('source', ['fichier', 'lien', 'texte']);
            $table->string('fichier_path')->nullable();
            $table->string('url')->nullable();
            $table->longText('contenu')->nullable();
            $table->foreignId('publie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
