<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Contable\MontoEsArSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Reparto del Debe de gasto (neto) en N cuentas desde la solapa Asiento.
 *
 * Solo aplica cuando el neto no viene de FAR/COM, OC artículos, anticipo o contrato manual.
 * Impuestos/percepciones siguen 1:1 desde conceptos IVA.
 */
final class ComprobanteProveedorDebeGastoSupport
{
    /**
     * @param  list<array{cuentacontable_id:int, importe:float, centrocosto_id:int, orden:int}>|Collection|null  $lineas
     */
    public static function tieneReparto(?iterable $lineas): bool
    {
        if ($lineas === null) {
            return false;
        }
        foreach ($lineas as $linea) {
            $importe = is_array($linea)
                ? (float) ($linea['importe'] ?? 0)
                : (float) ($linea->importe ?? 0);
            // Con importe ya hay reparto (la cuenta puede completarse después en preview).
            if (abs($importe) >= 0.0001) {
                return true;
            }
        }

        return false;
    }

    /**
     * Modo en el que el neto se imputa a cuenta(s) elegidas en Asiento (no FAR/OC/anticipo/contrato).
     */
    public static function modoPermiteReparto(
        bool $usaProvisionCom,
        bool $netoDesdeArticulosOc,
        bool $facturaAnticipada,
        bool $contratoImputacionManual,
    ): bool {
        return ! $usaProvisionCom
            && ! $netoDesdeArticulosOc
            && ! $facturaAnticipada
            && ! $contratoImputacionManual;
    }

    /**
     * Neto de mercadería / exento que integra el total (mismo criterio que el asiento).
     */
    public static function totalNetoImputable(Comprobante_Proveedor $comprobante): float
    {
        $conceptos = $comprobante->comprobante_proveedor_conceptos ?? collect();
        $exentoIntegra = ComprobanteProveedorImporteComparacionComSupport::exentoDeConceptosIntegraTotal(
            (float) ($comprobante->total ?? 0),
            $conceptos,
        );

        $total = 0.0;
        foreach ($conceptos as $linea) {
            $concepto = $linea->concepto_ivacompras ?? null;
            $tipo = (string) ($concepto?->tipoconcepto ?? '');
            $codigo = (string) ($concepto?->codigo ?? '');
            $montoRaw = round((float) ($linea->monto ?? 0), 2);
            $permiteNegativo = ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo($tipo, $codigo);
            $monto = $permiteNegativo ? $montoRaw : round(abs($montoRaw), 2);
            if (abs($monto) < 0.0001) {
                continue;
            }
            if (ComprobanteProveedorConceptoIvaTipos::esExento($tipo, $codigo) && ! $exentoIntegra) {
                continue;
            }
            if ($monto < 0) {
                // Descuentos E/80/81: restan del neto del reparto.
                if (ComprobanteProveedorConceptoIvaTipos::esDescuento($codigo)
                    || ComprobanteProveedorConceptoIvaTipos::esExento($tipo, $codigo)) {
                    $total = round($total + $monto, 2);
                }

                continue;
            }
            // Mismo universo que el skip de armarPreview con hayReparto: N/G/E o EXENTO
            // (código 1) que integra el total — aunque tipoconcepto venga vacío.
            if (! ComprobanteProveedorConceptoIvaTipos::esNetoMercaderia($tipo, $codigo)
                && ! ComprobanteProveedorConceptoIvaTipos::esExento($tipo, $codigo)) {
                continue;
            }
            $total = round($total + $monto, 2);
        }

        return round($total, 2);
    }

    /**
     * @return list<array{cuentacontable_id:int, importe:float, centrocosto_id:int, orden:int}>
     */
    public static function lineasDesdeComprobante(Comprobante_Proveedor $comprobante): array
    {
        $out = [];
        $orden = 0;
        foreach ($comprobante->comprobante_proveedor_debe_gastos ?? [] as $fila) {
            $cuentaId = (int) ($fila->cuentacontable_id ?? 0);
            $importe = round((float) ($fila->importe ?? 0), 2);
            if (abs($importe) < 0.0001) {
                continue;
            }
            $orden++;
            $out[] = [
                'cuentacontable_id' => $cuentaId,
                'importe' => $importe,
                'centrocosto_id' => (int) ($fila->centrocosto_id ?? 0),
                'orden' => (int) ($fila->orden ?? $orden),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{cuentacontable_id:int, importe:float, centrocosto_id:int, orden:int}>
     */
    public static function lineasDesdeRequest(Request $request): array
    {
        $cuentas = $request->input('debe_gasto_cuenta_ids', []);
        $importes = $request->input('debe_gasto_importes', []);
        $centros = $request->input('debe_gasto_centrocosto_ids', []);
        if (! is_array($cuentas)) {
            $cuentas = [];
        }
        if (! is_array($importes)) {
            $importes = [];
        }
        if (! is_array($centros)) {
            $centros = [];
        }

        $out = [];
        $orden = 0;
        $n = max(count($cuentas), count($importes));
        for ($i = 0; $i < $n; $i++) {
            $cuentaId = (int) ($cuentas[$i] ?? 0);
            $importe = MontoEsArSupport::parse($importes[$i] ?? 0);
            // Preview: permitir renglón sin cuenta aún (importe > 0). Al contabilizar se exige cuenta.
            if (abs($importe) < 0.0001) {
                continue;
            }
            $orden++;
            $out[] = [
                'cuentacontable_id' => $cuentaId,
                'importe' => round(abs($importe), 2),
                'centrocosto_id' => (int) ($centros[$i] ?? 0),
                'orden' => $orden,
            ];
        }

        return $out;
    }

    /**
     * Defensa: el form renderiza el asiento en 2 paneles; un selector global leía ambas
     * tablas y mandaba el mismo renglón (importe = neto completo) dos veces.
     * No toca un reparto legítimo 50/50 (cada línea ≈ neto/2).
     *
     * @param  list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int, orden?:int}>  $lineas
     * @return list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int, orden?:int}>
     */
    public static function depurarDuplicadoPanelClonado(array $lineas, float $netoEsperado): array
    {
        if (count($lineas) !== 2) {
            return $lineas;
        }
        $neto = round(abs($netoEsperado), 2);
        if ($neto < 0.0001) {
            return $lineas;
        }
        $a = $lineas[0];
        $b = $lineas[1];
        if ((int) ($a['cuentacontable_id'] ?? 0) !== (int) ($b['cuentacontable_id'] ?? 0)) {
            return $lineas;
        }
        $impA = round((float) ($a['importe'] ?? 0), 2);
        $impB = round((float) ($b['importe'] ?? 0), 2);
        if (abs($impA - $impB) >= 0.0001) {
            return $lineas;
        }
        // Solo si cada línea es el neto completo (síntoma del doble panel).
        if (abs($impA - $neto) > ComprobanteProveedorAsientoCuadreSupport::TOLERANCIA) {
            return $lineas;
        }
        $a['orden'] = 1;

        return [$a];
    }

    /**
     * @param  list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int, orden?:int}>  $lineas
     */
    public static function sumaImportes(array $lineas): float
    {
        $suma = 0.0;
        foreach ($lineas as $linea) {
            $suma += (float) ($linea['importe'] ?? 0);
        }

        return round($suma, 2);
    }

    /**
     * @param  list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int, orden?:int}>  $lineas
     */
    public static function assertSumaCuadraConNeto(array $lineas, float $netoEsperado): void
    {
        $suma = self::sumaImportes($lineas);
        $neto = round(abs($netoEsperado), 2);
        $diff = round($suma - $neto, 2);
        if (abs($diff) > ComprobanteProveedorAsientoCuadreSupport::TOLERANCIA) {
            throw new \RuntimeException(
                'El reparto de cuentas de gasto ('.number_format($suma, 2, ',', '.')
                .') no coincide con el neto del comprobante ('.number_format($neto, 2, ',', '.')
                .'). Diferencia: '.number_format($diff, 2, ',', '.').'.'
            );
        }
        foreach ($lineas as $i => $linea) {
            if ((int) ($linea['cuentacontable_id'] ?? 0) <= 0) {
                throw new \RuntimeException(
                    'Falta la cuenta contable en el renglón de gasto #'.($i + 1).'.'
                );
            }
        }
    }

    /**
     * @param  list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int, orden?:int}>  $lineas
     * @return list<array{cuentacontable_id:int, importe:float, centrocosto_id:int, observacion:string, origen:string, editable_cuenta:bool, editable_importe:bool, concepto_ivacompra_id:int}>
     */
    public static function aLineasDebeAsiento(
        array $lineas,
        int $centrocostoDefaultId,
        string $observacion,
    ): array {
        $out = [];
        foreach ($lineas as $linea) {
            $cc = (int) ($linea['centrocosto_id'] ?? 0);
            $out[] = [
                'cuentacontable_id' => (int) $linea['cuentacontable_id'],
                'importe' => round((float) $linea['importe'], 2),
                'centrocosto_id' => $cc > 0 ? $cc : $centrocostoDefaultId,
                'observacion' => $observacion,
                'origen' => 'debe_gasto',
                'editable_cuenta' => true,
                'editable_importe' => true,
                'concepto_ivacompra_id' => 0,
            ];
        }

        return $out;
    }
}
