<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración operativa Tiendanube (Ferli): defaults + pares gateway→cuenta y PV↔depósito.
 * Reemplaza el mapa/códigos que vivían solo en .env / config/tiendanube.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tiendanube_configuracion')) {
            Schema::create('tiendanube_configuracion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('puntoventa_id')->nullable()->comment('PV default al sincronizar/facturar');
                $table->unsignedBigInteger('deposito_id')->nullable()->comment('Depósito default');
                $table->unsignedBigInteger('listaprecio_id')->nullable();
                $table->string('articulo_envio_sku', 40)->nullable();
                $table->string('articulo_descuento_sku', 40)->nullable();
                $table->string('usocuentacaja_nombre', 80)->nullable();
                $table->timestamps();

                $table->foreign('empresa_id')->references('id')->on('empresa')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('tiendanube_gateway_cuentacaja')) {
            Schema::create('tiendanube_gateway_cuentacaja', function (Blueprint $table) {
                $table->id();
                $table->string('gateway_key', 80)->comment('Clave API o alias: gocuotas, pago-nube…');
                $table->unsignedBigInteger('cuentacaja_id');
                $table->unsignedInteger('orden')->default(0);
                $table->timestamps();

                $table->unique('gateway_key');
                $table->foreign('cuentacaja_id')->references('id')->on('cuentacaja')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('tiendanube_puntoventa_deposito')) {
            Schema::create('tiendanube_puntoventa_deposito', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('puntoventa_id');
                $table->unsignedBigInteger('deposito_id');
                $table->boolean('es_default')->default(false);
                $table->unsignedInteger('orden')->default(0);
                $table->timestamps();

                $table->unique('puntoventa_id');
                $table->foreign('puntoventa_id')->references('id')->on('puntoventa')->cascadeOnDelete();
                $table->foreign('deposito_id')->references('id')->on('depmae')->cascadeOnDelete();
            });
        }

        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $this->sembrarFerli();
    }

    public function down(): void
    {
        Schema::dropIfExists('tiendanube_puntoventa_deposito');
        Schema::dropIfExists('tiendanube_gateway_cuentacaja');
        Schema::dropIfExists('tiendanube_configuracion');
    }

    private function sembrarFerli(): void
    {
        $empresaId = (int) (DB::table('empresa')->orderBy('id')->value('id') ?? 1);
        $pvDefault = $this->puntoventaIdPorCodigo('00023');
        $depDefault = $this->depositoIdPorCodigo('10');
        $listaId = (int) (DB::table('listaprecio')->where('codigo', '12')->value('id') ?? 0);

        if (! DB::table('tiendanube_configuracion')->exists()) {
            DB::table('tiendanube_configuracion')->insert([
                'empresa_id' => $empresaId > 0 ? $empresaId : null,
                'puntoventa_id' => $pvDefault > 0 ? $pvDefault : null,
                'deposito_id' => $depDefault > 0 ? $depDefault : null,
                'listaprecio_id' => $listaId > 0 ? $listaId : null,
                'articulo_envio_sku' => 'FL',
                'articulo_descuento_sku' => null,
                'usocuentacaja_nombre' => 'TIENDA NUBE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Excel / Facturante: MEP 608, TN 609, GO 610, TR 4781/5, NBO 11310112, MP 611
        $gateways = [
            ['gocuotas', '610'],
            ['go cuotas', '610'],
            ['go-cuotas', '610'],
            ['pago-nube', '609'],
            ['pago_nube', '609'],
            ['pago nube', '609'],
            ['offline', '4781/5'],
            ['custom', '4781/5'],
            ['transferencia', '4781/5'],
            ['mercadolibre', '608'],
            ['meli', '608'],
            ['mercadopago', '611'],
            ['boa', '11310112'],
            ['nube boa', '11310112'],
        ];
        $orden = 0;
        foreach ($gateways as [$key, $codigo]) {
            $cuentaId = (int) (DB::table('cuentacaja')->where('codigo', $codigo)->value('id') ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }
            $existe = DB::table('tiendanube_gateway_cuentacaja')->where('gateway_key', $key)->exists();
            if ($existe) {
                continue;
            }
            $orden++;
            DB::table('tiendanube_gateway_cuentacaja')->insert([
                'gateway_key' => $key,
                'cuentacaja_id' => $cuentaId,
                'orden' => $orden,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($depDefault <= 0) {
            return;
        }
        $pares = [
            ['00021', false],
            ['00023', true],
            ['00026', false],
            ['00027', false],
        ];
        $ordenPv = 0;
        foreach ($pares as [$codigoPv, $esDefault]) {
            $pvId = $this->puntoventaIdPorCodigo($codigoPv);
            if ($pvId <= 0) {
                continue;
            }
            if (DB::table('tiendanube_puntoventa_deposito')->where('puntoventa_id', $pvId)->exists()) {
                continue;
            }
            $ordenPv++;
            DB::table('tiendanube_puntoventa_deposito')->insert([
                'puntoventa_id' => $pvId,
                'deposito_id' => $depDefault,
                'es_default' => $esDefault,
                'orden' => $ordenPv,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function puntoventaIdPorCodigo(string $codigo): int
    {
        return (int) (DB::table('puntoventa')->where('codigo', $codigo)->value('id') ?? 0);
    }

    private function depositoIdPorCodigo(string $codigo): int
    {
        return (int) (DB::table('depmae')->where('codigo', $codigo)->value('id') ?? 0);
    }
};
