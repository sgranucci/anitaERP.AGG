<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownSalaSupport;

class TesoreriaCrownSlotController extends Controller
{
    public function index()
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-slot');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = $salas->first();
        $jornada = null;
        if ($sala) {
            $jornada = TesoreriaCrownJornada::query()
                ->where('sala_id', $sala->id)
                ->where('estado', TesoreriaCrownJornada::ABIERTA)
                ->first()
                ?? TesoreriaCrownJornada::query()
                    ->where('sala_id', $sala->id)
                    ->orderByDesc('fecha')
                    ->first();
        }
        if ($jornada) {
            return redirect()->route('ver_tesoreria_crown_jornada', [
                'id' => $jornada->id,
                'panel' => 'slots',
            ]);
        }

        return view('caja.tesoreria_crown.slot.index', [
            'sala' => $sala,
        ]);
    }
}
