<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Services\Caja\InterbankingService;

class InterbankingController extends Controller
{
	private $interbankingService;

    public function __construct(InterbankingService $interbankingService)
    {
        $this->interbankingService = $interbankingService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-saldo-cuenta-interbaking');
        
		$datas = $this->interbankingService->leeSaldos(3);

        if (isset($datas['accounts']))
            $cuentas = $datas['accounts'];
        else
            $cuentas = collect();

        return view('caja.interbanking.index', compact('cuentas'));
    }

}
