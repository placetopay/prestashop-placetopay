<?php

namespace PlacetoPay\Carrier;

use Dnetix\Redirection\Exceptions\PlacetoPayException;
use Dnetix\Redirection\Message\CollectRequest;
use Dnetix\Redirection\Message\RedirectInformation;
use Dnetix\Redirection\Message\RedirectRequest;
use Dnetix\Redirection\Message\RedirectResponse;
use Dnetix\Redirection\Message\ReverseResponse;
use GuzzleHttp\Exception\BadResponseException;
use PlacetoPay\Contracts\Carrier;
use Throwable;

class RestCarrier extends Carrier
{
    private function makeRequest(string $url, array $arguments): array
    {
        try {
            $data = array_merge($arguments, ['auth' => $this->settings->authentication()->asArray()]);

            $response = $this->settings->client()->post($url, [
                'json' => $data,
                'headers' => $this->settings->headers(),
            ]);
            $result = $response->getBody()->getContents();
        } catch (BadResponseException $exception) {
            $result = $exception->getResponse()->getBody()->getContents();
        } catch (Throwable $exception) {
            throw new PlacetoPayException($exception);
        }

        return json_decode($result, true);
    }

    public function request(RedirectRequest $redirectRequest): RedirectResponse
    {
        $result = $this->makeRequest($this->settings->baseUrl('api/session'), $redirectRequest->toArray());
        return new RedirectResponse($result);
    }

    public function query(string $requestId): RedirectInformation
    {
        $result = $this->makeRequest($this->settings->baseUrl('api/session/' . $requestId), []);
        return new RedirectInformation($result);
    }

    public function collect(CollectRequest $collectRequest): RedirectInformation
    {
        $result = $this->makeRequest($this->settings->baseUrl('api/collect'), $collectRequest->toArray());
        return new RedirectInformation($result);
    }

    public function reverse(string $transactionId): ReverseResponse
    {
        $result = $this->makeRequest($this->settings->baseUrl('api/reverse'), [
            'internalReference' => $transactionId,
        ]);
        return new ReverseResponse($result);
    }
}
