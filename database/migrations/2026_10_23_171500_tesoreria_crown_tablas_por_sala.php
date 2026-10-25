<?php

use App\Models\Caja\TesoreriaCrownTipoDeposito;
use App\Models\Caja\TesoreriaCrownTipoValor;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada sala tiene su catálogo. Lo que estaba cargado sin sala se copia a cada una.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $this->partirTiposValor();
        $this->partirTiposDeposito();
        $this->salaEnValores();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $this->volverCatalogoGlobal('tesoreria_crown_tipo_valor', 'tc_tipo_valor_sala_codigo_uq', 'tc_tipo_valor_codigo_uq');
        $this->volverCatalogoGlobal('tesoreria_crown_tipo_deposito', 'tc_tipo_deposito_sala_codigo_uq', 'tc_tipo_deposito_codigo_uq');

        if (Schema::hasColumn('tesoreria_crown_valor', 'sala_id')) {
            Schema::table('tesoreria_crown_valor', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sala_id');
            });
        }
    }

    private function partirTiposValor(): void
    {
        if (Schema::hasColumn('tesoreria_crown_tipo_valor', 'sala_id')) {
            return;
        }

        Schema::table('tesoreria_crown_tipo_valor', function (Blueprint $table) {
            $table->dropUnique('tc_tipo_valor_codigo_uq');
            $table->foreignId('sala_id')->nullable()->after('id')->constrained('sala');
        });

        $this->copiarCatalogoPorSala('tesoreria_crown_tipo_valor', TesoreriaCrownTipoValor::class, [
            'codigo',
            'nombre',
            'es_moneda_referencia',
            'controla_stock',
            'cotizacion_fija',
            'orden',
            'activo',
        ]);

        DB::statement('ALTER TABLE tesoreria_crown_tipo_valor MODIFY sala_id BIGINT UNSIGNED NOT NULL');
        Schema::table('tesoreria_crown_tipo_valor', function (Blueprint $table) {
            $table->unique(['sala_id', 'codigo'], 'tc_tipo_valor_sala_codigo_uq');
        });
    }

    private function partirTiposDeposito(): void
    {
        if (Schema::hasColumn('tesoreria_crown_tipo_deposito', 'sala_id')) {
            return;
        }

        Schema::table('tesoreria_crown_tipo_deposito', function (Blueprint $table) {
            $table->dropUnique('tc_tipo_deposito_codigo_uq');
            $table->foreignId('sala_id')->nullable()->after('id')->constrained('sala');
        });

        $this->copiarCatalogoPorSala('tesoreria_crown_tipo_deposito', TesoreriaCrownTipoDeposito::class, [
            'codigo',
            'nombre',
            'orden',
            'activo',
        ]);

        DB::statement('ALTER TABLE tesoreria_crown_tipo_deposito MODIFY sala_id BIGINT UNSIGNED NOT NULL');
        Schema::table('tesoreria_crown_tipo_deposito', function (Blueprint $table) {
            $table->unique(['sala_id', 'codigo'], 'tc_tipo_deposito_sala_codigo_uq');
        });
    }

    private function salaEnValores(): void
    {
        if (Schema::hasColumn('tesoreria_crown_valor', 'sala_id')) {
            return;
        }

        Schema::table('tesoreria_crown_valor', function (Blueprint $table) {
            $table->foreignId('sala_id')->nullable()->after('id')->constrained('sala');
        });

        $huerfanos = DB::table('tesoreria_crown_valor')->whereNull('sala_id')->count();
        if ($huerfanos > 0) {
            throw new RuntimeException('Hay valores de tesorería sin sala. No se puede cerrar la columna.');
        }

        DB::statement('ALTER TABLE tesoreria_crown_valor MODIFY sala_id BIGINT UNSIGNED NOT NULL');
    }

    /**
     * @param  class-string  $modelo
     * @param  list<string>  $columnas
     */
    private function copiarCatalogoPorSala(string $tabla, string $modelo, array $columnas): void
    {
        $salas = DB::table('sala')->orderBy('id')->pluck('id');
        if ($salas->isEmpty()) {
            throw new RuntimeException('No hay salas para separar el catálogo de tesorería.');
        }

        $base = DB::table($tabla)->whereNull('sala_id')->get();
        foreach ($salas as $salaId) {
            foreach ($base as $fila) {
                $datos = ['sala_id' => (int) $salaId];
                foreach ($columnas as $columna) {
                    $datos[$columna] = in_array($columna, ['es_moneda_referencia', 'controla_stock', 'cotizacion_fija', 'activo'], true)
                        ? (int) $fila->{$columna}
                        : $fila->{$columna};
                }
                $modelo::query()->create($datos);
            }
        }

        $modelo::query()->whereNull('sala_id')->get()->each(function ($fila) {
            $fila->delete();
        });
    }

    private function volverCatalogoGlobal(string $tabla, string $uniqueSala, string $uniqueCodigo): void
    {
        if (! Schema::hasColumn($tabla, 'sala_id')) {
            return;
        }

        Schema::table($tabla, function (Blueprint $table) use ($uniqueSala) {
            $table->dropUnique($uniqueSala);
        });

        $codigos = DB::table($tabla)->select('codigo')->distinct()->pluck('codigo');
        foreach ($codigos as $codigo) {
            $ids = DB::table($tabla)->where('codigo', $codigo)->orderBy('id')->pluck('id');
            $sobran = $ids->slice(1)->all();
            if ($sobran !== []) {
                DB::table($tabla)->whereIn('id', $sobran)->delete();
            }
        }

        Schema::table($tabla, function (Blueprint $table) use ($uniqueCodigo) {
            $table->dropConstrainedForeignId('sala_id');
            $table->unique('codigo', $uniqueCodigo);
        });
    }
};
