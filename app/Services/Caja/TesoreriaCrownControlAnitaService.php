<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownCierreFiscal;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownPoderPublico;
use App\Support\Caja\TesoreriaCrownAnexo1Calculo;
use App\Support\Caja\TesoreriaCrownAnexo8Calculo;
use App\Support\Caja\TesoreriaCrownComprobanteSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Control diario ERP contra Anita mientras conviven los dos sistemas. Solo lectura: Anita por SELECT y
 * los anexos del ERP dentro de una transacción que se revierte (sus cálculos graban caché).
 */
class TesoreriaCrownControlAnitaService
{
    private const TOLERANCIA = 0.01;

    /**
     * @return list<array{fecha: string, area: string, concepto: string, erp: ?float, anita: ?float, dif: ?float, ok: bool, nota: string}>
     */
    public function controlar(string $desde, string $hasta): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new RuntimeException('El control contra Anita solo corre en Crown.');
        }
        $out = [];
        $pendiente = DB::table('tesoreria_crown_jornada')
            ->where('recalculo_pendiente', true)
            ->min('fecha');
        if ($pendiente !== null) {
            $out[] = [
                'fecha' => substr((string) $pendiente, 0, 10),
                'area' => 'Recálculo',
                'concepto' => 'Recálculo hacia adelante pendiente',
                'erp' => null,
                'anita' => null,
                'dif' => null,
                'ok' => false,
                'nota' => 'Poder público y VIII grabados pueden estar viejos: se recalculan al abrir un anexo.',
            ];
        }
        for ($dia = strtotime($desde); $dia <= strtotime($hasta); $dia = strtotime('+1 day', $dia)) {
            foreach ($this->controlarDia(date('Y-m-d', $dia)) as $fila) {
                $out[] = $fila;
            }
        }

        return $out;
    }

    /**
     * @return list<array{fecha: string, area: string, concepto: string, erp: ?float, anita: ?float, dif: ?float, ok: bool, nota: string}>
     */
    public function controlarDia(string $fecha): array
    {
        $fechaInt = (int) str_replace('-', '', $fecha);
        $jornada = TesoreriaCrownJornada::query()->whereDate('fecha', $fecha)->first();
        $filas = [];
        $agregar = function (string $area, string $concepto, ?float $erp, ?float $anita, string $nota = '') use (&$filas, $fecha): void {
            $dif = $erp !== null && $anita !== null ? round($erp - $anita, 2) : null;
            $filas[] = [
                'fecha' => $fecha,
                'area' => $area,
                'concepto' => $concepto,
                'erp' => $erp,
                'anita' => $anita,
                'dif' => $dif,
                'ok' => $dif !== null ? abs($dif) < self::TOLERANCIA : ($erp === null && $anita === null),
                'nota' => $nota,
            ];
        };

        $efectivo = $this->consultar(
            'tipo,cantidad,total_x',
            'SELECT efe_tipo_oper, COUNT(*), SUM(efe_total_x) FROM efectivo WHERE efe_fecha = '.$fechaInt
            // Caja 0: habilitaciones vacías sin rendición que el import no trae.
            .' AND efe_tipo_oper IN ("I","F") AND efe_caja > 0 GROUP BY 1'
        );
        if ($jornada === null) {
            if ($efectivo !== []) {
                $agregar('Jornada', 'Jornada ERP', null, 1.0, 'Anita operó y el ERP no tiene jornada');
            }

            return $filas;
        }

        $anitaEfe = [];
        foreach ($efectivo as $fila) {
            $anitaEfe[trim((string) $fila->tipo)] = $fila;
        }
        $erpEfe = TesoreriaCrownComprobante::query()
            ->where('jornada_id', $jornada->id)
            ->selectRaw('tipo, COUNT(*) cantidad, SUM(total_x) total_x')
            ->groupBy('tipo')
            ->get()
            ->keyBy('tipo');
        $hab = TesoreriaCrownComprobanteSupport::HABILITACION;
        $ren = TesoreriaCrownComprobanteSupport::RENDICION;
        $agregar('Cajas', 'Habilitaciones', (float) ($erpEfe[$hab]->cantidad ?? 0), (float) ($anitaEfe['I']->cantidad ?? 0));
        $agregar('Cajas', 'Rendiciones', (float) ($erpEfe[$ren]->cantidad ?? 0), (float) ($anitaEfe['F']->cantidad ?? 0));
        $agregar('Cajas', 'X a rendir', round((float) ($erpEfe[$ren]->total_x ?? 0), 2), round((float) ($anitaEfe['F']->total_x ?? 0), 2));

        $zAnita = $this->consultar(
            'cantidad,venta,cred',
            'SELECT COUNT(*), SUM(cief_tot_venta), SUM(cief_tot_cred) FROM cierrefiscal WHERE cief_fecha_jor = '.$fechaInt.' AND cief_nro_z > 0',
            'ventas'
        )[0] ?? null;
        $zErp = TesoreriaCrownCierreFiscal::query()
            ->whereDate('fecha_jor', $fecha)
            ->where('nro_z', '>', 0)
            ->selectRaw('COUNT(*) cantidad, SUM(tot_venta) venta, SUM(tot_cred) cred')
            ->first();
        $agregar('Cierre fiscal', 'Cierres Z', (float) ($zErp->cantidad ?? 0), (float) ($zAnita->cantidad ?? 0));
        $agregar('Cierre fiscal', 'Venta Z', round((float) ($zErp->venta ?? 0), 2), round((float) ($zAnita->venta ?? 0), 2));
        $agregar('Cierre fiscal', 'Crédito Z', round((float) ($zErp->cred ?? 0), 2), round((float) ($zAnita->cred ?? 0), 2));

        $bovAnita = [];
        foreach ($this->consultar(
            'cod,cantidad',
            'SELECT efeb_cod_trans, COUNT(*) FROM efeboveda WHERE efeb_fecha = '.$fechaInt.' GROUP BY 1'
        ) as $fila) {
            $bovAnita[(int) $fila->cod] = (float) $fila->cantidad;
        }
        $bovErp = [];
        foreach (TesoreriaCrownBoveda::query()
            ->with('tipo')
            ->where('jornada_id', $jornada->id)
            ->whereNull('comprobante_id')
            ->whereNull('revierte_id')
            ->whereDoesntHave('tipo', fn ($q) => $q->where('abreviatura', 'APER'))
            ->get() as $mov) {
            $codigo = (int) ($mov->tipo->codigo ?? 0);
            $bovErp[$codigo] = ($bovErp[$codigo] ?? 0) + 1;
        }
        $agregar('Bóveda', 'Movimientos', (float) array_sum($bovErp), (float) array_sum($bovAnita));
        foreach (array_unique(array_merge(array_keys($bovErp), array_keys($bovAnita))) as $codigo) {
            if (($bovErp[$codigo] ?? 0) != ($bovAnita[$codigo] ?? 0)) {
                $agregar('Bóveda', 'Tipo '.$codigo, (float) ($bovErp[$codigo] ?? 0), (float) ($bovAnita[$codigo] ?? 0));
            }
        }

        $publico = $this->consultar('cantidad', 'SELECT ingr_cantidad FROM ingreso WHERE ingr_fecha = '.$fechaInt)[0] ?? null;
        $agregar('Jornada', 'Público', (float) ($jornada->clientes ?? 0), $publico ? (float) $publico->cantidad : null);

        $ppAnita = $this->consultar(
            'fd,fm,fa,sd,sm,sa',
            'SELECT ppub_ppublico, ppub_ppublico_mes, ppub_ppublico_acum, ppub_pp_slot, ppub_pp_slot_mes, ppub_pp_slot_acum FROM ppublico WHERE ppub_fecha = '.$fechaInt
        )[0] ?? null;
        $ppErp = TesoreriaCrownPoderPublico::deSalaFecha((int) $jornada->sala_id, $fecha);
        if ($ppAnita !== null || $ppErp !== null) {
            $entero = fn ($v) => $v === null ? null : ((float) $v < 0 ? ceil((float) $v) : floor((float) $v));
            foreach ([
                ['Fichas día', 'ficha_dia', 'fd'], ['Fichas mes', 'ficha_mes', 'fm'], ['Fichas acumulado', 'ficha_acum', 'fa'],
                ['Slot día', 'slot_dia', 'sd'], ['Slot mes', 'slot_mes', 'sm'], ['Slot acumulado', 'slot_acum', 'sa'],
            ] as [$concepto, $campoErp, $campoAnita]) {
                $agregar(
                    'Poder público',
                    $concepto,
                    $entero($ppErp?->{$campoErp}),
                    $ppAnita ? (float) $ppAnita->{$campoAnita} : null,
                    'Anita graba sin centavos'
                );
            }
        }

        $acum = [];
        $ayerInt = (int) date('Ymd', strtotime($fecha.' -1 day'));
        foreach ($this->consultar(
            'fecha,item,diario',
            'SELECT acan1_fecha, acan1_item, acan1_diario FROM acumanexo1 WHERE acan1_fecha IN ('.$ayerInt.','.$fechaInt.') AND acan1_item IN (101,130)'
        ) as $fila) {
            $acum[(int) $fila->fecha][(int) $fila->item] = (float) $fila->diario;
        }
        $anexos = $this->anexosErp($jornada);
        if (isset($acum[$fechaInt][130])) {
            $agregar('Anexo VIII', 'Caja final', $anexos['viii'], round($acum[$fechaInt][130], 2));
        }
        if (isset($acum[$fechaInt][101], $acum[$ayerInt][101]) && $anexos['variacion'] !== null) {
            $agregar(
                'Anexo I',
                'Variación de la caja',
                $anexos['variacion'],
                round($acum[$fechaInt][101] - $acum[$ayerInt][101], 2),
                'Anita: caja final por variación (ítem 101) contra el día anterior'
            );
        }
        if ($anexos['diferencia'] !== null) {
            $agregar('Anexo I', 'Diferencia ERP (VIII − variación)', $anexos['diferencia'], 0.0, 'Distinto de cero: faltante o sobrante sin explicar en el día');
        }

        return $filas;
    }

    /**
     * @return array{viii: ?float, variacion: ?float, diferencia: ?float}
     */
    private function anexosErp(TesoreriaCrownJornada $jornada): array
    {
        $out = ['viii' => null, 'variacion' => null, 'diferencia' => null];
        DB::beginTransaction();
        try {
            $out['viii'] = round(TesoreriaCrownAnexo8Calculo::totalCacheadoOCalcular($jornada, true), 2);
            foreach (TesoreriaCrownAnexo1Calculo::deJornada($jornada)['filas'] ?? [] as $fila) {
                $concepto = (string) ($fila['concepto'] ?? '');
                if (str_starts_with($concepto, 'Variaci')) {
                    $out['variacion'] = round((float) ($fila['dia'] ?? 0), 2);
                } elseif ($concepto === 'Diferencia') {
                    $out['diferencia'] = round((float) ($fila['dia'] ?? 0), 2);
                }
            }
        } catch (Throwable) {
            // Sin anexos el resto del control sigue valiendo.
        } finally {
            DB::rollBack();
        }

        return $out;
    }

    /**
     * @return list<object>
     */
    private function consultar(string $campos, string $sql, ?string $sistema = null): array
    {
        $payload = [
            'acc' => 'customSql',
            'campos' => $campos,
            'customSql' => $sql,
            'sistema' => $sistema ?? config('caja_anita.sistema'),
        ];
        $ifx = trim((string) config('caja_anita.ifx_server', ''));
        if ($ifx !== '') {
            $payload['ifx_server'] = $ifx;
        }
        $path = rtrim(trim((string) config('caja_anita.path_sistema', '')), '/');
        if ($path !== '') {
            $payload['path_sistema'] = $path;
        }
        $raw = (new ApiAnita)->apiCall($payload);
        $error = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($error !== null && $error !== '') {
            throw new RuntimeException($error);
        }
        $filas = json_decode(is_string($raw) ? $raw : '[]');

        return is_array($filas) ? $filas : [];
    }
}
