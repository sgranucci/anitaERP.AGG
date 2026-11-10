<?php

namespace App\Http\Controllers\Ventas\GastronomiaCrown;

use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Sala;
use App\Models\Ventas\GastronomiaCrown\GastroCrownSalaCuentacaja;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * ABM master-detail: sala → N cuentas de caja (efectivo MN/ME y medios exclusivos).
 */
class GastronomiaCrownSalaCuentacajaController extends Controller
{
    public function index(): View
    {
        $this->assertCrown();
        can('listar-gastro-crown-sala-cuentacaja');

        $mapas = GastroCrownSalaCuentacaja::query()
            ->with(['cuentacaja.monedas:id,abreviatura'])
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->groupBy('sala_id');

        $salas = Sala::query()
            ->where('codigo', '!=', 'AC')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'empresa_id']);

        $salasPayload = $salas->map(function (Sala $sala) use ($mapas) {
            $lineas = ($mapas->get($sala->id) ?? collect())->map(function (GastroCrownSalaCuentacaja $mapa) {
                $cc = $mapa->cuentacaja;

                return [
                    'id' => (int) $mapa->id,
                    'cuentacaja_id' => $cc ? (int) $cc->id : (int) $mapa->cuentacaja_id,
                    'codigo' => (string) ($cc->codigo ?? ''),
                    'nombre' => (string) ($cc->nombre ?? ''),
                    'moneda_abreviatura' => $cc?->monedas?->abreviatura,
                    'es_default_f5' => (bool) $mapa->es_default_f5,
                    'orden' => (int) $mapa->orden,
                ];
            })->values()->all();

            return [
                'id' => (int) $sala->id,
                'codigo' => (string) $sala->codigo,
                'nombre' => (string) $sala->nombre,
                'empresa_id' => $sala->empresa_id ? (int) $sala->empresa_id : null,
                'lineas' => $lineas,
                'cantidad' => count($lineas),
            ];
        })->values()->all();

        return view('ventas.gastronomia_crown.configuracion_cuentacaja_sala.index', [
            'salas' => $salasPayload,
            'puedeEditar' => can('editar-gastro-crown-sala-cuentacaja', false)
                || can('actualizar-gastro-crown-sala-cuentacaja', false),
        ]);
    }

    public function actualizar(Request $request): RedirectResponse
    {
        $this->assertCrown();
        can('actualizar-gastro-crown-sala-cuentacaja');

        $request->validate([
            'salas' => 'nullable|array',
            'salas.*.sala_id' => 'required|integer|min:1',
            'salas.*.lineas' => 'nullable|array',
            'salas.*.lineas.*.cuentacaja_id' => 'nullable|integer|min:1',
            'salas.*.lineas.*.es_default_f5' => 'nullable',
            'salas.*.lineas.*.orden' => 'nullable|integer|min:0',
        ]);

        $salasInput = $request->input('salas', []);
        if (! is_array($salasInput)) {
            $salasInput = [];
        }

        try {
            DB::transaction(function () use ($salasInput) {
                foreach ($salasInput as $bloque) {
                    $salaId = (int) ($bloque['sala_id'] ?? 0);
                    if ($salaId <= 0) {
                        continue;
                    }

                    $lineasIn = is_array($bloque['lineas'] ?? null) ? $bloque['lineas'] : [];
                    $normalizadas = [];
                    $orden = 0;
                    $defaultYa = false;

                    foreach ($lineasIn as $linea) {
                        $ccId = (int) ($linea['cuentacaja_id'] ?? 0);
                        if ($ccId <= 0) {
                            continue;
                        }
                        if (! Cuentacaja::query()->whereKey($ccId)->exists()) {
                            throw new \InvalidArgumentException(
                                'Cuenta de caja id '.$ccId.' inexistente (sala #'.$salaId.').'
                            );
                        }
                        if (isset($normalizadas[$ccId])) {
                            continue;
                        }
                        $esDefault = ! $defaultYa && $this->truthy($linea['es_default_f5'] ?? false);
                        if ($esDefault) {
                            $defaultYa = true;
                        }
                        $orden++;
                        $normalizadas[$ccId] = [
                            'cuentacaja_id' => $ccId,
                            'es_default_f5' => $esDefault,
                            'orden' => (int) ($linea['orden'] ?? $orden),
                        ];
                    }

                    $idsConservar = [];
                    foreach ($normalizadas as $n) {
                        $row = GastroCrownSalaCuentacaja::query()->updateOrCreate(
                            [
                                'sala_id' => $salaId,
                                'cuentacaja_id' => $n['cuentacaja_id'],
                            ],
                            [
                                'es_default_f5' => $n['es_default_f5'],
                                'orden' => $n['orden'],
                            ]
                        );
                        $idsConservar[] = (int) $row->id;
                    }

                    $borrar = GastroCrownSalaCuentacaja::query()->where('sala_id', $salaId);
                    if ($idsConservar !== []) {
                        $borrar->whereNotIn('id', $idsConservar);
                    }
                    EloquentAuditDeleteSupport::each($borrar);
                }
            });
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('consultar_gastro_crown_sala_cuentacaja')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('consultar_gastro_crown_sala_cuentacaja')
            ->with('mensaje', 'Mapa sala → cuentas de caja actualizado.');
    }

    private function truthy(mixed $v): bool
    {
        return in_array($v, [1, '1', true, 'true', 'on', 'yes'], true);
    }

    private function assertCrown(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            abort(404);
        }
    }
}
