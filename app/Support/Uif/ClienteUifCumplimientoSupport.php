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
            $ahora = Carbon::now();
            $umbral6Meses = $ahora->copy()->subMonths(6);
            $tieneVencimiento = false;

            $parsedFirmaPep = self::parseFecha($cliente->fechafirmapep ?? null);
            if ($parsedFirmaPep === null) {
                $items[] = self::item(
                    'Pedí la firma PEP y cargá la fecha de última firma.',
                    '2',
                    '#div-fechafirmapep'
                );
            }

            $parsedConfPep = self::parseFecha($cliente->fechaconfirmapep ?? null);
            if ($parsedConfPep === null) {
                $items[] = self::item(
                    'Falta validación de firma PEP (la completa Enc-UIF).',
                    '2',
                    '#div-fechaconfirmapep'
                );
            } elseif ($parsedConfPep->lt($umbral6Meses)) {
                $items[] = self::item(
                    'PEP: debe renovar firma (última validación: '.self::formateaFecha($parsedConfPep).').',
                    '2',
                    '#div-fechaconfirmapep'
                );
                $tieneVencimiento = true;
            }

            $parsedDni = self::parseFecha($cliente->fechavencimientodni ?? null);
            if ($parsedDni === null) {
                $items[] = self::item(
                    'Pedí el DNI vigente; el vencimiento lo carga Enc-UIF.',
                    '2',
                    '#div-fechavencimientodni'
                );
            } elseif ($parsedDni->lt($ahora)) {
                $items[] = self::item(
                    'DNI: vencido el '.self::formateaFecha($parsedDni).'.',
                    '2',
                    '#div-fechavencimientodni'
                );
                $tieneVencimiento = true;
            }

            $parsedVtoAct = self::parseFecha($cliente->fechavencimientoactividad ?? null);
            if ($parsedVtoAct === null) {
                $items[] = self::item(
                    'Pedí constancia de actividad económica (vencimiento lo carga Enc-UIF).',
                    '2',
                    '#div-fechavencimientoactividad'
                );
            } elseif ($parsedVtoAct->lt($umbral6Meses)) {
                $items[] = self::item(
                    'Actividad económica: vencimiento próximo o vencido ('.self::formateaFecha($parsedVtoAct).').',
                    '2',
                    '#div-fechavencimientoactividad'
                );
                $tieneVencimiento = true;
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
                'titulo' => $tieneVencimiento
                    ? 'Hay documentos o firmas vencidos / a renovar'
                    : 'Pedí al cliente estos documentos y firmas',
                'subtitulo' => $tieneVencimiento
                    ? 'Pedí al cliente que vuelva a firmar o presente documentación vigente. Enc-UIF completa validaciones e informes.'
                    : 'Adjuntá lo que puedas ahora. Enc-UIF completa fechas de validación, vencimientos e informes.',
                'claseBanner' => $tieneVencimiento ? 'is-danger' : 'is-warning',
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
     * Cuadro de firmas y vencimientos para Enc-UIF.
     * Misma regla que evaluar() con perfil supervisor, más foto y archivos.
     *
     * @param  array{tiene_archivos?: bool, cantidad_archivos?: int}  $opciones
     * @return array{
     *     filas: list<array{requisito: string, cargado: string, aviso: string, alerta: bool}>,
     *     hay_aviso: bool,
     *     solo_riesgo_alto: bool,
     *     resumen: string
     * }
     */
    public static function cuadroEncUif(object $cliente, array $opciones = []): array
    {
        $ahora = Carbon::now()->startOfDay();
        $umbral6Meses = $ahora->copy()->subMonths(6);
        $filas = [];

        $parsedFirma = self::parseFecha($cliente->fechafirmapep ?? null);
        $parsedConf = self::parseFecha($cliente->fechaconfirmapep ?? null);
        $firmaVigente = $parsedConf !== null && ! $parsedConf->lt($umbral6Meses);
        if ($parsedFirma === null) {
            $filas[] = self::fila(
                'Firma PEP',
                'Sin fecha',
                'Sí. Falta la fecha de última firma.',
                true
            );
        } elseif ($firmaVigente) {
            $filas[] = self::fila(
                'Firma PEP',
                self::formateaFechaSlash($parsedFirma),
                'No. Se renueva el '.self::formateaFechaSlash(self::proximaRenovacion6Meses($parsedConf)).'.',
                false
            );
        } else {
            $filas[] = self::fila(
                'Firma PEP',
                self::formateaFechaSlash($parsedFirma),
                'No. La fecha de firma está cargada.',
                false
            );
        }

        if ($parsedConf === null) {
            $filas[] = self::fila(
                'Validación de firma PEP',
                'Sin fecha',
                'Sí. Falta la validación de la última firma.',
                true
            );
        } elseif ($parsedConf->lt($umbral6Meses)) {
            $filas[] = self::fila(
                'Validación de firma PEP',
                self::formateaFechaSlash($parsedConf),
                'Sí. Debe renovar firma (última validación: '.self::formateaFechaSlash($parsedConf).').',
                true
            );
        } else {
            $filas[] = self::fila(
                'Validación de firma PEP',
                self::formateaFechaSlash($parsedConf),
                'No.',
                false
            );
        }

        if (self::valorTexto($cliente->firmodeclaracionjurada ?? null) === 'S') {
            $filas[] = self::fila('Declaración jurada', 'Firmada', 'No.', false);
        } else {
            $filas[] = self::fila(
                'Declaración jurada',
                'No firmada',
                'Sí. Falta la declaración jurada firmada de origen de ingresos y/o fondos.',
                true
            );
        }

        $parsedDni = self::parseFecha($cliente->fechavencimientodni ?? null);
        if ($parsedDni === null) {
            $filas[] = self::fila(
                'DNI',
                'Sin fecha',
                'Sí. Falta o es inválida la fecha de vencimiento.',
                true
            );
        } elseif ($parsedDni->lt($ahora)) {
            $filas[] = self::fila(
                'DNI',
                'Venció el '.self::formateaFechaSlash($parsedDni),
                'Sí. DNI vencido el '.self::formateaFechaSlash($parsedDni).'.',
                true
            );
        } else {
            $filas[] = self::fila(
                'DNI',
                'Vence el '.self::formateaFechaSlash($parsedDni),
                'No.',
                false
            );
        }

        $filas[] = self::filaRenovacion6Meses(
            'Actividad económica',
            self::parseFecha($cliente->fechavencimientoactividad ?? null),
            $umbral6Meses,
            'Sí. Falta o es inválida la fecha de vencimiento.',
            'Sí. Vencimiento próximo o vencido (%s).'
        );

        $filas[] = self::filaRenovacion6Meses(
            'Informe NOSIS',
            self::parseFecha($cliente->fechainformenosis ?? null),
            $umbral6Meses,
            'Sí. Falta la fecha del informe o no es válida.',
            'Sí. Debe renovar (último: %s).'
        );

        $filas[] = self::filaRenovacion6Meses(
            'Informe PEP',
            self::parseFecha($cliente->fechainformepep ?? null),
            $umbral6Meses,
            'Sí. Falta la fecha del informe o no es válida.',
            'Sí. Debe renovar (último: %s).'
        );

        $riesgo = self::valorTexto($cliente->riesgopep ?? null);
        if ($riesgo === '') {
            $riesgo = 'Sin cargar';
        }
        if ($riesgo === 'ALTO') {
            $filas[] = self::fila(
                'Riesgo PEP',
                $riesgo,
                'Sí. El cartel de riesgo se muestra a Enc-UIF.',
                true
            );
        } else {
            $filas[] = self::fila(
                'Riesgo PEP',
                $riesgo,
                'No. El cartel solo sale si es ALTO.',
                false
            );
        }

        if (self::tieneFotoDocumento($cliente)) {
            $filas[] = self::fila('Foto del DNI', 'Sí', 'No.', false);
        } else {
            $filas[] = self::fila(
                'Foto del DNI',
                'No',
                'Sí. Falta la foto o el PDF del DNI.',
                true
            );
        }

        $cantArchivos = self::cantidadArchivos($cliente, $opciones);
        if ($cantArchivos > 0) {
            $filas[] = self::fila(
                'Archivos asociados',
                $cantArchivos === 1 ? '1 archivo' : $cantArchivos.' archivos',
                'No.',
                false
            );
        } else {
            $filas[] = self::fila(
                'Archivos asociados',
                'Ninguno',
                'Sí. Falta documentación de respaldo.',
                true
            );
        }

        $alertas = array_values(array_filter($filas, static fn (array $fila): bool => $fila['alerta']));
        $soloRiesgo = count($alertas) === 1 && $alertas[0]['requisito'] === 'Riesgo PEP';
        if ($alertas === []) {
            $resumen = 'No hay pedido de documentación ni de renovación. En la ficha no tiene que aparecer ningún cartel.';
        } elseif ($soloRiesgo) {
            $resumen = 'No hay firmas ni documentos vencidos. El único cartel de la ficha es el riesgo PEP ALTO.';
        } else {
            $resumen = 'Hay pedidos de documentación o renovación. Esos carteles tienen que verse en la ficha de Enc-UIF.';
        }

        return [
            'filas' => $filas,
            'hay_aviso' => $alertas !== [],
            'solo_riesgo_alto' => $soloRiesgo,
            'resumen' => $resumen,
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

    private static function formateaFechaSlash(Carbon $fecha): string
    {
        return $fecha->format('d/m/Y');
    }

    /**
     * @return array{requisito: string, cargado: string, aviso: string, alerta: bool}
     */
    private static function fila(string $requisito, string $cargado, string $aviso, bool $alerta): array
    {
        return [
            'requisito' => $requisito,
            'cargado' => $cargado,
            'aviso' => $aviso,
            'alerta' => $alerta,
        ];
    }

    /**
     * Fecha que se considera vencida cuando es anterior a hoy menos 6 meses.
     *
     * @return array{requisito: string, cargado: string, aviso: string, alerta: bool}
     */
    private static function filaRenovacion6Meses(
        string $requisito,
        ?Carbon $fecha,
        Carbon $umbral6Meses,
        string $avisoFalta,
        string $avisoVencido
    ): array {
        if ($fecha === null) {
            return self::fila($requisito, 'Sin fecha', $avisoFalta, true);
        }
        $texto = self::formateaFechaSlash($fecha);
        if ($fecha->lt($umbral6Meses)) {
            return self::fila($requisito, $texto, sprintf($avisoVencido, $texto), true);
        }

        return self::fila(
            $requisito,
            $texto,
            'No. Se renueva el '.self::formateaFechaSlash(self::proximaRenovacion6Meses($fecha)).'.',
            false
        );
    }

    /**
     * Primer día en que la fecha queda a más de 6 meses (misma comparación que el cartel).
     */
    private static function proximaRenovacion6Meses(Carbon $fecha): Carbon
    {
        $dia = $fecha->copy()->addDay()->startOfDay();
        $limite = $fecha->copy()->addMonths(8)->startOfDay();
        while ($dia->lte($limite)) {
            if ($fecha->lt($dia->copy()->subMonths(6))) {
                return $dia;
            }
            $dia->addDay();
        }

        return $dia;
    }

    /**
     * @param  array{tiene_archivos?: bool, cantidad_archivos?: int}  $opciones
     */
    private static function cantidadArchivos(object $cliente, array $opciones): int
    {
        if (array_key_exists('cantidad_archivos', $opciones)) {
            return max(0, (int) $opciones['cantidad_archivos']);
        }
        if (array_key_exists('tiene_archivos', $opciones)) {
            return $opciones['tiene_archivos'] ? 1 : 0;
        }

        $rel = $cliente->cliente_archivos_uif ?? null;
        if ($rel instanceof Countable) {
            return count($rel);
        }

        return empty($rel) ? 0 : 1;
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
