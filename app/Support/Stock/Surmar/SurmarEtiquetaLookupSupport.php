<?php

namespace App\Support\Stock\Surmar;

use App\Models\Stock\Stock_Etiqueta;
use App\Support\Stock\SurmarSupport;
use Illuminate\Validation\ValidationException;

/**
 * Resolución unificada de etiqueta Surmar (ID ERP o barcode Anita).
 * Sirve movimientos, certificado SENASA y trazabilidad.
 */
final class SurmarEtiquetaLookupSupport
{
    /**
     * @return array{
     *   etiqueta: Stock_Etiqueta,
     *   payload: array<string, mixed>
     * }
     */
    public static function resolver(
        string $raw,
        ?int $empresaId = null,
        bool $soloDisponible = true,
        bool $rechazarAnulada = true,
    ): array {
        $empresaId = $empresaId ?? SurmarSupport::EMPRESA_ID;
        if (! SurmarSupport::esEmpresaSurmar($empresaId)) {
            throw ValidationException::withMessages(['etiqueta' => 'Consulta Surmar solo en empresa Surmar.']);
        }

        $parsed = SurmarEtiquetaBarcodeSupport::parsear($raw);
        if (($parsed['modo'] ?? '') === 'vacio') {
            throw ValidationException::withMessages(['etiqueta' => 'Código de etiqueta inválido.']);
        }

        $q = Stock_Etiqueta::query()
            ->with(['articulos:id,sku,descripcion,grupocarne,tipocarne', 'depositos:id,codigo,nombre', 'unidadesmedida:id,abreviatura,nombre'])
            ->where('empresa_id', $empresaId);

        if ($parsed['modo'] === 'id') {
            $q->whereKey((int) $parsed['etiqueta_id']);
        } else {
            $q->where('anita_nro_interno', (int) $parsed['nro_interno'])
                ->where('anita_nro_apertura', (int) $parsed['nro_apertura']);
            if (! empty($parsed['sku'])) {
                $sku = (string) $parsed['sku'];
                $q->whereHas('articulos', function ($aq) use ($sku) {
                    $aq->where('sku', $sku)->orWhere('sku', ltrim($sku, '0'));
                });
            }
        }

        $eti = $q->first();
        if (! $eti) {
            throw ValidationException::withMessages(['etiqueta' => 'Etiqueta no encontrada (ERP o Anita).']);
        }
        if ($rechazarAnulada && $eti->estado === SurmarSupport::ESTADO_ANULADA) {
            throw ValidationException::withMessages(['etiqueta' => 'Etiqueta #'.$eti->id.' está ANULADA.']);
        }
        if ($soloDisponible && $eti->estado !== SurmarSupport::ESTADO_DISPONIBLE) {
            throw ValidationException::withMessages([
                'etiqueta' => 'Etiqueta #'.$eti->id.' no está DISPONIBLE ('.$eti->estado.').',
            ]);
        }

        return [
            'etiqueta' => $eti,
            'payload' => self::payload($eti),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(Stock_Etiqueta $eti): array
    {
        return [
            'etiqueta_id' => (int) $eti->id,
            'articulo_id' => (int) $eti->articulo_id,
            'sku' => (string) ($eti->articulos->sku ?? ''),
            'descripcion' => (string) ($eti->descripcion_snapshot ?: ($eti->articulos->descripcion ?? '')),
            'peso_neto' => (float) $eti->peso_neto,
            'peso_bruto' => (float) $eti->peso_bruto,
            'cant_pieza' => (float) $eti->cant_pieza,
            'lote_proveedor' => (string) ($eti->lote_proveedor ?? ''),
            'deposito_id' => $eti->deposito_id ? (int) $eti->deposito_id : null,
            'deposito_codigo' => $eti->depositos->codigo ?? null,
            'deposito_nombre' => $eti->depositos->nombre ?? null,
            'estado' => (string) $eti->estado,
            'origen_tipo' => (string) $eti->origen_tipo,
            'anita_nro_interno' => $eti->anita_nro_interno ? (int) $eti->anita_nro_interno : null,
            'anita_nro_apertura' => $eti->anita_nro_apertura ? (int) $eti->anita_nro_apertura : null,
            'anita_tipo' => $eti->anita_tipo ? (string) $eti->anita_tipo : null,
            'umd' => (string) ($eti->unidadesmedida->abreviatura ?? $eti->unidadesmedida->nombre ?? 'KG'),
            'grupocarne' => (int) ($eti->articulos->grupocarne ?? 0),
            'tipocarne' => (int) ($eti->articulos->tipocarne ?? 0),
            'fecha_vto' => optional($eti->fecha_vto)->format('Y-m-d'),
        ];
    }
}
