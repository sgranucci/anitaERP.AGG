<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Models\Ventas\Venta_Impuesto;
use App\Models\Ventas\Venta_emision;
use App\Support\Ventas\KandikoAnitaVentaTipoSupport;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill Anita → ERP: crea venta gastronomía en el ERP cuando la cabecera existe solo en Informix.
 */
final class GastronomiaImportarCabeceraAnitaErpService
{
    /**
     * @return array{venta_id:int, codigo:string, estado:string}
     */
    public function importarPorComprobante(
        int $puntoventaId,
        int $numeroComprobante,
        ?string $fechaJornada = null,
        ?int $usuarioId = null,
    ): array {
        $puntoventa = Puntoventa::query()->with('empresas')->findOrFail($puntoventaId);
        if (! KandikoAnitaVentaTipoSupport::esPvCaeaKandiko(
            (string) $puntoventa->codigo,
            $puntoventa->empresas?->codigo ?? $puntoventa->empresa_id,
            $puntoventa->modofacturacion ?? null,
        )) {
            throw new \InvalidArgumentException('Importación Anita→ERP soportada solo para PV CAEA Kandiko (00031).');
        }

        $existente = Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->where('numerocomprobante', $numeroComprobante)
            ->whereHas('gastronomiaEmision')
            ->first();
        if ($existente !== null) {
            throw new \InvalidArgumentException('Ya existe venta ERP '.$existente->codigo.' (id '.$existente->id.').');
        }

        $sucursal = (int) preg_replace('/\D+/', '', (string) $puntoventa->codigo);
        $cabecera = $this->leerCabeceraAnita($sucursal, $numeroComprobante);
        if ($cabecera === null) {
            throw new \RuntimeException('No se encontró cabecera FAK/FAC '.$numeroComprobante.' en Anita sucursal '.$sucursal.'.');
        }

        $fechaJornada = $fechaJornada ?: $this->fechaDesdeAnita((string) ($cabecera->ven_fecha_vto ?? $cabecera->ven_fecha ?? ''));
        $referencia = $this->resolverVentaReferenciaCortesia($puntoventaId, $fechaJornada);
        $tipotransaccion = Tipotransaccion::query()->findOrFail((int) $referencia->tipotransaccion_id);
        $tipoAnita = (string) ($tipotransaccion->abreviatura ?? 'FAC');
        $letra = 'B';
        $codigo = $tipoAnita.' '.$letra.'-'
            .str_pad((string) $puntoventa->codigo, (int) config('facturacion.DIGITOS_SUCURSAL'), '0', STR_PAD_LEFT).'-'
            .str_pad((string) $numeroComprobante, (int) config('facturacion.DIGITOS_COMPROBANTE'), '0', STR_PAD_LEFT);

        $montosCab = GastronomiaAnitaVenGravadoSupport::montosCabeceraImportDesdeAnita(
            (float) ($cabecera->ven_monto ?? 0),
            (float) ($cabecera->ven_gravado ?? 0),
            (float) ($cabecera->ven_exento ?? 0),
            (float) ($cabecera->ven_impuesto1 ?? 0),
        );
        $total = $montosCab['total'];
        $exento = $montosCab['exento'];
        $gravado = $montosCab['gravado'];
        $iva = $montosCab['iva'];
        $subtotalBruto = $this->resolverSubtotalBrutoCortesia($referencia, $total, $exento, $gravado, $iva);

        $usuarioId = $usuarioId ?: (int) (Auth::id() ?: $referencia->usuario_id ?: 1);

        return DB::transaction(function () use (
            $referencia,
            $puntoventa,
            $puntoventaId,
            $numeroComprobante,
            $fechaJornada,
            $codigo,
            $total,
            $exento,
            $gravado,
            $iva,
            $subtotalBruto,
            $tipotransaccion,
            $usuarioId,
        ): array {
            $venta = Venta::query()->create([
                'fecha' => $fechaJornada,
                'fechajornada' => $fechaJornada,
                'empresa_id' => $puntoventa->empresa_id,
                'tipotransaccion_id' => $tipotransaccion->id,
                'puntoventa_id' => $puntoventaId,
                'numerocomprobante' => $numeroComprobante,
                'actividad_arca_id' => $referencia->actividad_arca_id,
                'cliente_id' => $referencia->cliente_id,
                'condicionventa_id' => $referencia->condicionventa_id,
                'vendedor_id' => $referencia->vendedor_id,
                'transporte_id' => $referencia->transporte_id,
                'total' => $total,
                'moneda_id' => $referencia->moneda_id,
                'cotizacion' => $referencia->cotizacion,
                'estado' => ' ',
                'usuario_id' => $usuarioId,
                'leyenda' => 'Import Anita→ERP',
                'descuento' => $referencia->descuento ?? 0,
                'descuentointegrado' => ' ',
                'lugarentrega' => $referencia->lugarentrega,
                'codigo' => $codigo,
                'nombre' => $referencia->nombre,
                'domicilio' => $referencia->domicilio,
                'localidad_id' => $referencia->localidad_id,
                'provincia_id' => $referencia->provincia_id,
                'pais_id' => $referencia->pais_id,
                'codigopostal' => $referencia->codigopostal,
                'email' => $referencia->email,
                'telefono' => $referencia->telefono,
                'numerodocumento' => $referencia->numerodocumento,
                'condicioniva_id' => $referencia->condicioniva_id,
                'cantidadbulto' => 1,
                'ordenventa_id' => null,
            ]);

            $emisionRef = $referencia->gastronomiaEmision;
            VentaGastronomiaEmision::query()->create([
                'venta_id' => $venta->id,
                'configuracion_puntoventa_gastronomia_id' => $emisionRef?->configuracion_puntoventa_gastronomia_id,
                'origen_pos' => $emisionRef?->origen_pos ?? 'salon',
                'identificador_pc' => $emisionRef?->identificador_pc,
            ]);

            $lineaRef = $referencia->venta_emisiones->first();
            Venta_emision::query()->create([
                'venta_id' => $venta->id,
                'numeroitem' => 1,
                'lotestock' => 0,
                'articulo_id' => $lineaRef?->articulo_id,
                'detalle' => ($lineaRef?->detalle ?? 'CORTESIA').' (import Anita)',
                'cantidad' => 1,
                'precio' => $subtotalBruto,
                'impuesto_id' => $lineaRef?->impuesto_id ?? 1,
                'incluyeimpuesto' => $lineaRef?->incluyeimpuesto ?? 'N',
                'moneda_id' => $lineaRef?->moneda_id ?? $referencia->moneda_id,
                'descuento' => 0,
                'descuentointegrado' => ' ',
            ]);

            $this->crearImpuestos($venta->id, $subtotalBruto, $total, $exento, $gravado, $iva);

            Log::info('gastronomia.importar_cabecera_anita.ok', [
                'venta_id' => $venta->id,
                'codigo' => $codigo,
                'numero' => $numeroComprobante,
            ]);

            return [
                'venta_id' => (int) $venta->id,
                'codigo' => $codigo,
                'estado' => 'ok',
            ];
        });
    }

    private function leerCabeceraAnita(int $sucursal, int $numero): ?object
    {
        $api = new ApiAnita;
        foreach (KandikoAnitaVentaTipoSupport::tiposAnitaEquivalentesFacErp() as $tipo) {
            $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
                'acc' => 'list',
                'tabla' => 'venta',
                'campos' => implode(',', [
                    'ven_tipo', 'ven_letra', 'ven_sucursal', 'ven_nro',
                    'ven_fecha', 'ven_fecha_vto',
                    'ven_monto', 'ven_gravado', 'ven_exento', 'ven_impuesto1', 'ven_monto_desc',
                ]),
                'whereArmado' => " WHERE ven_sucursal = '".$sucursal."'"
                    ." AND ven_tipo = '".addslashes($tipo)."'"
                    ." AND ven_nro = '".$numero."'"
                    ." AND ven_letra = 'B' ",
            ]));

            if (($parsed['filas'][0] ?? null) !== null) {
                return $parsed['filas'][0];
            }
        }

        return null;
    }

    private function resolverVentaReferenciaCortesia(int $puntoventaId, string $fechaJornada): Venta
    {
        $referencia = Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->whereDate('fechajornada', $fechaJornada)
            ->whereHas('gastronomiaEmision')
            ->whereRaw('ABS(total - 0.01) <= 0.02')
            ->with(['venta_emisiones', 'gastronomiaEmision', 'clientes'])
            ->orderBy('numerocomprobante')
            ->first();

        if ($referencia === null) {
            $referencia = Venta::query()
                ->where('puntoventa_id', $puntoventaId)
                ->whereHas('gastronomiaEmision')
                ->with(['venta_emisiones', 'gastronomiaEmision', 'clientes'])
                ->orderByDesc('id')
                ->first();
        }

        if ($referencia === null) {
            throw new \RuntimeException('No hay venta gastronomía de referencia en el PV para clonar estructura.');
        }

        return $referencia;
    }

    private function resolverSubtotalBrutoCortesia(
        Venta $referencia,
        float $total,
        float $exento,
        float $gravado,
        float $iva,
    ): float {
        if ($gravado > 0. || $iva > 0.) {
            return round($gravado + $iva, 2);
        }

        if ($exento > 0.) {
            $lineaRef = $referencia->venta_emisiones->first();
            if ($lineaRef !== null && (float) $lineaRef->precio > $total) {
                return round((float) $lineaRef->precio, 2);
            }

            return max($exento, $total) * 1000;
        }

        return max($total, 0.01);
    }

    private function crearImpuestos(
        int $ventaId,
        float $subtotalBruto,
        float $total,
        float $exento,
        float $gravado,
        float $iva,
    ): void {
        if (GastronomiaAnitaVenGravadoSupport::esCortesiaMinima($total)) {
            foreach (GastronomiaAnitaVenGravadoSupport::filasVentaImpuestoImportCortesiaMinima() as $fila) {
                Venta_Impuesto::query()->create([
                    'venta_id' => $ventaId,
                    'concepto' => $fila['concepto'],
                    'importe' => $fila['importe'],
                    'baseimponible' => $fila['baseimponible'],
                    'tasa' => $fila['tasa'],
                    'impuesto_id' => $fila['impuesto_id'],
                ]);
            }

            return;
        }

        if ($gravado > 0. || $iva > 0.) {
            Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => 'Subtotal',
                'importe' => $gravado,
                'baseimponible' => $gravado,
            ]);
            Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => 'Gravado al 21.000%',
                'importe' => $gravado,
                'baseimponible' => $gravado,
            ]);
            Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => 'Iva 21.000%',
                'importe' => $iva,
                'baseimponible' => $gravado,
            ]);
            Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => 'Total',
                'importe' => $total,
                'baseimponible' => 0,
            ]);

            return;
        }

        $descuento = round($subtotalBruto - $total, 2);

        Venta_Impuesto::query()->create([
            'venta_id' => $ventaId,
            'concepto' => 'Subtotal',
            'importe' => $subtotalBruto,
            'baseimponible' => 0,
        ]);
        if (abs($descuento) > 0.0001) {
            Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => 'Descuento Gral.',
                'importe' => -abs($descuento),
                'baseimponible' => 0,
            ]);
        }
        Venta_Impuesto::query()->create([
            'venta_id' => $ventaId,
            'concepto' => 'Exento',
            'importe' => $exento > 0 ? $exento : $total,
            'baseimponible' => 0,
        ]);
        Venta_Impuesto::query()->create([
            'venta_id' => $ventaId,
            'concepto' => 'Total',
            'importe' => $total,
            'baseimponible' => 0,
        ]);
    }

    private function fechaDesdeAnita(string $fechaEntera): string
    {
        $fechaEntera = preg_replace('/\D+/', '', $fechaEntera);
        if (strlen($fechaEntera) !== 8) {
            throw new \InvalidArgumentException('Fecha Anita inválida: '.$fechaEntera);
        }

        return substr($fechaEntera, 0, 4).'-'.substr($fechaEntera, 4, 2).'-'.substr($fechaEntera, 6, 2);
    }
}
