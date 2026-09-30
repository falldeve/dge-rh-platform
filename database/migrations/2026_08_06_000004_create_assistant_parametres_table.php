<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_parametres', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('pool_credits')->default(0);
            $table->timestamps();
        });

        DB::table('assistant_parametres')->insert([
            'pool_credits' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_parametres');
    }
};
