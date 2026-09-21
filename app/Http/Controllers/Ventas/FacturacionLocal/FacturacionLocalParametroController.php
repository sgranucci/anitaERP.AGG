<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Http\Controllers\Controller;
use App\Models\Ventas\FacturacionLocalParametro;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalParametroSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacturacionLocalParametroController extends Controller
{
    public function index()
    {
        $this->assertFerli();
        can('editar-facturacion-local-parametro');

        $filas = $this->filasParaFormulario();

        return view('ventas.facturacion_local.parametros.editar', [
            'filas' => $filas,
            'formula' => \App\Support\Ventas\FacturacionLocal\FacturacionLocalCostoFabricaSupport::etiquetaFormula(),
        ]);
    }

    public function actualizar(Request $request)
    {
        $this->assertFerli();
        can('actualizar-facturacion-local-parametro');

        $request->validate([
            'valores' => 'required|array',
            'valores.*' => 'nullable|string|max:500',
        ]);

        $valores = $request->input('valores', []);
        $defs = FacturacionLocalParametroSupport::DEFINICIONES;

        DB::transaction(function () use ($valores, $defs) {
            foreach ($defs as $def) {
                $clave = $def['clave'];
                $valor = trim((string) ($valores[$clave] ?? ''));
                if ($clave === FacturacionLocalParametroSupport::CLAVE_COSTO_DESCUENTO_PCT) {
                    $valor = $this->normalizarPct($valor);
                }
                if ($clave === FacturacionLocalParametroSupport::CLAVE_COSTO_LISTAS_FABRICA) {
                    $valor = $this->normalizarListas($valor);
                }
                FacturacionLocalParametro::query()->updateOrCreate(
                    ['clave' => $clave],
                    [
                        'valor' => $valor,
                        'etiqueta' => $def['etiqueta'],
                        'ayuda' => $def['ayuda'],
                        'orden' => $def['orden'],
                    ]
                );
            }
        });

        FacturacionLocalParametroSupport::forget();

        return redirect()
            ->route('facturacion_local_parametros')
            ->with('mensaje', 'Parámetros de Facturación Local guardados.');
    }

    /**
     * @return list<array{clave:string,etiqueta:string,ayuda:string,orden:int,valor:string}>
     */
    private function filasParaFormulario(): array
    {
        $mapa = FacturacionLocalParametroSupport::mapa();
        $filas = [];
        foreach (FacturacionLocalParametroSupport::DEFINICIONES as $def) {
            $filas[] = [
                'clave' => $def['clave'],
                'etiqueta' => $def['etiqueta'],
                'ayuda' => $def['ayuda'],
                'orden' => $def['orden'],
                'valor' => (string) ($mapa[$def['clave']] ?? ''),
            ];
        }

        return $filas;
    }

    private function normalizarPct(string $valor): string
    {
        $valor = str_replace(',', '.', trim($valor));
        if ($valor === '' || ! is_numeric($valor)) {
            return (string) config('facturacion_local.costo_descuento_pct', 67);
        }
        $pct = max(0.0, min(100.0, (float) $valor));

        return (string) (abs($pct - round($pct)) < 0.0001 ? (int) round($pct) : round($pct, 2));
    }

    private function normalizarListas(string $valor): string
    {
        $partes = [];
        foreach (explode(',', $valor) as $c) {
            $c = trim($c);
            if ($c !== '') {
                $partes[] = $c;
            }
        }
        if ($partes === []) {
            return implode(',', config('facturacion_local.costo_listas_fabrica_codigos', ['1', '2', '3', '4', '5']));
        }

        return implode(',', array_values(array_unique($partes)));
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
