<?php

namespace ZerosDev\Durianpay;

use Exception;
use Carbon\Carbon;
use GuzzleHttp\TransferStats;
use GuzzleHttp\Client as GuzzleClient;
use ZerosDev\Durianpay\Traits\SetterGetter;

class Client
{
    use SetterGetter;

    protected $http;
    protected $api_key;
    protected $mode;
    protected $request_endpoint;
    protected $request_url;
    protected $request_method;
    protected $request_payload = [];
    protected $request_headers = [];
    protected $guzzle_options = [];
    protected $response;

    public function __construct(string $api_key = null, string $mode = 'production')
    {
        $this->init($api_key, $mode);
    }

    public function instance()
    {
        return $this;
    }

    public function useCredential(string $api_key = null, string $mode = 'production')
    {
        $this->init($api_key, $mode);

        return $this;
    }

    private function init(string $api_key, string $mode)
    {
        $this->setApiKey($api_key);
        $this->setMode($mode);

        $this->setRequestHeaders([
            'Authorization'		=> 'Basic '.base64_encode($this->getApiKey().":"),
            'Accept'			=> 'application/json'
        ]);

        $self = $this;
        $guzzleOptions = array_merge([
            'base_uri'		=> Constant::URL_API,
            'http_errors' 	=> false,
            'headers'		=> $this->getRequestHeaders(),
            'on_stats' => function (TransferStats $s) use (&$self) {
                $self->setRequestUrl(strval($s->getEffectiveUri()));
            }
        ], $this->getGuzzleOptions() ?? []);
        $this->http = new GuzzleClient($guzzleOptions);
    }

    public function request($endpoint, $method = 'GET', $content_type = Constant::CONTENT_FORM)
    {
        $method = strtolower($method);

        $this->setRequestEndpoint($endpoint);
        $this->setRequestMethod(strtoupper($method));

        $options = [];

        switch ($this->getRequestMethod()) {
            case "POST":
                $this->addRequestHeaders('Content-Type', $content_type);
                switch ($content_type) {
                    case Constant::CONTENT_JSON:
                        $options['json'] = $this->getRequestPayload();
                        break;
                    case Constant::CONTENT_FORM:
                        $options['form_params'] = $this->getRequestPayload();
                        break;
                }
                break;
        }

        try {
            $response = $this->http->{$method}($endpoint, $options)
                ->getBody()
                ->getContents();
        } catch (Exception $e) {
            $response = $e->getMessage();
        }

        $this->setResponse($response);

        return $this->getResponse();
    }

    public function debugs()
    {
        return [
            'mode' => $this->getMode(),
            'url'	=> $this->getRequestUrl(),
            'method' => $this->getRequestMethod(),
            'payload' => $this->getRequestPayload(),
            'headers' => $this->getRequestHeaders(),
            'response' => $this->getResponse(),
        ];
    }
}
