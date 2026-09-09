<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket')) {
            return;
        }

        Schema::table('ticket', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket', 'empresa_id')) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('usuario_id');
                $table->foreign('empresa_id', 'fk_ticket_empresa')
                    ->references('id')
                    ->on('empresa')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ticket') || ! Schema::hasColumn('ticket', 'empresa_id')) {
            return;
        }

        Schema::table('ticket', function (Blueprint $table) {
            $table->dropForeign('fk_ticket_empresa');
            $table->dropColumn('empresa_id');
        });
    }
};
