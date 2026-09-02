<?php

declare (strict_types=1);
namespace JooosiFon\Migrations;

use JooosiFon\Database\Migration\AbstractMigration;
/**
 * Repair installations where the rebrand migration history was recorded but
 * the Jooosi Fon font table was never created or restored.
 */
final class Version20260902000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repair a missing Jooosi Fon fonts table after the rebrand.';
    }
    public function up(): void
    {
        // Reapply the idempotent schema optimization when an earlier history
        // row says it ran even though the table itself was lost.
        (new \JooosiFon\Migrations\Version20260818000000())->up();
    }
}
