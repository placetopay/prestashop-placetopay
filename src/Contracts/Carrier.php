<?php

namespace PlacetoPay\Contracts;

use Dnetix\Redirection\Message\CollectRequest;
use Dnetix\Redirection\Message\RedirectInformation;
use Dnetix\Redirection\Message\RedirectRequest;
use Dnetix\Redirection\Message\RedirectResponse;
use Dnetix\Redirection\Message\ReverseResponse;
use PlacetoPay\Helpers\Settings;

abstract class Carrier
{
    /**
     * @var Settings
     */
    protected $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    abstract public function request(RedirectRequest $redirectRequest): RedirectResponse;

    abstract public function query(string $requestId): RedirectInformation;

    abstract public function collect(CollectRequest $collectRequest): RedirectInformation;

    abstract public function reverse(string $transactionId): ReverseResponse;
}
