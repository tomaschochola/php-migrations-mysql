<?php

/**
 * @author Tomáš Chochola <tomaschochola@tomaschochola.cz>
 * @copyright © 2026 Tomáš Chochola <tomaschochola@tomaschochola.cz>
 *
 * @license CC-BY-ND-4.0
 *
 * @see {@link https://creativecommons.org/licenses/by-nd/4.0/} License
 * @see {@link https://github.com/tomaschochola} GitHub Profile
 * @see {@link https://github.com/sponsors/tomaschochola} GitHub Sponsors
 */

declare(strict_types=1);

namespace TomasChochola\Migrations\Mysql;

use NoDiscard;
use Override;
use Psr\Log\LoggerInterface;
use Stringable;
use TomasChochola\Migrations\MigrationsInterface;
use TomasChochola\Pdo\QueryInterface;

/**
 * @no-named-arguments
 */
readonly class MysqlMigrations implements MigrationsInterface
{
    private LoggerInterface $logger;

    private QueryInterface $query;

    public function __construct(QueryInterface $query, LoggerInterface $logger)
    {
        $this->query = $query;
        $this->logger = $logger;
    }

    #[Override()]
    public function end(): void
    {
    }

    #[Override()]
    public function execute(Stringable | string $sql): void
    {
        $this->query->run($sql);
    }

    #[NoDiscard()]
    #[Override()]
    public function has(string $selector): bool
    {
        return $this->query->int('SELECT COUNT(*) FROM migrations WHERE selector = ?', [$selector]) > 0;
    }

    public function init(): void
    {
        $this->start();
    }

    #[Override()]
    public function mark(string $selector): void
    {
        $sql = 'INSERT INTO migrations (selector) VALUES (?)';

        $this->logger->notice('migrator.sql', ['selector' => $selector, 'sql' => $sql]);
        $this->query->run($sql, [$selector]);
    }

    #[Override()]
    public function start(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `migrations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `selector` VARCHAR(255) NOT NULL UNIQUE,
                `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL;

        $this->logger->notice('migrator.sql', ['selector' => 'migrations', 'sql' => $sql]);
        $this->query->run($sql);
    }
}
