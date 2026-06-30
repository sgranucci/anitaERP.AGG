<?php

namespace App\Services\Ventas;

use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Pais;
use App\Models\Ventas\Subzonavta;
use App\Models\Ventas\Zonavta;
use App\Repositories\Configuracion\ProvinciaRepositoryInterface;

class ClienteMaestrosAnitaSyncService
{
    /** @var array<string, array{label: string, tabla_anita: string, sistema: string}> */
    public const MAESTROS = [
        'pais' => ['label' => 'Países', 'tabla_anita' => 'pais', 'sistema' => 'ventas'],
        'provincia' => ['label' => 'Provincias', 'tabla_anita' => 'provincia', 'sistema' => 'shared'],
        'localidad' => ['label' => 'Localidades', 'tabla_anita' => 'localidad', 'sistema' => 'shared'],
        'zonavta' => ['label' => 'Zonas de venta', 'tabla_anita' => 'zonavta', 'sistema' => 'ventas'],
        'subzonavta' => ['label' => 'Subzonas de venta', 'tabla_anita' => 'subzona', 'sistema' => 'ventas'],
    ];

    public function __construct(
        private readonly ProvinciaRepositoryInterface $provinciaRepository,
    ) {}

    /**
     * Resincroniza maestros geográficos y de zona antes del sync de clientes.
     *
     * @return array<string, array{label: string, antes: int, despues: int, insertados: int, actualizados: int, omitidos: int, error: ?string}>
     */
    public function resincronizarTodos(?array $solo = null): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $claves = $solo !== null && $solo !== []
            ? array_values(array_intersect(array_keys(self::MAESTROS), $solo))
            : array_keys(self::MAESTROS);

        $resultado = [];
        foreach ($claves as $clave) {
            $cfg = self::MAESTROS[$clave];
            $antes = $this->conteoErp($clave);
            try {
                $stats = $this->resincronizarMaestro($clave);
                $resultado[$clave] = [
                    'label' => $cfg['label'],
                    'antes' => $antes,
                    'despues' => $this->conteoErp($clave),
                    'insertados' => $stats['insertados'],
                    'actualizados' => $stats['actualizados'],
                    'omitidos' => $stats['omitidos'],
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                $resultado[$clave] = [
                    'label' => $cfg['label'],
                    'antes' => $antes,
                    'despues' => $this->conteoErp($clave),
                    'insertados' => 0,
                    'actualizados' => 0,
                    'omitidos' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $resultado;
    }

    /**
     * @return array{insertados: int, actualizados: int, omitidos: int}
     */
    public function resincronizarMaestro(string $clave): array
    {
        return match ($clave) {
            'pais' => (new Pais)->resincronizarConAnita(),
            'provincia' => $this->provinciaRepository->resincronizarConAnita(),
            'localidad' => (new Localidad)->resincronizarConAnita(),
            'zonavta' => (new Zonavta)->resincronizarConAnita(),
            'subzonavta' => (new Subzonavta)->resincronizarConAnita(),
            default => throw new \InvalidArgumentException("Maestro desconocido: {$clave}"),
        };
    }

    public function conteoErp(string $clave): int
    {
        return match ($clave) {
            'pais' => (int) Pais::query()->count(),
            'provincia' => (int) \App\Models\Configuracion\Provincia::query()->count(),
            'localidad' => (int) Localidad::query()->count(),
            'zonavta' => (int) Zonavta::query()->count(),
            'subzonavta' => (int) Subzonavta::query()->count(),
            default => 0,
        };
    }
}
