<?php
declare(strict_types=1);


namespace Cratia\ORM\DBAL\Adapter\Interfaces;

/**
 * Interface ISqlPerformance
 * @package Cratia\ORM\DBAL\Adapter\Interfaces
 */
interface ISqlPerformance
{
    /**
     * @return string
     */
    public function getRuntime(): string;

    /**
     * @return string
     */
    public function getMemory(): string;
}