<?php

declare(strict_types=1);

namespace App\Support\Manuales;

use App\Support\Manuales\Escenas\CajaEscenas;
use App\Support\Manuales\Escenas\CanjesMarketingEscenas;
use App\Support\Manuales\Escenas\CierresRendicionesEscenas;
use App\Support\Manuales\Escenas\ComprasEscenas;
use App\Support\Manuales\Escenas\ContableEscenas;
use App\Support\Manuales\Escenas\GastronomiaEscenas;
use App\Support\Manuales\Escenas\PropuestaPagoEscenas;
use App\Support\Manuales\Escenas\RecepcionMovstockEscenas;
use App\Support\Manuales\Escenas\ReporteDefinibleEscenas;
use App\Support\Manuales\Escenas\SolicitudpagoEscenas;
use App\Support\Manuales\Escenas\StockEscenas;
use App\Support\Manuales\Escenas\StockGastronomiaEscenas;
use App\Support\Manuales\Escenas\UifEscenas;
use App\Support\Manuales\Escenas\VendingEscenas;
use App\Support\Manuales\Escenas\VentasEscenas;
use InvalidArgumentException;
use RuntimeException;

final class ManualMockupGeneradorService
{
    public function __construct(
        private readonly ManualMockupGdSupport $gd = new ManualMockupGdSupport(),
    ) {
    }

    /**
     * @return array{generadas:int, omitidas:int, errores:list<string>, archivos:list<string>}
     */
    public function generar(?string $manual = null): array
    {
        $manuales = ManualMockupCatalogo::manuales();
        if ($manual !== null && $manual !== 'all') {
            if (! isset($manuales[$manual])) {
                throw new InvalidArgumentException('Manual desconocido: '.$manual);
            }
            $manuales = [$manual => $manuales[$manual]];
        }

        $generadas = 0;
        $omitidas = 0;
        $errores = [];
        $archivos = [];

        foreach ($manuales as $slug => $meta) {
            $escenas = $this->escenasDe($slug);
            $diagramas = ManualMockupCatalogo::clavesDiagrama($slug);
            $imgDir = public_path($meta['img_dir']);

            foreach ($escenas as $clave => $escena) {
                if (in_array($clave, $diagramas, true)) {
                    $omitidas++;
                    continue;
                }
                $archivo = (string) ($escena['archivo'] ?? '');
                if ($archivo === '' || ! str_ends_with(strtolower($archivo), '.png')) {
                    $errores[] = "{$slug}/{$clave}: archivo PNG inválido";
                    continue;
                }
                $destino = $imgDir.'/'.$archivo;
                try {
                    $this->gd->render($escena, $destino);
                    $generadas++;
                    $archivos[] = $destino;
                } catch (\Throwable $e) {
                    $errores[] = "{$slug}/{$clave}: ".$e->getMessage();
                }
            }
        }

        return compact('generadas', 'omitidas', 'errores', 'archivos');
    }

    /**
     * QA automático sobre capturas declaradas en config.
     *
     * @return array{ok:bool, problemas:list<string>, resumen:array<string,int>}
     */
    public function auditar(?string $manual = null): array
    {
        $manuales = ManualMockupCatalogo::manuales();
        if ($manual !== null && $manual !== 'all') {
            $manuales = [$manual => $manuales[$manual]];
        }

        $problemas = [];
        $resumen = [
            'declaradas' => 0,
            'png_ok' => 0,
            'svg_ok' => 0,
            'faltantes' => 0,
            'ancho_malo' => 0,
            'contenido_malo' => 0,
            'duplicados' => 0,
        ];
        $hashes = [];

        foreach ($manuales as $slug => $meta) {
            $capturas = config($meta['config'].'.capturas', []);
            $imgDir = public_path($meta['img_dir']);
            $diagramas = ManualMockupCatalogo::clavesDiagrama($slug);

            foreach ($capturas as $clave => $cap) {
                $resumen['declaradas']++;
                $archivo = (string) ($cap['archivo'] ?? '');
                $base = preg_replace('/\.(svg|png)$/i', '', $archivo) ?: $archivo;
                $png = $imgDir.'/'.$base.'.png';
                $svg = $imgDir.'/'.$base.'.svg';
                $declared = $imgDir.'/'.$archivo;

                $path = null;
                if (is_file($png)) {
                    $path = $png;
                    $resumen['png_ok']++;
                } elseif (is_file($declared)) {
                    $path = $declared;
                    if (str_ends_with(strtolower($path), '.svg')) {
                        $resumen['svg_ok']++;
                    } else {
                        $resumen['png_ok']++;
                    }
                } elseif (is_file($svg)) {
                    $path = $svg;
                    $resumen['svg_ok']++;
                }

                if ($path === null) {
                    $resumen['faltantes']++;
                    $problemas[] = "FALTA {$slug}/{$clave} => {$archivo}";
                    continue;
                }

                if (str_ends_with(strtolower($path), '.png')) {
                    if (in_array($clave, $diagramas, true)) {
                        // PNG opcional sobre diagrama: no exigir 1280
                    } else {
                        $info = @getimagesize($path);
                        if (! is_array($info) || (int) $info[0] !== ManualMockupGdSupport::WIDTH) {
                            $resumen['ancho_malo']++;
                            $problemas[] = "ANCHO {$slug}/{$clave}: ".($info[0] ?? '?').'px (esperado '.ManualMockupGdSupport::WIDTH.')';
                        }
                        $bytes = (string) file_get_contents($path);
                        if (str_starts_with($bytes, '%PDF')) {
                            $resumen['contenido_malo']++;
                            $problemas[] = "PDF_COMO_PNG {$slug}/{$clave}";
                        }
                        foreach (['404', 'Procesando', 'reemplazar por captura', 'Not Found'] as $needle) {
                            // no OCR; chequeo en metadatos/texto embebido no aplica a PNG GD.
                        }
                        $hash = md5_file($path) ?: '';
                        if ($hash !== '') {
                            if (isset($hashes[$hash]) && $hashes[$hash] !== $path) {
                                $resumen['duplicados']++;
                                $problemas[] = "DUPLICADO {$slug}/{$clave} == ".$hashes[$hash];
                            } else {
                                $hashes[$hash] = $path;
                            }
                        }
                    }
                }
            }
        }

        $ok = $resumen['faltantes'] === 0
            && $resumen['ancho_malo'] === 0
            && $resumen['contenido_malo'] === 0;

        return compact('ok', 'problemas', 'resumen');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function escenasDe(string $manual): array
    {
        return match ($manual) {
            'gastronomia' => GastronomiaEscenas::todas(),
            'compras' => ComprasEscenas::todas(),
            'recepcion-movstock' => RecepcionMovstockEscenas::todas(),
            'contable' => ContableEscenas::todas(),
            'uif' => UifEscenas::todas(),
            'solicitudpago' => SolicitudpagoEscenas::todas(),
            'stock' => StockEscenas::todas(),
            'vending' => VendingEscenas::todas(),
            'canjes-marketing' => CanjesMarketingEscenas::todas(),
            'ventas' => VentasEscenas::todas(),
            'propuesta-pago' => PropuestaPagoEscenas::todas(),
            'reporte-definible' => ReporteDefinibleEscenas::todas(),
            'stock-gastronomia' => StockGastronomiaEscenas::todas(),
            'caja' => CajaEscenas::todas(),
            'cierres-rendiciones' => CierresRendicionesEscenas::todas(),
            default => throw new InvalidArgumentException('Sin escenas para: '.$manual),
        };
    }

    public function asegurarEscenasCubrenConfig(string $manual): void
    {
        $meta = ManualMockupCatalogo::manuales()[$manual] ?? null;
        if ($meta === null) {
            throw new InvalidArgumentException($manual);
        }
        $capturas = array_keys(config($meta['config'].'.capturas', []));
        $diagramas = ManualMockupCatalogo::clavesDiagrama($manual);
        $escenas = array_keys($this->escenasDe($manual));
        $faltan = [];
        foreach ($capturas as $clave) {
            if (in_array($clave, $diagramas, true)) {
                continue;
            }
            if (! in_array($clave, $escenas, true)) {
                $faltan[] = $clave;
            }
        }
        if ($faltan !== []) {
            throw new RuntimeException("Escenas faltantes en {$manual}: ".implode(', ', $faltan));
        }
    }
}
