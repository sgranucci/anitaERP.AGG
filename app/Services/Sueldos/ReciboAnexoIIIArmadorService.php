<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Support\Sueldos\NumeroALetrasEs;
use App\Support\Sueldos\ReciboAnexoIIITortaSvg;
use App\Support\Sueldos\ReciboBaseCalculoSupport;
use App\Support\Sueldos\RubroCostoLaboral;
use Carbon\Carbon;

/**
 * Arma el DTO de vista del recibo Anexo III (Dto. 407) a partir de un recibo calculado.
 */
class ReciboAnexoIIIArmadorService
{
    /**
     * @return array<string, mixed>
     */
    public function armar(Liquidacion_Recibo_Sueldos $recibo): array
    {
        $recibo->loadMissing([
            'detalles',
            'liquidacion.empresa',
            'empleado.obrasocial',
            'empleado.categoria',
            'empleado.lugartrabajo',
            'empleado.sindicato',
            'empleado.art',
        ]);

        $liq = $recibo->liquidacion;
        $emp = $recibo->empleado;
        $empresa = $liq?->empresa;

        $conceptoIds = $recibo->detalles->pluck('concepto_id')->filter()->unique()->all();
        $conceptos = Concepto_Sueldos::query()
            ->whereIn('id', $conceptoIds)
            ->get(['id', 'rubro_costo_laboral', 'unidad_medida', 'tipo'])
            ->keyBy('id');

        $ce = [];
        $rem = [];
        $norem = [];
        $ded = [];
        $rubrosEmp = array_fill_keys(RubroCostoLaboral::todos(), 0.0);
        $rubrosTra = [
            RubroCostoLaboral::SEGURIDAD_SOCIAL => 0.0,
            RubroCostoLaboral::INSSJP => 0.0,
            RubroCostoLaboral::OBRA_SOCIAL => 0.0,
            RubroCostoLaboral::SINDICAL => 0.0,
            RubroCostoLaboral::OTROS => 0.0,
        ];

        foreach ($recibo->detalles as $d) {
            if (! $d->va_recibo && $d->tipo !== 'contribucion') {
                continue;
            }
            $imp = (float) $d->importe;
            if (abs($imp) < 0.0001) {
                continue;
            }
            $c = $conceptos->get($d->concepto_id);
            $unidad = ReciboBaseCalculoSupport::normalizarUnidad($c?->unidad_medida)
                ?: (ReciboBaseCalculoSupport::inferirUnidad(
                    (string) ($d->concepto_descripcion ?? ''),
                    null,
                    (string) $d->tipo
                ) ?? '');
            $basePersistida = $d->base_calculo !== null ? (float) $d->base_calculo : null;
            $base = ($basePersistida !== null && abs($basePersistida) > 0.0000001)
                ? $basePersistida
                : ReciboBaseCalculoSupport::derivar(
                    $imp,
                    (float) $d->cantidad,
                    (float) $d->valor,
                    $unidad,
                    abs((float) $d->cantidad - 1.0) > 0.0000001 || $unidad === '%',
                    abs((float) $d->valor) > 0.0000001,
                );
            $linea = [
                'codigo' => $d->concepto_codigo,
                'descripcion' => $d->concepto_descripcion ?: ($d->leyenda ?: ''),
                'unidad' => $this->fmtUnidad($d->cantidad, $unidad),
                'base' => $base,
                'monto' => $imp,
            ];

            if ($d->tipo === 'contribucion' || $d->columna === 'contribucion') {
                $rubro = $c?->rubro_costo_laboral
                    ?: RubroCostoLaboral::inferirDesdeDescripcion($linea['descripcion']);
                $ce[] = $linea + ['rubro' => $rubro];
                $rubrosEmp[$rubro] = ($rubrosEmp[$rubro] ?? 0) + $imp;

                continue;
            }

            if (in_array($d->tipo, ['descuento', 'aporte', 'retencion'], true) || $d->columna === 'descuento') {
                $ded[] = $linea;
                $rubroTra = RubroCostoLaboral::inferirDesdeDescripcion($linea['descripcion']);
                if (! array_key_exists($rubroTra, $rubrosTra)) {
                    $rubroTra = RubroCostoLaboral::OTROS;
                }
                $rubrosTra[$rubroTra] = ($rubrosTra[$rubroTra] ?? 0) + $imp;

                continue;
            }

            if ($d->tipo === 'no_remunerativo' || $d->tipo === 'asignacion') {
                $norem[] = $linea;
            } elseif ($d->tipo === 'informativo') {
                // no imprime en secciones principales
            } else {
                $rem[] = $linea;
            }
        }

        $subtotalCe = round(array_sum(array_column($ce, 'monto')), 2);
        $totRem = round((float) $recibo->total_remunerativo, 2);
        $totNorem = round((float) $recibo->total_no_remunerativo, 2);
        $totDed = round((float) $recibo->total_descuentos, 2);
        $neto = round((float) $recibo->neto_a_pagar, 2);

        $pieVals = [
            'Sueldo Neto' => max(0, $neto),
            RubroCostoLaboral::ETIQUETAS_TORTA[RubroCostoLaboral::SEGURIDAD_SOCIAL] => $rubrosEmp[RubroCostoLaboral::SEGURIDAD_SOCIAL] ?? 0,
            RubroCostoLaboral::ETIQUETAS_TORTA[RubroCostoLaboral::SINDICAL] => $rubrosEmp[RubroCostoLaboral::SINDICAL] ?? 0,
            RubroCostoLaboral::ETIQUETAS_TORTA[RubroCostoLaboral::OBRA_SOCIAL] => $rubrosEmp[RubroCostoLaboral::OBRA_SOCIAL] ?? 0,
            RubroCostoLaboral::ETIQUETAS_TORTA[RubroCostoLaboral::INSSJP] => $rubrosEmp[RubroCostoLaboral::INSSJP] ?? 0,
            RubroCostoLaboral::ETIQUETAS_TORTA[RubroCostoLaboral::ART] => $rubrosEmp[RubroCostoLaboral::ART] ?? 0,
            RubroCostoLaboral::ETIQUETAS_TORTA[RubroCostoLaboral::SCVO] => $rubrosEmp[RubroCostoLaboral::SCVO] ?? 0,
        ];

        $mesNom = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $mes = (int) ($liq->periodo_mes ?? 0);
        $anio = (int) ($liq->periodo_anio ?? 0);

        $fechaIngreso = $recibo->fecha_ingreso
            ? Carbon::parse($recibo->fecha_ingreso)->format('d-m-Y')
            : ($emp?->fecha_ingreso ? Carbon::parse($emp->fecha_ingreso)->format('d-m-Y') : '');

        $antig = $this->antiguedadTexto($recibo->fecha_ingreso ?? $emp?->fecha_ingreso, $liq?->fecha_pago ?? $liq?->fecha_liquidacion);

        return [
            'recibo' => $recibo,
            'empresa_linea' => trim(implode(' - ', array_filter([
                $empresa?->nombre,
                $empresa?->domicilio ?? null,
                $empresa?->nroinscripcion
                    ? $this->fmtCuit((string) $empresa->nroinscripcion)
                    : null,
            ]))),
            'legajo' => $recibo->legajo,
            'documento' => $emp?->documento ?? $recibo->legajo,
            'apellido_nombre' => $recibo->apellido_nombre,
            'categoria' => $recibo->categoria_desc ?: ($emp?->categoria?->descripcion ?? ''),
            'art' => $emp?->art?->descripcion ?? $emp?->art?->nombre ?? '',
            'banco' => $liq?->banco_deposito ?: (string) ($emp?->banco_codigo ?? ''),
            'periodo_dep' => $liq?->periodo_deposito
                ?: ($mes && $anio ? sprintf('%02d/%04d', $mes, $anio) : (string) ($liq->periodo ?? '')),
            'fecha_pago_aportes' => $liq?->fecha_ultimo_deposito
                ? Carbon::parse($liq->fecha_ultimo_deposito)->format('d-m-Y')
                : '',
            'convenio' => $emp?->sindicato?->descripcion ?? '',
            'fecha_ingreso' => $fechaIngreso,
            'periodo_liq' => trim(($liq?->descripcion ?? '').' '.($liq?->numero ? (string) $liq->numero : '')),
            'lugar_pago' => $liq?->lugar_pago ?? '',
            'fecha_pago' => $liq?->fecha_pago ? Carbon::parse($liq->fecha_pago)->format('d-m-Y') : '',
            'cuil' => $recibo->cuil,
            'lugar_trabajo' => $emp?->lugartrabajo?->descripcion ?? '',
            'tareas' => $recibo->categoria_desc ?? ($emp?->categoria?->descripcion ?? ''),
            'quincena' => in_array($liq?->tipo, ['quincena_1', 'quincena_2'], true)
                ? ($liq->tipo === 'quincena_1' ? '1' : '2')
                : '',
            'mes_anio' => trim(($mesNom[$mes] ?? '').' '.$anio),
            'remun_basica' => (float) ($recibo->sueldo_basico ?? 0),
            'antiguedad' => $antig,
            'obra_social' => trim(($emp?->obrasocial?->codigo ?? '').' '.($emp?->obrasocial?->descripcion ?? '')),
            'lineas_ce' => $ce,
            'subtotal_ce' => $subtotalCe,
            'lineas_rem' => $rem,
            'lineas_norem' => $norem,
            'lineas_ded' => $ded,
            'tot_rem' => $totRem,
            'tot_norem' => $totNorem,
            'tot_ded' => $totDed,
            'neto' => $neto,
            'neto_letras' => NumeroALetrasEs::entero($neto),
            'cuenta' => $emp?->cuenta_bancaria ?? '',
            'cbu' => $emp?->cbu ?? '',
            'banco_deposito' => $liq?->banco_deposito ?: (string) ($emp?->banco_codigo ?? ''),
            'rubros_emp' => $rubrosEmp,
            'rubros_tra' => $rubrosTra,
            'tot_sindical' => ($rubrosEmp[RubroCostoLaboral::SINDICAL] ?? 0) + ($rubrosTra[RubroCostoLaboral::SINDICAL] ?? 0),
            'tot_inssjp' => ($rubrosEmp[RubroCostoLaboral::INSSJP] ?? 0) + ($rubrosTra[RubroCostoLaboral::INSSJP] ?? 0),
            'tot_segsoc' => ($rubrosEmp[RubroCostoLaboral::SEGURIDAD_SOCIAL] ?? 0) + ($rubrosTra[RubroCostoLaboral::SEGURIDAD_SOCIAL] ?? 0),
            'tot_os' => ($rubrosEmp[RubroCostoLaboral::OBRA_SOCIAL] ?? 0) + ($rubrosTra[RubroCostoLaboral::OBRA_SOCIAL] ?? 0),
            'tot_art' => $rubrosEmp[RubroCostoLaboral::ART] ?? 0,
            'tot_scvo' => $rubrosEmp[RubroCostoLaboral::SCVO] ?? 0,
            'torta_svg' => ReciboAnexoIIITortaSvg::render($pieVals, 110),
            'pie_leyenda' => $pieVals,
            'firma_nombre' => config('sueldos.recibo_firma_nombre', 'ERIKA PEREZ'),
            'firma_cargo' => config('sueldos.recibo_firma_cargo', 'CAPITAL HUMANO'),
            'modo_preview' => false,
        ];
    }

    private function fmtUnidad($cantidad, ?string $unidad): string
    {
        $c = (float) $cantidad;
        if (abs($c) < 0.0000001 || abs($c - 1.0) < 0.0000001 && ($unidad === null || $unidad === '')) {
            if ($unidad === null || $unidad === '') {
                return abs($c - 1.0) < 0.0000001 ? '' : rtrim(rtrim(number_format($c, 2, ',', '.'), '0'), ',');
            }
        }
        $num = rtrim(rtrim(number_format($c, 2, ',', '.'), '0'), ',');
        $u = trim((string) $unidad);

        return $u === '' ? $num : $num.$u;
    }

    private function fmtCuit(string $cuit): string
    {
        $d = preg_replace('/\D/', '', $cuit) ?? '';
        if (strlen($d) === 11) {
            return substr($d, 0, 2).'-'.substr($d, 2, 8).'-'.substr($d, 10, 1);
        }

        return $cuit;
    }

    private function antiguedadTexto($fechaIngreso, $fechaRef): string
    {
        if (! $fechaIngreso) {
            return '';
        }
        try {
            $a = Carbon::parse($fechaIngreso);
            $b = $fechaRef ? Carbon::parse($fechaRef) : Carbon::now();
            $y = $a->diffInYears($b);
            $a2 = $a->copy()->addYears($y);
            $m = $a2->diffInMonths($b);
            $a3 = $a2->copy()->addMonths($m);
            $d = $a3->diffInDays($b);

            return sprintf('%d anos', $y).sprintf(' %02dD %02dM %02dA', $d, $m, $y);
        } catch (\Throwable) {
            return '';
        }
    }
}
