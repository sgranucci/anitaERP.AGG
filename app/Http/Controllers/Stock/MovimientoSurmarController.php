<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMovimientoStock;
use App\Support\Stock\Surmar\MovimientoSurmarPermisoSupport;
use App\Support\Stock\SurmarSupport;
use Illuminate\Http\Request;

/**
 * Entrada de menú stock/movimiento-surmar.
 * Reutiliza el ABM de movimientos de stock (piqueo AP/DES/TRA ya vive ahí),
 * forzando empresa Surmar y permisos *-movimiento-surmar.
 */
class MovimientoSurmarController extends Controller
{
    public function __construct(
        private readonly MovimientoStockController $movimientoStock,
    ) {
    }

    public function index(Request $request)
    {
        MovimientoSurmarPermisoSupport::puedeListar();
        SurmarSupport::abortSiNoSurmar(SurmarSupport::EMPRESA_ID);

        $request->merge([
            'empresa_id' => SurmarSupport::EMPRESA_ID,
            'empresa_scope' => 'una',
            'empresa_todas' => 0,
            'modo_surmar' => 1,
        ]);

        return $this->movimientoStock->index($request);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        MovimientoSurmarPermisoSupport::puedeListar();
        SurmarSupport::abortSiNoSurmar(SurmarSupport::EMPRESA_ID);

        $request->merge([
            'empresa_id' => SurmarSupport::EMPRESA_ID,
            'empresa_scope' => 'una',
            'empresa_todas' => 0,
            'modo_surmar' => 1,
        ]);

        return $this->movimientoStock->listar($request, $formato, $busqueda);
    }

    public function crear(Request $request)
    {
        MovimientoSurmarPermisoSupport::puedeCrear();
        SurmarSupport::abortSiNoSurmar(SurmarSupport::EMPRESA_ID);

        $request->merge([
            'empresa_id' => SurmarSupport::EMPRESA_ID,
            'modo_surmar' => 1,
        ]);

        return $this->movimientoStock->crear();
    }

    public function guardar(ValidacionMovimientoStock $request)
    {
        MovimientoSurmarPermisoSupport::puedeCrear();
        SurmarSupport::abortSiNoSurmar(SurmarSupport::EMPRESA_ID);

        $request->merge([
            'empresa_id' => SurmarSupport::EMPRESA_ID,
            'modo_surmar' => 1,
        ]);

        return $this->movimientoStock->guardar($request);
    }

    public function editar(Request $request, $id)
    {
        MovimientoSurmarPermisoSupport::puedeEditar();
        SurmarSupport::abortSiNoSurmar(SurmarSupport::EMPRESA_ID);

        $request->merge(['modo_surmar' => 1]);

        return $this->movimientoStock->editar($id);
    }

    public function actualizar(ValidacionMovimientoStock $request, $id)
    {
        MovimientoSurmarPermisoSupport::puedeActualizar();
        SurmarSupport::abortSiNoSurmar(SurmarSupport::EMPRESA_ID);

        $request->merge([
            'empresa_id' => SurmarSupport::EMPRESA_ID,
            'modo_surmar' => 1,
        ]);

        return $this->movimientoStock->actualizar($request, $id);
    }
}
