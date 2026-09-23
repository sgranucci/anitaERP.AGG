<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\InterformingFacturacionMaestrosSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * INTERFORMING: maestros de facturación exportación alineados a Anita
 * (b-fremito.c carga_pant4 / help_8; sucursal 4 fiscal=X; tipos FAE/FAC/NCE/NDE).
 *
 * Gate obligatorio: no-op fuera de EMPRESA=INTERFORMING.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        $now = now();

        $this->actualizarPuntosVenta($now);
        $this->seedTipotransacciones($now);
        $this->seedIncoterms($now);
        $this->seedFormaspago($now);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        // No borra PV ni tipos ya usados en ventas; solo deja de ser “seed”.
    }

    private function actualizarPuntosVenta($now): void
    {
        // Anita suc 4: suc_fiscal=X → modo E + wsfex_v1
        DB::table('puntoventa')
            ->where('codigo', InterformingFacturacionMaestrosSupport::PV_EXPORTACION_CODIGO)
            ->update([
                'modofacturacion' => 'E',
                'webservice' => 'wsfex_v1',
                'pathafip' => '/usr2/interforming/afip_exp_v1',
                'updated_at' => $now,
            ]);

        // Anita suc 5: suc_fiscal=E → modo C (CAE local) + wsfev1
        DB::table('puntoventa')
            ->where('codigo', InterformingFacturacionMaestrosSupport::PV_LOCAL_ELECTRONICA_CODIGO)
            ->update([
                'modofacturacion' => 'C',
                'webservice' => 'wsfev1',
                'pathafip' => '/usr2/interforming/afip_local',
                'updated_at' => $now,
            ]);
    }

    private function seedTipotransacciones($now): void
    {
        // codigo = tipo AFIP final (019/021/020) o legacy 001 para FAC local.
        $tipos = [
            [
                'nombre' => 'Factura',
                'abreviatura' => 'FAC',
                'codigo' => '001',
                'signo' => '1',
            ],
            [
                'nombre' => 'Factura de exportación',
                'abreviatura' => 'FAE',
                'codigo' => '019',
                'signo' => '1',
            ],
            [
                'nombre' => 'Nota de crédito exportación',
                'abreviatura' => 'NCE',
                'codigo' => '021',
                'signo' => '-1',
            ],
            [
                'nombre' => 'Nota de débito exportación',
                'abreviatura' => 'NDE',
                'codigo' => '020',
                'signo' => '1',
            ],
        ];

        foreach ($tipos as $tipo) {
            $existe = DB::table('tipotransaccion')
                ->where('abreviatura', $tipo['abreviatura'])
                ->where('operacion', 'V')
                ->exists();
            if ($existe) {
                DB::table('tipotransaccion')
                    ->where('abreviatura', $tipo['abreviatura'])
                    ->where('operacion', 'V')
                    ->update([
                        'nombre' => $tipo['nombre'],
                        'codigo' => $tipo['codigo'],
                        'signo' => $tipo['signo'],
                        'estado' => 'A',
                        'iva_ventas' => 1,
                        'updated_at' => $now,
                    ]);
                continue;
            }

            DB::table('tipotransaccion')->insert([
                'nombre' => $tipo['nombre'],
                'operacion' => 'V',
                'operacionstock' => 'O',
                'abreviatura' => $tipo['abreviatura'],
                'codigo' => $tipo['codigo'],
                'signo' => $tipo['signo'],
                'estado' => 'A',
                'iva_ventas' => 1,
                'concepto_venta_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedIncoterms($now): void
    {
        foreach (InterformingFacturacionMaestrosSupport::INCOTERMS as $row) {
            $existe = DB::table('incoterm')->where('abreviatura', $row['abreviatura'])->exists();
            if ($existe) {
                DB::table('incoterm')
                    ->where('abreviatura', $row['abreviatura'])
                    ->update([
                        'nombre' => $row['nombre'],
                        'updated_at' => $now,
                    ]);
                continue;
            }

            DB::table('incoterm')->insert([
                'nombre' => $row['nombre'],
                'abreviatura' => $row['abreviatura'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedFormaspago($now): void
    {
        // Anita carga_pant4 no pide forma de pago; WSFEX/ERP modal sí la usan.
        foreach (InterformingFacturacionMaestrosSupport::FORMASPAGO as $row) {
            $existe = DB::table('formapago')->where('abreviatura', $row['abreviatura'])->exists();
            if ($existe) {
                DB::table('formapago')
                    ->where('abreviatura', $row['abreviatura'])
                    ->update([
                        'nombre' => $row['nombre'],
                        'updated_at' => $now,
                    ]);
                continue;
            }

            DB::table('formapago')->insert([
                'nombre' => $row['nombre'],
                'abreviatura' => $row['abreviatura'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
