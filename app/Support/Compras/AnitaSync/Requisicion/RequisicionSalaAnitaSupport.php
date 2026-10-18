<?php

namespace App\Support\Compras\AnitaSync\Requisicion;

use App\Models\Configuracion\Sala;
use App\Support\Compras\ProveedorSalaAnitaSupport;

/**
 * Casino Anita ↔ sala ERP en requisiciones Crown.
 *
 * Fuente: /usr2/cli/crown/compras/casino.h → CASINO_trae_casino:
 * si no hay sucursal, toma los 2 primeros dígitos del nro de requisición (ref_nro).
 */
final class RequisicionSalaAnitaSupport
{
    /**
     * Prefijo 2 dígitos del número de requisición → código casino Anita.
     *
     * @var array<int, int>
     */
    public const REF_NRO_A_CASINO = [
        11 => 1, // Cipolletti
        12 => 2, // Roca
        17 => 3, // Choele
        13 => 4, // Viedma
        14 => 5, // El Faro
        15 => 6, // Las Grutas
        18 => 7, // Catriel
        20 => 8, // Villa Regina
        19 => 0, // Adm.Central
    ];

    /**
     * Casino Anita → código sala ERP (extiende proveedor con VRE).
     *
     * @var array<int, string>
     */
    public const CASINO_A_CODIGO_SALA = [
        1 => 'CIP',
        2 => 'GRO',
        3 => 'CHO',
        4 => 'VIE',
        5 => 'EFA',
        6 => 'LGR',
        7 => 'CAT',
        8 => 'VRE',
        // 0 Adm.Central: sin sala ERP
    ];

    public static function casinoAnitaDesdeNumeroRequisicion(int $numerorequisicion): ?int
    {
        if ($numerorequisicion <= 0) {
            return null;
        }
        if ($numerorequisicion < 10000) {
            return 0; // Adm.Central
        }

        $prefijo = (int) substr((string) $numerorequisicion, 0, 2);

        return self::REF_NRO_A_CASINO[$prefijo] ?? null;
    }

    public static function codigoSalaDesdeCasinoAnita(int $casinoAnita): ?string
    {
        return self::CASINO_A_CODIGO_SALA[$casinoAnita]
            ?? ProveedorSalaAnitaSupport::codigoSalaDesdeCasinoAnita($casinoAnita);
    }

    public static function salaIdDesdeCasinoAnita(int $casinoAnita): ?int
    {
        $codigo = self::codigoSalaDesdeCasinoAnita($casinoAnita);
        if ($codigo === null) {
            return null;
        }

        $id = Sala::query()->where('codigo', $codigo)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return array{sala_id: ?int, empresa_id: int, casino_anita: ?int, codigo_sala: ?string}
     */
    public static function resolverDesdeNumeroRequisicion(int $numerorequisicion, int $empresaFallback = 1): array
    {
        $casino = self::casinoAnitaDesdeNumeroRequisicion($numerorequisicion);
        if ($casino === null) {
            return [
                'sala_id' => null,
                'empresa_id' => $empresaFallback,
                'casino_anita' => null,
                'codigo_sala' => null,
            ];
        }

        $codigo = self::codigoSalaDesdeCasinoAnita($casino);
        $sala = $codigo !== null
            ? Sala::query()->where('codigo', $codigo)->first(['id', 'empresa_id', 'codigo'])
            : null;

        if ($sala === null) {
            return [
                'sala_id' => null,
                'empresa_id' => $empresaFallback,
                'casino_anita' => $casino,
                'codigo_sala' => $codigo,
            ];
        }

        $empresaId = (int) ($sala->empresa_id ?: $empresaFallback);

        return [
            'sala_id' => (int) $sala->id,
            'empresa_id' => $empresaId > 0 ? $empresaId : $empresaFallback,
            'casino_anita' => $casino,
            'codigo_sala' => (string) $sala->codigo,
        ];
    }
}
