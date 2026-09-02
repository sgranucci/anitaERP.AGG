<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Destino;
use App\Models\Ventas\Zonavta;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * En El Bierzo la zona de venta carga el maestro destino SENASA (1:1 por zonavta_id).
 */
final class ZonavtaDestinoElBierzoSupport
{
    public static function activo(): bool
    {
        return EntornoEmpresaSupport::esElBierzo() && Destino::tablaLista();
    }

    /**
     * Alta: si no cargó localidad de destino, usa el nombre de la zona.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function entradaAltaDesdeZona(array $input, string $nombreZona): array
    {
        if (! self::activo()) {
            return $input;
        }

        if (trim((string) ($input['dest_localidad'] ?? '')) === '') {
            $input['dest_localidad'] = trim($nombreZona);
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function sincronizar(Zonavta $zonavta, array $input): void
    {
        if (! self::activo()) {
            return;
        }

        $localidad = mb_substr(trim((string) ($input['dest_localidad'] ?? '')), 0, 80);
        $senasa = (int) ($input['dest_codigo_localidad_senasa'] ?? 0);
        $codigo = (int) Zonavta::codigoAnitaDesdeId((int) $zonavta->id);
        if ($codigo <= 0) {
            $codigo = (int) $zonavta->codigo;
        }
        if ($codigo <= 0) {
            return;
        }

        if ($localidad === '' && $senasa <= 0) {
            return;
        }

        $pais = (int) ($input['dest_pais_codigo'] ?? 0);
        $payload = [
            'codigo' => $codigo,
            'zonavta_id' => (int) $zonavta->id,
            'localidad' => $localidad,
            'provincia' => mb_substr(trim((string) ($input['dest_provincia'] ?? '')), 0, 80),
            'pais_codigo' => $pais > 0 ? $pais : null,
            'patagonico' => ! empty($input['dest_patagonico']),
            'codigo_localidad_senasa' => $senasa > 0 ? $senasa : null,
        ];

        $porZona = Destino::query()->where('zonavta_id', $zonavta->id)->first();
        $porCodigo = Destino::query()->where('codigo', $codigo)->first();

        if ($porZona) {
            $porZona->update($payload);

            return;
        }
        if ($porCodigo) {
            $porCodigo->update($payload);

            return;
        }

        Destino::create($payload);
    }
}
