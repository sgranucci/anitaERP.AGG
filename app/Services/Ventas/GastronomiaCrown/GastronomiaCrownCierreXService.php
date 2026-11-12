<?php

namespace App\Services\Ventas\GastronomiaCrown;

use App\Models\Caja\CajeroCrown;
use App\Models\Caja\TesoreriaCrownCierreFiscal;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Ventas\GastronomiaCrown\GastroCrownCuenta;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\MozoGastronomia;
use App\Support\Caja\TesoreriaCrownComprobanteSupport;
use App\Support\Caja\TesoreriaCrownDiaCajeroSupport;
use App\Support\Caja\TesoreriaCrownNumeradorSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cierre X de gastronomía Crown → una sola operación:
 * genera la rendición de cajero G en tesorería Crown (como a-transfefe gastro),
 * con el total X desde cuentas POS cerradas, y un informe X en
 * tesoreria_crown_cierre_fiscal. No pasa por Caja→Rendición gastronomía.
 */
class GastronomiaCrownCierreXService
{
    /**
     * @return Collection<int, JornadaGastronomia>
     */
    public function jornadasParaElegir(int $empresaId, ?int $salaId, int $limite = 30): Collection
    {
        if ($empresaId <= 0) {
            return collect();
        }

        return JornadaGastronomia::query()
            ->with(['sala:id,codigo,nombre', 'usuarioApertura:id,nombre'])
            ->where('empresa_id', $empresaId)
            ->when($salaId !== null && $salaId > 0, fn ($q) => $q->where('sala_id', $salaId))
            ->orderByDesc('fecha_jornada')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    /**
     * @return list<array{
     *   mozo_gastronomia_id:int,
     *   mozo_codigo:string,
     *   mozo_nombre:string,
     *   cuentas:int,
     *   total_x:float,
     *   total_facturado:float,
     *   ya_rendido:bool,
     *   comprobante_id:int,
     *   abiertas_total:int,
     *   abiertas_con_mesa:int,
     *   abiertas_sin_mesa:int,
     *   aviso_abiertas:?string
     * }>
     */
    public function totalesPorMozo(
        int $empresaId,
        ?int $salaId,
        ?JornadaGastronomia $jornada = null,
        ?string $fecha = null,
    ): array {
        if ($empresaId <= 0) {
            return [];
        }

        $q = GastroCrownCuenta::query()
            ->leftJoin('venta as v', 'v.id', '=', 'gastro_crown_cuenta.venta_id')
            ->where('gastro_crown_cuenta.empresa_id', $empresaId)
            ->where('gastro_crown_cuenta.estado', GastroCrownCuenta::ESTADO_CERRADA)
            ->whereNotNull('gastro_crown_cuenta.mozo_gastronomia_id')
            ->when($salaId !== null && $salaId > 0, fn ($qq) => $qq->where('gastro_crown_cuenta.sala_id', $salaId))
            ->groupBy('gastro_crown_cuenta.mozo_gastronomia_id')
            ->select([
                'gastro_crown_cuenta.mozo_gastronomia_id',
                DB::raw('COUNT(*) as cuentas'),
                DB::raw('COALESCE(SUM(gastro_crown_cuenta.importe_cobrado), 0) as total_x'),
                DB::raw('COALESCE(SUM(v.total), 0) as total_facturado'),
            ]);

        $this->aplicarVentana($q, $jornada, $fecha);

        $filas = $q->get();
        if ($filas->isEmpty()) {
            return [];
        }

        $mozos = MozoGastronomia::query()
            ->whereIn('id', $filas->pluck('mozo_gastronomia_id')->map(fn ($v) => (int) $v)->all())
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('id');

        $comprobantesPorMozo = $this->comprobantesCierreXPorMozo($jornada, $salaId);

        $out = [];
        foreach ($filas as $fila) {
            $mozoId = (int) $fila->mozo_gastronomia_id;
            $mozo = $mozos->get($mozoId);
            $comprobanteId = (int) ($comprobantesPorMozo[$mozoId] ?? 0);
            $abiertas = $this->cuentasAbiertasDelMozo($empresaId, $salaId, $mozoId);
            $nombreMozo = (string) ($mozo->nombre ?? ('Mozo #'.$mozoId));

            $out[] = [
                'mozo_gastronomia_id' => $mozoId,
                'mozo_codigo' => (string) ($mozo->codigo ?? ''),
                'mozo_nombre' => $nombreMozo,
                'cuentas' => (int) $fila->cuentas,
                'total_x' => round((float) $fila->total_x, 2),
                'total_facturado' => round((float) $fila->total_facturado, 2),
                'ya_rendido' => $comprobanteId > 0,
                'comprobante_id' => $comprobanteId,
                'rendicion_id' => $comprobanteId, // compat vista
                'abiertas_total' => $abiertas['total'],
                'abiertas_con_mesa' => $abiertas['con_mesa'],
                'abiertas_sin_mesa' => $abiertas['sin_mesa'],
                'aviso_abiertas' => $abiertas['total'] > 0
                    ? $this->mensajeAbiertasMozo($nombreMozo, $abiertas)
                    : null,
            ];
        }

        usort($out, fn (array $a, array $b) => strcmp($a['mozo_nombre'], $b['mozo_nombre']));

        return $out;
    }

    /**
     * @return array{total:int,con_mesa:int,sin_mesa:int}
     */
    public function cuentasAbiertasDelMozo(int $empresaId, ?int $salaId, int $mozoId): array
    {
        if ($empresaId <= 0 || $mozoId <= 0) {
            return ['total' => 0, 'con_mesa' => 0, 'sin_mesa' => 0];
        }

        $filas = GastroCrownCuenta::query()
            ->where('empresa_id', $empresaId)
            ->where('mozo_gastronomia_id', $mozoId)
            ->whereIn('estado', [GastroCrownCuenta::ESTADO_ABIERTA, GastroCrownCuenta::ESTADO_COBRANDO])
            ->when($salaId !== null && $salaId > 0, fn ($q) => $q->where('sala_id', $salaId))
            ->get(['id', 'mesa_gastronomia_id']);

        $conMesa = $filas->filter(fn ($c) => (int) ($c->mesa_gastronomia_id ?? 0) > 0)->count();

        return [
            'total' => $filas->count(),
            'con_mesa' => $conMesa,
            'sin_mesa' => $filas->count() - $conMesa,
        ];
    }

    /**
     * @param  array{total:int,con_mesa:int,sin_mesa:int}  $abiertas
     */
    public function mensajeAbiertasMozo(string $mozoNombre, array $abiertas): string
    {
        $partes = [];
        if ($abiertas['con_mesa'] > 0) {
            $partes[] = $abiertas['con_mesa'].' mesa(s)';
        }
        if ($abiertas['sin_mesa'] > 0) {
            $partes[] = $abiertas['sin_mesa'].' barra/sin mesa';
        }

        return 'El mozo '.$mozoNombre.' deja '.$abiertas['total']
            .' cuenta(s) abierta(s) sin facturar'
            .($partes !== [] ? ' ('.implode(', ', $partes).')' : '')
            .'. Cóbrelas o cerralas en el POS antes de rendir el cierre X.';
    }

    /**
     * @return array{total:int,con_mesa:int,sin_mesa:int}
     */
    public function cuentasAbiertasSala(int $empresaId, ?int $salaId): array
    {
        if ($empresaId <= 0) {
            return ['total' => 0, 'con_mesa' => 0, 'sin_mesa' => 0];
        }

        $filas = GastroCrownCuenta::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', [GastroCrownCuenta::ESTADO_ABIERTA, GastroCrownCuenta::ESTADO_COBRANDO])
            ->when($salaId !== null && $salaId > 0, fn ($q) => $q->where('sala_id', $salaId))
            ->get(['id', 'mesa_gastronomia_id']);

        $conMesa = $filas->filter(fn ($c) => (int) ($c->mesa_gastronomia_id ?? 0) > 0)->count();

        return [
            'total' => $filas->count(),
            'con_mesa' => $conMesa,
            'sin_mesa' => $filas->count() - $conMesa,
        ];
    }

    /**
     * Crea la rendición de cajero G en tesorería Crown + informe X fiscal ERP.
     */
    public function crearRendicion(JornadaGastronomia $jornada, int $mozoId, ?int $salaId = null): TesoreriaCrownComprobante
    {
        $empresaId = (int) $jornada->empresa_id;
        $salaId = $salaId !== null && $salaId > 0
            ? $salaId
            : ($jornada->sala_id !== null ? (int) $jornada->sala_id : null);

        if ($mozoId <= 0) {
            throw new InvalidArgumentException('Debe indicar el mozo a rendir.');
        }
        if ($salaId === null || $salaId <= 0) {
            throw new InvalidArgumentException('La jornada de gastronomía no tiene sala.');
        }

        $mozo = MozoGastronomia::query()->findOrFail($mozoId);
        if ((int) $mozo->empresa_id !== $empresaId) {
            throw new InvalidArgumentException('El mozo pertenece a otra empresa.');
        }

        $totales = collect($this->totalesPorMozo($empresaId, $salaId, $jornada))
            ->firstWhere('mozo_gastronomia_id', $mozoId);

        if ($totales === null) {
            throw new InvalidArgumentException(
                'El mozo '.$mozo->nombre.' no tiene cuentas cerradas en esta jornada.'
            );
        }
        if (! empty($totales['ya_rendido'])) {
            throw new InvalidArgumentException(
                'El mozo '.$mozo->nombre.' ya tiene una rendición de cierre X en tesorería para esta jornada.'
            );
        }

        $abiertasMozo = $this->cuentasAbiertasDelMozo($empresaId, $salaId, $mozoId);
        if ($abiertasMozo['total'] > 0) {
            throw new InvalidArgumentException($this->mensajeAbiertasMozo($mozo->nombre, $abiertasMozo));
        }

        $totalX = round((float) $totales['total_x'], 2);
        $fechaYmd = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        if ($fechaYmd === '') {
            throw new InvalidArgumentException('La jornada de gastronomía no tiene fecha.');
        }

        $jornadaTesoreria = TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fechaYmd)
            ->orderBy('id')
            ->first();
        if ($jornadaTesoreria === null) {
            throw new InvalidArgumentException(
                'No hay jornada de tesorería Crown para la sala en '.$fechaYmd.'. Abrala en Tesorería Crown.'
            );
        }
        if (! $jornadaTesoreria->estaAbierta()) {
            throw new InvalidArgumentException('La jornada de tesorería Crown de ese día está cerrada.');
        }

        $cajero = CajeroCrown::query()
            ->where('mozo_gastronomia_id', $mozoId)
            ->orderBy('id')
            ->first();
        if ($cajero === null) {
            throw new InvalidArgumentException(
                'El mozo '.$mozo->nombre.' no está vinculado a un cajero Crown. Enlácelo en cajeros Crown.'
            );
        }

        if ((string) ($cajero->tipo_tarea ?? '') !== 'G') {
            throw new InvalidArgumentException(
                'El cajero de '.$mozo->nombre.' no tiene tarea gastronomía (G).'
            );
        }
        if (TesoreriaCrownDiaCajeroSupport::cierreAbierto((int) $jornadaTesoreria->id, (int) $cajero->id)) {
            throw new InvalidArgumentException('El cajero de '.$mozo->nombre.' ya está cerrado en tesorería.');
        }

        $hayHabilitacion = TesoreriaCrownComprobante::query()
            ->where('jornada_id', $jornadaTesoreria->id)
            ->where('cajero_crown_id', $cajero->id)
            ->where('tipo', TesoreriaCrownComprobanteSupport::HABILITACION)
            ->where('estado', TesoreriaCrownComprobanteSupport::CONFORME)
            ->whereNull('revierte_id')
            ->exists();
        if (! $hayHabilitacion) {
            throw new InvalidArgumentException(
                'El cajero de '.$mozo->nombre.' necesita una habilitación conforme antes de rendir.'
            );
        }

        $yaHayRendicion = TesoreriaCrownComprobante::query()
            ->where('jornada_id', $jornadaTesoreria->id)
            ->where('cajero_crown_id', $cajero->id)
            ->where('tipo', TesoreriaCrownComprobanteSupport::RENDICION)
            ->whereNull('revierte_id')
            ->exists();
        if ($yaHayRendicion) {
            throw new InvalidArgumentException(
                'El cajero de '.$mozo->nombre.' ya tiene una rendición en tesorería para esta jornada.'
            );
        }

        return DB::transaction(function () use (
            $cajero,
            $jornadaTesoreria,
            $jornada,
            $mozo,
            $mozoId,
            $totalX,
            $fechaYmd,
        ) {
            $nroX = $this->siguienteNroX($fechaYmd);
            $hora = now()->format('H:i:s');

            $comprobante = TesoreriaCrownComprobante::query()->create([
                'sala_id' => (int) $jornadaTesoreria->sala_id,
                'empresa_id' => (int) $jornadaTesoreria->empresa_id,
                'jornada_id' => (int) $jornadaTesoreria->id,
                'cajero_crown_id' => (int) $cajero->id,
                'asignacion_id' => null,
                'tipo' => TesoreriaCrownComprobanteSupport::RENDICION,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $jornadaTesoreria->sala_id, TesoreriaCrownComprobanteSupport::RENDICION),
                'estado' => TesoreriaCrownComprobanteSupport::BORRADOR,
                'total_x' => $totalX,
                'nro_z' => (string) $nroX,
                'observacion' => 'Cierre X gastronomía — mozo '.$mozo->nombre
                    .' — jornada gastro #'.$jornada->id
                    .' del '.($jornada->fecha_jornada?->format('d/m/Y') ?? ''),
                'creado_usuario_id' => (int) Auth::id(),
            ]);

            TesoreriaCrownComprobanteSupport::sellarCotizacionVigenteYGuardar($comprobante);

            TesoreriaCrownCierreFiscal::query()->create([
                'jornada_id' => (int) $jornadaTesoreria->id,
                'comprobante_id' => (int) $comprobante->id,
                'sucursal' => 0,
                'fecha_jor' => $fechaYmd,
                'hora' => $hora,
                'nro_x' => $nroX,
                'nro_z' => 0,
                'tipo' => 'X',
                'nombre_sucursal' => 'X mozo '.$mozo->nombre.' (gastro #'.$mozoId.')',
                'tot_venta' => $totalX,
                'tot_cred' => 0,
                'tot_iva' => 0,
                'tot_iva_cred' => 0,
                'ult_fact_b' => 0,
            ]);

            return $comprobante->fresh();
        });
    }

    /**
     * @return array<int, int> mozo_id => comprobante_id
     */
    private function comprobantesCierreXPorMozo(?JornadaGastronomia $jornada, ?int $salaId): array
    {
        if ($jornada === null) {
            return [];
        }
        $fechaYmd = $jornada->fecha_jornada?->format('Y-m-d');
        if ($fechaYmd === null || $fechaYmd === '') {
            return [];
        }
        $sala = $salaId !== null && $salaId > 0
            ? $salaId
            : ($jornada->sala_id !== null ? (int) $jornada->sala_id : 0);
        if ($sala <= 0) {
            return [];
        }

        $jornadaTesoreria = TesoreriaCrownJornada::query()
            ->where('sala_id', $sala)
            ->whereDate('fecha', $fechaYmd)
            ->value('id');
        if (! $jornadaTesoreria) {
            return [];
        }

        $comps = TesoreriaCrownComprobante::query()
            ->with(['cajero:id,mozo_gastronomia_id,tipo_tarea'])
            ->where('jornada_id', (int) $jornadaTesoreria)
            ->where('tipo', TesoreriaCrownComprobanteSupport::RENDICION)
            ->whereNull('revierte_id')
            ->whereHas('cajero', fn ($q) => $q->where('tipo_tarea', 'G'))
            ->get();

        $out = [];
        foreach ($comps as $comp) {
            $mozoId = (int) ($comp->cajero->mozo_gastronomia_id ?? 0);
            if ($mozoId > 0) {
                $out[$mozoId] = (int) $comp->id;
            }
        }

        return $out;
    }

    private function siguienteNroX(string $fechaYmd): int
    {
        $max = (int) TesoreriaCrownCierreFiscal::query()
            ->whereDate('fecha_jor', $fechaYmd)
            ->where('tipo', 'X')
            ->max('nro_x');

        return max(1, $max + 1);
    }

    private function aplicarVentana($q, ?JornadaGastronomia $jornada, ?string $fecha): void
    {
        if ($jornada !== null) {
            $desde = $jornada->apertura_en ?? $jornada->fecha_jornada?->startOfDay();
            $hasta = $jornada->cierre_en ?? now();

            if ($desde !== null) {
                $q->where('gastro_crown_cuenta.cerrada_at', '>=', $desde);
            }
            $q->where('gastro_crown_cuenta.cerrada_at', '<=', $hasta);

            return;
        }

        $q->whereDate('gastro_crown_cuenta.cerrada_at', $fecha ?: now()->format('Y-m-d'));
    }
}
