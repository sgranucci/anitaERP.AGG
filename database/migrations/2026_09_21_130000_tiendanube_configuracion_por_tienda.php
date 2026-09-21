<?php

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
            DB::table($tabla)->whereNull('store_id')->orWhere('store_id', '')->update([
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
            && ! $this->tieneIndice('tiendanube_configuracion', 'tiendanube_configuracion_store_id_unique')) {
            Schema::table('tiendanube_configuracion', function (Blueprint $table) {
                $table->unique('store_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tiendanube_configuracion')
            && $this->tieneIndice('tiendanube_configuracion', 'tiendanube_configuracion_store_id_unique')) {
            Schema::table('tiendanube_configuracion', function (Blueprint $table) {
                $table->dropUnique('tiendanube_configuracion_store_id_unique');
            });
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
            && $this->tieneIndice($tabla, $indiceViejo)
            && ! $this->tieneIndice($tabla, $indiceFk)) {
            Schema::table($tabla, function (Blueprint $table) use ($columnaIndiceFk, $indiceFk) {
                $table->index($columnaIndiceFk, $indiceFk);
            });
        }

        Schema::table($tabla, function (Blueprint $table) use ($tabla, $indiceViejo, $columnasNuevas, $indiceNuevo) {
            if ($this->tieneIndice($tabla, $indiceViejo)) {
                $table->dropUnique($indiceViejo);
            }
            if (! $this->tieneIndice($tabla, $indiceNuevo)) {
                $table->unique($columnasNuevas, $indiceNuevo);
            }
        });
    }

    private function tieneIndice(string $tabla, string $nombre): bool
    {
        $filas = DB::select('SHOW INDEX FROM `'.$tabla.'` WHERE Key_name = ?', [$nombre]);

        return $filas !== [];
    }
};
