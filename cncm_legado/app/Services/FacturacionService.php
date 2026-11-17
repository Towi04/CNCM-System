<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Collection;

class FacturacionService
{
    protected $cliente;

    protected $facturacom;

    public function __construct()
    {
        $enviroment = env('FACTURACOM_ENVIRONMENT');

        if(empty($enviroment)) {
            throw new \Exception("No has definio FACTURACOM_ENVIRONMENT", 1);
        }

        $this->facturacom = config('facturacom')[$enviroment] ?? [];

        if (empty($this->facturacom)) {
            throw new \Exception("El entorno {$enviroment} para facturacion no esta definido", 1);
        }

        $this->cliente = new Client(['base_uri' => $this->facturacom['url']]);
    }

    public function usosCfdi()
    {
        $response = $this->cliente->get($this->facturacom['url'] . '/api/v3/catalogo/UsoCfdi', [
            'headers' => $this->facturacom['headers'],
        ]);

        $collection = $this->buildCollection($response->getBody());

        return $collection;
    }

    public function metodoPago()
    {
        $response = $this->cliente->get($this->facturacom['url'] . '/api/v3/catalogo/MetodoPago', [
            'headers' => $this->facturacom['headers'],
        ]);

        $collection = $this->buildCollection($response->getBody());

        return $collection;
    }

    public function formaPago()
    {
        $response = $this->cliente->get($this->facturacom['url'] . '/api/v3/catalogo/FormaPago', [
            'headers' => $this->facturacom['headers'],
        ]);

        $collection =  $this->buildCollection($response->getBody());

        return $collection;
    }

    public function claveUnidad()
    {
        $response = $this->cliente->get($this->facturacom['url'] . '/api/v3/catalogo/ClaveUnidad', [
            'headers' => $this->facturacom['headers'],
        ]);

        $collection  =  $this->buildCollection($response->getBody());

        return $collection;
    }

    public function getInfoCliente(string $rfc)
    {
        $client = new Client(['base_uri' => $this->facturacom['url']]);

        $response = $client->get("{$this->facturacom['url']}/api/v1/clients/{$rfc}", [
            'headers' => $this->facturacom['headers'],
        ]);

        if(json_decode($response->getBody())->status == 'error' ){
            return [
                'success' => false,
                'message' => 'El cliente no existe'
            ];
        }

        $cliente_rfc = json_decode($response->getBody())->Data;
        $UID = $cliente_rfc->UID;

        return [
            'success' => true,
            'data'    => [
                'uid' => $UID
            ]
        ];
    }

    public function crearCliente(array $fields)
    {
        $ch = curl_init();

        $jsonfield = json_encode($fields);

        curl_setopt($ch, CURLOPT_URL, $this->facturacom['url'] . "/api/v1/clients/create");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonfield);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->facturacom['headers_curl']);


        $curl_crear_cliente = curl_exec($ch);
        $response = json_decode($curl_crear_cliente);

        # CAPTURAR ERROR AL TRATAR DE CREAR EL CLIENTE
        if ($response->status == 'error') {
            if (is_object($response->message)) {
                $error = '';

                foreach ($response->message as $key => $message) {
                    $error .= $message[0];
                }

                return [
                    'success'  => false,
                    'error'    => $error,
                ];
            } else {
                return [
                    'success'  => false,
                    'error'    => $response->message,
                ];
            }
        }

        $UID = json_decode($curl_crear_cliente)->Data->UID;

        curl_close($ch);

        return [
            'success'   => true,
            'data'      => [
                'uid' => $UID
            ]
        ];
    }

    public function timbrar(array $fields)
    {
        $ch = curl_init();

        $jsonfield = json_encode($fields);

        curl_setopt($ch, CURLOPT_URL, $this->facturacom['url'] . "/api/v3/cfdi33/create");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonfield);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->facturacom['headers_curl']);

        $request_timbrado = curl_exec($ch);
        $response = json_decode($request_timbrado);

        $uuids = [
            'uid'   => '',
            'uuid'  => '',
        ];

        if ($response->response == 'error') {
            if (is_object($response->message)) {
                $error = '';

                foreach ($response->message as $key => $message) {
                    $error .= $message;
                }

                return [
                    'success' => false,
                    'message' => $error
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response->message
                ];
            }
        } else {
            $uuids = [
                'uid'   => $response->uid,
                'uuid'  => $response->UUID,
            ];
        }

        curl_close($ch);

        return [
            'success'   => true,
            'data'      => $uuids
        ];
    }

    public function descargar(string $uid,string $filename, string $tipo)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->facturacom['url'] . "/api/v3/cfdi33/{$uid}/{$tipo}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->facturacom['headers_curl']);

        $response = curl_exec($ch);

        curl_close($ch);

        if ($tipo == 'pdf') {
            header('Content-Type: application/pdf');
            header("Content-Transfer-Encoding: Binary");
            header('Content-disposition: attachment; filename="' . $filename . '.pdf"');
        } else {
            header('Content-disposition: attachment; filename="' . $filename . '.xml"');
            header('Content-type: "text/xml"; charset="utf8"');
        }

        return $response;
    }

    public function cancelacion(string $uid)
    {
        $client = new Client(['base_uri' => $this->facturacom['url']]);

        $request = $client->get("{$this->facturacom['url']}/api/v3/cfdi33/{$uid}/cancel", [
            'headers' => $this->facturacom['headers']
        ]);

        $result = json_decode($request->getBody());

        return [
            'success' => true,
            'message' => 'Factura cancelada correctamente',
            'result'  => $result
        ];
    }

    private function buildCollection($response)
    {
        $data = json_decode($response)->data;
        $collection = new Collection($data);

        $collection = $collection->mapWithKeys(function ($item) {
            return [$item->key => $item->key . ' - ' . $item->name];
        });

        return $collection;
    }
}
