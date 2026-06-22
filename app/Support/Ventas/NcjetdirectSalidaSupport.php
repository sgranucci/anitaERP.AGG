<?php

namespace App\Support\Ventas;

use InvalidArgumentException;
use Symfony\Component\Process\Process;

/**
 * Ejecución y códigos de salida de /usr/bin/ncjetdirect (JetDirect puerto 9100, sin CUPS).
 *
 * @see /usr/bin/ncjetdirect
 */
final class NcjetdirectSalidaSupport
{
    public const EXIT_ARCHIVO_NO_EXISTE = 1;

    public const EXIT_IMPRESORA_NO_DISPONIBLE = 2;

    public const EXIT_DEMASIADAS_COPIAS = 3;

    public const EXIT_NETCAT_ERROR = 4;

    public static function mensajeExitCode(int $exitCode): string
    {
        return match ($exitCode) {
            self::EXIT_ARCHIVO_NO_EXISTE => 'El archivo a imprimir no existe.',
            self::EXIT_IMPRESORA_NO_DISPONIBLE => 'Impresora no disponible (sin respuesta en puerto 9100).',
            self::EXIT_DEMASIADAS_COPIAS => 'Demasiadas copias solicitadas (máximo 3 en ncjetdirect).',
            self::EXIT_NETCAT_ERROR => 'Error al enviar datos a la impresora (netcat).',
            0 => 'Comando finalizó con éxito (TCP OK; no confirma salida física de papel).',
            default => 'Código de salida del comando de impresión: '.$exitCode,
        };
    }

    public static function esNcjetdirect(string $comando): bool
    {
        return stripos($comando, 'ncjetdirect') !== false;
    }

    /**
     * @return array{ok:bool,exit_code:int,stderr:string,mensaje:string,comando:string,es_ncjetdirect:bool}
     */
    public static function ejecutar(
        string $comandoPlantilla,
        string $rutaArchivo,
        ?int $timeoutSegundos = null,
    ): array {
        if (! str_contains($comandoPlantilla, '%s')) {
            throw new InvalidArgumentException('El comando de salida debe incluir el marcador %s.');
        }

        $comando = sprintf($comandoPlantilla, $rutaArchivo);
        if (trim($comando) === '') {
            throw new InvalidArgumentException('Comando de salida vacío.');
        }

        $process = Process::fromShellCommandline($comando);
        $process->setTimeout($timeoutSegundos ?? (int) config('gastronomia.ticket_comando_timeout_segundos', 30));
        $process->run();

        $exitCode = (int) ($process->getExitCode() ?? 1);
        $stderr = trim($process->getErrorOutput());
        $esNcjetdirect = self::esNcjetdirect($comando);
        $mensaje = $esNcjetdirect
            ? self::mensajeExitCode($exitCode)
            : self::mensajeExitCodeGenerico($exitCode);

        if ($stderr !== '') {
            $mensaje .= ' Stderr: '.$stderr;
        }

        return [
            'ok' => $process->isSuccessful(),
            'exit_code' => $exitCode,
            'stderr' => $stderr,
            'mensaje' => $mensaje,
            'comando' => $comando,
            'es_ncjetdirect' => $esNcjetdirect,
        ];
    }

    /**
     * Campos para Log::info / Log::warning de ticket térmico.
     *
     * @param  array{ok:bool,exit_code:int,stderr:string,mensaje:string,comando:string,es_ncjetdirect:bool}  $resultado
     * @return array<string, int|string>
     */
    public static function contextoLog(array $resultado): array
    {
        $stderr = trim((string) ($resultado['stderr'] ?? ''));
        $exitCode = (int) ($resultado['exit_code'] ?? -1);
        $esNcjetdirect = (bool) ($resultado['es_ncjetdirect'] ?? self::esNcjetdirect((string) ($resultado['comando'] ?? '')));

        $ctx = [
            'comando_exit_code' => $exitCode,
            'comando_mensaje' => $esNcjetdirect
                ? self::mensajeExitCode($exitCode)
                : self::mensajeExitCodeGenerico($exitCode),
        ];

        if ($stderr !== '') {
            $ctx['comando_stderr'] = $stderr;
        }

        if ($esNcjetdirect) {
            $ctx['ncjetdirect_exit_code'] = $exitCode;
            $ctx['ncjetdirect_mensaje'] = self::mensajeExitCode($exitCode);
            if ($stderr !== '') {
                $ctx['ncjetdirect_stderr'] = $stderr;
            }
        }

        return $ctx;
    }

    private static function mensajeExitCodeGenerico(int $exitCode): string
    {
        return $exitCode === 0
            ? 'Comando de impresión finalizó correctamente.'
            : 'Comando de impresión falló con código '.$exitCode.'.';
    }

    /** Aviso informativo POS: ncjetdirect exit 0 no confirma papel físico. */
    public static function mensajeAvisoSinConfirmacionPapel(): string
    {
        return 'Ticket enviado a la impresora. Si no salió el papel, use Reimprimir ticket.';
    }

    /**
     * @param  array{ok:bool,exit_code:int,es_ncjetdirect:bool}  $resultado
     */
    public static function requiereAvisoSinConfirmacionPapel(array $resultado): bool
    {
        return ! empty($resultado['es_ncjetdirect'])
            && ! empty($resultado['ok'])
            && (int) ($resultado['exit_code'] ?? -1) === 0;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $imp
     * @return array<string, mixed>
     */
    public static function anexarAvisoWarnSinConfirmacionPapel(array $resultado, array $imp): array
    {
        if (empty($imp['aviso_sin_confirmacion_papel'])) {
            return $resultado;
        }

        $avisoPapel = self::mensajeAvisoSinConfirmacionPapel();
        $warnPrevio = trim((string) ($resultado['warn'] ?? ''));
        $resultado['warn'] = $warnPrevio !== '' ? $warnPrevio."\n\n".$avisoPapel : $avisoPapel;

        return $resultado;
    }
}
