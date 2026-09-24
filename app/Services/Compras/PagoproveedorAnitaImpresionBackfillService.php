<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportClaveSupport;
use App\Support\Compras\AnitaImport\PagoproveedorAnitaImpresionAuxpagMapper;
use App\Support\Compras\AnitaImport\PagoproveedorAnitaImpresionBridgeReader;
use App\Support\Compras\AnitaImport\PagoproveedorAnitaImpresionElegibleSupport;
use App\Support\Compras\AnitaImport\PagoproveedorAnitaImpresionRetencionMapper;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\DB;

/**
 * Completa impresión de OP importadas desde Anita (auxpag + ret*mov).
 * No toca OP emitidas en ERP ni escribe en Anita.
 */
class PagoproveedorAnitaImpresionBackfillService
{
    public function __construct(
        private readonly PagoproveedorAnitaImpresionBridgeReader $reader = new PagoproveedorAnitaImpresionBridgeReader,
    ) {}

    /**
     * @return array{
     *   candidatas: int,
     *   elegibles: int,
     *   omitidas_erp: int,
     *   actualizadas: int,
     *   sin_auxpag: int,
     *   retenciones_creadas: int,
     *   retenciones_omitidas: int,
     *   errores: list<string>,
     *   errores_bridge: list<string>
     * }
     */
    public function backfill(string $desdeIso, string $hastaIso, bool $dryRun = true): array
    {
        $stats = [
            'candidatas' => 0,
            'elegibles' => 0,
            'omitidas_erp' => 0,
            'actualizadas' => 0,
            'sin_auxpag' => 0,
            'retenciones_creadas' => 0,
            'retenciones_omitidas' => 0,
            'errores' => [],
            'errores_bridge' => [],
        ];

        $desdeYmd = ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso($desdeIso);
        $hastaYmd = ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso($hastaIso);
        if ($desdeYmd <= 0 || $hastaYmd <= 0) {
            $stats['errores'][] = 'Rango de fechas inválido';

            return $stats;
        }

        $pagos = Pagoproveedor::query()
            ->whereBetween('fecha', [$desdeIso, $hastaIso])
            ->whereIn('tipocomprobante', ['OPP', 'OPA'])
            ->with(['pagoproveedor_estados', 'pagoproveedor_retenciones', 'caja_movimientos', 'cheques'])
            ->orderBy('id')
            ->get();

        $stats['candidatas'] = $pagos->count();

        /** @var list<Pagoproveedor> $elegibles */
        $elegibles = [];
        foreach ($pagos as $pago) {
            if (! PagoproveedorAnitaImpresionElegibleSupport::esElegible($pago)) {
                $stats['omitidas_erp']++;

                continue;
            }
            $elegibles[] = $pago;
        }
        $stats['elegibles'] = count($elegibles);
        if ($elegibles === []) {
            return $stats;
        }

        $empresasAnita = [];
        foreach ($elegibles as $pago) {
            $cod = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $pago->empresa_id);
            if ($cod <= 0) {
                $cod = (int) $pago->empresa_id;
            }
            if ($cod > 0) {
                $empresasAnita[$cod] = $cod;
            }
        }

        $auxpags = $this->reader->listarAuxpag(
            array_values($empresasAnita),
            $desdeYmd,
            $hastaYmd,
            $stats['errores_bridge']
        );

        $cuentas = [];
        $auxPorClave = [];
        foreach ($auxpags as $axp) {
            $clave = PagoproveedorAnitaImpresionAuxpagMapper::claveOp(
                (int) ($axp->axp_empresa ?? 0),
                (string) ($axp->axp_tipo ?? ''),
                (int) ($axp->axp_rec ?? 0)
            );
            $auxPorClave[$clave][] = $axp;
            $c = strtoupper(trim((string) ($axp->axp_banco ?? '')));
            if ($c !== '') {
                $cuentas[$c] = true;
            }
        }

        $tesmae = $this->reader->mapaTesmae(array_keys($cuentas), $stats['errores_bridge']);
        $retsPorClave = $this->reader->indexarRetencionesPeriodo(
            array_values($empresasAnita),
            $desdeYmd,
            $hastaYmd,
            $stats['errores_bridge']
        );

        foreach ($elegibles as $pago) {
            try {
                $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $pago->empresa_id);
                if ($empresaAnita <= 0) {
                    $empresaAnita = (int) $pago->empresa_id;
                }
                $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo((string) $pago->tipocomprobante);
                $nro = (int) $pago->numerotransaccion;
                $claveAux = PagoproveedorAnitaImpresionAuxpagMapper::claveOp($empresaAnita, $tipo, $nro);
                $lineas = $auxPorClave[$claveAux] ?? [];

                $letra = ComprobanteProveedorAnitaImportClaveSupport::letra((string) ($pago->letra ?? ' '));
                $suc = (int) $pago->sucursal;
                $claveRet = $this->reader->claveRet($empresaAnita, $tipo, $letra, $suc, $nro);
                // Anita MultiEmpresa a veces graba letra espacio; reintentar con espacio/A.
                $bloques = $retsPorClave[$claveRet]
                    ?? $retsPorClave[$this->reader->claveRet($empresaAnita, $tipo, ' ', $suc, $nro)]
                    ?? $retsPorClave[$this->reader->claveRet($empresaAnita, $tipo, 'A', $suc, $nro)]
                    ?? ['ganancias' => [], 'iva' => [], 'suss' => [], 'ibr' => []];

                if ($lineas === [] && self::bloquesVacios($bloques)) {
                    $stats['sin_auxpag']++;
                }

                $snapshotPartes = PagoproveedorAnitaImpresionAuxpagMapper::aSnapshot($lineas, $tesmae);
                $snapshot = [
                    'version' => 1,
                    'sincronizado_at' => now()->toIso8601String(),
                    'origen' => PagoproveedorAnitaImpresionElegibleSupport::ORIGEN_RETENCION,
                    'aplicaciones' => $snapshotPartes['aplicaciones'],
                    'medios_caja' => $snapshotPartes['medios_caja'],
                    'cheques' => $snapshotPartes['cheques'],
                ];

                $filasRet = PagoproveedorAnitaImpresionRetencionMapper::aFilasPersistencia(
                    $bloques,
                    (int) ($pago->moneda_id ?: 1),
                    (float) ($pago->cotizacion ?: 1)
                );

                $puedeReemplazarRet = $this->puedeReemplazarRetenciones($pago);
                if (! $puedeReemplazarRet && $filasRet !== []) {
                    $stats['retenciones_omitidas']++;
                }

                if ($dryRun) {
                    $stats['actualizadas']++;
                    if ($puedeReemplazarRet) {
                        $stats['retenciones_creadas'] += count($filasRet);
                    }

                    continue;
                }

                DB::transaction(function () use ($pago, $snapshot, $filasRet, $puedeReemplazarRet, &$stats) {
                    $pago->anita_impresion_json = $snapshot;
                    $pago->save();

                    if ($puedeReemplazarRet) {
                        $this->reemplazarRetencionesImport($pago, $filasRet);
                        $stats['retenciones_creadas'] += count($filasRet);
                    }
                });

                $stats['actualizadas']++;
            } catch (\Throwable $e) {
                $stats['errores'][] = sprintf(
                    '%s %s-%s: %s',
                    (string) $pago->tipocomprobante,
                    (string) $pago->sucursal,
                    (string) $pago->numerotransaccion,
                    $e->getMessage()
                );
            }
        }

        return $stats;
    }

    /**
     * @param  array{ganancias?:list<object>,iva?:list<object>,suss?:list<object>,ibr?:list<object>}  $bloques
     */
    private static function bloquesVacios(array $bloques): bool
    {
        return ($bloques['ganancias'] ?? []) === []
            && ($bloques['iva'] ?? []) === []
            && ($bloques['suss'] ?? []) === []
            && ($bloques['ibr'] ?? []) === [];
    }

    private function puedeReemplazarRetenciones(Pagoproveedor $pago): bool
    {
        $pago->loadMissing('pagoproveedor_retenciones');
        if ($pago->pagoproveedor_retenciones->isEmpty()) {
            return true;
        }

        foreach ($pago->pagoproveedor_retenciones as $ret) {
            $detalle = is_array($ret->detalle_calculo) ? $ret->detalle_calculo : null;
            if (! PagoproveedorAnitaImpresionElegibleSupport::esRetencionImportImpresion($detalle, $ret->motivo)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Solo invocar si {@see puedeReemplazarRetenciones()} (sin retenciones ERP nativas).
     *
     * @param  list<array<string, mixed>>  $filas
     */
    private function reemplazarRetencionesImport(Pagoproveedor $pago, array $filas): void
    {
        Pagoproveedor_Retencion::query()
            ->where('pagoproveedor_id', $pago->id)
            ->delete();

        foreach ($filas as $attrs) {
            Pagoproveedor_Retencion::query()->create(array_merge($attrs, [
                'pagoproveedor_id' => $pago->id,
            ]));
        }
    }
}
