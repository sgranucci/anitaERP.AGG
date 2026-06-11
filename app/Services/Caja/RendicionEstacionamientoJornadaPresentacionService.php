<?php

namespace App\Services\Caja;

use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Models\Caja\Estacionamiento\TurnoOperativoEstacionamiento;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Support\Caja\Estacionamiento\EstacionamientoJornadaNumeracionComprobanteSupport;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Presentación a caja de una jornada estacionamiento cerrada: numeración PV y disparo de totales Z en Anita.
 */
final class RendicionEstacionamientoJornadaPresentacionService
{
    /**
     * @return array{numeracion_comprobantes_json: array<string, mixed>}
     */
    public function resolverMarcadoresAuditoria(JornadaEstacionamiento $jornada): array
    {
        $jornada->loadMissing(['empresa']);
        $numeracion = EstacionamientoJornadaNumeracionComprobanteSupport::paraJornada($jornada);

        return [
            'numeracion_comprobantes_json' => [
                'jornada_id' => (int) $jornada->id,
                'fecha_jornada' => $jornada->fecha_jornada?->format('Y-m-d'),
                'apertura_en' => $jornada->apertura_en?->format('Y-m-d H:i:s'),
                'cierre_en' => $jornada->cierre_en?->format('Y-m-d H:i:s'),
                'resumen_numeracion' => $numeracion['resumen_etiqueta'] ?? '',
                'por_puntoventa' => $numeracion['filas'] ?? [],
                'registrado_en' => now()->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datosAuditoriaJornadaParaCaja(JornadaEstacionamiento $jornada): array
    {
        $marcadores = $this->resolverMarcadoresAuditoria($jornada);

        return array_merge($marcadores, [
            'numeracion_resumen' => (string) (($marcadores['numeracion_comprobantes_json']['resumen_numeracion'] ?? '') ?: ''),
            'numeracion_por_puntoventa' => $marcadores['numeracion_comprobantes_json']['por_puntoventa'] ?? [],
        ]);
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeRendir(JornadaEstacionamiento $jornada, ?int $exceptoRendicionId = null): array
    {
        $errores = [];

        if ((int) $jornada->empresa_id <= 0) {
            $errores[] = 'Jornada sin empresa válida.';

            return $errores;
        }

        if ($jornada->estado !== JornadaEstacionamiento::ESTADO_CERRADA || $jornada->cierre_en === null) {
            $errores[] = 'La jornada debe estar cerrada antes de presentarla en caja.';
        }

        if ($this->jornadaYaRendida((int) $jornada->id, $exceptoRendicionId)) {
            $errores[] = 'La jornada #'.$jornada->id.' ya tiene una rendición registrada en caja.';
        }

        $turnosAbiertos = TurnoOperativoEstacionamiento::query()
            ->where('jornada_estacionamiento_id', (int) $jornada->id)
            ->whereIn('estado', [TurnoOperativoEstacionamiento::ESTADO_HABILITADO])
            ->count();

        if ($turnosAbiertos > 0) {
            $errores[] = 'Hay '.$turnosAbiertos.' turno(s) operativo(s) aún habilitado(s) en esta jornada. Ciérrelos antes de rendir la jornada.';
        }

        $turnosSinCierre = TurnoOperativoEstacionamiento::query()
            ->where('jornada_estacionamiento_id', (int) $jornada->id)
            ->where('estado', '!=', TurnoOperativoEstacionamiento::ESTADO_CERRADO)
            ->whereNotNull('habilitacion_en')
            ->count();

        if ($turnosSinCierre > 0) {
            $errores[] = 'Hay turnos operativos sin cierre definitivo en esta jornada.';
        }

        $cierresTurnoSinRendir = $this->turnosCerradosSinRendirEnJornada((int) $jornada->id, $exceptoRendicionId);
        if ($cierresTurnoSinRendir->isNotEmpty()) {
            $errores[] = $this->mensajeCierresTurnoPendientesEnCaja($cierresTurnoSinRendir);
        }

        return $errores;
    }

    /**
     * @return Collection<int, TurnoOperativoEstacionamiento>
     */
    public function turnosCerradosSinRendirEnJornada(int $jornadaId, ?int $exceptoRendicionId = null): Collection
    {
        if ($jornadaId <= 0) {
            return collect();
        }

        $rendidos = RendicionEstacionamientoCaja::query()
            ->whereNotNull('turno_operativo_estacionamiento_id')
            ->when($exceptoRendicionId, fn ($q) => $q->where('id', '!=', $exceptoRendicionId))
            ->pluck('turno_operativo_estacionamiento_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->values()
            ->all();

        return TurnoOperativoEstacionamiento::query()
            ->with(['turno:id,nombre'])
            ->where('jornada_estacionamiento_id', $jornadaId)
            ->where('estado', TurnoOperativoEstacionamiento::ESTADO_CERRADO)
            ->whereNotNull('cierre_en')
            ->whereNotIn('id', $rendidos)
            ->orderBy('identificador_pc')
            ->orderBy('cierre_en')
            ->get();
    }

    public function jornadaListaParaRendirEnCaja(int $jornadaId, ?int $exceptoRendicionId = null): bool
    {
        return $this->turnosCerradosSinRendirEnJornada($jornadaId, $exceptoRendicionId)->isEmpty();
    }

    /**
     * @param  Collection<int, TurnoOperativoEstacionamiento>  $pendientes
     */
    private function mensajeCierresTurnoPendientesEnCaja(Collection $pendientes): string
    {
        $cantidad = $pendientes->count();
        $detalle = $pendientes
            ->take(8)
            ->map(function (TurnoOperativoEstacionamiento $t) {
                $pc = trim((string) ($t->identificador_pc ?? ''));
                $turno = trim((string) ($t->turno?->nombre ?? ''));
                $cierre = $t->cierre_en?->format('d/m/Y H:i') ?? '';

                return '#'.$t->id
                    .($pc !== '' ? ' '.$pc : '')
                    .($turno !== '' ? ' ('.$turno.')' : '')
                    .($cierre !== '' ? ' cierre '.$cierre : '');
            })
            ->implode('; ');

        $sufijo = $cantidad > 8 ? '…' : '';

        return 'Hay '.$cantidad.' cierre(s) de turno sin rendir en caja para esta jornada'
            .($detalle !== '' ? ': '.$detalle.$sufijo : '.')
            .' Registre primero las rendiciones de turno (Caja → Rendiciones estacionamiento, alcance turno); después podrá presentar la jornada.';
    }

    public function jornadaYaRendida(int $jornadaId, ?int $exceptoRendicionId = null): bool
    {
        return RendicionEstacionamientoCaja::query()
            ->where('tipo', RendicionEstacionamientoCaja::TIPO_JORNADA)
            ->where('jornada_estacionamiento_id', $jornadaId)
            ->when($exceptoRendicionId, fn ($q) => $q->where('id', '!=', $exceptoRendicionId))
            ->exists();
    }

    public function jornadaPresentadaBloqueaRendicionesTurno(int $jornadaId): bool
    {
        return $jornadaId > 0 && $this->jornadaYaRendida($jornadaId);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function exigirRendicionTurnoModificable(int $jornadaId): void
    {
        if (! $this->jornadaPresentadaBloqueaRendicionesTurno($jornadaId)) {
            return;
        }

        throw new InvalidArgumentException(
            'La jornada ya fue presentada en caja. No puede crear, modificar ni eliminar rendiciones de turno. '
            .'Para corregir datos, edite o anule la presentación de jornada (alcance jornada en esta pantalla).'
        );
    }

    public function proponerCodigoInterno(int $empresaId, int $jornadaId): string
    {
        if ($empresaId <= 0 || $jornadaId <= 0) {
            throw new InvalidArgumentException('Empresa o jornada inválida para el código de rendición.');
        }

        $max = (int) RendicionEstacionamientoCaja::query()
            ->where('tipo', RendicionEstacionamientoCaja::TIPO_JORNADA)
            ->where('empresa_id', $empresaId)
            ->count();

        return sprintf('REJ-%d-%d-%04d', $empresaId, $jornadaId, $max + 1);
    }
}
