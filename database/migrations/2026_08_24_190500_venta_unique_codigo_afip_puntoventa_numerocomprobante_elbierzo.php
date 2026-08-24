<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\MigrationDialectSupport;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use App\Support\Ventas\VentaNumerocomprobanteUnicidadSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL BIERZO: unique fiscal para PV que numera el ERP (manual y CAEA).
 * Clave: tipo ARCA efectivo + sucursal + número.
 * 001 FAC / 002 ND / 003 NC / 201 FCE / 202 NDE / 203 NCE, con offset de letra
 * (FAC A=1, FAC B=6). FAC A y FAG A no pueden repetir sucursal+número; FAC A 10-1 y FAC B 10-1 sí.
 * AGG no corre: conserva unique CAEA gastro (puntoventa + número). PV CAE no se toca (numera ARCA).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo() || ! Schema::hasTable('venta')) {
            return;
        }

        Schema::table('venta', function (Blueprint $table): void {
            if (! Schema::hasColumn('venta', 'codigo_afip')) {
                $table->unsignedSmallInteger('codigo_afip')->nullable()->after('numerocomprobante');
            }
        });

        $this->backfillCodigoAfip();

        MigrationDialectSupport::dropIndiceOUnique(
            'venta',
            VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX_ELBIERZO_TIPO,
        );

        Schema::table('venta', function (Blueprint $table): void {
            if (! MigrationDialectSupport::tieneIndice(
                'venta',
                VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX_ELBIERZO_AFIP
            )) {
                $table->unique(
                    ['codigo_afip', 'puntoventa_id', 'numerocomprobante'],
                    VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX_ELBIERZO_AFIP,
                );
            }
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo() || ! Schema::hasTable('venta')) {
            return;
        }

        MigrationDialectSupport::dropIndiceOUnique(
            'venta',
            VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX_ELBIERZO_AFIP,
        );

        Schema::table('venta', function (Blueprint $table): void {
            if (! MigrationDialectSupport::tieneIndice(
                'venta',
                VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX_ELBIERZO_TIPO
            )) {
                $table->unique(
                    ['puntoventa_id', 'tipotransaccion_id', 'numerocomprobante'],
                    VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX_ELBIERZO_TIPO,
                );
            }
            if (Schema::hasColumn('venta', 'codigo_afip')) {
                $table->dropColumn('codigo_afip');
            }
        });
    }

    private function backfillCodigoAfip(): void
    {
        $filas = DB::table('venta')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'venta.tipotransaccion_id')
            ->select('venta.id', 'venta.codigo', 'tt.codigo as tt_codigo')
            ->orderBy('venta.id')
            ->get();

        foreach ($filas as $fila) {
            $afip = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                (string) ($fila->tt_codigo ?? ''),
                (string) ($fila->codigo ?? ''),
            );
            DB::table('venta')->where('id', (int) $fila->id)->update([
                'codigo_afip' => $afip > 0 ? $afip : null,
            ]);
        }
    }
};
