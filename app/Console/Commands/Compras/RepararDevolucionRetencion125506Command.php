<?php

declare(strict_types=1);

namespace App\Console\Commands\Compras;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Contable\Asiento;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaRetencionEscrituraSupport;
use App\Support\Compras\PagoproveedorDevolucionRetencionSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot: vincula el OPP 125506 (I/E 15/10 BIYEMAS) como DEV RET de la OP 125457.
 */
final class RepararDevolucionRetencion125506Command extends Command
{
    protected $signature = 'compras:reparar-devolucion-retencion-125506
                            {--dry-run : Solo muestra lo que haría}
                            {--skip-anita : No escribe AOP en Anita}';

    protected $description = 'Repara la devolución de retenciones OPP 125506 (DIAZ / OP 125457) como DEV RET vinculada';

    private const EMPRESA_ID = 1;

    private const ORIGEN_ID = 28269;

    private const CAJA_ID = 140580;

    private const ASIENTO_ID = 171542;

    private const NRO_DEV = '125506';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $skipAnita = (bool) $this->option('skip-anita');

        $origen = Pagoproveedor::query()->with('pagoproveedor_retenciones')->find(self::ORIGEN_ID);
        $caja = Caja_Movimiento::query()->find(self::CAJA_ID);
        $asiento = Asiento::query()->find(self::ASIENTO_ID);

        if ($origen === null || $caja === null || $asiento === null) {
            $this->error('Faltan OP 28269, caja 140580 o asiento 171542.');

            return self::FAILURE;
        }

        $ya = Pagoproveedor::query()
            ->where('empresa_id', self::EMPRESA_ID)
            ->where('numerotransaccion', self::NRO_DEV)
            ->where('pagoproveedor_origen_id', self::ORIGEN_ID)
            ->where('detalle', 'like', PagoproveedorDevolucionRetencionSupport::PREFIJO_DETALLE.'%')
            ->first();

        if ($ya !== null) {
            $this->info('Ya existe OP hija DEV RET id='.$ya->id.' nro='.$ya->numerotransaccion.'. Nada que hacer.');

            return self::SUCCESS;
        }

        $retG = $origen->pagoproveedor_retenciones
            ->first(fn ($r) => (string) $r->tiporetencion === Pagoproveedor_Retencion::TIPO_GANANCIAS
                && abs((float) $r->importe - 16640) < 0.02);
        $retB = $origen->pagoproveedor_retenciones
            ->first(fn ($r) => (string) $r->tiporetencion === Pagoproveedor_Retencion::TIPO_IIBB);

        if ($retG === null) {
            $this->error('No se encontró retención G 16640 en OP origen.');

            return self::FAILURE;
        }

        $importeG = 16640.0;
        $importeB = 1920.0;
        $total = $importeG + $importeB;
        $leyenda = PagoproveedorDevolucionRetencionSupport::leyenda(
            $origen,
            'Orden de pago BANCO MACRO S.A.'
        );

        $this->line('Origen: OPP '.$origen->numerotransaccion.' id='.$origen->id);
        $this->line('Caja: '.$caja->id.' / Asiento: '.$asiento->id.' / nro '.self::NRO_DEV);
        $this->line('Leyenda: '.$leyenda);
        $this->line('Retenciones: G -'.$importeG.($retB ? ' + B -'.$importeB : ''));

        if ($dry) {
            $this->warn('Dry-run: no se grabó nada.');

            return self::SUCCESS;
        }

        $clonesAnita = [];
        $hija = DB::transaction(function () use (
            $origen,
            $caja,
            $asiento,
            $retG,
            $retB,
            $importeG,
            $importeB,
            $total,
            $leyenda,
            &$clonesAnita,
        ) {
            $hija = Pagoproveedor::query()->create([
                'empresa_id' => self::EMPRESA_ID,
                'tipotransaccion_caja_id' => (int) ($caja->tipotransaccion_caja_id ?: 10),
                'tipocomprobante' => 'OPP',
                'letra' => (string) ($origen->letra ?? ' '),
                'sucursal' => (int) ($origen->sucursal ?: 1),
                'numerotransaccion' => self::NRO_DEV,
                'fecha' => '2026-10-15',
                'caja_id' => $origen->caja_id,
                'proveedor_id' => (int) $origen->proveedor_id,
                'detalle' => $leyenda,
                'estado' => 'CONFIRMADA',
                'monto' => $total,
                'cotizacion' => (float) ($origen->cotizacion ?: 1),
                'moneda_id' => (int) ($origen->moneda_id ?: 1),
                'modo_cotizacion' => (string) ($origen->modo_cotizacion ?? 'factura'),
                'usuario_id' => (int) ($caja->usuario_id ?: $origen->usuario_id),
                'pagoproveedor_origen_id' => (int) $origen->id,
                'caja_movimiento_id' => (int) $caja->id,
                'asiento_id' => (int) $asiento->id,
            ]);

            Pagoproveedor_Estado::query()->create([
                'pagoproveedor_id' => (int) $hija->id,
                'fecha' => now(),
                'estado' => 'CONFIRMADA',
                'usuario_id' => (int) ($hija->usuario_id ?: 0) ?: null,
                'observacion' => 'Reparación DEV RET 125506 → OP 125457',
            ]);

            $detG = is_array($retG->detalle_calculo) ? $retG->detalle_calculo : [];
            $detG['origen_retencion_id'] = (int) $retG->id;
            $detG['devolucion'] = true;
            $detG['reparacion_125506'] = true;

            Pagoproveedor_Retencion::query()->create([
                'pagoproveedor_id' => (int) $hija->id,
                'tiporetencion' => Pagoproveedor_Retencion::TIPO_GANANCIAS,
                'retencionganancia_id' => $retG->retencionganancia_id,
                'codigo_regimen' => $retG->codigo_regimen,
                'codigo_retencion' => $retG->codigo_retencion,
                'base_calculo' => abs((float) $retG->base_calculo),
                'alicuota' => (float) $retG->alicuota,
                'importe' => -abs($importeG),
                'nro_certificado' => $retG->nro_certificado,
                'moneda_id' => (int) ($retG->moneda_id ?: 1),
                'cotizacion' => (float) ($retG->cotizacion ?: 1),
                'detalle_calculo' => $detG,
                'motivo' => 'devolucion_retencion_reparacion',
            ]);

            $cloneG = $retG->replicate();
            $cloneG->importe = abs($importeG);
            $clonesAnita[] = $cloneG;

            if ($retB !== null && $importeB > 0) {
                $detB = is_array($retB->detalle_calculo) ? $retB->detalle_calculo : [];
                $detB['origen_retencion_id'] = (int) $retB->id;
                $detB['devolucion'] = true;
                $detB['reparacion_125506'] = true;

                Pagoproveedor_Retencion::query()->create([
                    'pagoproveedor_id' => (int) $hija->id,
                    'tiporetencion' => Pagoproveedor_Retencion::TIPO_IIBB,
                    'provincia_id' => $retB->provincia_id,
                    'codigo_regimen' => $retB->codigo_regimen,
                    'codigo_retencion' => $retB->codigo_retencion,
                    'base_calculo' => abs((float) $retB->base_calculo),
                    'alicuota' => (float) $retB->alicuota,
                    'importe' => -abs($importeB),
                    'nro_certificado' => $retB->nro_certificado,
                    'moneda_id' => (int) ($retB->moneda_id ?: 1),
                    'cotizacion' => (float) ($retB->cotizacion ?: 1),
                    'detalle_calculo' => $detB,
                    'motivo' => 'devolucion_retencion_reparacion',
                ]);

                $cloneB = $retB->replicate();
                $cloneB->importe = abs($importeB);
                $clonesAnita[] = $cloneB;
            }

            Caja_Movimiento::query()->where('id', (int) $caja->id)->update([
                'pagoproveedor_id' => (int) $hija->id,
                'detalle' => $leyenda,
            ]);
            Asiento::query()->where('id', (int) $asiento->id)->update([
                'pagoproveedor_id' => (int) $hija->id,
                'observacion' => $leyenda,
            ]);

            return $hija->fresh();
        });

        $this->info('OP hija creada id='.$hija->id.' nro='.$hija->numerotransaccion);

        if (! $skipAnita) {
            try {
                PagoproveedorAnitaRetencionEscrituraSupport::grabarReversoDesdeRetenciones(
                    $origen,
                    $hija,
                    $clonesAnita,
                );
                $this->info('Anita AOP parcial grabado.');
            } catch (\Throwable $e) {
                $this->warn('ERP ok; Anita falló: '.$e->getMessage());
            }
        } else {
            $this->warn('Anita omitido (--skip-anita).');
        }

        return self::SUCCESS;
    }
}
