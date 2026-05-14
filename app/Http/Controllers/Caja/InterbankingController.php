<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Repositories\Caja\BancoRepositoryInterface;
use App\Services\Caja\InterbankingSaldoPersistenciaService;
use App\Services\Caja\InterbankingService;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class InterbankingController extends Controller
{
    private $interbankingService;

    private $bancoRepository;

    private InterbankingSaldoPersistenciaService $interbankingSaldoPersistenciaService;

    public function __construct(InterbankingService $interbankingService,
        BancoRepositoryInterface $bancoRepository,
        InterbankingSaldoPersistenciaService $interbankingSaldoPersistenciaService)
    {
        $this->interbankingService = $interbankingService;
        $this->bancoRepository = $bancoRepository;
        $this->interbankingSaldoPersistenciaService = $interbankingSaldoPersistenciaService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-saldo-cuenta-interbanking');

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');

        $customerIds = config('interbanking.customer_id');
        $customerIds = is_array($customerIds) ? $customerIds : [];

        $cuentasRaw = [];
        $erroresInterbanking = [];

        foreach ($user->usuario_empresas->sortBy('id') as $empresa) {
            $empresaId = (int) $empresa->id;
            $idx = $empresaId - 1;
            if ($idx < 0 || ! array_key_exists($idx, $customerIds)) {
                continue;
            }

            foreach (['ARS', 'USD'] as $currency) {
                $resultado = $this->interbankingService->leeSaldos($empresaId, $currency);

                if (empty($resultado['ok'])) {
                    if (! empty($resultado['error'])) {
                        $erroresInterbanking[] = $empresa->nombre.' ('.$currency.'): '.$resultado['error'];
                    }

                    continue;
                }

                foreach ($resultado['accounts'] as $account) {
                    $acc = (array) $account;
                    $acc['nombre_empresa'] = $empresa->nombre;
                    $acc['empresa_id'] = $empresaId;

                    $this->interbankingSaldoPersistenciaService->persistirCuenta($empresaId, $acc);

                    $cuentasRaw[] = $acc;
                }
            }
        }

        if ($erroresInterbanking !== []) {
            Session::flash('errores', array_values(array_unique($erroresInterbanking)));
        }

        // map(): evita la limitación de PHP "Indirect modification of overloaded element" si $cuentas
        // fuera Collection; además devuelve cada fila nueva con nombrebanco ya fusionado.
        $cuentas = collect($cuentasRaw)->map(function ($cuenta) {
            $cuenta = (array) $cuenta;
            $codigo = $cuenta['bank_number'] ?? $cuenta['bankNumber'] ?? null;
            $cuenta['nombrebanco'] = $this->resolverNombreBanco($codigo);

            return $cuenta;
        });

        return view('caja.interbanking.index', compact('cuentas'));
    }

    /**
     * Listado de movimientos Interbanking (JSON). Usado por el modal en `caja/interbanking` y callable por GET autenticado.
     *
     * Llamada (navegador, fetch o curl con sesión/cookies del usuario logueado):
     * `GET /caja/interbanking/movimientos?empresa_id=1&account_number=...&bank_number=011&movement_type=dia&currency=ARS&account_type=CC&limit=100&page=0`
     * Parámetros opcionales: `date_since`, `date_until` (formato `Y-m-d`).
     * Nombre de ruta: `route('interbanking_movimientos')`.
     *
     * @return \Illuminate\Http\JsonResponse Cuerpo alineado a {@see InterbankingService::leeMovimientos()}
     */
    public function movimientos(Request $request)
    {
        can('ver-movimientos-cuenta-interbanking');

        $validated = $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'account_number' => 'required|string|max:64',
            'bank_number' => 'required|string|regex:/^[0-9]{3}$/',
            'account_type' => 'nullable|string|in:CC,CA',
            'currency' => 'nullable|string|in:ARS,USD',
            'movement_type' => 'required|string|in:dia,diferidos,anteriores,zughus',
            'date_since' => 'nullable|date_format:Y-m-d',
            'date_until' => 'nullable|date_format:Y-m-d',
            'limit' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:0',
        ]);

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIds = $user->usuario_empresas->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! in_array((int) $validated['empresa_id'], $empresaIds, true)) {
            return response()->json([
                'ok' => false,
                'general_data' => null,
                'movements_detail' => [],
                'error' => 'No tiene acceso a la empresa indicada.',
            ], 403);
        }

        $resultado = $this->interbankingService->leeMovimientos(
            (int) $validated['empresa_id'],
            $validated['account_number'],
            $validated['movement_type'],
            [
                'bank_number' => $validated['bank_number'],
                'account_type' => $validated['account_type'] ?? 'CC',
                'currency' => $validated['currency'] ?? 'ARS',
                'date_since' => $validated['date_since'] ?? null,
                'date_until' => $validated['date_until'] ?? null,
                'limit' => array_key_exists('limit', $validated) ? (int) $validated['limit'] : null,
                'page' => array_key_exists('page', $validated) ? (int) $validated['page'] : null,
            ]
        );

        return response()->json($resultado);
    }

    /**
     * Coincide códigos de banco de la API con tabla banco (Anita suele usar codigos con ceros a la izquierda).
     */
    private function resolverNombreBanco($codigo): string
    {
        if ($codigo === null || $codigo === '') {
            return 'Banco no encontrado';
        }

        $str = (string) $codigo;
        $sinCerosIzq = ltrim($str, '0');
        $sinCerosIzq = $sinCerosIzq === '' ? '0' : $sinCerosIzq;

        $candidatos = array_unique(array_filter([
            $str,
            str_pad($sinCerosIzq, 3, '0', STR_PAD_LEFT),
            str_pad($sinCerosIzq, 4, '0', STR_PAD_LEFT),
        ]));

        foreach ($candidatos as $c) {
            $banco = $this->bancoRepository->findPorCodigo($c);
            if ($banco) {
                return $banco->nombre;
            }
        }

        return 'Banco no encontrado';
    }
}
