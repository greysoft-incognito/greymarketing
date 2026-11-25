<?php

namespace App\Messaging\Contracts;

interface Driver
{
    /**
     * Check that the credentials work, throwing a ProviderException when they do not.
     */
    public function verify(): void;
}
