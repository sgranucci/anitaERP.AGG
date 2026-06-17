<?php

namespace App\Support\Stock;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Articulo;

class RecepcionProveedorDiferenciaSupport
{
    public const TIPO_OC = 'OC';

    public const TIPO_EXTRA = 'EXTRA';

    public const TIPO_SUSTITUTO = 'SUSTITUTO';

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{
     *   items: list<array<string, mixed>>,
     *   fl_precio_diferencia: bool,
     *   fl_diferencia_cantidad: bool,
     *   fl_articulo_extra: bool,
     *   fl_faltante_oc: bool,
     *   fl_laboratorio: bool,
     *   fl_linea_rechazada: bool,
     *   resumen_diferencias: string,
     *   resumen_rechazos: string,
     *   faltantes: list<string>
     * }
     */
    public static function analizar(Ordencompra $oc, array $items, bool $omitirFaltantes = false): array
    {
        $empresaId = (int) $oc->empresa_id;
        $ccOc = (int) ($oc->centrocosto_id ?? 0);
        $tolEmpresa = RecepcionProveedorToleranciaSupport::resolver($empresaId, $ccOc);

        $ocPorId = $oc->ordencompra_articulos->keyBy('id');
        $recibidosPorOcArt = [];
        $flPrecio = $flCant = $flExtra = $flLab = $flRechazo = false;
        $resumenes = [];
        $resumenesRechazo = [];
        $enriquecidos = [];

        foreach ($items as $idx => $item) {
            $articuloId = (int) ($item['articulo_id'] ?? 0);
            $articulo = Articulo::query()->find($articuloId);
            $sku = $articulo->sku ?? ('Art.'.$articuloId);

            $ocArtId = (int) ($item['ordencompra_articulo_id'] ?? 0);
            $sustituidoId = (int) ($item['ordencompra_articulo_sustituido_id'] ?? 0);
            $tipoLinea = (string) ($item['tipo_linea'] ?? self::TIPO_OC);

            if ($tipoLinea === self::TIPO_EXTRA || ($ocArtId <= 0 && $sustituidoId <= 0)) {
                $tipoLinea = self::TIPO_EXTRA;
            } elseif ($sustituidoId > 0 || ($ocArtId > 0 && self::articuloDistintoDeOc($ocPorId->get($ocArtId), $articuloId))) {
                $tipoLinea = self::TIPO_SUSTITUTO;
                if ($sustituidoId <= 0 && $ocArtId > 0) {
                    $sustituidoId = $ocArtId;
                    $ocArtId = 0;
                }
            } else {
                $tipoLinea = self::TIPO_OC;
            }

            $ocArt = $ocArtId > 0 ? $ocPorId->get($ocArtId) : ($sustituidoId > 0 ? $ocPorId->get($sustituidoId) : null);
            $cantOc = (float) ($item['cantidad_oc'] ?? ($ocArt->cantidad ?? 0));
            $cantRec = (float) ($item['cantidad'] ?? 0) + (float) ($item['cantidad_rechazada'] ?? 0);
            $cantRechazada = (float) ($item['cantidad_rechazada'] ?? 0);
            $cantAceptada = (float) ($item['cantidad'] ?? 0);
            $precioOc = (float) ($item['precio_ordencompra'] ?? ($ocArt->precio ?? $item['precio'] ?? 0));
            $precioRec = (float) ($item['precio'] ?? 0);

            $claveOc = $sustituidoId > 0 ? $sustituidoId : $ocArtId;
            if ($claveOc > 0 && $tipoLinea !== self::TIPO_EXTRA) {
                $recibidosPorOcArt[$claveOc] = ($recibidosPorOcArt[$claveOc] ?? 0) + $cantRec;
            }

            if ($tipoLinea === self::TIPO_EXTRA) {
                $flExtra = true;
                $resumenes[] = "Extra: {$sku} x {$cantRec}";
            } elseif ($tipoLinea === self::TIPO_SUSTITUTO) {
                $flExtra = true;
                $resumenes[] = "Sustituto: {$sku} por línea OC #{$sustituidoId}";
            }

            $ccLinea = (int) ($item['centrocosto_id'] ?? $ccOc);
            $tol = RecepcionProveedorToleranciaSupport::resolver($empresaId, $ccLinea);

            $flCantDiff = $tipoLinea !== self::TIPO_EXTRA
                && $cantOc > 0
                && ! RecepcionProveedorToleranciaSupport::cantidadDentroTolerancia($cantOc, $cantRec, $tol);
            $precioDistintoOc = $tipoLinea !== self::TIPO_EXTRA
                && $precioOc > 0
                && abs($precioRec - $precioOc) >= 0.0001;
            $precioFueraTolerancia = $precioOc > 0
                && ! RecepcionProveedorToleranciaSupport::precioDentroTolerancia($precioOc, $precioRec, $tol);

            if ($flCantDiff) {
                $flCant = true;
                $resumenes[] = "{$sku}: cant. OC {$cantOc} vs rec. {$cantRec}";
            }
            if ($precioDistintoOc) {
                $flPrecio = true;
                $resumenes[] = "{$sku}: precio OC {$precioOc} vs rec. {$precioRec}";
            }
            if ($precioFueraTolerancia) {
                $coment = trim((string) ($item['comentario_precio'] ?? $item['comentario_diferencia'] ?? ''));
                if ($coment === '') {
                    throw new \RuntimeException(
                        "Línea ".($idx + 1)." ({$sku}): precio fuera de tolerancia. Indique comentario."
                    );
                }
            }

            if (RecepcionProveedorLaboratorioSupport::esArticuloLaboratorio($articulo)) {
                $flLab = true;
            }

            if ($cantRechazada > 0.000001) {
                $flRechazo = true;
                $motivoRechazo = trim((string) ($item['motivorechazo'] ?? ''));
                $lineaRechazo = "{$sku}: acept. {$cantAceptada}, rech. {$cantRechazada}";
                if ($motivoRechazo !== '') {
                    $lineaRechazo .= " — {$motivoRechazo}";
                }
                $resumenesRechazo[] = $lineaRechazo;
            }

            $enriquecidos[] = array_merge($item, [
                'tipo_linea' => $tipoLinea,
                'ordencompra_articulo_id' => $ocArtId > 0 ? $ocArtId : null,
                'ordencompra_articulo_sustituido_id' => $sustituidoId > 0 ? $sustituidoId : null,
                'cantidad_oc' => $cantOc,
                'precio_ordencompra' => $precioOc,
                'fl_cantidad_diferencia' => $flCantDiff,
                'fl_precio_diferencia' => $precioDistintoOc,
                'fl_articulo_distinto' => in_array($tipoLinea, [self::TIPO_EXTRA, self::TIPO_SUSTITUTO], true),
            ]);
        }

        $faltantes = [];
        $flFaltante = false;
        if (! $omitirFaltantes) {
            foreach ($oc->ordencompra_articulos as $ocArt) {
            $rec = (float) ($recibidosPorOcArt[$ocArt->id] ?? 0);
            $ped = (float) $ocArt->cantidad;
            $sku = optional($ocArt->articulos)->sku ?? (string) $ocArt->articulo_id;

            if ($rec <= 0.000001) {
                $flFaltante = true;
                $faltantes[] = "{$sku} (pedido {$ped}, recibido 0)";
            } elseif ($rec + 0.000001 < $ped
                && ! RecepcionProveedorToleranciaSupport::cantidadDentroTolerancia($ped, $rec, $tolEmpresa)) {
                $flFaltante = true;
                $faltantes[] = "{$sku} (pedido {$ped}, recibido {$rec})";
            }
        }

            if ($flFaltante) {
                $resumenes[] = 'Faltantes OC: '.implode('; ', $faltantes);
            }
        }

        return [
            'items' => $enriquecidos,
            'fl_precio_diferencia' => $flPrecio,
            'fl_diferencia_cantidad' => $flCant,
            'fl_articulo_extra' => $flExtra,
            'fl_faltante_oc' => $flFaltante,
            'fl_laboratorio' => $flLab,
            'fl_linea_rechazada' => $flRechazo,
            'resumen_diferencias' => implode("\n", array_unique($resumenes)),
            'resumen_rechazos' => implode("\n", array_unique($resumenesRechazo)),
            'faltantes' => $faltantes,
        ];
    }

    private static function articuloDistintoDeOc(?object $ocArt, int $articuloId): bool
    {
        if (! $ocArt || $articuloId <= 0) {
            return false;
        }

        return (int) $ocArt->articulo_id !== $articuloId;
    }

    /**
     * Diferencia estricta precio recepción vs OC (independiente de tolerancias configuradas).
     */
    public static function recepcionTieneDiferenciaPrecioEstricta(object $recepcion): bool
    {
        $lineas = $recepcion->recepcion_proveedor_articulos ?? collect();
        foreach ($lineas as $linea) {
            if ((string) ($linea->tipo_linea ?? 'OC') === self::TIPO_EXTRA) {
                continue;
            }
            $precioOc = (float) ($linea->precio_ordencompra ?? 0);
            $precioRec = (float) ($linea->precio ?? 0);
            if ($precioOc > 0 && abs($precioRec - $precioOc) >= 0.0001) {
                return true;
            }
        }

        return false;
    }
}
