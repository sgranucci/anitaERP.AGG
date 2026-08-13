<?php

namespace App\Support\Stock;

use App\Mail\Stock\TransferenciaMercaderiaTitoPrecioAviso;
use App\Models\Stock\Articulo;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Models\Stock\Transferencia_Mercaderia_Articulo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Aviso por mail al contabilizar TRCONT con artículos TITO (precio promedio 3 compras).
 */
final class TransferenciaMercaderiaTitoPrecioAvisoSupport
{
    public static function habilitado(): bool
    {
        return (bool) config('stock.aviso_tito_trcont.habilitado', true);
    }

    /**
     * @return list<string>
     */
    public static function destinatarios(): array
    {
        $raw = (string) config('stock.aviso_tito_trcont.destinatarios', '');
        $emails = array_values(array_unique(array_filter(array_map(
            static fn ($e) => strtolower(trim((string) $e)),
            explode(',', $raw)
        ), static fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))));

        return $emails;
    }

    public static function notificarSiCorresponde(Transferencia_Mercaderia $transferencia): void
    {
        if (! self::habilitado()) {
            return;
        }

        $destinatarios = self::destinatarios();
        if ($destinatarios === []) {
            return;
        }

        try {
            $informe = self::armarInforme($transferencia);
            if ($informe === null || ($informe['lineas'] ?? []) === []) {
                return;
            }

            Mail::to($destinatarios)->send(new TransferenciaMercaderiaTitoPrecioAviso($informe));
        } catch (\Throwable $e) {
            Log::error('stock.aviso_tito_trcont.mail_fallo', [
                'transferencia_id' => (int) ($transferencia->id ?? 0),
                'codigo' => (string) ($transferencia->codigo ?? ''),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null null si no hay líneas TITO
     */
    public static function armarInforme(Transferencia_Mercaderia $transferencia): ?array
    {
        $transferencia->loadMissing([
            'articulos.articuloOrigen',
            'depositoOrigen',
            'depositoDestino',
            'empresas',
            'asientos',
            'tipotransaccion_stock',
            'usuarioOrigen',
            'centrocostoDestino',
        ]);

        $lineasTito = [];
        foreach ($transferencia->articulos as $linea) {
            $articulo = $linea->articuloOrigen;
            if (! $articulo instanceof Articulo) {
                continue;
            }
            if (! ArticuloPrecioTransferenciaContableSupport::usaPrecioPromedio($articulo)) {
                continue;
            }
            $lineasTito[] = $linea;
        }

        if ($lineasTito === []) {
            return null;
        }

        $articulos = [];
        foreach ($lineasTito as $linea) {
            $art = $linea->articuloOrigen;
            if ($art instanceof Articulo) {
                $articulos[(int) $art->id] = $art;
            }
        }

        $promedios = ArticuloPrecioPromedioCompraSupport::resolverPorArticulos($articulos);

        $filas = [];
        $totalImporte = 0.0;
        foreach ($lineasTito as $linea) {
            /** @var Transferencia_Mercaderia_Articulo $linea */
            $articulo = $linea->articuloOrigen;
            if (! $articulo instanceof Articulo) {
                continue;
            }
            $articuloId = (int) $articulo->id;
            $dato = $promedios[$articuloId] ?? ['precio' => null, 'origen' => null, 'compras' => []];
            $precioPromedio = $dato['precio'] !== null ? (float) $dato['precio'] : null;
            $origen = $dato['origen'] ?? null;
            $cantidad = (float) ($linea->cantidad_origen ?? 0);
            $importe = ($precioPromedio !== null && $precioPromedio > 0 && $cantidad > 0)
                ? round($cantidad * $precioPromedio, 2)
                : null;
            if ($importe !== null) {
                $totalImporte += $importe;
            }

            $filas[] = [
                'item' => (int) ($linea->item ?? 0),
                'sku' => (string) ($articulo->sku ?? ''),
                'descripcion' => (string) ($articulo->descripcion ?? ''),
                'cantidad' => $cantidad,
                'precio_costo_linea' => isset($linea->precio_costo_origen)
                    ? (float) $linea->precio_costo_origen
                    : null,
                'precio_promedio' => $precioPromedio,
                'origen' => $origen,
                'origen_etiqueta' => self::etiquetaOrigen($origen),
                'importe' => $importe,
                'compras' => $dato['compras'] ?? [],
            ];
        }

        $asiento = $transferencia->asientos;
        $depositoOrigen = $transferencia->depositoOrigen;
        $depositoDestino = $transferencia->depositoDestino;
        $empresa = $transferencia->empresas;
        $tipo = $transferencia->tipotransaccion_stock;
        $cc = $transferencia->centrocostoDestino;

        return [
            'transferencia_id' => (int) $transferencia->id,
            'codigo' => (string) ($transferencia->codigo ?? ''),
            'fecha' => $transferencia->fecha?->format('Y-m-d') ?? '',
            'estado' => (string) ($transferencia->estado ?? ''),
            'empresa_id' => (int) ($transferencia->empresa_id ?? 0),
            'empresa_nombre' => (string) ($empresa->nombre ?? $empresa->descripcion ?? ''),
            'tipo_abreviatura' => (string) ($tipo->abreviatura ?? ''),
            'tipo_nombre' => (string) ($tipo->nombre ?? ''),
            'deposito_origen' => trim(
                (string) ($depositoOrigen->codigo ?? '').' '.
                (string) ($depositoOrigen->nombre ?? $depositoOrigen->descripcion ?? '')
            ),
            'deposito_destino' => trim(
                (string) ($depositoDestino->codigo ?? '').' '.
                (string) ($depositoDestino->nombre ?? $depositoDestino->descripcion ?? '')
            ),
            'centrocosto_destino' => trim(
                (string) ($cc->codigo ?? '').' '.
                (string) ($cc->nombre ?? $cc->descripcion ?? '')
            ),
            'usuario_origen' => (string) ($transferencia->usuarioOrigen->nombre ?? ''),
            'asiento_id' => (int) ($transferencia->asiento_id ?? 0),
            'asiento_numero' => (string) ($asiento->numeroasiento ?? ''),
            'total_importe' => round($totalImporte, 2),
            'lineas' => $filas,
        ];
    }

    private static function etiquetaOrigen(?string $origen): string
    {
        return match ($origen) {
            ArticuloPrecioPromedioCompraSupport::ORIGEN_ERP_COM => 'Promedio 3 COM ERP',
            ArticuloPrecioPromedioCompraSupport::ORIGEN_ANITA_STKMAE => 'Promedio Anita stkmae compra1/2/3',
            default => 'Sin precio',
        };
    }
}
