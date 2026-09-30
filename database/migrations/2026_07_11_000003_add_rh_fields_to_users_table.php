<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('matricule')->nullable()->unique()->after('name');
            $table->enum('role', ['agent', 'chef_direction', 'admin_rh', 'dg'])
                ->default('agent')->after('matricule');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['matricule', 'role']);
        });
    }
};
