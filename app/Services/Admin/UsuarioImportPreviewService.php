<?php

namespace App\Services\Admin;

use App\Imports\Admin\UsuarioImportLecturaCruda;
use App\Support\Admin\UsuarioImportColumnasSupport;
use App\Support\Admin\UsuarioImportIdentidadSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class UsuarioImportPreviewService
{
    private const MAX_FILAS_MUESTRA = 25;

    /**
     * @return array<string, mixed>
     */
    public function previsualizar(
        UploadedFile $archivo,
        ?string $colUsuario,
        ?string $colNombre,
        ?string $colEmail,
        ?int $filaEncabezadoManual,
        ?int $hojaIndice1Based = null,
        ?string $dominioEmail = null,
        ?bool $generarLoginSiFalta = null,
        ?bool $generarEmailSiFalta = null
    ): array {
        $colUsuario = $this->nombreColumna($colUsuario, UsuarioImportColumnasSupport::COL_USUARIO_DEFAULT);
        $colNombre = $this->nombreColumna($colNombre, UsuarioImportColumnasSupport::COL_NOMBRE_DEFAULT);
        $colEmail = $this->nombreColumna($colEmail, UsuarioImportColumnasSupport::COL_EMAIL_DEFAULT);
        $dominioEmail = UsuarioImportIdentidadSupport::normalizarDominio(
            $dominioEmail ?? UsuarioImportIdentidadSupport::dominioEmailDefault()
        );
        $generarLoginSiFalta = $generarLoginSiFalta ?? (bool) config('usuario_import.generar_login_si_falta', true);
        $generarEmailSiFalta = $generarEmailSiFalta ?? (bool) config('usuario_import.generar_email_si_falta', true);

        $hojas = UsuarioImportColumnasSupport::hojasParaSelector($archivo);
        $hojaIndice0 = UsuarioImportColumnasSupport::indiceHojaDesdeRequest($hojaIndice1Based, count($hojas));
        $hojaSeleccionada = $hojas[$hojaIndice0] ?? $hojas[0];

        $hoja = Excel::toArray(new UsuarioImportLecturaCruda(), $archivo)[$hojaIndice0] ?? [];
        if ($hoja === []) {
            return $this->anexarMetaHojas([
                'ok' => false,
                'mensaje' => 'La hoja seleccionada ('.($hojaSeleccionada['nombre'] ?? ('#'.$hojaIndice0)).') no tiene filas legibles.',
            ], $hojas, $hojaSeleccionada);
        }

        $filaEncabezado = UsuarioImportColumnasSupport::detectarFilaEncabezado(
            $archivo,
            $filaEncabezadoManual,
            $hojaIndice0
        );
        $indiceEncabezado = $filaEncabezado - 1;
        $encabezados = $hoja[$indiceEncabezado] ?? [];

        if (! is_array($encabezados) || ! UsuarioImportColumnasSupport::pareceFilaEncabezado($encabezados)) {
            return $this->anexarMetaHojas([
                'ok' => false,
                'mensaje' => 'No se detectó fila de encabezados en la fila '.$filaEncabezado
                    .' de «'.($hojaSeleccionada['nombre'] ?? '').'». Revise el archivo o indique la fila manualmente.',
                'fila_encabezado' => $filaEncabezado,
            ], $hojas, $hojaSeleccionada);
        }

        $colUsuarioInfo = UsuarioImportColumnasSupport::resolverColumna(
            $encabezados,
            $colUsuario,
            UsuarioImportColumnasSupport::COL_USUARIO_DEFAULT,
            UsuarioImportColumnasSupport::ALIAS_ENCABEZADO_USUARIO
        );
        $colNombreInfo = UsuarioImportColumnasSupport::resolverColumna(
            $encabezados,
            $colNombre,
            UsuarioImportColumnasSupport::COL_NOMBRE_DEFAULT,
            UsuarioImportColumnasSupport::ALIAS_ENCABEZADO_NOMBRE
        );
        $colEmailInfo = UsuarioImportColumnasSupport::resolverColumna(
            $encabezados,
            $colEmail,
            UsuarioImportColumnasSupport::COL_EMAIL_DEFAULT,
            UsuarioImportColumnasSupport::ALIAS_ENCABEZADO_EMAIL
        );

        $advertencias = [];
        if ($colNombreInfo === null) {
            $advertencias[] = 'No se encontró la columna de nombre («'.$colNombre.'»), que es obligatoria.';
        }
        if ($colUsuarioInfo === null) {
            $advertencias[] = $generarLoginSiFalta
                ? 'Sin columna de usuario: se generará login (inicial + apellido) desde el nombre.'
                : 'No se encontró la columna de usuario («'.$colUsuario.'») y la generación automática está desactivada.';
        }
        if ($colEmailInfo === null) {
            $advertencias[] = $generarEmailSiFalta
                ? 'Sin columna de email: se generará como login'.$dominioEmail.'.'
                : 'No se encontró la columna de email («'.$colEmail.'») y la generación automática está desactivada.';
        }

        $columnasOk = $colNombreInfo !== null;

        $resumen = [
            'total_filas_datos' => 0,
            'importables' => 0,
            'omitidas' => 0,
            'usuarios_existentes' => 0,
            'emails_duplicados_archivo' => 0,
            'logins_duplicados_archivo' => 0,
            'logins_generados' => 0,
            'emails_generados' => 0,
        ];
        $filasMuestra = [];
        $loginsVistos = [];
        $emailsVistos = [];

        if ($columnasOk) {
            for ($i = $indiceEncabezado + 1; $i < count($hoja); $i++) {
                $fila = $hoja[$i] ?? [];
                if (! is_array($fila)) {
                    continue;
                }

                $loginExcel = $colUsuarioInfo
                    ? UsuarioImportColumnasSupport::normalizarTextoCelda(
                        UsuarioImportColumnasSupport::valorCeldaFila($fila, $colUsuarioInfo)
                    )
                    : '';
                $nombre = UsuarioImportColumnasSupport::normalizarTextoCelda(
                    UsuarioImportColumnasSupport::valorCeldaFila($fila, $colNombreInfo)
                );
                $emailExcel = $colEmailInfo
                    ? UsuarioImportColumnasSupport::normalizarEmail(
                        UsuarioImportColumnasSupport::valorCeldaFila($fila, $colEmailInfo)
                    )
                    : '';

                if ($loginExcel === '' && $nombre === '' && $emailExcel === '') {
                    continue;
                }

                $resumen['total_filas_datos']++;
                $filaExcel = $i + 1;

                $identidad = UsuarioImportIdentidadSupport::resolverIdentidadFila(
                    $nombre,
                    $loginExcel,
                    $emailExcel,
                    $dominioEmail,
                    $generarLoginSiFalta,
                    $generarEmailSiFalta,
                    $loginsVistos,
                    $emailsVistos
                );

                $login = $identidad['login'];
                $email = $identidad['email'];
                $estado = 'ok';
                $mensaje = 'Listo para crear';

                if ($identidad['error'] !== null) {
                    $estado = 'omitida';
                    $mensaje = $identidad['error'];
                    if (str_contains($mensaje, 'Ya existe')) {
                        $resumen['usuarios_existentes']++;
                    } elseif (str_contains($mensaje, 'Login duplicado')) {
                        $resumen['logins_duplicados_archivo']++;
                    } elseif (str_contains($mensaje, 'Email duplicado')) {
                        $resumen['emails_duplicados_archivo']++;
                    }
                } else {
                    $emailValidator = Validator::make(['email' => $email], ['email' => 'email']);
                    if ($emailValidator->fails()) {
                        $estado = 'omitida';
                        $mensaje = 'Email inválido';
                    } else {
                        $notas = [];
                        if ($identidad['login_generado']) {
                            $resumen['logins_generados']++;
                            $notas[] = 'login auto';
                        }
                        if ($identidad['email_generado']) {
                            $resumen['emails_generados']++;
                            $notas[] = 'email auto';
                        }
                        if ($notas !== []) {
                            $mensaje = 'Listo para crear ('.implode(', ', $notas).')';
                        }
                    }
                }

                if ($estado === 'ok') {
                    $resumen['importables']++;
                } else {
                    $resumen['omitidas']++;
                }

                if (count($filasMuestra) < self::MAX_FILAS_MUESTRA) {
                    $filasMuestra[] = [
                        'fila_excel' => $filaExcel,
                        'usuario' => $login,
                        'nombre' => $nombre,
                        'email' => $email,
                        'login_generado' => (bool) $identidad['login_generado'],
                        'email_generado' => (bool) $identidad['email_generado'],
                        'estado' => $estado,
                        'mensaje' => $mensaje,
                    ];
                }
            }
        }

        $ok = $columnasOk && $resumen['importables'] > 0;

        return $this->anexarMetaHojas([
            'ok' => $ok,
            'fila_encabezado' => $filaEncabezado,
            'fila_encabezado_automatica' => $filaEncabezadoManual === null || $filaEncabezadoManual < 1,
            'dominio_email' => $dominioEmail,
            'generar_login_si_falta' => $generarLoginSiFalta,
            'generar_email_si_falta' => $generarEmailSiFalta,
            'columnas' => [
                'usuario' => $this->metaColumna($colUsuario, $colUsuarioInfo, false),
                'nombre' => $this->metaColumna($colNombre, $colNombreInfo, true),
                'email' => $this->metaColumna($colEmail, $colEmailInfo, false),
            ],
            'resumen' => $resumen,
            'filas' => $filasMuestra,
            'hay_mas_filas' => $resumen['total_filas_datos'] > count($filasMuestra),
            'advertencias' => $advertencias,
            'mensaje' => $ok
                ? null
                : ($columnasOk
                    ? 'No hay filas importables con la configuración actual.'
                    : 'Configure la columna de nombre (obligatoria) antes de importar.'),
        ], $hojas, $hojaSeleccionada);
    }

    private function nombreColumna(?string $valor, string $default): string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : $default;
    }

    /**
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $info
     * @return array{configurado: string, encontrada: bool, requerida: bool, titulo: ?string}
     */
    private function metaColumna(string $configurado, ?array $info, bool $requerida): array
    {
        return [
            'configurado' => $configurado,
            'encontrada' => $info !== null,
            'requerida' => $requerida,
            'titulo' => $info['titulo'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  list<array{indice: int, nombre: string}>  $hojas
     * @param  array{indice: int, nombre: string}  $hojaSeleccionada
     * @return array<string, mixed>
     */
    private function anexarMetaHojas(array $preview, array $hojas, array $hojaSeleccionada): array
    {
        $preview['hojas'] = $hojas;
        $preview['multiple_hojas'] = count($hojas) > 1;
        $preview['hoja_seleccionada'] = (int) $hojaSeleccionada['indice'];
        $preview['hoja_nombre'] = (string) $hojaSeleccionada['nombre'];

        if ($preview['multiple_hojas']) {
            $preview['advertencias'] = array_values(array_merge(
                [
                    'El archivo tiene '.count($hojas).' hojas. Elija cuál importar (por defecto hoja 1).',
                ],
                $preview['advertencias'] ?? []
            ));
        }

        return $preview;
    }
}
