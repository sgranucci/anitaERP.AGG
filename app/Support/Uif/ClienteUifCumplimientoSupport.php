<?php

declare(strict_types=1);

namespace App\Support\Uif;

use Carbon\Carbon;
use Countable;

/**
 * Faltantes de documentación / firmas UIF.
 * Misma regla que public/assets/pages/scripts/uif/cliente_uif/crear.js (verificaAlertaUif).
 */
final class ClienteUifCumplimientoSupport
{
    /**
     * @param  object  $cliente
     * @param  array{tiene_archivos?: bool}  $opciones
     * @return array{
     *     items: list<array{texto: string, tab: string, selector: string}>,
     *     titulo: string,
     *     subtitulo: string,
     *     claseBanner: string
     * }
     */
    public static function evaluar(object $cliente, bool $esSupervisor, array $opciones = []): array
    {
        $items = [];

        if (! self::tieneFotoDocumento($cliente)) {
            $items[] = self::item(
                'Pedí y adjuntá la foto o PDF del DNI.',
                '1',
                '#div-fotodocumento'
            );
        }

        if (! self::tieneArchivosAdjuntos($cliente, $opciones)) {
            $items[] = self::item(
                'Adjuntá documentación de respaldo (declaración jurada, informes, constancias) en Archivos asociados.',
                '5',
                '#div-archivos-uif'
            );
        }

        if (! $esSupervisor) {
            if (self::parseFecha($cliente->fechafirmapep ?? null) === null) {
                $items[] = self::item(
                    'Pedí la firma PEP y cargá la fecha de última firma.',
                    '2',
                    '#div-fechafirmapep'
                );
            }
            if (self::parseFecha($cliente->fechaconfirmapep ?? null) === null) {
                $items[] = self::item(
                    'Falta validación de firma PEP (la completa Enc-UIF).',
                    '2',
                    '#div-fechaconfirmapep'
                );
            }
            if (self::parseFecha($cliente->fechavencimientodni ?? null) === null) {
                $items[] = self::item(
                    'Pedí el DNI vigente; el vencimiento lo carga Enc-UIF.',
                    '2',
                    '#div-fechavencimientodni'
                );
            }
            if (self::parseFecha($cliente->fechavencimientoactividad ?? null) === null) {
                $items[] = self::item(
                    'Pedí constancia de actividad económica (vencimiento lo carga Enc-UIF).',
                    '2',
                    '#div-fechavencimientoactividad'
                );
            }
            if (self::valorTexto($cliente->firmodeclaracionjurada ?? null) !== 'S') {
                $items[] = self::item(
                    'Pedí la declaración jurada firmada de origen de ingresos/fondos.',
                    '2',
                    '#div-firmodeclaracionjurada'
                );
            }

            return [
                'items' => $items,
                'titulo' => 'Pedí al cliente estos documentos y firmas',
                'subtitulo' => 'Adjuntá lo que puedas ahora. Enc-UIF completa fechas de validación, vencimientos e informes.',
                'claseBanner' => 'is-warning',
            ];
        }

        $ahora = Carbon::now();
        $umbral6Meses = $ahora->copy()->subMonths(6);

        $parsedConfPep = self::parseFecha($cliente->fechaconfirmapep ?? null);
        $fechaConfirmaPep = self::valorTexto($cliente->fechaconfirmapep ?? null);
        if ($parsedConfPep === null) {
            $items[] = self::item(
                'PEP: falta fecha de validación de última firma'.($fechaConfirmaPep !== '' ? ' (fecha no válida).' : '.'),
                '2',
                '#div-fechaconfirmapep'
            );
        } elseif ($parsedConfPep->lt($umbral6Meses)) {
            $items[] = self::item(
                'PEP: debe renovar firma (última validación: '.self::formateaFecha($parsedConfPep).').',
                '2',
                '#div-fechaconfirmapep'
            );
        }

        $parsedDni = self::parseFecha($cliente->fechavencimientodni ?? null);
        if ($parsedDni === null) {
            $items[] = self::item(
                'DNI: falta o es inválida la fecha de vencimiento.',
                '2',
                '#div-fechavencimientodni'
            );
        } elseif ($parsedDni->lt($ahora)) {
            $items[] = self::item(
                'DNI: vencido el '.self::formateaFecha($parsedDni).'.',
                '2',
                '#div-fechavencimientodni'
            );
        }

        $parsedVtoAct = self::parseFecha($cliente->fechavencimientoactividad ?? null);
        if ($parsedVtoAct === null) {
            $items[] = self::item(
                'Actividad económica: falta o es inválida la fecha de vencimiento.',
                '2',
                '#div-fechavencimientoactividad'
            );
        } elseif ($parsedVtoAct->lt($umbral6Meses)) {
            $items[] = self::item(
                'Actividad económica: vencimiento próximo o vencido ('.self::formateaFecha($parsedVtoAct).').',
                '2',
                '#div-fechavencimientoactividad'
            );
        }

        if (self::valorTexto($cliente->firmodeclaracionjurada ?? null) !== 'S') {
            $items[] = self::item(
                'Falta declaración jurada firmada de origen de ingresos y/o fondos.',
                '2',
                '#div-firmodeclaracionjurada'
            );
        }

        if (self::valorTexto($cliente->riesgopep ?? null) === 'ALTO') {
            $items[] = self::item(
                'Nivel de riesgo PEP: ALTO.',
                '2',
                '#div-riesgopep'
            );
        }

        $parsedNosis = self::parseFecha($cliente->fechainformenosis ?? null);
        if ($parsedNosis === null) {
            $items[] = self::item(
                'Informe NOSIS: sin fecha o fecha inválida.',
                '2',
                '#div-fechainformenosis'
            );
        } elseif ($parsedNosis->lt($umbral6Meses)) {
            $items[] = self::item(
                'Informe NOSIS: debe renovar (último: '.self::formateaFecha($parsedNosis).').',
                '2',
                '#div-fechainformenosis'
            );
        }

        $parsedInfPep = self::parseFecha($cliente->fechainformepep ?? null);
        if ($parsedInfPep === null) {
            $items[] = self::item(
                'Informe PEP: sin fecha o fecha inválida.',
                '2',
                '#div-fechainformepep'
            );
        } elseif ($parsedInfPep->lt($umbral6Meses)) {
            $items[] = self::item(
                'Informe PEP: debe renovar (último: '.self::formateaFecha($parsedInfPep).').',
                '2',
                '#div-fechainformepep'
            );
        }

        return [
            'items' => $items,
            'titulo' => 'Faltan documentos o firmas de cumplimiento UIF',
            'subtitulo' => 'Completá o renová estos requisitos. Tocá un ítem para ir al campo.',
            'claseBanner' => 'is-danger',
        ];
    }

    /**
     * URLs de la ficha del cliente por solapa (alta de premio, sin tabs locales).
     *
     * @return array<string, string>
     */
    public static function urlsFichaCliente(int $clienteId): array
    {
        if ($clienteId <= 0) {
            return [];
        }

        $urls = [];
        foreach ([1, 2, 5] as $tab) {
            $urls[(string) $tab] = route('edita_cliente_uif', ['id' => $clienteId, 'uif_tab' => $tab]);
        }

        return $urls;
    }

    /**
     * @return array{texto: string, tab: string, selector: string}
     */
    private static function item(string $texto, string $tab, string $selector): array
    {
        return [
            'texto' => $texto,
            'tab' => $tab,
            'selector' => $selector,
        ];
    }

    private static function tieneFotoDocumento(object $cliente): bool
    {
        return self::valorTexto($cliente->fotodocumento ?? null) !== '';
    }

    /**
     * @param  array{tiene_archivos?: bool}  $opciones
     */
    private static function tieneArchivosAdjuntos(object $cliente, array $opciones): bool
    {
        if (array_key_exists('tiene_archivos', $opciones)) {
            return (bool) $opciones['tiene_archivos'];
        }

        $rel = $cliente->cliente_archivos_uif ?? null;
        if ($rel === null) {
            return false;
        }
        if ($rel instanceof Countable) {
            return count($rel) > 0;
        }

        return ! empty($rel);
    }

    private static function parseFecha(mixed $val): ?Carbon
    {
        if ($val instanceof Carbon) {
            return $val->copy()->startOfDay();
        }
        $texto = self::valorTexto($val);
        if ($texto === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $texto, $m) === 1) {
            try {
                $fecha = Carbon::createFromFormat('Y-m-d', $m[1].'-'.$m[2].'-'.$m[3]);
                if ($fecha === false) {
                    return null;
                }

                return $fecha->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $texto, $m) === 1) {
            $iso = $m[3].'-'.str_pad($m[2], 2, '0', STR_PAD_LEFT).'-'.str_pad($m[1], 2, '0', STR_PAD_LEFT);
            try {
                $fecha = Carbon::createFromFormat('Y-m-d', $iso);
                if ($fecha === false) {
                    return null;
                }

                return $fecha->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        }
        try {
            return Carbon::parse($texto)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function formateaFecha(Carbon $fecha): string
    {
        return $fecha->format('d-m-Y');
    }

    private static function valorTexto(mixed $val): string
    {
        if ($val instanceof Carbon) {
            return $val->format('Y-m-d');
        }
        if ($val === null) {
            return '';
        }

        return trim((string) $val);
    }
}
