<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownBovedaLinea;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownValor;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownBovedaSupport;
use App\Support\Caja\TesoreriaCrownComprobanteSupport;
use App\Support\Caja\TesoreriaCrownFirmaSupport;
use App\Support\Caja\TesoreriaCrownNumeradorSupport;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class TesoreriaCrownBovedaController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $jornada = $this->jornadaPedida($request, $sala ? (int) $sala->id : 0);
        $lugares = $sala ? TesoreriaCrownBovedaSupport::lugares((int) $sala->id) : collect();
        $depositoId = (int) $request->input('deposito_id', 0);
        $lugar = $lugares->firstWhere('id', $depositoId) ?? $lugares->first(
            fn ($fila) => (string) ($fila->tipoDeposito->codigo ?? '') === 'BOVEDA'
        ) ?? $lugares->first();
        $fecha = $jornada?->fecha?->format('Y-m-d');
        $saldo = ($lugar && $fecha) ? TesoreriaCrownBovedaSupport::saldoAgrupado(
            (int) $sala->id,
            TesoreriaCrownBovedaSupport::saldoCantidades((int) $lugar->id, $fecha)
        ) : [];

        return view('caja.tesoreria_crown.boveda.index', [
            'salas' => $salas,
            'sala' => $sala,
            'jornada' => $jornada,
            'lugares' => $lugares,
            'lugar' => $lugar,
            'saldo' => $saldo,
            'tipos' => $sala ? TesoreriaCrownBovedaSupport::tiposActivos((int) $sala->id) : collect(),
            'movimientos' => $jornada
                ? TesoreriaCrownBoveda::query()
                    ->with(['tipo', 'deposito', 'comprobante'])
                    ->where('jornada_id', $jornada->id)
                    ->orderByDesc('numero')
                    ->get()
                : collect(),
        ]);
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda');

        $jornada = $this->jornadaEnAlcance((int) $request->input('jornada_id'));
        if ($error = $this->errorJornada($jornada)) {
            return back()->with('mensaje-error', $error);
        }
        $lugar = $this->lugarDeSala((int) $jornada->sala_id, (int) $request->input('deposito_id'));
        $tipo = $this->tipoPorAbreviatura((int) $jornada->sala_id, (string) $request->input('tipo_abrev'));
        if ($lugar === null || $tipo === null) {
            return back()->withInput()->with('mensaje-error', 'Elegí el lugar y una abreviatura de movimiento que exista en esta sala.');
        }
        $comprobante = $this->comprobantePorNumero(
            $jornada,
            (string) $request->input('comprobante_tipo'),
            (string) $request->input('comprobante_numero')
        );
        if ($request->filled('comprobante_numero') && $comprobante === null) {
            return back()->withInput()->with('mensaje-error', 'Ese comprobante no es una habilitación o rendición conforme de esta jornada.');
        }

        $movimiento = DB::transaction(function () use ($jornada, $lugar, $tipo, $comprobante, $request) {
            $movimiento = TesoreriaCrownBoveda::query()->create([
                'sala_id' => $jornada->sala_id,
                'empresa_id' => $jornada->empresa_id,
                'jornada_id' => $jornada->id,
                'deposito_id' => $lugar->id,
                'tipo_id' => $tipo->id,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $jornada->sala_id, 'V'),
                'estado' => TesoreriaCrownBoveda::BORRADOR,
                'comentario' => $this->comentario($request->input('comentario')),
                'comprobante_id' => $comprobante?->id,
            ]);
            if ($comprobante !== null && $request->boolean('copiar')) {
                $this->copiarLineas($movimiento, $comprobante);
            }

            return $movimiento;
        });

        return redirect()->route('editar_tesoreria_crown_boveda', $movimiento->id);
    }

    public function editar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda');

        $movimiento = $this->movimientoEnAlcance($id);
        $cantidades = $movimiento->lineas->pluck('cantidad', 'valor_id');

        return view('caja.tesoreria_crown.boveda.editar', [
            'movimiento' => $movimiento,
            'grupos' => TesoreriaCrownComprobanteSupport::gruposEdicion((int) $movimiento->sala_id, TesoreriaCrownComprobanteSupport::RENDICION),
            'cantidades' => $cantidades,
            'editable' => $movimiento->esBorrador() && $movimiento->jornada->estaAbierta(),
        ]);
    }

    public function guardar(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda');

        $movimiento = $this->movimientoEnAlcance($id);
        if (! $movimiento->esBorrador() || ! $movimiento->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'Este movimiento ya no se puede modificar.');
        }
        $datos = $request->validate([
            'comentario' => ['nullable', 'string', 'max:80'],
            'comprobante_tipo' => ['nullable', 'string', 'max:1'],
            'comprobante_numero' => ['nullable', 'string', 'max:12'],
            'cantidad' => ['nullable', 'array'],
            'cantidad.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $comprobante = $this->comprobantePorNumero(
            $movimiento->jornada,
            (string) ($datos['comprobante_tipo'] ?? ''),
            (string) ($datos['comprobante_numero'] ?? '')
        );
        if (trim((string) ($datos['comprobante_numero'] ?? '')) !== '' && $comprobante === null) {
            return back()->with('mensaje-error', 'Ese comprobante no es una habilitación o rendición conforme de esta jornada.');
        }
        $comprobanteId = (int) ($comprobante->id ?? 0);
        $valoresSala = TesoreriaCrownValor::query()
            ->where('sala_id', $movimiento->sala_id)
            ->where('activo', true)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($movimiento, $datos, $comprobanteId, $valoresSala) {
            $movimiento->comentario = $this->comentario($datos['comentario'] ?? null);
            $movimiento->comprobante_id = $comprobanteId > 0 ? $comprobanteId : null;
            $movimiento->save();
            EloquentAuditDeleteSupport::each($movimiento->lineas()->getQuery());
            foreach ((array) ($datos['cantidad'] ?? []) as $valorId => $cantidad) {
                $cantidad = (float) $cantidad;
                if ($cantidad <= 0 || ! in_array((int) $valorId, $valoresSala, true)) {
                    continue;
                }
                TesoreriaCrownBovedaLinea::query()->create([
                    'boveda_id' => $movimiento->id,
                    'valor_id' => (int) $valorId,
                    'cantidad' => $cantidad,
                ]);
            }
        });

        return back()->with('mensaje', 'Borrador guardado.');
    }

    public function confirmar(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-boveda');

        $movimiento = $this->movimientoEnAlcance($id);
        if ($error = $this->errorJornada($movimiento->jornada)) {
            return back()->with('mensaje-error', $error);
        }
        if (! $movimiento->esBorrador()) {
            return back()->with('mensaje-error', 'Este movimiento ya no está en borrador.');
        }
        if ($movimiento->lineas()->doesntExist()) {
            return back()->with('mensaje-error', 'Cargá al menos una cantidad antes de confirmar.');
        }
        $limite = 'tc-boveda:'.$movimiento->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($limite, 8)) {
            return back()->with('mensaje-error', 'Demasiados intentos. Esperá un minuto y volvé a probar.');
        }
        $usuario = TesoreriaCrownFirmaSupport::usuarioPorClave(
            (string) $request->input('firma_usuario'),
            (string) $request->input('firma_password')
        );
        if ($usuario === null || ! TesoreriaCrownBovedaSupport::puedeConfirmar($usuario)) {
            RateLimiter::hit($limite, 60);

            return back()->with('mensaje-error', 'Usuario o clave de tesorería incorrectos.');
        }
        RateLimiter::clear($limite);
        $movimiento->estado = TesoreriaCrownBoveda::CONFIRMADO;
        $movimiento->usuario_id = $usuario->id;
        $movimiento->confirmado_at = now();
        $movimiento->save();

        return back()->with('mensaje', 'Movimiento confirmado. El saldo de '.$movimiento->deposito->nombre.' quedó actualizado.');
    }

    public function anular(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-boveda');

        $movimiento = $this->movimientoEnAlcance($id);
        if ($error = $this->errorJornada($movimiento->jornada)) {
            return back()->with('mensaje-error', $error);
        }
        if ($movimiento->estado !== TesoreriaCrownBoveda::CONFIRMADO) {
            return back()->with('mensaje-error', 'Solo se anula un movimiento confirmado.');
        }
        $limite = 'tc-boveda:'.$movimiento->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($limite, 8)) {
            return back()->with('mensaje-error', 'Demasiados intentos. Esperá un minuto y volvé a probar.');
        }
        $usuario = TesoreriaCrownFirmaSupport::usuarioPorClave(
            (string) $request->input('firma_usuario'),
            (string) $request->input('firma_password')
        );
        if ($usuario === null || ! TesoreriaCrownBovedaSupport::puedeConfirmar($usuario)) {
            RateLimiter::hit($limite, 60);

            return back()->with('mensaje-error', 'Usuario o clave de tesorería incorrectos.');
        }
        RateLimiter::clear($limite);
        $movimiento->estado = TesoreriaCrownBoveda::ANULADO;
        $movimiento->save();

        return back()->with('mensaje', 'Movimiento anulado. Dejó de sumar en el saldo.');
    }

    public function eliminar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda');

        $movimiento = $this->movimientoEnAlcance($id);
        if (! $movimiento->esBorrador() || ! $movimiento->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'Solo se borra un borrador de una jornada abierta.');
        }
        $jornadaId = (int) $movimiento->jornada_id;
        DB::transaction(function () use ($movimiento) {
            EloquentAuditDeleteSupport::each($movimiento->lineas()->getQuery());
            $movimiento->delete();
        });

        return redirect()
            ->route('tesoreria_crown_boveda', ['jornada_id' => $jornadaId])
            ->with('mensaje', 'Borrador eliminado.');
    }

    private function jornadaPedida(Request $request, int $salaId): ?TesoreriaCrownJornada
    {
        if ($salaId <= 0) {
            return null;
        }
        $id = (int) $request->input('jornada_id', 0);
        if ($id > 0) {
            $jornada = TesoreriaCrownJornada::query()->whereKey($id)->where('sala_id', $salaId)->first();
            if ($jornada === null) {
                abort(404);
            }

            return $jornada;
        }

        return TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->where('estado', TesoreriaCrownJornada::ABIERTA)
            ->first()
            ?? TesoreriaCrownJornada::query()->where('sala_id', $salaId)->orderByDesc('fecha')->first();
    }

    private function jornadaEnAlcance(int $id): TesoreriaCrownJornada
    {
        $jornada = TesoreriaCrownJornada::query()->find($id);
        if ($jornada === null) {
            abort(404);
        }
        TesoreriaCrownSalaSupport::exigida((int) $jornada->sala_id);

        return $jornada;
    }

    private function movimientoEnAlcance(int $id): TesoreriaCrownBoveda
    {
        $movimiento = TesoreriaCrownBoveda::query()
            ->with(['jornada', 'deposito.tipoDeposito', 'tipo', 'lineas.valor.tipoValor', 'comprobante', 'usuario'])
            ->find($id);
        if ($movimiento === null) {
            abort(404);
        }
        TesoreriaCrownSalaSupport::exigida((int) $movimiento->sala_id);

        return $movimiento;
    }

    private function lugarDeSala(int $salaId, int $depositoId): ?\App\Models\Caja\TesoreriaCrownDeposito
    {
        return TesoreriaCrownBovedaSupport::lugares($salaId)->firstWhere('id', $depositoId);
    }

    private function errorJornada(TesoreriaCrownJornada $jornada): ?string
    {
        return $jornada->estaAbierta() ? null : 'La jornada está cerrada. El movimiento queda en consulta.';
    }

    private function tipoPorAbreviatura(int $salaId, string $abreviatura): ?\App\Models\Caja\TesoreriaCrownBovedaTipo
    {
        $abreviatura = strtoupper(trim($abreviatura));
        if ($abreviatura === '') {
            return null;
        }

        return TesoreriaCrownBovedaSupport::tiposActivos($salaId)
            ->first(fn ($tipo) => strtoupper(trim((string) $tipo->abreviatura)) === $abreviatura);
    }

    private function comprobantePorNumero(TesoreriaCrownJornada $jornada, string $tipo, string $numero): ?TesoreriaCrownComprobante
    {
        $tipo = strtoupper(trim($tipo));
        $numero = (int) preg_replace('/\D/', '', $numero);
        if ($numero <= 0 || ! isset(TesoreriaCrownComprobanteSupport::TIPOS[$tipo])) {
            return null;
        }

        return $this->comprobantesConformes($jornada)
            ->first(fn (TesoreriaCrownComprobante $doc) => $doc->tipo === $tipo && (int) $doc->numero === $numero);
    }

    /**
     * @return \Illuminate\Support\Collection<int, TesoreriaCrownComprobante>
     */
    private function comprobantesConformes(TesoreriaCrownJornada $jornada)
    {
        return TesoreriaCrownComprobante::query()
            ->with(['lineas', 'asignacion.deposito', 'asignacion.cajero'])
            ->where('jornada_id', $jornada->id)
            ->where('estado', TesoreriaCrownComprobanteSupport::CONFORME)
            ->orderBy('numero')
            ->get();
    }

    private function copiarLineas(TesoreriaCrownBoveda $movimiento, TesoreriaCrownComprobante $comprobante): void
    {
        foreach ($comprobante->lineas as $linea) {
            $cantidad = (float) $linea->cantidad;
            if ($cantidad <= 0) {
                continue;
            }
            TesoreriaCrownBovedaLinea::query()->create([
                'boveda_id' => $movimiento->id,
                'valor_id' => $linea->valor_id,
                'cantidad' => $cantidad,
            ]);
        }
    }

    private function comentario(mixed $texto): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : mb_substr($texto, 0, 80);
    }
}
