<?php

namespace App\Support\Caja;

use App\Models\Caja\Cuentacaja;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Contable\Cuentacontable;
use App\Support\Compras\ComprobanteProveedorConceptoIvaTipos;
use App\Support\Compras\ComprobanteProveedorTipoTesoreria;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\ConceptoIvacompraConsultaSupport;
use App\Support\Compras\ConceptoIvacompraFormulaSupport;
use App\Support\Numerico\NumeroDecimalLocalSupport;

/**
 * Planilla de liquidación de tarjeta (el DTC de Anita).
 *
 * El bruto de cupones se acredita en la cuenta contable de la tarjeta.
 * Los gastos van al comprobante DTC. La cuenta de caja del movimiento
 * queda por el neto depositado: bruto − gastos.
 */
final class IngresoEgresoLiquidacionTarjetaSupport
{
    public const TOLERANCIA = 0.05;

    /** @param  array<string, mixed>  $payload */
    public static function es(array $payload): bool
    {
        if ((string) ($payload['tipo_tesoreria'] ?? '') === ComprobanteProveedorTipoTesoreria::LIQUIDACION_TARJETA) {
            return true;
        }

        return self::parsearLeyenda((string) ($payload['leyenda'] ?? ''))['cuentacaja_tarjeta_id'] > 0
            && (float) (self::parsearLeyenda((string) ($payload['leyenda'] ?? ''))['bruto_cupones']) > 0;
    }

    /** @param  list<array<string, mixed>>  $comprobantes */
    public static function hay(array $comprobantes): bool
    {
        foreach ($comprobantes as $comprobante) {
            if (is_array($comprobante) && self::es($comprobante)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gastos del DTC para el PDF de la cobranza de tarjeta.
     *
     * @param  iterable<mixed>  $comprobantes
     * @return array{
     *     es: bool,
     *     bruto: float,
     *     gastos: float,
     *     neto: float,
     *     tarjetas: list<string>,
     *     comprobantes: list<array{
     *         numero: string,
     *         emisor: string,
     *         cuit: string,
     *         tarjeta: string,
     *         bruto: float,
     *         gastos: float,
     *         conceptos: list<array{etiqueta: string, monto: float}>
     *     }>
     * }
     */
    public static function resumenImpresion(iterable $comprobantes): array
    {
        $items = [];
        $idsTarjeta = [];
        foreach ($comprobantes as $cp) {
            if (! $cp instanceof Comprobante_Proveedor) {
                continue;
            }
            $marca = self::parsearLeyenda((string) $cp->leyenda);
            $es = (string) $cp->tipo_tesoreria === ComprobanteProveedorTipoTesoreria::LIQUIDACION_TARJETA
                || $marca['cuentacaja_tarjeta_id'] > 0;
            if (! $es) {
                continue;
            }
            if ($marca['cuentacaja_tarjeta_id'] > 0) {
                $idsTarjeta[] = $marca['cuentacaja_tarjeta_id'];
            }
            $items[] = ['cp' => $cp, 'marca' => $marca];
        }

        $nombresTarjeta = [];
        if ($idsTarjeta !== []) {
            foreach (Cuentacaja::query()->whereIn('id', array_values(array_unique($idsTarjeta)))->get(['id', 'codigo', 'nombre']) as $cuenta) {
                $nombresTarjeta[(int) $cuenta->id] = trim((string) $cuenta->codigo.' '.(string) $cuenta->nombre);
            }
        }

        $salida = [];
        $bruto = 0.0;
        $gastos = 0.0;
        $tarjetas = [];
        foreach ($items as $item) {
            /** @var Comprobante_Proveedor $cp */
            $cp = $item['cp'];
            $marca = $item['marca'];
            $conceptos = [];
            $totalConceptos = 0.0;
            foreach ($cp->comprobante_proveedor_conceptos as $linea) {
                $monto = round((float) $linea->monto, 2);
                if (abs($monto) < 0.0001) {
                    continue;
                }
                $codigo = trim((string) ($linea->concepto_ivacompras->codigo ?? ''));
                $nombre = trim((string) ($linea->concepto_ivacompras->nombre ?? ''));
                $etiqueta = trim($codigo.' '.$nombre);
                $conceptos[] = [
                    'etiqueta' => $etiqueta !== '' ? $etiqueta : 'Concepto',
                    'monto' => $monto,
                ];
                $totalConceptos += $monto;
            }
            $totalConceptos = round($totalConceptos, 2);
            if ($totalConceptos == 0.0) {
                $totalConceptos = round((float) $cp->total, 2);
            }
            $brutoLinea = round((float) $marca['bruto_cupones'], 2);
            $bruto += $brutoLinea;
            $gastos += $totalConceptos;
            $tarjeta = $nombresTarjeta[$marca['cuentacaja_tarjeta_id']] ?? '';
            if ($tarjeta !== '' && ! in_array($tarjeta, $tarjetas, true)) {
                $tarjetas[] = $tarjeta;
            }
            $abrev = strtoupper(trim((string) ($cp->tipotransaccion_compras->abreviatura ?? 'DTC')));
            if ($abrev === '') {
                $abrev = 'DTC';
            }
            $emisor = trim((string) ($cp->proveedor_nombre_eventual ?: ($cp->proveedores->nombre ?? '')));
            $salida[] = [
                'numero' => $abrev.' '.strtoupper((string) $cp->letra).' '
                    .str_pad((string) (int) $cp->sucursal, 4, '0', STR_PAD_LEFT).'-'
                    .str_pad((string) (int) $cp->numerocomprobante, 8, '0', STR_PAD_LEFT),
                'emisor' => $emisor,
                'cuit' => ComprobanteProveedorUnicidadSupport::formatearCuit((string) ($cp->proveedor_documento_eventual ?? '')),
                'tarjeta' => $tarjeta,
                'bruto' => $brutoLinea,
                'gastos' => $totalConceptos,
                'conceptos' => $conceptos,
            ];
        }

        return [
            'es' => $salida !== [],
            'bruto' => round($bruto, 2),
            'gastos' => round($gastos, 2),
            'neto' => round($bruto - $gastos, 2),
            'tarjetas' => $tarjetas,
            'comprobantes' => $salida,
        ];
    }

    public static function cierra(float $bruto, float $gastos, float $cobrado, float $tolerancia = self::TOLERANCIA): bool
    {
        return abs(round($bruto - $gastos - $cobrado, 2)) <= $tolerancia;
    }

    public static function leyenda(int $cuentacajaId, float $bruto, string $texto = ''): string
    {
        $base = 'LT:'.$cuentacajaId.':'.number_format($bruto, 2, '.', '');
        $texto = trim($texto);
        if (preg_match('/^LT:\d+:\d+(?:\.\d+)?\s*/', $texto) === 1) {
            $texto = trim((string) preg_replace('/^LT:\d+:\d+(?:\.\d+)?\s*/', '', $texto));
        }
        $leyenda = $texto === '' ? $base : $base.' '.$texto;

        return substr($leyenda, 0, 255);
    }

    /**
     * @return array{cuentacaja_tarjeta_id: int, bruto_cupones: float}
     */
    public static function parsearLeyenda(?string $leyenda): array
    {
        if (preg_match('/^LT:(\d+):(\d+(?:\.\d+)?)/', (string) $leyenda, $coincidencias) !== 1) {
            return ['cuentacaja_tarjeta_id' => 0, 'bruto_cupones' => 0.0];
        }

        return [
            'cuentacaja_tarjeta_id' => (int) $coincidencias[1],
            'bruto_cupones' => round((float) $coincidencias[2], 2),
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public static function bruto(array $payload): float
    {
        $bruto = round(NumeroDecimalLocalSupport::aFloat($payload['bruto_cupones'] ?? 0), 2);
        if ($bruto > 0) {
            return $bruto;
        }

        return self::parsearLeyenda((string) ($payload['leyenda'] ?? ''))['bruto_cupones'];
    }

    /** @param  array<string, mixed>  $payload */
    public static function cuentacajaTarjetaId(array $payload): int
    {
        $id = (int) ($payload['cuentacaja_tarjeta_id'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        return self::parsearLeyenda((string) ($payload['leyenda'] ?? ''))['cuentacaja_tarjeta_id'];
    }

    /**
     * Cupones − gastos de todos los comprobantes = lo que entró a la caja.
     *
     * @param  list<array<string, mixed>>  $comprobantes
     * @param  list<object|array<string, mixed>>  $lineasCaja
     */
    public static function validarCierre(array $comprobantes, array $lineasCaja, int $monedaReferenciaId = 1): void
    {
        $bruto = 0.0;
        foreach ($comprobantes as $comprobante) {
            if (! is_array($comprobante) || ! self::es($comprobante)) {
                continue;
            }
            if (self::cuentacajaTarjetaId($comprobante) <= 0) {
                throw new \RuntimeException('La liquidación de tarjeta no tiene la cuenta de la tarjeta.');
            }
            $brutoLinea = self::bruto($comprobante);
            if ($brutoLinea <= 0) {
                throw new \RuntimeException('La liquidación de tarjeta no tiene el bruto de cupones.');
            }
            $bruto += $brutoLinea;
        }
        $bruto = round($bruto, 2);
        $gastos = IngresoEgresoComprobanteIvaValidacionSupport::totalComprobantes($comprobantes, $monedaReferenciaId);
        $cobrado = IngresoEgresoComprobanteIvaValidacionSupport::totalPagoCaja($lineasCaja, $monedaReferenciaId);

        if ($cobrado <= 0) {
            throw new \RuntimeException('Cargá en la cuenta de caja el total cobrado (lo que depositó la tarjeta).');
        }
        if (! self::cierra($bruto, $gastos, $cobrado)) {
            throw new \RuntimeException(
                'La liquidación no cierra: cupones '
                .self::formato($bruto)
                .' − gastos '
                .self::formato($gastos)
                .' = cobrado '
                .self::formato(round($bruto - $gastos, 2))
                .'. En la caja hay '
                .self::formato($cobrado)
                .'.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function catalogo(): array
    {
        $tipo = Tipotransaccion_Compra::query()
            ->where('abreviatura', 'DTC')
            ->where('estado', 'A')
            ->first();

        $tipoId = (int) ($tipo->id ?? 0);
        $lista = $tipoId > 0
            ? ConceptoIvacompraConsultaSupport::listarPorTipoTransaccion($tipoId)
            : collect();
        if ($lista->isNotEmpty()) {
            ConceptoIvacompraFormulaSupport::inferirTiposYTasasEnColeccion($lista);
        }

        $idPorCodigo = [];
        foreach ($lista as $concepto) {
            $codigo = trim((string) $concepto->codigo);
            if ($codigo !== '') {
                $idPorCodigo[$codigo] = (int) $concepto->id;
            }
        }

        $filas = [];
        foreach ($lista as $concepto) {
            if (! $concepto instanceof Concepto_Ivacompra) {
                continue;
            }
            $parsed = ConceptoIvacompraFormulaSupport::parse((string) $concepto->formula);
            $baseId = 0;
            $tasa = 0.0;
            if ($parsed !== null) {
                $baseId = (int) ($idPorCodigo[$parsed['codigo_base']] ?? 0);
                $tasa = (float) $parsed['coeficiente'];
            }
            $codigo = trim((string) $concepto->codigo);
            $nombre = trim((string) $concepto->nombre);
            $tipoConcepto = strtoupper(trim((string) ($concepto->tipoconcepto ?? '')));
            $filas[] = [
                'clave' => (string) $concepto->id,
                'etiqueta' => trim($codigo.' '.$nombre),
                'base' => $baseId > 0 ? (string) $baseId : '',
                'tasa' => $tasa,
                'concepto_id' => (int) $concepto->id,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'tipoconcepto' => $tipoConcepto,
                'columna' => ComprobanteProveedorConceptoIvaTipos::esImpuesto($tipoConcepto) ? 'puestos' : 'netos',
                'cuenta_debe_id' => (int) ($concepto->cuentacontabledebe_id ?? 0),
            ];
        }

        $comisiones = Cuentacaja::query()
            ->where('nombre', 'like', '%COMISIONES TARJETAS%')
            ->where('cuentacontable_id', '>', 0)
            ->first();
        $cuentaComision = null;
        if ($comisiones && (int) $comisiones->cuentacontable_id > 0) {
            $cuenta = Cuentacontable::query()->find((int) $comisiones->cuentacontable_id);
            if ($cuenta) {
                $cuentaComision = [
                    'id' => (int) $cuenta->id,
                    'codigo' => (string) $cuenta->codigo,
                    'nombre' => (string) $cuenta->nombre,
                ];
            }
        }

        $tarjetas = [];
        foreach (Cuentacaja::query()->operativa()->where('es_tarjeta', true)->orderBy('nombre')->get() as $cuenta) {
            $contable = (int) $cuenta->cuentacontable_id > 0
                ? Cuentacontable::query()->find((int) $cuenta->cuentacontable_id)
                : null;
            $tarjetas[] = [
                'id' => (int) $cuenta->id,
                'codigo' => (string) $cuenta->codigo,
                'nombre' => (string) $cuenta->nombre,
                'cuentacontable_id' => (int) ($contable->id ?? 0),
                'cuentacontable_codigo' => (string) ($contable->codigo ?? ''),
                'cuentacontable_nombre' => (string) ($contable->nombre ?? ''),
            ];
        }

        return [
            'tipotransaccion_compra_id' => (int) ($tipo->id ?? 0),
            'tipo_abreviatura' => (string) ($tipo->abreviatura ?? ''),
            'tipo_nombre' => (string) ($tipo->nombre ?? ''),
            'filas' => $filas,
            'cuenta_comisiones' => $cuentaComision,
            'tarjetas' => $tarjetas,
            'cuits' => self::cuitsRecientes((int) ($tipo->id ?? 0)),
        ];
    }

    /**
     * @return list<array{nombre: string, cuit: string, condicioniva_id: int}>
     */
    private static function cuitsRecientes(int $tipoId): array
    {
        if ($tipoId <= 0) {
            return [];
        }

        $filas = \App\Models\Compras\Comprobante_Proveedor::query()
            ->where('tipotransaccion_compra_id', $tipoId)
            ->where('proveedor_documento_eventual', '!=', '')
            ->orderByDesc('id')
            ->limit(40)
            ->get(['proveedor_nombre_eventual', 'proveedor_documento_eventual', 'proveedor_condicioniva_id_eventual']);

        $vistos = [];
        $salida = [];
        foreach ($filas as $fila) {
            $cuit = preg_replace('/\D+/', '', (string) $fila->proveedor_documento_eventual) ?? '';
            if (strlen($cuit) !== 11 || isset($vistos[$cuit])) {
                continue;
            }
            $vistos[$cuit] = true;
            $salida[] = [
                'nombre' => (string) $fila->proveedor_nombre_eventual,
                'cuit' => $cuit,
                'condicioniva_id' => (int) ($fila->proveedor_condicioniva_id_eventual ?? 0),
            ];
        }

        return $salida;
    }

    private static function formato(float $importe): string
    {
        return number_format($importe, 2, ',', '.');
    }
}
