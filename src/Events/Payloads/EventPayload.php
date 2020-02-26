<?php
declare(strict_types=1);


namespace Cratia\ORM\DBAL\Adapter\Events\Payloads;


use Doctrine\Common\EventArgs;
use JsonSerializable;

/**
 * Class Payload
 * @package Cratia\ORM\DBAL\Adapter\Events\Payloads
 */
class EventPayload extends EventArgs implements JsonSerializable
{
    /**
     * @var string
     */
    private $sentence;
    /**
     * @var array
     */
    private $params;
    /**
     * @var array
     */
    private $types;

    /**
     * EventQueryAfter constructor.
     * @param string $sentence
     * @param array $params
     * @param array $types
     */
    public function __construct(string $sentence, array $params, array $types)
    {
        $this->sentence = $sentence;
        $this->params = $params;
        $this->types = $types;
    }

    /**
     * @return string
     */
    public function getSentence(): string
    {
        return $this->sentence;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return array
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return
            [
                'sentence' => $this->getSentence(),
                'params' => $this->getParams(),
                'types' => $this->getTypes(),
            ];
    }
}