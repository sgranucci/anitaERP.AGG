<?php
namespace App\Services\Caja;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App;
use Auth;
use DB;
use Exception;

class InterbankingService 
{
    public function __construct(
								)
    {
    }

	public function leeSaldos() 
	{

        // 1. Configurar los par┬ámetros de la consulta
        $params = [
            //'account-number' => 'REPLACE_THIS_VALUE',
            //'account-type'   => 'REPLACE_THIS_VALUE',
            //'bank-number'    => 'REPLACE_THIS_VALUE',
            //'currency'       => 'REPLACE_THIS_VALUE',
            'customer-id'    => 'C25656A',
            //'date-since'     => 'REPLACE_THIS_VALUE',
            //'date-until'     => 'REPLACE_THIS_VALUE',
            //'limit'          => 'REPLACE_THIS_VALUE',
            //'page'           => 'REPLACE_THIS_VALUE'
        ];

        $baseUrl = 'https://api-gw.interbanking.com.ar/api/prod/v1/accounts/balances';
        $url = $baseUrl . '?' . http_build_query($params);

        $token = file_get_contents("token.json");
        $clientId = 'ohLciTIWzAgaNui7XbRH1wznR50PqepBYfhp';

        // 2. Definir los headers
        $headers = [
            "Authorization: Bearer $token",
            "accept: application/json",
            "client_id: $clientId"
            ];

        // 3. Inicializar cURL
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        // 4. Ejecutar la solicitud
        $response = curl_exec($ch);

        // 5. Manejar errores y cerrar
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        } else {
            // Procesar la respuesta JSON
            $data = json_decode($response, true);
            print_r($data);
        }

        curl_close($ch);

		try {
		  $result = $this->client->ListadoComprobantesFull($request);
		  return($result->ListadoComprobantesFullResult->ListadoComprobantes->Comprobante);
		}
		catch (\Exception $e) {
		 	Log::info('Caught Exception :'. $e->getMessage());
			return $e;       // just re-throw it
		}
	}

    private function pideTokenInterbanking()
    {
        $url = 'https://auth.interbanking.com.ar/cas/oidc/accessToken';
        $clienteId = 'ohLciTIWzAgaNui7XbRH1wznR50PqepBYfhp';
        $clientSecret = 'QCOOkdzAzwUgLB1esv5XmDCrlG7DSrjJVoMF';

        $curl = curl_init();

        $header = array("Content-Type: application/x-www-form-urlencoded");

        $postData = [
            'grant_type' => 'client_credentials',
            'client_id' => $clienteId,
            'client_secret' => $clientSecret,
            'scope' => 'info-financiera - Informacion Financiera'
            ];

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0 );
        //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0 );

        $response = curl_exec($curl);

        // 5. Manejar errores
        if (curl_errno($curl)) {
            echo 'Error en cURL: ' . curl_error($curl);
        } else {
            // 6. Decodificar la respuesta
            $result = json_decode($response, true);
            if (isset($result['access_token'])) {

                file_put_contents("token.json", $result['access_token']);

            } else {
                echo "Error al obtener token: " . $response;
            }
        }

        // 7. Cerrar sesi┬ón cURL
        curl_close($curl);

    }
}