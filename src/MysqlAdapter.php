<?php
declare(strict_types=1);


namespace Cratia\ORM\DBAL\Adapter;


use Cratia\Common\Functions;
use Cratia\ORM\DBAL\Adapter\Events\Events;
use Cratia\ORM\DBAL\Adapter\Events\Payloads\EventErrorPayload;
use Cratia\ORM\DBAL\Adapter\Events\Payloads\EventAfterPayload;
use Cratia\ORM\DBAL\Adapter\Events\Payloads\EventBeforePayload;
use Cratia\ORM\DBAL\Adapter\Interfaces\IAdapter;
use Cratia\ORM\DBAL\Adapter\Interfaces\ISqlPerformance;
use Cratia\Pipeline;
use Doctrine\Common\EventArgs;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Exception as DBALException;
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
     * @throws \Doctrine\DBAL\Exception
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
     * @throws \Doctrine\DBAL\Exception
     */
    public function query(string $sentence, array $params = [], array $types = []): array
    {
        $sentence = trim($sentence);
        return Pipeline::try(
            function () {
            })
            ->tap(function () use ($sentence, $params, $types) {
                $this->notify(
                    Events::ON_BEFORE_QUERY,
                    new EventBeforePayload($sentence, $params, $types)
                );
            })
            ->then(function () use ($sentence, $params, $types) {
                $time = -microtime(true);
                $result = $this->_query($sentence, $params, $types);
                $time += microtime(true);
                return [$result, $time];
            })
            ->tap(function (array $response) use ($sentence, $params, $types) {
                list($result, $time) = $response;
                $this->notify(
                    Events::ON_AFTER_QUERY,
                    new EventAfterPayload($sentence, $params, $types, $result, $this->calculatePerformance($time))
                );
            })
            ->tap(function (array $response) use ($sentence, $params) {
                list($_, $time) = $response;
                $this->logPerformance($sentence, $params, $time);
            })
            ->then(function (array $response) {
                list($result, $_) = $response;
                return $result;
            })
            ->catch(function (\Doctrine\DBAL\Exception $e) {
                throw $e;
            })
            ->tapCatch(function (\Doctrine\DBAL\Exception $e) use ($sentence, $params, $types) {
                $this->notify(
                    Events::ON_ERROR,
                    new EventErrorPayload($sentence, $params, $types, $e)
                );
            })
            ->tapCatch(function (Exception $e) use ($sentence, $params, $types) {
                $this->logError(__METHOD__, $e);
            })
        ();
    }

    /**
     * @param string $sentence
     * @param array $params
     * @param array $types
     * @return array
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function _query(string $sentence, array $params = [], array $types = []): array
    {
        try {
            $result = $this
                ->getConnection()
                ->executeQuery($sentence, $params, $types)
                ->fetchAssociative();
        } catch (Exception $_e) {
            throw new Exception($_e->getMessage(), $_e->getCode(), $_e->getPrevious());
        }
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
        return Pipeline::try(
            function () {
            })
            ->tap(function () use ($sentence, $params, $types) {
                $this->notify(
                    Events::ON_BEFORE_NON_QUERY,
                    new EventBeforePayload($sentence, $params, $types)
                );
            })
            ->then(function () use ($sentence, $params, $types) {
                $time = -microtime(true);
                $affectedRows = $this->_nonQuery($sentence, $params, $types);
                $time += microtime(true);
                return [$affectedRows, $time];
            })
            ->tap(function (array $response) use ($sentence, $params, $types) {
                list($affectedRows, $time) = $response;
                $this->notify(
                    Events::ON_AFTER_NON_QUERY,
                    new EventAfterPayload($sentence, $params, $types, ['affectedRows' => $affectedRows], $this->calculatePerformance($time))
                );
            })
            ->tap(function (array $response) use ($sentence, $params) {
                list($_, $time) = $response;
                $this->logPerformance($sentence, $params, $time);
            })
            ->then(function (array $response) {
                list($affectedRows, $_) = $response;
                return $affectedRows;
            })
            ->catch(function (DBALException $e) {
                throw $e;
            })
            ->tapCatch(function (DBALException $e) use ($sentence, $params, $types) {
                $this->notify(
                    Events::ON_ERROR,
                    new EventErrorPayload($sentence, $params, $types, $e)
                );
            })
            ->tapCatch(function (DBALException $e) use ($sentence, $params, $types) {
                $this->logError(__METHOD__, $e);
            })
        ();
    }

    /**
     * @param string $sentence
     * @param array $params
     * @param array $types
     * @return int
     * @throws DBALException
     */
    public function _nonQuery(string $sentence, array $params = [], array $types = []): int
    {
        try {
            $affectedRows = $this
                ->getConnection()
                ->executeUpdate($sentence, $params, $types);
        } catch (Exception $_e) {
            throw new DBALException($_e->getMessage(), $_e->getCode(), $_e->getPrevious());
        }
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
        $performance->prettyRunTime = $_performance->getPrettyRunTime();
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