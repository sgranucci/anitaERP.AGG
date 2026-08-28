<?php

namespace App\Support\Ventas;

use App\Models\Ventas\CotRemitoEnvio;
use App\Models\Ventas\CotSesionEnvio;

/**
 * Datos de pantalla / PDF de la constancia COT (no el archivo ARBA).
 */
final class CotConstanciaSupport
{
    public static function etiquetaRemito(?string $tipo, ?string $letra, mixed $sucursal, mixed $numero): string
    {
        $tipoNorm = trim((string) $tipo) ?: 'REM';
        $letraNorm = trim((string) $letra) ?: 'R';
        $suc = (int) $sucursal;
        $nro = (int) $numero;
        $sucTxt = $suc > 0 ? (string) $suc : '';

        return trim($tipoNorm.' '.$letraNorm.' '.$sucTxt.' '.$nro);
    }

    /**
     * @param  array<string, mixed>  $dest
     */
    public static function domicilioTexto(array $dest): string
    {
        $calle = trim((string) ($dest['calle'] ?? ''));
        $numero = trim((string) ($dest['numero'] ?? ''));
        $cp = trim((string) ($dest['codigo_postal'] ?? ''));
        $localidad = trim((string) ($dest['localidad'] ?? ''));
        $linea = trim($calle.($numero !== '' && strtoupper($numero) !== 'S/N' ? ' '.$numero : ''));
        $pie = trim(($cp !== '' ? 'CP '.$cp.' ' : '').$localidad);

        return trim($linea.($pie !== '' ? ' — '.$pie : ''));
    }

    /**
     * @param  list<array<string, mixed>>|null  $repartos
     * @return array{codigo:string,nombre:string,patente:string,cuit_chofer:string}
     */
    public static function repartoDeSesion(?array $repartos, ?int $transporteId): array
    {
        $vacio = [
            'codigo' => '',
            'nombre' => '',
            'patente' => '',
            'cuit_chofer' => '',
        ];
        if (! is_array($repartos) || $repartos === []) {
            return $vacio;
        }

        $elegido = null;
        foreach ($repartos as $reparto) {
            if (! is_array($reparto)) {
                continue;
            }
            if ($transporteId && (int) ($reparto['transporte_id'] ?? 0) === $transporteId) {
                $elegido = $reparto;
                break;
            }
            $elegido ??= $reparto;
        }
        if ($elegido === null) {
            return $vacio;
        }

        return [
            'codigo' => trim((string) ($elegido['codigo'] ?? '')),
            'nombre' => trim((string) ($elegido['nombre'] ?? '')),
            'patente' => strtoupper(trim((string) ($elegido['patente'] ?? ''))),
            'cuit_chofer' => trim((string) ($elegido['cuit_chofer'] ?? '')),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CotRemitoEnvio>|list<CotRemitoEnvio>  $remitos
     * @return list<CotRemitoEnvio>
     */
    public static function remitosImprimibles($remitos, ?int $remitoEnvioId = null): array
    {
        $filas = [];
        foreach ($remitos as $remito) {
            if (! $remito instanceof CotRemitoEnvio || ! $remito->fueEmitido()) {
                continue;
            }
            if ($remitoEnvioId && (int) $remito->id !== $remitoEnvioId) {
                continue;
            }
            $filas[] = $remito;
        }

        return $filas;
    }

    public static function tituloSesion(CotSesionEnvio $sesion, int $cantidad): string
    {
        $reparto = $sesion->etiquetaRepartos();

        return 'COT sesión #'.$sesion->id
            .($reparto !== '' ? ' — '.$reparto : '')
            .' ('.$cantidad.' constancia'.($cantidad === 1 ? '' : 's').')';
    }
}
