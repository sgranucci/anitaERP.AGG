<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Totales de kilos e importe declarados en el COT (consulta y selección a enviar).
 */
final class CotRemitoTotalesSupport
{
    /**
     * @param  list<array<string, mixed>>  $remitos
     * @return array{
     *     consulta: array{cantidad: int, kilos: float, importe: float},
     *     pendientes: array{cantidad: int, kilos: float, importe: float},
     *     bloqueados: array{cantidad: int, kilos: float, importe: float},
     *     emitidos: array{cantidad: int, kilos: float, importe: float},
     *     por_reparto: list<array{codigo: string, nombre: string, cantidad: int, kilos: float, importe: float}>
     * }
     */
    public static function resumir(array $remitos): array
    {
        $vacio = static fn (): array => ['cantidad' => 0, 'kilos' => 0.0, 'importe' => 0.0];

        $consulta = $vacio();
        $pendientes = $vacio();
        $bloqueados = $vacio();
        $emitidos = $vacio();
        $porReparto = [];

        foreach ($remitos as $fila) {
            if (! is_array($fila)) {
                continue;
            }

            $kilos = round((float) ($fila['kilos'] ?? 0), 2);
            $importeOk = ! empty($fila['importe_ok']);
            $importe = $importeOk ? round((float) ($fila['importe'] ?? 0), 2) : 0.0;
            $yaEnviado = ! empty($fila['ya_enviado']);

            $consulta['cantidad']++;
            $consulta['kilos'] += $kilos;
            $consulta['importe'] += $importe;

            if ($yaEnviado) {
                $emitidos['cantidad']++;
                $emitidos['kilos'] += $kilos;
                $emitidos['importe'] += $importe;

                continue;
            }

            if (! $importeOk) {
                $bloqueados['cantidad']++;
                $bloqueados['kilos'] += $kilos;

                continue;
            }

            $pendientes['cantidad']++;
            $pendientes['kilos'] += $kilos;
            $pendientes['importe'] += $importe;

            $codigo = trim((string) ($fila['transporte_codigo'] ?? ''));
            $nombre = trim((string) ($fila['transporte_nombre'] ?? ''));
            $clave = $codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '—');
            if (! isset($porReparto[$clave])) {
                $porReparto[$clave] = [
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'cantidad' => 0,
                    'kilos' => 0.0,
                    'importe' => 0.0,
                ];
            }
            $porReparto[$clave]['cantidad']++;
            $porReparto[$clave]['kilos'] += $kilos;
            $porReparto[$clave]['importe'] += $importe;
        }

        $redondear = static function (array $bloque): array {
            $bloque['kilos'] = round((float) $bloque['kilos'], 2);
            $bloque['importe'] = round((float) $bloque['importe'], 2);

            return $bloque;
        };

        return [
            'consulta' => $redondear($consulta),
            'pendientes' => $redondear($pendientes),
            'bloqueados' => $redondear($bloqueados),
            'emitidos' => $redondear($emitidos),
            'por_reparto' => array_values(array_map($redondear, $porReparto)),
        ];
    }
}
