<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Recordatorios SLA del árbol (campos recordatorio / diasinrespuesta del ABM).
 */
class ArbolAprobacionRecordatorioService
{
    public function __construct(
        private MisAprobacionesArbolService $misAprobacionesService,
    ) {}

    /**
     * @return array{enviados: int, omitidos: int, errores: int}
     */
    public function enviarPendientes(?Carbon $hoy = null): array
    {
        $hoy = ($hoy ?? Carbon::now())->startOfDay();
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $movimientos = Arbolaprobacion_Movimiento::query()
            ->where('estado', $nombrePendiente)
            ->whereNotNull('fechaenvio')
            ->whereNotNull('destinatariousuario_id')
            ->orderBy('id')
            ->get();

        $stats = ['enviados' => 0, 'omitidos' => 0, 'errores' => 0];

        foreach ($movimientos as $mov) {
            try {
                if (! $this->debeRecordar($mov, $hoy)) {
                    $stats['omitidos']++;

                    continue;
                }

                $cacheKey = 'arbol_recordatorio_'.$mov->id.'_'.$hoy->toDateString();
                if (! Cache::add($cacheKey, 1, $hoy->copy()->endOfDay())) {
                    $stats['omitidos']++;

                    continue;
                }

                $this->reenviarMail($mov);
                $stats['enviados']++;
            } catch (\Throwable $e) {
                $stats['errores']++;
                Log::warning('arbol_recordatorio_error', [
                    'movimiento_id' => (int) $mov->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function debeRecordar(Arbolaprobacion_Movimiento $mov, Carbon $hoy): bool
    {
        $arbol = Arbolaprobacion::query()->find((int) $mov->arbolaprobacion_id);
        if (! $arbol || strtoupper((string) ($arbol->recordatorio ?? 'N')) !== 'S') {
            return false;
        }
        $estado = (string) ($arbol->estado ?? '');
        if ($estado !== '' && ! in_array($estado, ['Activo', 'Active'], true)) {
            return false;
        }

        $diasSinRespuesta = max(1, (int) ($arbol->diasinrespuesta ?? 1));
        $diasVencimiento = max(0, (int) ($arbol->diavencimientorecordatorio ?? 0));
        $fechaEnvio = Carbon::parse($mov->fechaenvio)->startOfDay();
        $dias = $fechaEnvio->diffInDays($hoy);

        if ($dias < $diasSinRespuesta) {
            return false;
        }
        if ($diasVencimiento > 0 && $dias > $diasVencimiento) {
            return false;
        }

        return true;
    }

    private function reenviarMail(Arbolaprobacion_Movimiento $mov): void
    {
        $this->misAprobacionesService->reenviarCorreoPendiente($mov, [
            'es_recordatorio' => true,
        ]);
    }

    private function nombreTipoArbol(string $codigo): string
    {
        $idx = array_search($codigo, array_column(Arbolaprobacion::$enumTipoArbol, 'valor'));

        return $idx === false ? $codigo : (string) Arbolaprobacion::$enumTipoArbol[$idx]['nombre'];
    }
}
