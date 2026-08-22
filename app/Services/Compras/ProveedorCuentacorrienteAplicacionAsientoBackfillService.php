<?php

namespace App\Services\Compras;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Contable\Cuentacontable;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionFilaSupport;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionLiquidacionSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Aplicaciones CC ya grabadas sin asiento que hoy sí lo requerirían.
 *
 * Caso típico: anticipos aplicados antes de configurar `pago.anticipo_proveedor`.
 * Al activar la cuenta de anticipos, la reclasificación (Debe proveedores /
 * Haber anticipos) quedó pendiente en los pares viejos.
 */
class ProveedorCuentacorrienteAplicacionAsientoBackfillService
{
    public function __construct(
        private ProveedorCuentacorrienteAplicacionAsientoService $asientoService,
    ) {}

    /**
     * Un par que no se puede previsualizar (falta cuenta de DC, cotización inconsistente)
     * se devuelve con `error` en vez de cortar el análisis del resto.
     *
     * @param  list<int>  $aplicacionIds  vacío = todas las candidatas
     * @return list<array{
     *   aplicacion_deuda_id:int,
     *   aplicacion_credito_id:int,
     *   deuda:Proveedor_Cuentacorriente,
     *   credito:Proveedor_Cuentacorriente,
     *   fecha:string,
     *   liquidacion:array<string, mixed>|null,
     *   preview:array<string, mixed>|null,
     *   lineas:list<array{codigo:string, nombre:string, debe:float, haber:float}>,
     *   error:string|null
     * }>
     */
    public function analizar(
        array $aplicacionIds = [],
        ?string $desde = null,
        ?string $hasta = null,
        bool $incluirSoloDc = false,
    ): array {
        $candidatos = [];

        foreach ($this->paresSinAsiento($aplicacionIds, $desde, $hasta) as $par) {
            /** @var Proveedor_Cuentacorriente_Aplicacion $aplDeuda */
            $aplDeuda = $par['aplicacion'];
            $deuda = $par['deuda'];
            $credito = $par['credito'];
            $fecha = substr((string) $aplDeuda->getRawOriginal('fecha'), 0, 10);

            // Sin --incluir-dc solo se reconstruye lo que introdujo la cuenta de anticipos:
            // las diferencias de cambio históricas ya están contabilizadas en Anita.
            if (! $incluirSoloDc && ! $this->reclasifica($deuda, $credito)) {
                continue;
            }

            $liq = null;
            $preview = null;
            $error = null;

            try {
                $liq = ProveedorCuentacorrienteAplicacionLiquidacionSupport::liquidar(
                    ['moneda_id' => (int) $deuda->moneda_id, 'cotizacion' => $deuda->cotizacion],
                    ['moneda_id' => (int) $credito->moneda_id, 'cotizacion' => $credito->cotizacion],
                    abs((float) $aplDeuda->total),
                    $aplDeuda->cotizacion_liquidacion
                );
                $preview = $this->asientoService->previsualizar($deuda, $credito, $liq['dc'], $fecha, $liq);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }

            if ($error === null && $preview === null) {
                continue;
            }

            $candidatos[] = [
                'aplicacion_deuda_id' => (int) $aplDeuda->id,
                'aplicacion_credito_id' => (int) $par['aplicacion_credito_id'],
                'deuda' => $deuda,
                'credito' => $credito,
                'fecha' => $fecha,
                'liquidacion' => $liq,
                'preview' => $preview,
                'lineas' => $preview !== null ? $this->lineasLegibles($preview['payload']) : [],
                'error' => $error,
            ];
        }

        return $candidatos;
    }

    /**
     * Graba el asiento faltante y lo estampa en las dos filas de la aplicación.
     *
     * @param  array<string, mixed>  $candidato  fila de analizar()
     */
    public function generar(array $candidato): int
    {
        if (($candidato['error'] ?? null) !== null) {
            throw new RuntimeException((string) $candidato['error']);
        }

        return DB::transaction(function () use ($candidato) {
            $ids = array_values(array_filter([
                (int) $candidato['aplicacion_deuda_id'],
                (int) $candidato['aplicacion_credito_id'],
            ]));

            /** @var \Illuminate\Support\Collection<int, Proveedor_Cuentacorriente_Aplicacion> $filas */
            $filas = Proveedor_Cuentacorriente_Aplicacion::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            foreach ($filas as $fila) {
                if ((int) ($fila->asiento_id ?? 0) > 0) {
                    throw new RuntimeException(
                        'La aplicación '.$fila->id.' ya tiene el asiento '.$fila->asiento_id.'.'
                    );
                }
            }

            $asientoId = $this->asientoService->generarSiCorresponde(
                $candidato['deuda'],
                $candidato['credito'],
                (float) $candidato['liquidacion']['dc'],
                (string) $candidato['fecha'],
                $candidato['liquidacion']
            );

            if ($asientoId === null || $asientoId <= 0) {
                throw new RuntimeException('No se generó asiento para la aplicación '.$candidato['aplicacion_deuda_id'].'.');
            }

            foreach ($filas as $fila) {
                $fila->asiento_id = $asientoId;
                $fila->save();
            }

            return $asientoId;
        });
    }

    private function reclasifica(Proveedor_Cuentacorriente $deuda, Proveedor_Cuentacorriente $credito): bool
    {
        try {
            return $this->asientoService->cuentasDeLados($deuda, $credito)['reclasifica'];
        } catch (Throwable) {
            // Sin cuenta de proveedores resoluble no hay reclasificación que reconstruir.
            return false;
        }
    }

    public static function etiqueta(Proveedor_Cuentacorriente $cc, string $ladoDefault): string
    {
        return ProveedorCuentacorrienteAplicacionFilaSupport::etiqueta(
            $cc,
            ProveedorCuentacorrienteAplicacionFilaSupport::tipo($cc, $ladoDefault)
        );
    }

    /**
     * Filas de aplicación del lado deuda sin asiento, con su par del lado crédito.
     *
     * @param  list<int>  $aplicacionIds
     * @return list<array{
     *   aplicacion:Proveedor_Cuentacorriente_Aplicacion,
     *   aplicacion_credito_id:int,
     *   deuda:Proveedor_Cuentacorriente,
     *   credito:Proveedor_Cuentacorriente
     * }>
     */
    private function paresSinAsiento(array $aplicacionIds, ?string $desde, ?string $hasta): array
    {
        $query = Proveedor_Cuentacorriente_Aplicacion::query()
            ->whereNull('asiento_id')
            ->whereNull('pagoproveedor_id')
            ->whereNotNull('proveedor_cuentacorriente_aplicado_id')
            ->where('total', '<', 0)
            ->orderBy('id');

        if ($aplicacionIds !== []) {
            $query->whereIn('id', $aplicacionIds);
        }
        if ($desde !== null && $desde !== '') {
            $query->whereDate('fecha', '>=', $desde);
        }
        if ($hasta !== null && $hasta !== '') {
            $query->whereDate('fecha', '<=', $hasta);
        }

        /** @var \Illuminate\Support\Collection<int, Proveedor_Cuentacorriente_Aplicacion> $filas */
        $filas = $query->get();
        if ($filas->isEmpty()) {
            return [];
        }

        $ccIds = $filas
            ->flatMap(fn ($f) => [
                (int) $f->proveedor_cuentacorriente_id,
                (int) $f->proveedor_cuentacorriente_aplicado_id,
            ])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $ccs = Proveedor_Cuentacorriente::query()
            ->with([
                'proveedores',
                'monedas',
                'comprobante_proveedores.ordencompras.ordencompra_articulos',
                'comprobante_proveedores.proveedores',
                'comprobante_proveedores.tipotransaccion_compras',
                'pagoproveedores',
            ])
            ->whereIn('id', $ccIds)
            ->get()
            ->keyBy('id');

        $pares = [];
        foreach ($filas as $fila) {
            $deuda = $ccs->get((int) $fila->proveedor_cuentacorriente_id);
            $credito = $ccs->get((int) $fila->proveedor_cuentacorriente_aplicado_id);
            if ($deuda === null || $credito === null) {
                continue;
            }
            // total < 0 sobre una fila cuyo CC es deudor: la contraparte es el crédito.
            if ((float) $deuda->total <= 0 || (float) $credito->total >= 0) {
                continue;
            }

            $pares[] = [
                'aplicacion' => $fila,
                'aplicacion_credito_id' => (int) (Proveedor_Cuentacorriente_Aplicacion::query()
                    ->whereNull('pagoproveedor_id')
                    ->where('proveedor_cuentacorriente_id', $credito->id)
                    ->where('proveedor_cuentacorriente_aplicado_id', $deuda->id)
                    ->where('id', '!=', $fila->id)
                    ->orderByDesc('id')
                    ->value('id') ?? 0),
                'deuda' => $deuda,
                'credito' => $credito,
            ];
        }

        return $pares;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{codigo:string, nombre:string, debe:float, haber:float}>
     */
    private function lineasLegibles(array $payload): array
    {
        $cuentas = Cuentacontable::query()
            ->whereIn('id', array_filter((array) ($payload['cuentacontable_ids'] ?? [])))
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('id');

        $lineas = [];
        foreach ((array) ($payload['cuentacontable_ids'] ?? []) as $i => $cuentaId) {
            $cuenta = $cuentas->get((int) $cuentaId);
            $lineas[] = [
                'codigo' => (string) ($cuenta->codigo ?? $cuentaId),
                'nombre' => (string) ($cuenta->nombre ?? ''),
                'debe' => (float) ($payload['debes'][$i] ?? 0),
                'haber' => (float) ($payload['haberes'][$i] ?? 0),
            ];
        }

        return $lineas;
    }
}
