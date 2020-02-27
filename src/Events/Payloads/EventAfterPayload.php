<?php
declare(strict_types=1);


namespace Cratia\ORM\DBAL\Adapter\Events\Payloads;


use Cratia\ORM\DBAL\Adapter\Interfaces\ISqlPerformance;
use JsonSerializable;

/**
 * Class EventAfterPayload
 * @package Cratia\ORM\DBAL\Adapter\Events\Payloads
 */
class EventAfterPayload extends EventPayload implements JsonSerializable
{
    /**
     * @var ISqlPerformance
     */
    private $performance;
    /**
     * @var array
     */
    private $result;

    /**
     * EventQueryAfter constructor.
     * @param string $sentence
     * @param array $params
     * @param array $types
     * @param array $result
     * @param ISqlPerformance $performance
     */
    public function __construct(
        string $sentence,
        array $params,
        array $types,
        array $result,
        ISqlPerformance $performance
    )
    {
        $this->performance = $performance;
        $this->result = $result;
        parent::__construct($sentence, $params, $types);
    }

    /**
     * @return ISqlPerformance
     */
    public function getPerformance(): ISqlPerformance
    {
        return $this->performance;
    }

    /**
     * @return array
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'performance' => $this->getPerformance(),
            ]
        );
    }
}