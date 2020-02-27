<?php
declare(strict_types=1);


namespace Cratia\ORM\DBAL\Adapter\Events\Payloads;


/**
 * Class EventBeforePayload
 * @package Cratia\ORM\DBAL\Adapter\Events\Payloads
 */
class EventBeforePayload extends EventPayload
{
    /**
     * EventQueryAfter constructor.
     * @param string $sentence
     * @param array $params
     * @param array $types
     */
    public function __construct(string $sentence, array $params, array $types)
    {
        parent::__construct($sentence, $params, $types);
    }
}