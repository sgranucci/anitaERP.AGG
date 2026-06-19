<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ticket', 'titulo')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->string('titulo', 255)->nullable()->after('sector_id');
            });
        }

        if (Schema::hasColumn('ticket', 'detalle') && ! Schema::hasColumn('ticket', 'comentario')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->renameColumn('detalle', 'comentario');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ticket', 'comentario') && ! Schema::hasColumn('ticket', 'detalle')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->renameColumn('comentario', 'detalle');
            });
        }

        if (Schema::hasColumn('ticket', 'titulo')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->dropColumn('titulo');
            });
        }
    }
};
