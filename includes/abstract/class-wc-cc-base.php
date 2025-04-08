<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\Abstract;

use WC_Integration;

/**
 * Base abstract class for Convert Cart functionality.
 */
abstract class WC_CC_Base {
    /**
     * @var WC_Integration Parent integration instance
     */
    protected WC_Integration $integration;

    /**
     * Constructor.
     *
     * @param WC_Integration $integration Parent integration instance
     */
    public function __construct(WC_Integration $integration) {
        $this->integration = $integration;
    }

    /**
     * Initialize hooks.
     */
    abstract public function init(): void;
} 