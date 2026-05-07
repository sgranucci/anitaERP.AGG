<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Repositories\Caja\BancoRepositoryInterface;
use App\Services\Caja\InterbankingService;

class InterbankingController extends Controller
{
    private $interbankingService;

    private $bancoRepository;

    public function __construct(InterbankingService $interbankingService,
        BancoRepositoryInterface $bancoRepository)
    {
        $this->interbankingService = $interbankingService;
        $this->bancoRepository = $bancoRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-saldo-cuenta-interbanking');

        $dataARS = $this->interbankingService->leeSaldos(3, 'ARS');

        $dataUSD = $this->interbankingService->leeSaldos(3, 'USD');

        $datas = array_merge($dataARS['accounts'], $dataUSD['accounts']);

        $cuentasRaw = is_array($datas) && isset($datas) ? $datas : [];

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
