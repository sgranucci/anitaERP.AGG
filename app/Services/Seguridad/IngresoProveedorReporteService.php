<?php

namespace App\Services\Seguridad;

use App\Models\Seguridad\IngresoProveedor;
use App\Models\Seguridad\IngresoProveedorPersona;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Support\Seguridad\IngresoProveedorEstados;
use App\Support\Seguridad\IngresoProveedorVisitanteSupport;
use Illuminate\Support\Collection;

class IngresoProveedorReporteService
{
    public const MODO_KPI = 'kpi';

    public const MODO_PLANTA = 'planta';

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<array<string, mixed>>, kpis: array<string, mixed>}
     */
    public function generar(array $filtros, string $modo = self::MODO_KPI): array
    {
        $tickets = $this->consultarTickets($filtros);
        $filas = [];
        foreach ($tickets as $ticket) {
            $personas = $ticket->personas;
            if ($personas->isEmpty()) {
                if ($modo === self::MODO_PLANTA) {
                    continue;
                }
                $filas[] = $this->fila($ticket, null, $modo);
                continue;
            }
            foreach ($personas as $persona) {
                if ($modo === self::MODO_PLANTA && ! $persona->fecha_ingreso) {
                    continue;
                }
                $filas[] = $this->fila($ticket, $persona, $modo);
            }
        }

        return [
            'filas' => $filas,
            'kpis' => $modo === self::MODO_PLANTA
                ? $this->kpisPlanta($filas)
                : $this->kpisKpi($tickets, $filas),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, IngresoProveedor>
     */
    private function consultarTickets(array $filtros): Collection
    {
        $query = IngresoProveedor::query()
            ->with([
                'personas.usuarioIngreso:id,nombre',
                'personas.usuarioEgreso:id,nombre',
                'proveedores:id,codigo,nombre',
                'ordencompras:id,numeroordencompra',
                'motivos:id,nombre',
                'puntos:id,nombre',
                'areas:id,nombre',
                'sectores:id,nombre',
                'usuarios:id,nombre',
                'usuarioAutorizo:id,nombre',
                'empresas:id,nombre',
            ]);

        app(EmpresaRepository::class)->aplicarFiltroEmpresasAsignadas($query, 'ingreso_proveedor.empresa_id');

        if (! empty($filtros['empresa_id'])) {
            $query->where('ingreso_proveedor.empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('ingreso_proveedor.fecha', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('ingreso_proveedor.fecha', '<=', $filtros['fecha_hasta']);
        }
        if (! empty($filtros['estado'])) {
            $query->where('ingreso_proveedor.estado', $filtros['estado']);
        }
        if (($filtros['tipo'] ?? '') === IngresoProveedorVisitanteSupport::VISITANTE) {
            $query->where(function ($q) {
                $q->where('ingreso_proveedor.visitante_tipo', IngresoProveedorVisitanteSupport::VISITANTE)
                    ->orWhereNull('ingreso_proveedor.proveedor_id');
            });
        } elseif (($filtros['tipo'] ?? '') === IngresoProveedorVisitanteSupport::PROVEEDOR) {
            $query->where('ingreso_proveedor.visitante_tipo', IngresoProveedorVisitanteSupport::PROVEEDOR)
                ->whereNotNull('ingreso_proveedor.proveedor_id');
        }
        if (! empty($filtros['motivo_id'])) {
            $query->where('ingreso_proveedor.motivo_id', (int) $filtros['motivo_id']);
        }
        if (! empty($filtros['punto_id'])) {
            $query->where('ingreso_proveedor.punto_id', (int) $filtros['punto_id']);
        }
        if (! empty($filtros['sector_id'])) {
            $query->where('ingreso_proveedor.sector_id', (int) $filtros['sector_id']);
        }
        if (! empty($filtros['area_id'])) {
            $query->where('ingreso_proveedor.area_id', (int) $filtros['area_id']);
        }

        return $query->orderBy('ingreso_proveedor.fecha')->orderBy('ingreso_proveedor.id')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(IngresoProveedor $ticket, ?IngresoProveedorPersona $persona, string $modo): array
    {
        $esVisitante = IngresoProveedorVisitanteSupport::esVisitante($ticket);
        $minutos = $persona?->minutos_en_planta ?? $ticket->minutos_en_planta;
        $enPlanta = $persona && $persona->fecha_ingreso && ! $persona->fecha_egreso;

        $base = [
            'ticket_id' => (int) $ticket->id,
            'fecha' => optional($ticket->fecha)->format('d/m/Y'),
            'nombreempresa' => (string) ($ticket->empresas->nombre ?? ''),
            'tipo' => IngresoProveedorVisitanteSupport::etiquetaTipo($ticket),
            'origen' => IngresoProveedorVisitanteSupport::etiquetaOrigen($ticket),
            'motivo' => (string) ($ticket->motivos->nombre ?? ''),
            'punto' => (string) ($ticket->puntos->nombre ?? ''),
            'area' => (string) ($ticket->areas->nombre ?? ''),
            'sector' => (string) ($ticket->sectores->nombre ?? ''),
            'patente' => (string) ($ticket->patente ?? ''),
            'estado' => IngresoProveedorEstados::etiqueta((string) $ticket->estado),
            'estado_codigo' => (string) $ticket->estado,
            'persona' => (string) ($persona->nombre ?? ''),
            'documento' => (string) ($persona->documento ?? ''),
            'fecha_ingreso' => optional($persona?->fecha_ingreso ?? $ticket->fecha_ingreso)->format('d/m/Y'),
            'hora_ingreso' => $this->hora($persona?->hora_ingreso ?? $ticket->hora_ingreso),
            'fecha_egreso' => optional($persona?->fecha_egreso ?? $ticket->fecha_egreso)->format('d/m/Y'),
            'hora_egreso' => $this->hora($persona?->hora_egreso ?? $ticket->hora_egreso),
            'minutos' => $minutos,
            'minutos_fmt' => $this->fmtMinutos($minutos),
            'en_planta' => $enPlanta ? 'Sí' : 'No',
            'usuario_ingreso' => (string) ($persona?->usuarioIngreso->nombre ?? ''),
            'usuario_egreso' => (string) ($persona?->usuarioEgreso->nombre ?? ''),
        ];

        if ($modo === self::MODO_PLANTA) {
            return $base;
        }

        return array_merge($base, [
            'proveedor_codigo' => $esVisitante ? '' : (string) ($ticket->proveedores->codigo ?? ''),
            'proveedor_nombre' => $esVisitante ? '' : (string) ($ticket->proveedores->nombre ?? ''),
            'visitante_nombre' => $esVisitante ? IngresoProveedorVisitanteSupport::etiquetaOrigen($ticket) : '',
            'oc_id' => $ticket->ordencompra_id,
            'oc_numero' => (string) ($ticket->ordencompras->numeroordencompra ?? ''),
            'titulo' => (string) ($ticket->titulo ?? ''),
            'comentario' => (string) ($ticket->comentario ?? ''),
            'usuario_genero' => (string) ($ticket->usuarios->nombre ?? ''),
            'usuario_autorizo' => (string) ($ticket->usuarioAutorizo->nombre ?? ''),
            'autorizado_at' => optional($ticket->autorizado_at)->format('d/m/Y H:i'),
            'minutos_ticket' => $ticket->minutos_en_planta,
        ]);
    }

    /**
     * @param  Collection<int, IngresoProveedor>  $tickets
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function kpisKpi(Collection $tickets, array $filas): array
    {
        $porEstado = [];
        foreach (IngresoProveedorEstados::todos() as $est) {
            $porEstado[$est] = 0;
        }
        $proveedor = 0;
        $visitante = 0;
        foreach ($tickets as $ticket) {
            $est = (string) $ticket->estado;
            $porEstado[$est] = ($porEstado[$est] ?? 0) + 1;
            if (IngresoProveedorVisitanteSupport::esVisitante($ticket)) {
                $visitante++;
            } else {
                $proveedor++;
            }
        }

        $personas = 0;
        $conIngreso = 0;
        $enPlanta = 0;
        $minutos = [];
        $porMotivo = [];
        $porPunto = [];
        $porEmpresa = [];
        foreach ($filas as $fila) {
            if (($fila['persona'] ?? '') !== '') {
                $personas++;
            }
            if (($fila['fecha_ingreso'] ?? '') !== '') {
                $conIngreso++;
            }
            if (($fila['en_planta'] ?? '') === 'Sí') {
                $enPlanta++;
            }
            if (isset($fila['minutos']) && $fila['minutos'] !== null && $fila['minutos'] !== '') {
                $minutos[] = (int) $fila['minutos'];
            }
            $this->contar($porMotivo, (string) ($fila['motivo'] ?: '(sin motivo)'));
            $this->contar($porPunto, (string) ($fila['punto'] ?: '(sin punto)'));
            $this->contar($porEmpresa, (string) ($fila['nombreempresa'] ?: '(sin empresa)'));
        }
        arsort($porMotivo);
        arsort($porPunto);
        arsort($porEmpresa);

        return [
            'tickets' => $tickets->count(),
            'personas' => $personas,
            'con_ingreso' => $conIngreso,
            'en_planta' => $enPlanta,
            'sin_ingreso' => max(0, $personas - $conIngreso),
            'proveedor' => $proveedor,
            'visitante' => $visitante,
            'por_estado' => $porEstado,
            'minutos_promedio' => $minutos === [] ? null : (int) round(array_sum($minutos) / count($minutos)),
            'minutos_total' => $minutos === [] ? null : array_sum($minutos),
            'por_motivo' => $porMotivo,
            'por_punto' => $porPunto,
            'por_empresa' => $porEmpresa,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function kpisPlanta(array $filas): array
    {
        $enPlanta = 0;
        $salieron = 0;
        $minutos = [];
        $porPunto = [];
        foreach ($filas as $fila) {
            if (($fila['en_planta'] ?? '') === 'Sí') {
                $enPlanta++;
            } elseif (($fila['fecha_egreso'] ?? '') !== '') {
                $salieron++;
            }
            if (isset($fila['minutos']) && $fila['minutos'] !== null && $fila['minutos'] !== '') {
                $minutos[] = (int) $fila['minutos'];
            }
            $this->contar($porPunto, (string) ($fila['punto'] ?: '(sin punto)'));
        }
        arsort($porPunto);

        return [
            'ingresos' => count($filas),
            'en_planta' => $enPlanta,
            'salieron' => $salieron,
            'minutos_promedio' => $minutos === [] ? null : (int) round(array_sum($minutos) / count($minutos)),
            'por_punto' => $porPunto,
        ];
    }

    /**
     * @param  array<string, int>  $mapa
     */
    private function contar(array &$mapa, string $clave): void
    {
        $mapa[$clave] = ($mapa[$clave] ?? 0) + 1;
    }

    private function hora($valor): string
    {
        $v = (string) $valor;
        if ($v === '') {
            return '';
        }

        return substr($v, 0, 5);
    }

    private function fmtMinutos($minutos): string
    {
        if ($minutos === null || $minutos === '') {
            return '';
        }
        $m = (int) $minutos;
        $h = intdiv($m, 60);
        $r = $m % 60;

        return $h > 0 ? $h.'h '.$r.'m' : $r.' min';
    }
}
