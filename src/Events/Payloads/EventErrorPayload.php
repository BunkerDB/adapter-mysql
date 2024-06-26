<?php
declare(strict_types=1);


namespace Cratia\ORM\DBAL\Adapter\Events\Payloads;


use Exception as DBALException;
use JsonSerializable;

/**
 * Class EventErrorPayload
 * @package Cratia\ORM\DBAL\Adapter\Events\Payloads
 */
class EventErrorPayload extends EventPayload implements JsonSerializable
{
    /**
     * @var DBALException
     */
    private $exception;

    /**
     * Query constructor.
     * @param string $sentence
     * @param array $params
     * @param array $types
     * @param DBALException $exception
     */
    public function __construct(string $sentence, array $params, array $types, DBALException $exception)
    {
        $this->exception = $exception;
        parent::__construct($sentence, $params, $types);
    }

    /**
     * @return DBALException
     */
    public function getException(): DBALException
    {
        return $this->exception;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), ['exception' => $this->getException()]
        );
    }
}