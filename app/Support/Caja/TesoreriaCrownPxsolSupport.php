<?php

namespace App\Support\Caja;

use App\Models\Caja\CajeroCrownExterno;
use App\Models\Caja\TesoreriaCrownComprobante;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Lectura PXSol de la rendición (procesa_lectura_pxsol en a-transfefe.c).
 * Anita pide cuatro números entre la primera habilitación y la rendición:
 * facturado, crédito, IVA de facturas e IVA de notas de crédito.
 */
final class TesoreriaCrownPxsolSupport
{
    /**
     * @return list<string>
     */
    public static function codigos(TesoreriaCrownComprobante $comprobante): array
    {
        $cajeroId = (int) ($comprobante->asignacion->cajero_crown_id ?? 0);
        if ($cajeroId <= 0) {
            return [];
        }

        return CajeroCrownExterno::query()
            ->where('cajero_crown_id', $cajeroId)
            ->where('sistema', CajeroCrownSistemaExternoSupport::PXSOL)
            ->orderBy('id')
            ->pluck('codigo_externo')
            ->map(fn ($codigo) => trim((string) $codigo))
            ->filter(fn (string $codigo) => $codigo !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{facturado: float, credito: float, iva_facturas: float, iva_nc: float, desde: string, hasta: string}
     */
    public static function leer(TesoreriaCrownComprobante $comprobante): array
    {
        if ($comprobante->tipo !== TesoreriaCrownComprobanteSupport::RENDICION
            || ! TesoreriaCrownRendicionCampos::esCajaGastroHotel($comprobante->asignacion->tipo_tarea ?? '')) {
            throw new RuntimeException('PXSol se consulta en la rendición de gastronomía u hotel.');
        }
        $codigos = self::codigos($comprobante);
        if ($codigos === []) {
            throw new RuntimeException('Este cajero no tiene código PXSol.');
        }
        $comando = trim((string) config('tesoreria_crown.pxsol_comando'));
        if ($comando === '') {
            throw new RuntimeException('PXSol no está configurado.');
        }
        [$desde, $hasta] = self::ventana($comprobante);
        $total = [
            'facturado' => 0.0,
            'credito' => 0.0,
            'iva_facturas' => 0.0,
            'iva_nc' => 0.0,
        ];
        foreach ($codigos as $codigo) {
            $fila = self::consultar($comando, $codigo, $desde, $hasta);
            foreach ($total as $clave => $importe) {
                $total[$clave] = round($importe + $fila[$clave], 2);
            }
        }
        $total['desde'] = $desde;
        $total['hasta'] = $hasta;

        return $total;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function ventana(TesoreriaCrownComprobante $comprobante): array
    {
        $habilitacion = TesoreriaCrownComprobante::query()
            ->where('asignacion_id', $comprobante->asignacion_id)
            ->where('tipo', TesoreriaCrownComprobanteSupport::HABILITACION)
            ->orderBy('id')
            ->first();
        if ($habilitacion === null || $habilitacion->created_at === null) {
            throw new RuntimeException('Esta caja no tiene habilitación. PXSol arranca en esa hora.');
        }
        $dia = $comprobante->jornada->fecha->format('Y-m-d');
        $desde = $habilitacion->created_at->toDateString() === $dia
            ? $habilitacion->created_at->format('Y-m-d H:i:s')
            : $dia.' '.$habilitacion->created_at->format('H:i:s');
        $hasta = now()->toDateString() === $dia
            ? now()->format('Y-m-d H:i:s')
            : $dia.' 23:59:59';
        if ($hasta < $desde) {
            $hasta = $desde;
        }

        return [$desde, $hasta];
    }

    /**
     * @return array{facturado: float, credito: float, iva_facturas: float, iva_nc: float}
     */
    public static function parsear(string $salida): array
    {
        $linea = trim((string) strtok($salida, "\n"));
        $partes = explode('=', $linea);
        if (count($partes) < 4 || ! is_numeric($partes[0]) || ! is_numeric($partes[1]) || ! is_numeric($partes[2]) || ! is_numeric($partes[3])) {
            throw new RuntimeException('PXSol no devolvió los cuatro importes.');
        }

        return [
            'facturado' => round((float) $partes[0], 2),
            'credito' => round((float) $partes[1], 2),
            'iva_facturas' => round((float) $partes[2], 2),
            'iva_nc' => round((float) $partes[3], 2),
        ];
    }

    /**
     * @return array{facturado: float, credito: float, iva_facturas: float, iva_nc: float}
     */
    private static function consultar(string $comando, string $codigo, string $desde, string $hasta): array
    {
        $proceso = new Process([$comando, $codigo, $desde, $hasta]);
        $proceso->setTimeout(max(5, (int) config('tesoreria_crown.pxsol_timeout')));
        try {
            $proceso->run();
        } catch (ProcessTimedOutException) {
            throw new RuntimeException('PXSol no respondió a tiempo.');
        }
        if (! $proceso->isSuccessful()) {
            throw new RuntimeException('PXSol no respondió.');
        }

        return self::parsear($proceso->getOutput());
    }
}
