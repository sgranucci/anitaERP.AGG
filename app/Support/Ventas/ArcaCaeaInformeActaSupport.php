<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\ArcaCaea;
use Carbon\Carbon;

/**
 * Acta en texto de cada presentación CAEA. Un archivo por quincena, se le agrega
 * un bloque por corrida. Es texto plano: el resultado y las observaciones, nada más.
 */
final class ArcaCaeaInformeActaSupport
{
    public static function ruta(int $arcaCaeaId): string
    {
        return storage_path('app/arca/caea/actas/'.$arcaCaeaId.'.txt');
    }

    public static function existe(int $arcaCaeaId): bool
    {
        return $arcaCaeaId > 0 && is_file(self::ruta($arcaCaeaId));
    }

    public static function leer(int $arcaCaeaId): ?string
    {
        if (! self::existe($arcaCaeaId)) {
            return null;
        }

        $texto = file_get_contents(self::ruta($arcaCaeaId));

        return $texto === false ? null : $texto;
    }

    /**
     * @param  array{ok?: bool, mensaje?: string, resumen?: array<string, mixed>, detalle?: array<string, mixed>}  $resultado
     */
    public static function grabar(ArcaCaea $registro, array $resultado, ?Carbon $cuando = null): void
    {
        $ruta = self::ruta((int) $registro->id);
        $dir = dirname($ruta);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio del acta CAEA.');
        }

        $bloque = self::armarTexto($registro, $resultado, $cuando ?? now());
        $previo = is_file($ruta) ? (string) file_get_contents($ruta) : '';
        $contenido = $previo === '' ? $bloque : rtrim($previo)."\n\n".$bloque;

        file_put_contents($ruta, $contenido, LOCK_EX);
    }

    /**
     * @param  array{ok?: bool, mensaje?: string, resumen?: array<string, mixed>, detalle?: array<string, mixed>}  $resultado
     */
    public static function armarTexto(ArcaCaea $registro, array $resultado, ?Carbon $cuando = null): string
    {
        $cuando = ($cuando ?? now())->copy();
        $detalle = is_array($resultado['detalle'] ?? null) ? $resultado['detalle'] : [];
        $resumen = is_array($resultado['resumen'] ?? null) ? $resultado['resumen'] : [];
        $ok = (bool) ($resultado['ok'] ?? false);
        $empresa = trim((string) ($registro->empresa->nombre ?? ''));

        $lineas = [];
        $lineas[] = '=== '.$cuando->format('Y-m-d H:i:s').' ===';
        $lineas[] = 'CAEA '.trim((string) ($registro->nro_caea ?? ''));
        $lineas[] = CaeaQuincenaSupport::etiquetaQuincena((int) $registro->periodo, (int) $registro->orden);
        if ($empresa !== '') {
            $lineas[] = 'Empresa: '.$empresa;
        }
        $lineas[] = 'Resultado: '.($ok ? 'OK' : 'ERROR');
        $mensaje = trim((string) ($resultado['mensaje'] ?? ''));
        if ($mensaje !== '') {
            $lineas[] = 'Mensaje: '.$mensaje;
        }
        $lineas[] = 'Informados: '.(int) ($detalle['informados'] ?? $resumen['informados_ok'] ?? 0);
        $lineas[] = 'Con observaciones: '.(int) ($detalle['con_observaciones'] ?? $resumen['informados_obs'] ?? 0);
        $lineas[] = 'Errores: '.(int) ($detalle['errores_lote'] ?? $resumen['errores'] ?? 0);
        $lineas[] = 'Pendientes: '.(int) ($detalle['pendientes_restantes'] ?? $resumen['pendientes'] ?? 0);
        $lineas[] = 'Total de la quincena: '.(int) ($resumen['total'] ?? 0);

        $ultimos = self::lineasUltimoArca($resumen);
        if ($ultimos !== []) {
            $lineas[] = 'Ultimo en ARCA:';
            foreach ($ultimos as $fila) {
                $lineas[] = '  '.$fila;
            }
        }

        $freno = is_array($detalle['freno'] ?? null) ? $detalle['freno'] : null;
        if ($freno !== null) {
            $lineas[] = 'Freno: '.trim((string) ($freno['etiqueta'] ?? ''));
            $textoFreno = trim((string) ($freno['mensaje'] ?? ''));
            if ($textoFreno !== '') {
                $lineas[] = '  '.$textoFreno;
            }
        }

        $observaciones = is_array($detalle['observaciones'] ?? null) ? $detalle['observaciones'] : [];
        if ($observaciones !== []) {
            $lineas[] = 'Observaciones:';
            foreach ($observaciones as $obs) {
                if (! is_array($obs)) {
                    continue;
                }
                $texto = trim((string) ($obs['texto'] ?? ''));
                if ($texto === '') {
                    continue;
                }
                $lineas[] = '  '.trim((string) ($obs['etiqueta'] ?? 'Comprobante')).' | '.$texto;
            }
        }

        return implode("\n", $lineas)."\n";
    }

    /**
     * @param  array<string, mixed>  $resumen
     * @return list<string>
     */
    private static function lineasUltimoArca(array $resumen): array
    {
        $filas = is_array($resumen['por_tipo_pv'] ?? null) ? $resumen['por_tipo_pv'] : [];
        $out = [];
        foreach ($filas as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $pto = (int) ($fila['pto_vta'] ?? 0);
            $tipo = (int) ($fila['tipo_afip'] ?? 0);
            $ultimo = (int) ($fila['ultimo_arca'] ?? 0);
            if ($pto < 1 || $tipo < 1) {
                continue;
            }
            $letra = trim((string) ($fila['letra'] ?? ''));
            $out[] = trim($letra.' PV '.$pto.' tipo '.$tipo.' = '.$ultimo);
        }

        return $out;
    }
}
