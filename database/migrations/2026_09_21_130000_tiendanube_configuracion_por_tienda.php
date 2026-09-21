<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Defaults de facturación Tiendanube por tienda (Ferli y Boaonda).
 * Las filas que ya existen quedan en la tienda Ferli.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tiendanube_configuracion')) {
            return;
        }

        $storeFerli = trim((string) env('TIENDANUBE_STORE_ID', '3796054'));
        if ($storeFerli === '') {
            $storeFerli = '3796054';
        }

        foreach ([
            'tiendanube_configuracion',
            'tiendanube_gateway_cuentacaja',
            'tiendanube_puntoventa_deposito',
        ] as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'store_id')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('store_id', 32)->nullable()->after('id');
            });
            DB::table($tabla)->where(function ($q) {
                $q->whereNull('store_id')->orWhere('store_id', '');
            })->update([
                'store_id' => $storeFerli,
            ]);
        }

        $this->reemplazarUnico(
            'tiendanube_gateway_cuentacaja',
            'tiendanube_gateway_cuentacaja_gateway_key_unique',
            ['store_id', 'gateway_key'],
            'tiendanube_gateway_store_key_unique'
        );
        $this->reemplazarUnico(
            'tiendanube_puntoventa_deposito',
            'tiendanube_puntoventa_deposito_puntoventa_id_unique',
            ['store_id', 'puntoventa_id'],
            'tiendanube_pv_dep_store_pv_unique',
            'puntoventa_id',
            'tiendanube_puntoventa_deposito_puntoventa_id_index'
        );

        if (Schema::hasTable('tiendanube_configuracion')
            && ! MigrationDialectSupport::tieneIndice('tiendanube_configuracion', 'tiendanube_configuracion_store_id_unique')) {
            Schema::table('tiendanube_configuracion', function (Blueprint $table) {
                $table->unique('store_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tiendanube_configuracion')
            && MigrationDialectSupport::tieneIndice('tiendanube_configuracion', 'tiendanube_configuracion_store_id_unique')) {
            MigrationDialectSupport::dropIndiceOUnique(
                'tiendanube_configuracion',
                'tiendanube_configuracion_store_id_unique'
            );
        }

        $this->reemplazarUnico(
            'tiendanube_gateway_cuentacaja',
            'tiendanube_gateway_store_key_unique',
            ['gateway_key'],
            'tiendanube_gateway_cuentacaja_gateway_key_unique'
        );
        $this->reemplazarUnico(
            'tiendanube_puntoventa_deposito',
            'tiendanube_pv_dep_store_pv_unique',
            ['puntoventa_id'],
            'tiendanube_puntoventa_deposito_puntoventa_id_unique'
        );

        foreach ([
            'tiendanube_configuracion',
            'tiendanube_gateway_cuentacaja',
            'tiendanube_puntoventa_deposito',
        ] as $tabla) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'store_id')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dropColumn('store_id');
                });
            }
        }
    }

    /**
     * @param  list<string>  $columnasNuevas
     */
    private function reemplazarUnico(
        string $tabla,
        string $indiceViejo,
        array $columnasNuevas,
        string $indiceNuevo,
        ?string $columnaIndiceFk = null,
        ?string $indiceFk = null,
    ): void {
        if (! Schema::hasTable($tabla)) {
            return;
        }

        if ($columnaIndiceFk && $indiceFk
            && MigrationDialectSupport::tieneIndice($tabla, $indiceViejo)
            && ! MigrationDialectSupport::tieneIndice($tabla, $indiceFk)) {
            Schema::table($tabla, function (Blueprint $table) use ($columnaIndiceFk, $indiceFk) {
                $table->index($columnaIndiceFk, $indiceFk);
            });
        }

        // Crear el índice nuevo antes de dropear el viejo (FK que apoyaba en el unique viejo).
        if (! MigrationDialectSupport::tieneIndice($tabla, $indiceNuevo)) {
            Schema::table($tabla, function (Blueprint $table) use ($columnasNuevas, $indiceNuevo) {
                $table->unique($columnasNuevas, $indiceNuevo);
            });
        }

        MigrationDialectSupport::dropIndiceOUnique($tabla, $indiceViejo);
    }
};
