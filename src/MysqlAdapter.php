<?php
declare(strict_types=1);


namespace Cratia\ORM\DBAL\Adapter;


use Cratia\Common\Functions;
use Cratia\ORM\DBAL\Adapter\Events\Events;
use Cratia\ORM\DBAL\Adapter\Events\Payloads\EventErrorPayload;
use Cratia\ORM\DBAL\Adapter\Events\Payloads\EventAfterEventPayload;
use Cratia\ORM\DBAL\Adapter\Events\Payloads\EventBeforeEventPayload;
use Cratia\ORM\DBAL\Adapter\Interfaces\IAdapter;
use Cratia\ORM\DBAL\Adapter\Interfaces\ISqlPerformance;
use Doctrine\Common\EventArgs;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\FetchMode;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use stdClass;

/**
 * Class MysqlAdapter
 * @package Cratia\ORM\DBAL\Adapter
 */
class MysqlAdapter implements IAdapter
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @var EventManager|null
     */
    private $eventManager;

    /**
     * Adapter constructor.
     * @param array $params
     * @param LoggerInterface|null $logger
     * @param EventManager|null $eventManager
     * @throws DBALException
     */
    public function __construct(array $params, ?LoggerInterface $logger = null, ?EventManager $eventManager = null)
    {
        $this->connection = DriverManager::getConnection($params, null, $eventManager);
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @param LoggerInterface $logger
     * @return IAdapter
     */
    public function setLogger(LoggerInterface $logger): IAdapter
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @return LoggerInterface|null
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @param EventManager $eventManager
     * @return IAdapter
     */
    public function setEventManager(EventManager $eventManager): IAdapter
    {
        $this->eventManager = $eventManager;
        return $this;
    }

    /**
     * @return EventManager|null
     */
    public function getEventManager(): ?EventManager
    {
        return $this->eventManager;
    }

    /**
     * @param string $sentence
     * @param array $params
     * @param array $types
     * @return mixed[]
     * @throws DBALException
     */
    public function query(string $sentence, array $params = [], array $types = []): array
    {
        $sentence = trim($sentence);

        $this->notify(
            Events::ON_BEFORE_QUERY,
            new EventBeforeEventPayload($sentence, $params, $types)
        );

        try {
            $time = -microtime(true);

            $result = $this
                ->getConnection()
                ->executeQuery($sentence, $params, $types)
                ->fetchAll(FetchMode::ASSOCIATIVE);

            $time += microtime(true);
            $this->logPerformance($sentence, $params, $time);
        } catch (Exception $_e) {
            $e = new DBALException($_e->getMessage(), $_e->getCode(), $_e->getPrevious());
            $this->logError(__METHOD__, $e);
            $this->notify(Events::ON_ERROR, new EventErrorPayload($sentence, $params, $types, $e));
            throw $e;
        }

        $this->notify(
            Events::ON_AFTER_QUERY,
            new EventAfterEventPayload($sentence, $params, $types, $result, $this->calculatePerformance($time))
        );

        return $result;
    }

    /**
     * @param string $sentence
     * @param array $params
     * @param array $types
     * @return int
     * @throws DBALException
     */
    public function nonQuery(string $sentence, array $params = [], array $types = []): int
    {
        $sentence = trim($sentence);

        $this->notify(
            Events::ON_BEFORE_NON_QUERY,
            new EventBeforeEventPayload($sentence, $params, $types)
        );

        try {
            $time = -microtime(true);

            $affectedRows = $this->getConnection()->executeUpdate($sentence, $params, $types);

            $time += microtime(true);
            $this->logPerformance($sentence, $params, $time);
        } catch (Exception $_e) {
            $e = new DBALException($_e->getMessage(), $_e->getCode(), $_e->getPrevious());
            $this->logError(__METHOD__, $e);
            $this->notify(Events::ON_ERROR, new EventErrorPayload($sentence, $params, $types, $e));
            throw $e;
        }

        $this->notify(
            Events::ON_AFTER_NON_QUERY,
            new EventAfterEventPayload($sentence, $params, $types, ['affectedRows' => $affectedRows], $this->calculatePerformance($time))
        );

        return $affectedRows;
    }

    /**
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * @param string $sentence
     * @param array $params
     * @param float $time
     */
    protected function logPerformance(string $sentence, array $params, float $time): void
    {
        $_performance = $this->calculatePerformance($time);
        $performance = new stdClass;
        $performance->sql = Functions::formatSql($sentence, $params);
        $performance->run_time = $_performance->getRuntime();
        $performance->memmory = $_performance->getMemory();
        $this->logInfo(json_encode($performance));
    }

    /**
     * @param float $time
     * @return ISqlPerformance
     */
    protected function calculatePerformance(float $time): ISqlPerformance
    {
        return new SqlPerformance($time);
    }

    /**
     * @param $level
     * @param string $message
     */
    public function log($level, string $message): void
    {
        if (
            !is_null($logger = $this->getLogger()) &&
            ($logger instanceof LoggerInterface)
        ) {
            $logger->log($level, $message);
        }
    }

    /**
     * @param string $location
     * @param Exception $e
     */
    public function logError(string $location, Exception $e)
    {
        $this->log(LogLevel::ERROR, "Error in the {$location}(...) -> {$e->getMessage()}");
    }

    /**
     * @param string $message
     */
    public function logInfo(string $message): void
    {
        $this->log(LogLevel::INFO, $message);
    }

    /**
     * @param string $eventName
     * @param EventArgs $event
     * @return $this
     */
    public function notify(string $eventName, EventArgs $event)
    {
        if (
            !is_null($eventManager = $this->getEventManager()) &&
            ($eventManager instanceof EventManager)
        ) {
            $eventManager->dispatchEvent($eventName, $event);
        }
        return $this;
    }
}