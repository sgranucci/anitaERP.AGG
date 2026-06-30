<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Ventas\MaquinavendingRendicion;
use Illuminate\Support\Facades\Log;

/**
 * Re-sincroniza rendiciones vending ERP → Anita (rendgastro, rendvalor, rendmvart).
 */
final class MaquinavendingRendicionResincronizarAnitaService
{
    private const LOG_EVENTO = 'maquinavending_rendicion.resincronizar_anita';

    public function __construct(
        private readonly MaquinavendingRendicionAnitaSyncService $anitaSyncService,
    ) {
    }

    /**
     * @param  list<int>  $empresaIds  vacío = todas las empresas con rendiciones
     * @return array<string, mixed>
     */
    public function ejecutar(
        array $empresaIds = [],
        ?int $rendicionId = null,
        bool $dryRun = false,
        ?callable $progreso = null,
    ): array {
        if (! $this->anitaSyncService->sincronizacionHabilitada()) {
            throw new \RuntimeException('RENDICION_MAQUINAVENDING_SINCRONIZAR_ANITA está deshabilitado.');
        }

        $resultado = [
            'dry_run' => $dryRun,
            'empresas' => $empresaIds,
            'total' => 0,
            'actualizadas' => 0,
            'insertadas' => 0,
            'errores' => [],
            'detalle' => [],
        ];

        $query = MaquinavendingRendicion::query()->orderBy('id');

        if ($rendicionId !== null && $rendicionId > 0) {
            $query->whereKey($rendicionId);
        }

        if ($empresaIds !== []) {
            $empresaIds = array_values(array_filter(array_map('intval', $empresaIds), static fn (int $id): bool => $id > 0));
            if ($empresaIds === []) {
                throw new \InvalidArgumentException('Indique al menos una empresa válida.');
            }
            $query->whereIn('empresa_id', $empresaIds);
            $resultado['empresas'] = $empresaIds;
        } else {
            $resultado['empresas'] = MaquinavendingRendicion::query()
                ->distinct()
                ->orderBy('empresa_id')
                ->pluck('empresa_id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        }

        $rendiciones = $query->get();
        $resultado['total'] = $rendiciones->count();

        if ($resultado['total'] === 0) {
            return $resultado;
        }

        /** @var MaquinavendingRendicion $rendicion */
        foreach ($rendiciones as $rendicion) {
            $etiqueta = sprintf(
                'Rendición #%d | emp %d | cierre %d | nro_oper %s',
                (int) $rendicion->id,
                (int) $rendicion->empresa_id,
                (int) $rendicion->numero_cierre,
                $rendicion->nro_oper_anita ?: '—',
            );

            $progreso && $progreso($etiqueta);

            if ($dryRun) {
                $resultado['actualizadas']++;
                $resultado['detalle'][] = [
                    'rendicion_id' => (int) $rendicion->id,
                    'accion' => 'simulada',
                ];

                continue;
            }

            try {
                $rendicion->load([
                    'articulos.articulo',
                    'mediosPago.cuentacaja',
                    'maquinavending.puntoventa',
                    'rendicionCaja',
                ]);

                $existia = $this->anitaSyncService->existsCabeceraEnAnita($rendicion);

                $this->anitaSyncService->sincronizarDespuesDeGuardar($rendicion);

                if ($existia) {
                    $resultado['actualizadas']++;
                    $accion = 'actualizada';
                } else {
                    $resultado['insertadas']++;
                    $accion = 'insertada';
                }

                $rendicion->refresh();
                $resultado['detalle'][] = [
                    'rendicion_id' => (int) $rendicion->id,
                    'accion' => $accion,
                    'nro_oper_anita' => (int) ($rendicion->nro_oper_anita ?? 0),
                    'numero_cierre' => (int) $rendicion->numero_cierre,
                ];
            } catch (\Throwable $e) {
                Log::warning(self::LOG_EVENTO.'.fallo', [
                    'rendicion_id' => (int) $rendicion->id,
                    'empresa_id' => (int) $rendicion->empresa_id,
                    'mensaje' => $e->getMessage(),
                ]);
                $resultado['errores'][] = [
                    'rendicion_id' => (int) $rendicion->id,
                    'empresa_id' => (int) $rendicion->empresa_id,
                    'numero_cierre' => (int) $rendicion->numero_cierre,
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        return $resultado;
    }
}
