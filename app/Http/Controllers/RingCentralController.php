<?php

namespace App\Http\Controllers;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;
use RingCentral\SDK\Http\ApiException;
use RingCentral\SDK\SDK;

class RingCentralController extends Controller
{
    const MODE_GUZZLE = 'guzzle';
    const MODE_CURL   = 'curl';
    const MODE_SDK    = 'sdk';

    private $ringCentralId    = null;
    private $ringCentalSecret = null;

    public function __construct()
    {
        $this->ringCentralId = env('RINGCENTRAL_ID');
        $this->ringCentalSecret = env('RINGCENTRAL_SECRET');
    }

    public static function guzzle()
    {
        return (new self())->getAuthenticationUrl(self::MODE_GUZZLE);
    }

    public static function curl()
    {
        return (new self())->getAuthenticationUrl(self::MODE_CURL);
    }

    public static function sdk()
    {
        return (new self())->getSdkUrl();
    }

    public function index()
    {
        return $this->getAuthenticationUrl(self::MODE_GUZZLE);
    }

    public function callback(Request $request)
    {
        $state = json_decode(base64_decode($request->get('state')));

        switch ($state->mode) {
            case self::MODE_GUZZLE:
                return $this->guzzleCallback($request);

            case self::MODE_CURL:
                return $this->curlCallback($request);

            case self::MODE_SDK:
                return $this->sdkCallback($request);

            default:
                throw new \Exception('Unhandled mode', 500);
        }
    }

    private function guzzleCallback(Request $request)
    {
        try {
            $httpClient = new \GuzzleHttp\Client([
                'http_errors' => false,
            ]);

            $formParams = [
                'client_id'     => $this->ringCentralId,
                'client_secret' => $this->ringCentalSecret,
                'redirect_uri'  => $this->getRedirectUri(),
                'grant_type'    => 'authorization_code',
                'code'          => $request->get('code'),
            ];

            $response = $httpClient->request(Request::METHOD_POST, $this->url('token'), [
                RequestOptions::FORM_PARAMS => $formParams,
            ]);
        } catch (ConnectException $e) {
            return "Exception thrown by GuzzleHTTP! Details:\n\n" . json_encode([
                    'message' => $e->getMessage(),
                    'headers' => $e->getRequest()->getHeaders(),
                    'uri'     => [
                        'host' => $e->getRequest()->getUri()->getHost(),
                        'path' => $e->getRequest()->getUri()->getPath(),
                    ],
                    'method'  => $e->getRequest()->getMethod(),
                ], JSON_PRETTY_PRINT);
        }

        return $response->getBody()->getContents();
    }

    private function curlCallback(Request $request)
    {
        try {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $this->url('token'));
            curl_setopt($ch, CURLOPT_POST, 1);

            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'client_id'     => $this->ringCentralId,
                'client_secret' => $this->ringCentalSecret,
                'redirect_uri'  => $this->getRedirectUri(),
                'grant_type'    => 'authorization_code',
                'code'          => $request->get('code'),
            ]));

            // Receive server response ...
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            if (($response = curl_exec($ch)) === false) {
                throw new \Exception(curl_error($ch), curl_errno($ch));
            }

            curl_close($ch);
        } catch (\Exception $e) {
            return "Exception thrown by cURL! Details:\n\n" . json_encode([
                    'message' => $e->getMessage(),
                ], JSON_PRETTY_PRINT);
        }

        return $response;
    }

    private function sdkCallback(Request $request)
    {
        try {
            $qs = $this->getSdkPlatform()->parseAuthRedirectUrl($_SERVER['QUERY_STRING']);
            $qs['redirectUri'] = $this->getRedirectUri();
            $response = $this->getSdkPlatform()->login($qs);
        } catch (ApiException $e) {
            return "Exception thrown by the RingCentral SDK! Details:\n\n" . json_encode([
                    'message' => $e->getMessage(),
                    'headers' => $e->apiResponse()->request()->getHeaders(),
                    'uri'     => [
                        'host' => $e->apiResponse()->request()->getUri()->getHost(),
                        'path' => $e->apiResponse()->request()->getUri()->getPath(),
                    ],
                    'method'  => $e->apiResponse()->request()->getMethod(),
                ], JSON_PRETTY_PRINT);
        }

        return $response;
    }

    private function getRedirectUri()
    {
        return str_replace(config('app.url'), 'https://www.oneupsales-dev.io', url(route('callback'), [], true));
    }

    private function getAuthenticationUrl(string $mode)
    {
        return sprintf('%s?%s', $this->url('auth'), http_build_query([
            'response_type' => 'code',
            'redirect_uri'  => $this->getRedirectUri(),
            'client_id'     => $this->ringCentralId,
            'state'         => base64_encode(json_encode([
                'svr'  => 'local',
                'mode' => $mode,
            ])),
        ]));
    }

    private function getSdkUrl()
    {
        return $this->getSdkPlatform()->authUrl([
            'redirectUri' => $this->getRedirectUri(),
            'state'       => base64_encode(json_encode([
                'svr'  => 'local',
                'mode' => self::MODE_SDK,
            ])),
            'brandId'     => '',
            'display'     => '',
            'prompt'      => '',
        ]);
    }

    private function getSdkPlatform()
    {
        $rcsdk = new SDK($this->ringCentralId, $this->ringCentalSecret, SDK::SERVER_PRODUCTION, 'OneUp Sales', '1.0');
        return $rcsdk->platform();
    }

    private function url(string $key) : string
    {
        $urls = [
            'auth'  => 'https://platform.ringcentral.com/restapi/oauth/authorize',
            'token' => 'https://platform.ringcentral.com/restapi/oauth/token',
        ];

        return $urls[$key] ?? 'invalid url';
    }
}
