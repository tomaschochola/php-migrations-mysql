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
use TomasChochola\Migrations\MigrationsInterface;
use TomasChochola\Migrations\Mysql\MysqlMigrations;
use TomasChochola\Pdo\QueryInterface;

use function str_contains;

/**
 * @internal
 *
 * @no-named-arguments
 */
#[CoversClass(MysqlMigrations::class)]
#[Small()]
class CompatibilityTest extends TestCase
{
    #[Test()]
    public function currentAndLegacyContractsRemainAvailable(): void
    {
        $class = new ReflectionClass(MysqlMigrations::class);

        self::assertTrue($class->implementsInterface(MigrationsInterface::class));
        self::assertTrue($class->hasMethod('init'));
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
        $query = $this->createMock(QueryInterface::class);

        $query
            ->expects($this->once())
            ->method('run')
            ->with(self::callback(static fn(Stringable | string $sql): bool => str_contains((string) $sql, 'CREATE TABLE IF NOT EXISTS `migrations`')))
            ->seal();

        (new MysqlMigrations($query, self::createStub(LoggerInterface::class)))->init();
    }
}
