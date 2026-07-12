<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Permite que varios usuarios compartan el mismo email: se elimina el índice
 * único usuario_email_unique. La columna email sigue siendo NOT NULL, pero
 * deja de tener control de unicidad tanto en BD como en ValidacionUsuario.
 */
return new class extends Migration
{
    private const INDICE = 'usuario_email_unique';

    public function up(): void
    {
        if ($this->indiceExiste(self::INDICE)) {
            Schema::table('usuario', function (Blueprint $table) {
                $table->dropUnique(self::INDICE);
            });
        }
    }

    public function down(): void
    {
        if (! $this->indiceExiste(self::INDICE)) {
            Schema::table('usuario', function (Blueprint $table) {
                $table->unique('email', self::INDICE);
            });
        }
    }

    private function indiceExiste(string $nombre): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'usuario')
            ->where('index_name', $nombre)
            ->exists();
    }
};
