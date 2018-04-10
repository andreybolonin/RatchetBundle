<?php

namespace Andreybolonin\RatchetBundle\Periodic;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class PdoPeriodicPing.
 */
class PdoPeriodicPing
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $pdo;

    /**
     * @var LoggerInterface|NullLogger
     */
    protected $logger;

    /**
     * @var int|float
     */
    protected $timeout;

    /**
     * PdoPeriodicPing constructor.
     *
     * @param EntityManagerInterface   $em
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(EntityManagerInterface $em, LoggerInterface $logger)
    {
        $this->pdo = $em->getConnection();
        $this->logger = $logger;
        $this->timeout = 20;
    }

    /**
     * @param int|float $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function tick()
    {
        if (null === $this->pdo) {
            $this->logger->warning('Unable to ping sql server, service em is unavailable');

            return;
        }

        try {
            $startTime = microtime(true);
            $this->pdo->query('SELECT 1');
            $endTime = microtime(true);
            $this->logger->info(sprintf('Successfully ping sql server (~%s ms)', round(($endTime - $startTime) * 100000)));
        } catch (\PDOException $e) {
            $this->logger->emergency('Sql server is gone, and unable to reconnect');
            throw $e;
        }
    }

    /**
     * @return int|float
     */
    public function getTimeout()
    {
        return $this->timeout;
    }
}
