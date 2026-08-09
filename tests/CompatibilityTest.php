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

namespace Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Stringable;
use TomasChochola\Database\Mysql\Contract\QueryInterface;
use TomasChochola\Migrations\MigrationsInterface;
use TomasChochola\Migrations\Mysql\MysqlMigrations;

/**
 * @internal
 *
 * @no-named-arguments
 */
#[CoversClass(MysqlMigrations::class)]
#[Small()]
final class CompatibilityTest extends TestCase
{
    #[Test()]
    public function currentAndLegacyContractsRemainAvailable(): void
    {
        $class = new ReflectionClass(MysqlMigrations::class);

        self::assertTrue($class->implementsInterface(MigrationsInterface::class));
        self::assertTrue($class->hasMethod('init'));
    }

    #[Test()]
    public function endingMigrationStorageHasNoAdditionalSideEffects(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $query->expects($this->never())->method('run')->seal();
        $logger->expects($this->never())->method('log')->seal();
        (new MysqlMigrations($query, $logger))->end();
    }

    #[Test()]
    public function executesMigrationSqlThroughTheCompatibleQueryContract(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $query->expects($this->once())->method('run')->with('SELECT 1')->seal();
        (new MysqlMigrations($query, self::createStub(LoggerInterface::class)))->execute('SELECT 1');
    }

    #[Test()]
    public function legacyInitCreatesTheMigrationsTable(): void
    {
        $query = self::createStub(QueryInterface::class);
        $logger = self::createStub(LoggerInterface::class);
        $queries = [];
        $records = [];

        $query->method('run')->willReturnCallback(static function (Stringable | string $sql, array $params = []) use (&$queries): void {
            $queries[] = [(string) $sql, $params];
        });

        $logger->method('notice')->willReturnCallback(static function (Stringable | string $message, array $context = []) use (&$records): void {
            $records[] = [(string) $message, $context];
        });

        (new MysqlMigrations($query, $logger))->init();

        self::assertCount(1, $queries);
        self::assertSame([], $queries[0][1]);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `migrations`', $queries[0][0]);

        self::assertSame([
            ['migrator.sql', ['selector' => 'migrations', 'sql' => $queries[0][0]]],
        ], $records);
    }

    #[Test()]
    public function markingMigrationExecutesAndLogsTheSelector(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $query
            ->expects($this->once())
            ->method('run')
            ->with('INSERT INTO migrations (selector) VALUES (?)', ['migration-1'])
            ->seal();

        $logger
            ->expects($this->once())
            ->method('notice')
            ->with('migrator.sql', [
                'selector' => 'migration-1',
                'sql' => 'INSERT INTO migrations (selector) VALUES (?)',
            ])
            ->seal();

        (new MysqlMigrations($query, $logger))->mark('migration-1');
    }

    #[Test()]
    public function migrationStateIsReadThroughTheCompatibleQueryContract(): void
    {
        $calls = [];
        $query = self::createStub(QueryInterface::class);

        $query->method('int')->willReturnCallback(static function (Stringable | string $sql, array $params) use (&$calls): int {
            $calls[] = [(string) $sql, $params];

            return $params === ['pending'] ? 0 : 1;
        });

        $migrations = new MysqlMigrations($query, self::createStub(LoggerInterface::class));

        self::assertFalse($migrations->has('pending'));
        self::assertTrue($migrations->has('applied'));

        self::assertSame([
            ['SELECT COUNT(*) FROM migrations WHERE selector = ?', ['pending']],
            ['SELECT COUNT(*) FROM migrations WHERE selector = ?', ['applied']],
        ], $calls);
    }
}
