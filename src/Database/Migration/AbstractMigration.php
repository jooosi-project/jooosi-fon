<?php

declare (strict_types=1);
namespace JooosiFon\Database\Migration;

use JooosiFon\Database\Migration\Exception\AbortMigration;
use JooosiFon\Database\Migration\Exception\SkipMigration;
/**
 * Base class for Jooosi Fon schema migrations.
 */
abstract class AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }
    public function abortIf(bool $condition, string $message = 'Unknown reason'): void
    {
        if ($condition) {
            throw new AbortMigration($message);
        }
    }
    public function skipIf(bool $condition, string $message = 'Unknown reason'): void
    {
        if ($condition) {
            throw new SkipMigration($message);
        }
    }
    public function preUp(): void
    {
    }
    public function postUp(): void
    {
    }
    public function preDown(): void
    {
    }
    public function postDown(): void
    {
    }
    abstract public function up(): void;
    public function down(): void
    {
        $this->abortIf(\true, sprintf('No down() migration implemented for "%s".', static::class));
    }
    protected function collation(): string
    {
        global $wpdb;
        if (!$wpdb->has_cap('collation')) {
            return '';
        }
        return $wpdb->get_charset_collate();
    }
}
