<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Repositories\Configuracion\CotizacionRepositoryInterface;
use App\Repositories\Configuracion\Cotizacion_MonedaRepositoryInterface;
use App\Queries\Configuracion\CotizacionQueryInterface;
use DB;
use Carbon\Carbon;

class LeeCotizacionApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cotizacion:leeapi';
    private $cotizacionRepository;
    private $cotizacion_MonedaRepository;
    private $cotizacionQuery;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lee cotizacion del dolar';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(CotizacionRepositoryInterface $cotizacionrepository, 
                                Cotizacion_MonedaRepositoryInterface $cotizacion_monedarepository,
                                CotizacionQueryInterface $cotizacionquery)
    {
		$this->cotizacionRepository = $cotizacionrepository;
        $this->cotizacion_MonedaRepository = $cotizacion_monedarepository;
        $this->cotizacionQuery = $cotizacionquery;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $scriptPath = storage_path('cotizacionbna.sh');
        $cotizacionLeida = shell_exec("bash $scriptPath 2>&1");

        $fecha = \Carbon\Carbon::now()->format('Ymd');
        $moneda_id = config('cotizacion.monedaIdCommand');
        $usuario = config('cotizacion.usuarioIdCommand');

        DB::beginTransaction();
        try
        {
            // Verifica si ya esta grabada la cotizacion del dia
            $cotizacion = $this->cotizacionQuery->leeCotizacionDiaria($fecha, $moneda_id);

            if (isset($cotizacion->fecha))
                $fechaUltimaCotizacion = date('Ymd', strtotime($cotizacion->fecha));
            else
                $fechaUltimaCotizacion = '';

            if ($cotizacion ? $fechaUltimaCotizacion != $fecha : true)
            {
                $cotizacion = $this->cotizacionRepository->create(['fecha' => $fecha, 'usuario_id' => $usuario]);

                Log::info(array("grabo cotizacion diaria id: $cotizacion->id $cotizacionLeida"));
                if ($cotizacion == 'Error')
                    throw new Exception('Error en grabacion anita.');

                // Guarda tablas asociadas
                if ($cotizacion)
                    $this->cotizacion_MonedaRepository->create(['cotizacion_ids' => [$cotizacion->id],
                                                                'moneda_ids' => [$moneda_id],
                                                                'cotizacioncompras' => [0], 
                                                                'cotizacionventas' => [$cotizacionLeida]], 
                                                                $cotizacion->id);
                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollback();

            // Borra el cotizacion creado
            Log::info(array("error $e"));

            return ['errores' => $e->getMessage()];
        }

        return 0;
    }
}
