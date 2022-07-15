<?php

namespace PlacetoPay\Helpers;

use Dnetix\Redirection\Carrier\Authentication;
use PlacetoPay\Carrier\RestCarrier;
use PlacetoPay\Contracts\Carrier;
use PlacetoPay\Contracts\Entity;
use Dnetix\Redirection\Exceptions\PlacetoPayException;
use Dnetix\Redirection\Traits\LoaderTrait;
use GuzzleHttp\Client;

class Settings extends Entity
{
    use LoaderTrait;

    public const TP_REST = 'rest';
    public const TP_SOAP = 'soap';
    protected $type = self::TP_REST;
    protected $baseUrl = '';
    protected $timeout = 15;
    protected $verifySsl = true;
    protected $login;
    protected $tranKey;
    protected $headers = [];
    protected $authAdditional = [];
    protected $client = null;
    protected $carrier = null;

    public function __construct(array $data)
    {
        if (!isset($data['login']) || !isset($data['tranKey'])) {
            throw new PlacetoPayException('No login or tranKey provided on gateway');
        }

        if (!isset($data['baseUrl']) || !filter_var($data['baseUrl'], FILTER_VALIDATE_URL)) {
            throw new PlacetoPayException('No service URL provided to use');
        }

        if (substr($data['baseUrl'], -1) != '/') {
            $data['baseUrl'] .= '/';
        }

        if (isset($data['type']) && in_array($data['type'], [self::TP_SOAP, self::TP_REST])) {
            $this->type = $data['type'];
        }

        $allowedKeys = [
            'baseUrl',
            'timeout',
            'verifySsl',
            'login',
            'tranKey',
            'headers',
            'client',
            'authAdditional',
        ];

        $this->load($data, $allowedKeys);
    }

    public function baseUrl(string $endpoint = ''): string
    {
        return $this->baseUrl . $endpoint;
    }

    public function wsdl(): string
    {
        return $this->baseUrl('soap/redirect?wsdl');
    }

    public function location(): ?string
    {
        return $this->baseUrl('soap/redirect');
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    public function verifySsl(): bool
    {
        return $this->verifySsl;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function login(): string
    {
        return $this->login;
    }

    public function tranKey(): string
    {
        return $this->tranKey;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function client(): Client
    {
        if (!$this->client) {
            $this->client = new Client([
                'timeout' => $this->timeout(),
                'connect_timeout' => $this->timeout(),
                'verify' => $this->verifySsl(),
            ]);
        }
        return $this->client;
    }

    public function toArray(): array
    {
        return [];
    }

    public function carrier(): Carrier
    {
        if ($this->carrier instanceof Carrier) {
            return $this->carrier;
        }

        $this->carrier = new RestCarrier($this);

        return $this->carrier;
    }

    public function authentication(): Authentication
    {
        return new Authentication([
            'login' => $this->login(),
            'tranKey' => $this->tranKey(),
            'authAdditional' => $this->authAdditional,
        ]);
    }
}
