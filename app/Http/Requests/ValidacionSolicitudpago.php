<?php

namespace App\Http\Requests;

use App\Models\Solicitudpago\Concepto_Solicitudpago;
use App\Models\Solicitudpago\Solicitudpago;
use App\Support\Solicitudpago\ConceptoSolicitudpagoFormaPago;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionSolicitudpago extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('monto')) {
            $merge['monto'] = $this->parseMontoAr($this->input('monto'));
        }

        foreach (['montos_debe', 'montos_haber', 'montos_cuenta', 'montos_cuota'] as $campo) {
            $arr = $this->input($campo);
            if (! is_array($arr)) {
                continue;
            }
            $merge[$campo] = array_map(fn ($v) => $this->parseMontoAr($v), $arr);
        }

        // Fecha entrega vacía → misma fecha de la SP.
        $fechaEntrega = trim((string) $this->input('fecha_entrega', ''));
        $fechaSp = trim((string) $this->input('fecha', ''));
        if ($fechaEntrega === '' && $fechaSp !== '') {
            $merge['fecha_entrega'] = $fechaSp;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Acepta 1234.56 / 1.234,56 / 1234,56 y devuelve float (o null si vacío).
     */
    private function parseMontoAr($valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_int($valor) || is_float($valor)) {
            return round((float) $valor, 2);
        }
        $t = trim(str_replace(' ', '', (string) $valor));
        if ($t === '') {
            return null;
        }
        if (str_contains($t, ',')) {
            $t = str_replace('.', '', $t);
            $t = str_replace(',', '.', $t);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $t)) {
            $t = str_replace('.', '', $t);
        }
        if (! is_numeric($t)) {
            return null;
        }

        return round((float) $t, 2);
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'codigo' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('solicitudpago', 'codigo')->ignore($id),
            ],
            'empresa_id' => 'required|exists:empresa,id',
            'fecha' => 'required|date',
            'tratamiento' => ['required', Rule::in(array_column(SolicitudpagoTratamientos::opciones(), 'valor'))],
            'proveedor_id' => 'nullable|exists:proveedor,id',
            'concepto_solicitudpago_id' => 'nullable|exists:concepto_solicitudpago,id',
            'formapagosol_id' => 'required|exists:formapagosol,id',
            'moneda_id' => 'required|exists:moneda,id',
            'beneficiario' => 'nullable|string|max:80',
            'endoso' => 'nullable|string|max:80',
            'fecha_entrega' => 'nullable|date',
            'fecha_vencimiento' => 'required|date',
            'monto' => 'required|numeric|min:0',
            'observacion' => 'nullable|string|max:160',
            'estado' => ['nullable', Rule::in(array_column(SolicitudpagoEstados::opciones(), 'valor'))],
            'sector_solicitudpago_id' => 'nullable|exists:sector_solicitudpago,id',
            'detalle' => 'required|string|max:180',
            'solicitudpago_madre_id' => 'nullable|exists:solicitudpago,id',
            'empresa_ids' => 'nullable|array',
            'empresa_ids.*' => 'nullable|exists:empresa,id',
            'cuentacontable_ids' => 'nullable|array',
            'cuentacontable_ids.*' => 'nullable|exists:cuentacontable,id',
            'centrocosto_ids' => 'nullable|array',
            'centrocosto_ids.*' => 'nullable|exists:centrocosto,id',
            'debe_haberes' => 'nullable|array',
            'debe_haberes.*' => 'nullable|in:D,H',
            'montos_cuenta' => 'nullable|array',
            'montos_cuenta.*' => 'nullable|numeric',
            'montos_debe' => 'nullable|array',
            'montos_debe.*' => 'nullable|numeric|min:0',
            'montos_haber' => 'nullable|array',
            'montos_haber.*' => 'nullable|numeric|min:0',
            'nro_cuotas' => 'nullable|array',
            'nro_cuotas.*' => 'nullable|integer|min:1',
            'fecha_vencimientos_cuota' => 'nullable|array',
            'fecha_vencimientos_cuota.*' => 'nullable|date',
            'montos_cuota' => 'nullable|array',
            'montos_cuota.*' => 'nullable|numeric',
            'solicitudpago_hija_ids' => 'nullable|array',
            'solicitudpago_hija_ids.*' => 'nullable|exists:solicitudpago,id',
            'archivo_ids_existentes' => 'nullable|array',
            'archivo_ids_existentes.*' => 'nullable|integer',
            'nombrearchivos' => 'nullable|array',
            'nombrearchivos.*' => 'nullable|file|max:10240',
            'archivos_nuevos' => 'nullable|array',
            'archivos_nuevos.*' => 'nullable|file|max:10240',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->validarAsientoObligatorio($validator);
            $this->validarCuotasSegunConcepto($validator);
        });
    }

    private function validarAsientoObligatorio($validator): void
    {
        $cuentaIds = $this->input('cuentacontable_ids', []);
        if (! is_array($cuentaIds)) {
            $cuentaIds = [];
        }
        $montosDebe = $this->input('montos_debe');
        $montosHaber = $this->input('montos_haber');
        $montos = $this->input('montos_cuenta', []);
        $dhs = $this->input('debe_haberes', []);

        $ok = false;
        $totalDebe = 0.0;
        $totalHaber = 0.0;

        foreach ($cuentaIds as $i => $cuentaId) {
            if ((int) $cuentaId <= 0) {
                continue;
            }
            if (is_array($montosDebe) || is_array($montosHaber)) {
                $debe = (float) ($montosDebe[$i] ?? 0);
                $haber = (float) ($montosHaber[$i] ?? 0);
                $totalDebe += max(0, $debe);
                $totalHaber += max(0, $haber);
                if ($debe > 0 || $haber > 0) {
                    $ok = true;
                }
            } else {
                $monto = (float) ($montos[$i] ?? 0);
                $dh = strtoupper((string) ($dhs[$i] ?? 'D'));
                if ($monto > 0 && in_array($dh, ['D', 'H'], true)) {
                    $ok = true;
                    if ($dh === 'H') {
                        $totalHaber += $monto;
                    } else {
                        $totalDebe += $monto;
                    }
                }
            }
        }

        if (! $ok) {
            $validator->errors()->add(
                'cuentacontable_ids',
                'Debe cargar al menos una cuenta contable con importe en Debe o Haber (solapa Cuentas).'
            );

            return;
        }

        if (abs($totalDebe - $totalHaber) >= 0.009) {
            $validator->errors()->add(
                'montos_debe',
                'El asiento no balancea: Debe ('.number_format($totalDebe, 2, ',', '.').') debe ser igual a Haber ('.number_format($totalHaber, 2, ',', '.').').'
            );

            return;
        }

        // Con asiento balanceado, Debe (= Haber) debe coincidir con el monto de la SP.
        $montoSp = (float) ($this->input('monto') ?? 0);
        $totalAsiento = $totalDebe;
        if (abs($totalAsiento - $montoSp) >= 0.009) {
            $validator->errors()->add(
                'monto',
                'El total del asiento contable ('.number_format($totalAsiento, 2, ',', '.').') '
                .'debe ser igual al monto de la solicitud ('.number_format($montoSp, 2, ',', '.').').'
            );
        }
    }

    private function validarCuotasSegunConcepto($validator): void
    {
        $formaConcepto = $this->formaPagoConceptoDesdeRequest();
        if (! ConceptoSolicitudpagoFormaPago::requiereCuotas($formaConcepto, $this->solicitudpagoMadreIdDesdeRequest())) {
            return;
        }

        $vtos = $this->input('fecha_vencimientos_cuota', []);
        $montos = $this->input('montos_cuota', []);
        if (! is_array($vtos)) {
            $vtos = [];
        }
        if (! is_array($montos)) {
            $montos = [];
        }

        $ok = false;
        $n = max(count($vtos), count($montos));
        for ($i = 0; $i < $n; $i++) {
            $vto = trim((string) ($vtos[$i] ?? ''));
            $monto = (float) ($montos[$i] ?? 0);
            if ($vto !== '' && $monto > 0) {
                $ok = true;
                break;
            }
        }

        if (! $ok) {
            $validator->errors()->add(
                'nro_cuotas',
                'El concepto requiere cuotas: cargue al menos una cuota con vencimiento e importe (solapa Cuotas).'
            );
        }
    }

    /** SP hija: el plan de cuotas está en la madre, no en este comprobante. */
    private function solicitudpagoMadreIdDesdeRequest(): int
    {
        $madreId = (int) $this->input('solicitudpago_madre_id', 0);
        if ($madreId > 0) {
            return $madreId;
        }

        $id = (int) $this->route('id');
        if ($id <= 0) {
            return 0;
        }

        return (int) Solicitudpago::query()->whereKey($id)->value('solicitudpago_madre_id');
    }

    private function formaPagoConceptoDesdeRequest(): ?string
    {
        $conceptoId = (int) $this->input('concepto_solicitudpago_id', 0);
        if ($conceptoId <= 0) {
            return null;
        }

        $forma = Concepto_Solicitudpago::query()
            ->whereKey($conceptoId)
            ->value('forma_pago');

        return $forma !== null ? (string) $forma : null;
    }

    public function attributes()
    {
        return [
            'empresa_id' => 'empresa',
            'concepto_solicitudpago_id' => 'concepto',
            'formapagosol_id' => 'forma de pago',
            'sector_solicitudpago_id' => 'sector',
            'moneda_id' => 'moneda',
            'fecha_vencimiento' => 'vencimiento',
            'proveedor_id' => 'proveedor',
            'detalle' => 'detalle',
            'cuentacontable_ids' => 'asiento contable',
            'nro_cuotas' => 'cuotas',
        ];
    }
}
