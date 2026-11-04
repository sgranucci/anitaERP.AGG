<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownBanca;
use App\Models\Caja\TesoreriaCrownBancaLinea;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownMesa;
use App\Models\Caja\TesoreriaCrownMesaJuego;
use App\Models\Caja\TesoreriaCrownMesaLinea;
use App\Models\Caja\TesoreriaCrownValor;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownEscritorioNav;
use App\Support\Caja\TesoreriaCrownFirmaSupport;
use App\Support\Caja\TesoreriaCrownMesaSupport;
use App\Support\Caja\TesoreriaCrownNumeradorSupport;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class TesoreriaCrownMesaController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-mesa');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $salaId = $sala ? (int) $sala->id : 0;
        $jornada = $this->jornadaPedida($request, $salaId);
        $mesas = $salaId > 0 ? TesoreriaCrownMesaSupport::mesas($salaId) : collect();
        $mesa = $this->mesaPedida($mesas, (string) $request->input('mesa_codigo'));
        $fecha = $jornada?->fecha?->format('Y-m-d') ?? now()->toDateString();
        $stock = ($mesa && $sala) ? TesoreriaCrownMesaSupport::stock((int) $mesa->id, $salaId, $fecha) : null;

        return view('caja.tesoreria_crown.mesa.index', [
            'salas' => $salas,
            'sala' => $sala,
            'jornada' => $jornada,
            'mesas' => $mesas,
            'mesa' => $mesa,
            'stock' => $stock,
            'apertura' => $stock ? TesoreriaCrownMesaSupport::filas($salaId, $stock['apertura']) : [],
            'saldo' => $stock ? TesoreriaCrownMesaSupport::filas($salaId, $stock['saldo']) : [],
            'estadoFinal' => $stock ? TesoreriaCrownMesaSupport::filas($salaId, $stock['estado_final']) : [],
            'drop' => $stock ? TesoreriaCrownMesaSupport::filas($salaId, $stock['drop']) : [],
            'gruposFichas' => $salaId > 0 ? TesoreriaCrownMesaSupport::grupos($salaId, 'A') : [],
            'juego' => $mesa ? TesoreriaCrownMesaJuego::query()->where('deposito_id', $mesa->id)->first() : null,
            'movimientos' => ($jornada && $mesa)
                ? TesoreriaCrownMesa::query()->with(['cajero', 'banca', 'reversa', 'revierte'])->where('jornada_id', $jornada->id)->where('deposito_id', $mesa->id)->orderByDesc('numero')->get()
                : collect(),
        ]);
    }

    public function formulario(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-mesa');

        $jornada = $this->jornadaEnAlcance((int) $request->input('jornada_id'));
        $volver = route('ver_tesoreria_crown_jornada', $jornada->id).'?panel=mesas';
        if (! $jornada->estaAbierta()) {
            return redirect()->to($volver)->with('mensaje-error', 'La jornada está cerrada.');
        }
        $mesa = TesoreriaCrownMesaSupport::mesas((int) $jornada->sala_id)
            ->firstWhere('id', (int) $request->input('deposito_id'));
        if ($mesa === null) {
            return redirect()->to($volver.'#piso')->with('mensaje-error', 'Esa mesa no es de esta sala.');
        }
        $stock = TesoreriaCrownMesaSupport::stock(
            (int) $mesa->id,
            (int) $jornada->sala_id,
            $jornada->fecha->format('Y-m-d')
        );

        return view('caja.tesoreria_crown.mesa.crear', [
            'jornada' => $jornada,
            'mesa' => $mesa,
            'banca' => $stock['banca'] ?? null,
            'escritorio' => TesoreriaCrownEscritorioNav::armar($jornada),
        ]);
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-mesa');

        $jornada = $this->jornadaEnAlcance((int) $request->input('jornada_id'));
        if (! $jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'La jornada está cerrada.');
        }
        $tipo = (string) $request->input('tipo');
        if (! isset(TesoreriaCrownMesaSupport::TIPOS[$tipo])) {
            return back()->withInput()->with('mensaje-error', 'Elegí el tipo de movimiento.');
        }
        $mesa = TesoreriaCrownMesaSupport::mesas((int) $jornada->sala_id)->firstWhere('id', (int) $request->input('deposito_id'));
        if ($mesa === null) {
            return back()->withInput()->with('mensaje-error', 'Esa mesa no es de esta sala.');
        }
        $cajero = TesoreriaCrownMesaSupport::cajeroPorNumero((int) $jornada->sala_id, (string) $request->input('cajero_numero'));
        if (is_string($cajero)) {
            return back()->withInput()->with('mensaje-error', $cajero);
        }

        $movimiento = DB::transaction(function () use ($jornada, $mesa, $tipo, $cajero, $request) {
            return TesoreriaCrownMesa::query()->create([
                'sala_id' => $jornada->sala_id,
                'empresa_id' => $jornada->empresa_id,
                'jornada_id' => $jornada->id,
                'deposito_id' => $mesa->id,
                'cajero_crown_id' => $cajero?->id,
                'tipo' => $tipo,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $jornada->sala_id, 'M'),
                'estado' => TesoreriaCrownMesa::BORRADOR,
                'comentario' => $this->comentario($request->input('comentario')),
            ]);
        });

        return redirect()->route('editar_tesoreria_crown_mesa', $movimiento->id);
    }

    public function editar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-mesa');

        $movimiento = $this->movimientoEnAlcance($id);

        return view('caja.tesoreria_crown.mesa.editar', [
            'movimiento' => $movimiento,
            'grupos' => TesoreriaCrownMesaSupport::grupos((int) $movimiento->sala_id, $movimiento->tipo),
            'cantidades' => $movimiento->lineas->pluck('cantidad', 'valor_id'),
            'editable' => $movimiento->esBorrador() && $movimiento->jornada->estaAbierta(),
            'escritorio' => $movimiento->jornada
                ? TesoreriaCrownEscritorioNav::armar($movimiento->jornada)
                : null,
        ]);
    }

    public function guardar(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-mesa');

        $movimiento = $this->movimientoEnAlcance($id);
        if (! $movimiento->esBorrador() || ! $movimiento->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'Este movimiento ya no se puede modificar.');
        }
        $datos = $this->datosBorrador($request);
        if ($error = $this->persistirBorrador($movimiento, $datos)) {
            return back()->withInput()->with('mensaje-error', $error);
        }

        return back()->with('mensaje', 'Borrador guardado.');
    }

    public function confirmar(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-mesa');

        $movimiento = $this->movimientoEnAlcance($id);
        if (! $movimiento->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'La jornada está cerrada.');
        }
        if (! $movimiento->esBorrador()) {
            return back()->with('mensaje-error', 'Este movimiento ya no está en borrador.');
        }
        if ($request->exists('cantidad')) {
            $datos = $this->datosBorrador($request);
            if ($error = $this->persistirBorrador($movimiento, $datos)) {
                return back()->withInput()->with('mensaje-error', $error);
            }
            $movimiento->unsetRelation('lineas');
        }
        if ($movimiento->lineas()->doesntExist()) {
            return back()->withInput()->with('mensaje-error', 'Cargá al menos una cantidad antes de confirmar.');
        }
        if (trim((string) $request->input('firma_usuario')) === '' || trim((string) $request->input('firma_password')) === '') {
            return back()->withInput()->with('mensaje-error', 'Completá el usuario y la clave de tesorería.');
        }
        $limite = 'tc-mesa:'.$movimiento->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($limite, 8)) {
            return back()->with('mensaje-error', 'Demasiados intentos. Esperá un minuto y volvé a probar.');
        }
        $usuario = TesoreriaCrownFirmaSupport::usuarioPorClave(
            (string) $request->input('firma_usuario'),
            (string) $request->input('firma_password')
        );
        if ($usuario === null || ! TesoreriaCrownMesaSupport::puedeConfirmar($usuario)) {
            RateLimiter::hit($limite, 60);

            return back()->with('mensaje-error', 'Usuario o clave de tesorería incorrectos.');
        }
        RateLimiter::clear($limite);
        $juego = TesoreriaCrownMesaJuego::query()->where('deposito_id', $movimiento->deposito_id)->first();
        $banca = $juego
            ? TesoreriaCrownMesaSupport::bancaVigente((int) $movimiento->sala_id, (int) $juego->banca_codigo, $movimiento->jornada->fecha->format('Y-m-d'))
            : null;
        $movimiento->banca_id = $banca?->id;
        $movimiento->estado = TesoreriaCrownMesa::CONFIRMADO;
        $movimiento->usuario_id = $usuario->id;
        $movimiento->confirmado_at = now();
        $movimiento->save();

        return back()->with('mensaje', 'Movimiento confirmado. Quedó con la banca del '.$movimiento->jornada->fecha->format('d/m/Y').'.');
    }

    public function anular(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-mesa');

        $movimiento = $this->movimientoEnAlcance($id);
        if (! $movimiento->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'La jornada está cerrada.');
        }
        if ($movimiento->estado !== TesoreriaCrownMesa::CONFIRMADO) {
            return back()->with('mensaje-error', 'Solo se anula un movimiento confirmado.');
        }
        $limite = 'tc-mesa:'.$movimiento->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($limite, 8)) {
            return back()->with('mensaje-error', 'Demasiados intentos. Esperá un minuto y volvé a probar.');
        }
        $usuario = TesoreriaCrownFirmaSupport::usuarioPorClave(
            (string) $request->input('firma_usuario'),
            (string) $request->input('firma_password')
        );
        if ($usuario === null || ! TesoreriaCrownMesaSupport::puedeConfirmar($usuario)) {
            RateLimiter::hit($limite, 60);

            return back()->with('mensaje-error', 'Usuario o clave de tesorería incorrectos.');
        }
        RateLimiter::clear($limite);
        $movimiento->estado = TesoreriaCrownMesa::ANULADO;
        $movimiento->save();

        return back()->with('mensaje', 'Movimiento anulado. Dejó de entrar en el stock.');
    }

    public function revertir(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-mesa');

        $movimiento = $this->movimientoEnAlcance($id);
        if (! $movimiento->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'La jornada está cerrada.');
        }
        if (! $movimiento->puedeRevertir()) {
            return back()->with('mensaje-error', 'Solo se revierte un movimiento confirmado, y una sola vez.');
        }
        $motivo = trim((string) $request->input('motivo'));
        if ($motivo === '' || mb_strlen($motivo) > 500) {
            return back()->with('mensaje-error', 'Escribí el motivo de la reversa.');
        }
        $limite = 'tc-mesa:'.$movimiento->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($limite, 8)) {
            return back()->with('mensaje-error', 'Demasiados intentos. Esperá un minuto y volvé a probar.');
        }
        $usuario = TesoreriaCrownFirmaSupport::usuarioPorClave(
            (string) $request->input('firma_usuario'),
            (string) $request->input('firma_password')
        );
        if ($usuario === null || ! TesoreriaCrownMesaSupport::puedeConfirmar($usuario)) {
            RateLimiter::hit($limite, 60);

            return back()->with('mensaje-error', 'Usuario o clave de tesorería incorrectos.');
        }
        RateLimiter::clear($limite);

        $lineas = $movimiento->lineas->filter(fn ($linea) => abs((float) $linea->cantidad) >= 0.0001);
        if ($lineas->isEmpty()) {
            return back()->with('mensaje-error', 'Este movimiento no tiene cantidades para revertir.');
        }

        try {
            $nuevo = DB::transaction(function () use ($movimiento, $usuario, $motivo, $lineas) {
                $original = TesoreriaCrownMesa::query()->whereKey($movimiento->id)->lockForUpdate()->first();
                if ($original === null || ! $original->puedeRevertir()) {
                    throw new \RuntimeException('Solo se revierte un movimiento confirmado, y una sola vez.');
                }

                $nuevo = TesoreriaCrownMesa::query()->create([
                    'sala_id' => $original->sala_id,
                    'empresa_id' => $original->empresa_id,
                    'jornada_id' => $original->jornada_id,
                    'deposito_id' => $original->deposito_id,
                    'cajero_crown_id' => $original->cajero_crown_id,
                    'banca_id' => $original->banca_id,
                    'revierte_id' => $original->id,
                    'tipo' => $original->tipo,
                    'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $original->sala_id, 'M'),
                    'estado' => TesoreriaCrownMesa::CONFIRMADO,
                    'comentario' => 'Reversa de '.TesoreriaCrownNumeradorSupport::visible((int) $original->numero),
                    'motivo' => $motivo,
                    'usuario_id' => $usuario->id,
                    'confirmado_at' => now(),
                ]);
                foreach ($lineas as $linea) {
                    TesoreriaCrownMesaLinea::query()->create([
                        'mesa_id' => $nuevo->id,
                        'valor_id' => $linea->valor_id,
                        'cantidad' => (int) round((float) $linea->cantidad * -1),
                    ]);
                }

                return $nuevo;
            });
        } catch (\RuntimeException $e) {
            return back()->with('mensaje-error', $e->getMessage());
        }

        return back()->with(
            'mensaje',
            'Reversa '.TesoreriaCrownNumeradorSupport::visible((int) $nuevo->numero).' grabada. El movimiento original sigue y este lo compensa.'
        );
    }

    public function eliminar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('eliminar-borrador-tesoreria-crown-mesa');

        $movimiento = $this->movimientoEnAlcance($id);
        if (! $movimiento->esBorrador() || ! $movimiento->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'Solo se borra un borrador de una jornada abierta.');
        }
        $volver = ['sala_id' => $movimiento->sala_id, 'jornada_id' => $movimiento->jornada_id, 'mesa_codigo' => $movimiento->deposito->codigo ?? ''];
        DB::transaction(function () use ($movimiento) {
            EloquentAuditDeleteSupport::each($movimiento->lineas()->getQuery());
            $movimiento->delete();
        });

        return redirect()->route('tesoreria_crown_mesa', $volver)->with('mensaje', 'Borrador eliminado.');
    }

    public function guardarBanca(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-mesa');

        $sala = TesoreriaCrownSalaSupport::exigida((int) $request->input('sala_id'));
        $datos = $request->validate([
            'codigo' => ['required', 'integer', 'min:1'],
            'nombre' => ['required', 'string', 'max:40'],
            'vigencia_desde' => ['required', 'date'],
            'cantidad' => ['nullable', 'array'],
            'cantidad.*' => ['nullable', 'integer', 'min:0'],
        ]);
        $vigencia = $datos['vigencia_desde'];
        if ($vigencia < now()->toDateString()) {
            return back()->with('mensaje-error', 'Una banca nueva rige desde hoy. Las fechas anteriores conservan la vigencia que ya tenían.');
        }
        $banca = TesoreriaCrownBanca::query()
            ->where('sala_id', $sala->id)
            ->where('codigo', (int) $datos['codigo'])
            ->whereDate('vigencia_desde', $vigencia)
            ->first();
        if ($banca !== null && TesoreriaCrownMesa::query()->where('banca_id', $banca->id)->exists()) {
            return back()->with('mensaje-error', 'Esa vigencia ya fue usada en un movimiento. Cargá una fecha posterior.');
        }
        $valoresSala = TesoreriaCrownValor::query()->where('sala_id', $sala->id)->where('activo', true)->pluck('id')->all();

        DB::transaction(function () use ($sala, $datos, $vigencia, $banca, $valoresSala) {
            $banca = $banca ?? TesoreriaCrownBanca::query()->create([
                'sala_id' => $sala->id,
                'codigo' => (int) $datos['codigo'],
                'nombre' => trim($datos['nombre']),
                'vigencia_desde' => $vigencia,
            ]);
            $banca->nombre = trim($datos['nombre']);
            $banca->save();
            EloquentAuditDeleteSupport::each($banca->lineas()->getQuery());
            foreach ((array) ($datos['cantidad'] ?? []) as $valorId => $cantidad) {
                $cantidad = (float) $cantidad;
                if ($cantidad <= 0 || ! in_array((int) $valorId, $valoresSala, true)) {
                    continue;
                }
                TesoreriaCrownBancaLinea::query()->create([
                    'banca_id' => $banca->id,
                    'valor_id' => (int) $valorId,
                    'cantidad' => $cantidad,
                ]);
            }
        });

        return back()->with('mensaje', 'La banca rige desde el '.$vigencia.'. Los días anteriores no cambian.');
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

        return TesoreriaCrownJornada::query()->where('sala_id', $salaId)->where('estado', TesoreriaCrownJornada::ABIERTA)->first()
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

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Caja\TesoreriaCrownDeposito>  $mesas
     */
    private function mesaPedida($mesas, string $codigo): ?\App\Models\Caja\TesoreriaCrownDeposito
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return $mesas->first();
        }
        if (ctype_digit($codigo)) {
            $codigo = 'M'.$codigo;
        }

        return $mesas->first(fn ($mesa) => strtoupper((string) $mesa->codigo) === $codigo);
    }

    private function movimientoEnAlcance(int $id): TesoreriaCrownMesa
    {
        $movimiento = TesoreriaCrownMesa::query()
            ->with(['jornada', 'deposito', 'cajero', 'banca', 'lineas.valor', 'usuario', 'reversa', 'revierte'])
            ->find($id);
        if ($movimiento === null) {
            abort(404);
        }
        TesoreriaCrownSalaSupport::exigida((int) $movimiento->sala_id);

        return $movimiento;
    }

    /**
     * @return array{comentario: ?string, cajero_numero: ?string, cantidad: array<int|string, mixed>}
     */
    private function datosBorrador(Request $request): array
    {
        return $request->validate([
            'comentario' => ['nullable', 'string', 'max:80'],
            'cajero_numero' => ['nullable', 'string', 'max:12'],
            'cantidad' => ['nullable', 'array'],
            'cantidad.*' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param  array{comentario?: ?string, cajero_numero?: ?string, cantidad?: array<int|string, mixed>}  $datos
     */
    private function persistirBorrador(TesoreriaCrownMesa $movimiento, array $datos): ?string
    {
        $cajero = TesoreriaCrownMesaSupport::cajeroPorNumero((int) $movimiento->sala_id, (string) ($datos['cajero_numero'] ?? ''));
        if (is_string($cajero)) {
            return $cajero;
        }
        $valoresSala = TesoreriaCrownValor::query()->where('sala_id', $movimiento->sala_id)->where('activo', true)->pluck('id')->all();

        DB::transaction(function () use ($movimiento, $datos, $cajero, $valoresSala) {
            $movimiento->comentario = $this->comentario($datos['comentario'] ?? null);
            $movimiento->cajero_crown_id = $cajero?->id;
            $movimiento->save();
            EloquentAuditDeleteSupport::each($movimiento->lineas()->getQuery());
            foreach ((array) ($datos['cantidad'] ?? []) as $valorId => $cantidad) {
                $cantidad = (int) $cantidad;
                if ($cantidad <= 0 || ! in_array((int) $valorId, $valoresSala, true)) {
                    continue;
                }
                TesoreriaCrownMesaLinea::query()->create([
                    'mesa_id' => $movimiento->id,
                    'valor_id' => (int) $valorId,
                    'cantidad' => $cantidad,
                ]);
            }
        });

        return null;
    }

    private function comentario(mixed $texto): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : mb_substr($texto, 0, 80);
    }
}
