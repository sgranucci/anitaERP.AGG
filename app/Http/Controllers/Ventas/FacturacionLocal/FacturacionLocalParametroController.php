<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Http\Controllers\Controller;
use App\Models\Ventas\FacturacionLocalParametro;
use App\Models\Ventas\LocalVenta;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalParametroSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
                $valor = $this->normalizarShopping($clave, $valor);
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

    private function normalizarShopping(string $clave, string $valor): string
    {
        return match ($clave) {
            FacturacionLocalParametroSupport::CLAVE_SHOPPING_EMAIL => $this->normalizarEmailsShopping($valor),
            FacturacionLocalParametroSupport::CLAVE_SHOPPING_NRO_LOCAL => $this->normalizarDigitosShopping($clave, $valor, 4),
            FacturacionLocalParametroSupport::CLAVE_SHOPPING_CONTRATO => $this->normalizarDigitosShopping($clave, $valor, 10),
            FacturacionLocalParametroSupport::CLAVE_SHOPPING_CAJA => $this->normalizarDigitosShopping($clave, $valor, 2) ?: '01',
            FacturacionLocalParametroSupport::CLAVE_SHOPPING_HORA => $this->normalizarHoraShopping($valor),
            FacturacionLocalParametroSupport::CLAVE_SHOPPING_HABILITADO => $this->normalizarHabilitadoShopping($valor),
            FacturacionLocalParametroSupport::CLAVE_SHOPPING_LOCAL_CODIGO => $this->normalizarLocalShopping($valor),
            default => $valor,
        };
    }

    private function normalizarEmailsShopping(string $valor): string
    {
        if ($valor === '') {
            return '';
        }
        $partes = preg_split('/[,;\s]+/', $valor) ?: [];
        $ok = [];
        $mal = [];
        foreach ($partes as $parte) {
            $parte = trim((string) $parte);
            if ($parte === '') {
                continue;
            }
            if (filter_var($parte, FILTER_VALIDATE_EMAIL)) {
                $ok[] = $parte;
            } else {
                $mal[] = $parte;
            }
        }
        if ($mal !== []) {
            throw ValidationException::withMessages([
                'valores.'.FacturacionLocalParametroSupport::CLAVE_SHOPPING_EMAIL => 'Correo inválido: '.implode(', ', $mal),
            ]);
        }

        return implode(', ', array_values(array_unique($ok)));
    }

    private function normalizarDigitosShopping(string $clave, string $valor, int $largo): string
    {
        $digitos = preg_replace('/\D+/', '', $valor) ?? '';
        if ($digitos === '') {
            return '';
        }
        if (strlen($digitos) > $largo) {
            throw ValidationException::withMessages([
                'valores.'.$clave => 'Como máximo '.$largo.' dígitos.',
            ]);
        }

        return str_pad($digitos, $largo, '0', STR_PAD_LEFT);
    }

    private function normalizarHoraShopping(string $valor): string
    {
        if ($valor === '') {
            return '21:00';
        }
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $valor) !== 1) {
            throw ValidationException::withMessages([
                'valores.'.FacturacionLocalParametroSupport::CLAVE_SHOPPING_HORA => 'La hora tiene que ser HH:MM, por ejemplo 21:00.',
            ]);
        }

        return $valor;
    }

    private function normalizarHabilitadoShopping(string $valor): string
    {
        $valor = strtolower($valor);
        if (in_array($valor, ['1', 'si', 'sí', 's', 'true'], true)) {
            return '1';
        }
        if ($valor === '' || in_array($valor, ['0', 'no', 'n', 'false'], true)) {
            return '0';
        }

        throw ValidationException::withMessages([
            'valores.'.FacturacionLocalParametroSupport::CLAVE_SHOPPING_HABILITADO => 'Use 1 para activar el envío automático o 0 para apagarlo.',
        ]);
    }

    private function normalizarLocalShopping(string $valor): string
    {
        if ($valor === '') {
            return '';
        }
        $existe = LocalVenta::query()->where('codigo', $valor)->exists();
        if (! $existe) {
            throw ValidationException::withMessages([
                'valores.'.FacturacionLocalParametroSupport::CLAVE_SHOPPING_LOCAL_CODIGO => 'No hay un local con código '.$valor.'.',
            ]);
        }

        return $valor;
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
