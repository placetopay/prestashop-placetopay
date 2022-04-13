<?php

namespace PlacetoPay\Constants;

/**
 * Interface PaymentStatus
 * @package PlacetoPay\Constants
 */
interface PaymentStatus
{
    /**
     * Transaction FAILED
     */
    const FAILED = 0;

    /**
     * Transaction APPROVED
     */
    const APPROVED = 1;

    /**
     * Transaction REJECTED
     */
    const REJECTED = 2;

    /**
     * Transaction PENDING
     */
    const PENDING = 3;

    /**
     * Transaction DUPLICATE (before APPROVED)
     */
    const DUPLICATE = 4;

    const STATUS = [
        self::FAILED => 'FAILED',
        self::APPROVED => 'APPROVED',
        self::REJECTED => 'REJECTED',
        self::PENDING => 'PENDING',
    ];
}
