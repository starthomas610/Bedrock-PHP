<?php

declare(strict_types=1);

namespace Expensify\Bedrock;

use Exception;
use Psr\Log\LoggerInterface;
use SQLite3;

/**
 * Class the represents a database on the local server.
 */
class LocalDB
{
    /** @var SQLite3 $handle */
    private $handle;

    /** @var string $location */
    private $location;

    /** @var LoggerInterface $logger */
    private $logger;

    /** @var Stats\StatsInterface */
    private $stats;

    /**
     * Creates a localDB object and sets the file location.
     *
     * @param Stats\StatsInterface $stats
     */
    public function __construct(string $location, LoggerInterface $logger, $stats)
    {
        $this->location = $location;
        $this->logger = $logger;
        $this->stats = $stats;
    }

    /**
     * Opens a DB connection.
     */
    public function open()
    {
        if (!isset($this->handle)) {
            $this->stats->benchmark('bedrockWorkerManager.db.open', function () {
                $this->handle = new SQLite3($this->location);
                $this->handle->busyTimeout(15000);
                $this->handle->enableExceptions(true);
            });
        }
    }

    /**
     * Close the DB connection and unset the object.
     */
    public function close()
    {
        if (isset($this->handle)) {
            $this->stats->benchmark('bedrockWorkerManager.db.close', function () {
                $startTime = microtime(true);
                $this->handle->close();
                unset($this->handle);
            });
        }
    }

    /**
     * Runs a read query on a local database.
     *
     * @return ?array
     */
    public function read(string $query)
    {
        $result = null;
        $returnValue = null;
        while (true) {
            try {
                $result = $this->handle->query($query);
                break;
            } catch (Exception $e) {
                if ($e->getMessage() === 'database is locked') {
                    $this->logger->info("Query failed, retrying", ['query' => $query, 'error' => $e->getMessage()]);
                } else {
                    $this->logger->info("Query failed, not retrying", ['query' => $query, 'error' => $e->getMessage()]);
                    throw $e;
                }
            }
        }

        if ($result) {
            $returnValue = $result->fetchArray(SQLITE3_NUM);
        }

        return $returnValue;
    }

    /**
     * Runs a write query on a local database.
     */
    public function write(string $query)
    {
        while (true) {
            try {
                $this->handle->query($query);
                break;
            } catch (Exception $e) {
                if ($e->getMessage() === 'database is locked') {
                    $this->logger->info("Query failed, retrying", ['query' => $query, 'error' => $e->getMessage()]);
                } else {
                    $this->logger->info("Query failed, not retrying", ['query' => $query, 'error' => $e->getMessage()]);
                    throw $e;
                }
            }
        }
    }

    /**
     * Gets last inserted row.
     *
     * @return int|null
     */
    public function getLastInsertedRowID()
    {
        if (!isset($this->handle)) {
            return null;
        }

        return $this->handle->lastInsertRowID();
    }
}
