<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Services\Caja\InterbakingService;

class InterbankingController extends Controller
{
	private $interbankingService;

    public function __construct(InterbakingService $interbankingService)
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
        can('listar-saldo-cuentas');
        
		$datas = $this->interbankingService->leeSaldos();

        return view('caja.interbanking.index', compact('datas'));
    }

}
