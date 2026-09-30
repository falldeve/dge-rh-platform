<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('ordre')->default(0);
            $table->longText('contenu');
            $table->timestamps();
        });

        // FULLTEXT uniquement sous MySQL (SQLite ne le supporte pas — tests).
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE document_chunks ADD FULLTEXT bibliotheque_chunks_ft (contenu)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
