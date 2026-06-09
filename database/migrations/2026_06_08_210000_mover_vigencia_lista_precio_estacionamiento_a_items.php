<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lista_precio_estacionamiento_item', 'fecha_vigencia')) {
            Schema::table('lista_precio_estacionamiento_item', function (Blueprint $table) {
                $table->date('fecha_vigencia')->nullable()->after('precio');
                $table->unsignedBigInteger('usuarioultcambio_id')->nullable()->after('fecha_vigencia');
            });
        }

        if (Schema::hasColumn('lista_precio_estacionamiento', 'fecha_vigencia')) {
            $listas = DB::table('lista_precio_estacionamiento')->select('id', 'fecha_vigencia', 'creousuario_id')->get();
            foreach ($listas as $lista) {
                DB::table('lista_precio_estacionamiento_item')
                    ->where('lista_precio_estacionamiento_id', $lista->id)
                    ->whereNull('fecha_vigencia')
                    ->update([
                        'fecha_vigencia' => $lista->fecha_vigencia,
                        'usuarioultcambio_id' => $lista->creousuario_id,
                    ]);
            }
        }

        if (! $this->foreignKeyExists('lista_precio_estacionamiento_item', 'fk_lp_est_item_usuario')) {
            Schema::table('lista_precio_estacionamiento_item', function (Blueprint $table) {
                $table->foreign('usuarioultcambio_id', 'fk_lp_est_item_usuario')
                    ->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            });
        }

        DB::table('lista_precio_estacionamiento_item')
            ->whereNull('fecha_vigencia')
            ->update(['fecha_vigencia' => now()->toDateString()]);

        $usuarioDefault = (int) (DB::table('usuario')->orderBy('id')->value('id') ?? 1);
        DB::table('lista_precio_estacionamiento_item')
            ->whereNull('usuarioultcambio_id')
            ->update(['usuarioultcambio_id' => $usuarioDefault]);

        if ($this->foreignKeyExists('lista_precio_estacionamiento_item', 'fk_lp_est_item_usuario')) {
            Schema::table('lista_precio_estacionamiento_item', function (Blueprint $table) {
                $table->dropForeign('fk_lp_est_item_usuario');
            });
        }

        DB::statement('ALTER TABLE lista_precio_estacionamiento_item MODIFY fecha_vigencia DATE NOT NULL');
        DB::statement('ALTER TABLE lista_precio_estacionamiento_item MODIFY usuarioultcambio_id BIGINT UNSIGNED NOT NULL');

        Schema::table('lista_precio_estacionamiento_item', function (Blueprint $table) {
            $table->foreign('usuarioultcambio_id', 'fk_lp_est_item_usuario')
                ->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
        });

        if (Schema::hasColumn('lista_precio_estacionamiento', 'fecha_vigencia')) {
            DB::statement('ALTER TABLE lista_precio_estacionamiento DROP COLUMN fecha_vigencia');
        }

        if (! $this->indexExists('lista_precio_estacionamiento', 'uq_lista_precio_estacionamiento_empresa_cat')) {
            Schema::table('lista_precio_estacionamiento', function (Blueprint $table) {
                $table->unique(
                    ['empresa_id', 'categoria_automovil_id'],
                    'uq_lista_precio_estacionamiento_empresa_cat'
                );
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('lista_precio_estacionamiento', 'uq_lista_precio_estacionamiento_empresa_cat')) {
            Schema::table('lista_precio_estacionamiento', function (Blueprint $table) {
                $table->dropUnique('uq_lista_precio_estacionamiento_empresa_cat');
            });
        }

        if (! Schema::hasColumn('lista_precio_estacionamiento', 'fecha_vigencia')) {
            Schema::table('lista_precio_estacionamiento', function (Blueprint $table) {
                $table->date('fecha_vigencia')->nullable()->after('categoria_automovil_id');
            });
        }

        $listas = DB::table('lista_precio_estacionamiento')->pluck('id');
        foreach ($listas as $listaId) {
            $maxFecha = DB::table('lista_precio_estacionamiento_item')
                ->where('lista_precio_estacionamiento_id', $listaId)
                ->max('fecha_vigencia');
            if ($maxFecha) {
                DB::table('lista_precio_estacionamiento')
                    ->where('id', $listaId)
                    ->update(['fecha_vigencia' => $maxFecha]);
            }
        }

        DB::statement('ALTER TABLE lista_precio_estacionamiento MODIFY fecha_vigencia DATE NOT NULL');

        Schema::table('lista_precio_estacionamiento', function (Blueprint $table) {
            $table->unique(
                ['empresa_id', 'categoria_automovil_id', 'fecha_vigencia'],
                'uq_lista_precio_estacionamiento_empresa_cat_fecha'
            );
        });

        if ($this->foreignKeyExists('lista_precio_estacionamiento_item', 'fk_lp_est_item_usuario')) {
            Schema::table('lista_precio_estacionamiento_item', function (Blueprint $table) {
                $table->dropForeign('fk_lp_est_item_usuario');
            });
        }

        Schema::table('lista_precio_estacionamiento_item', function (Blueprint $table) {
            $table->dropColumn(['fecha_vigencia', 'usuarioultcambio_id']);
        });
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $db = DB::getDatabaseName();

        return (int) DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $db)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->count() > 0;
    }

    private function indexExists(string $table, string $name): bool
    {
        $db = DB::getDatabaseName();

        return (int) DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $name)
            ->count() > 0;
    }
};
