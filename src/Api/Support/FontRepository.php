<?php

declare (strict_types=1);
namespace JooosiFon\Api\Support;

/**
 * Owns database writes for font records and turns silent wpdb failures into
 * exceptions that REST controllers can handle consistently.
 */
final class FontRepository
{
    public function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'jooosi_fon_fonts';
    }
    public function find(int $id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id));
    }
    public function findOfType(int $id, string $type)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d AND type = %s", $id, $type));
    }
    public function findBySlug(string $slug)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE slug = %s LIMIT 1", $slug));
    }
    /**
     * @param array<string, mixed> $data
     * @param string[] $formats
     */
    public function insert(array $data, array $formats): int
    {
        global $wpdb;
        if ($wpdb->insert($this->table(), $data, $formats) === \false || $wpdb->insert_id < 1) {
            throw new \RuntimeException($wpdb->last_error ?: 'Unable to create the font record.');
        }
        return (int) $wpdb->insert_id;
    }
    /**
     * @param array<string, mixed> $data
     * @param string[] $formats
     */
    public function update(int $id, array $data, array $formats): void
    {
        global $wpdb;
        if ($wpdb->update($this->table(), $data, ['id' => $id], $formats, ['%d']) === \false) {
            throw new \RuntimeException($wpdb->last_error ?: 'Unable to update the font record.');
        }
    }
    public function delete(int $id): void
    {
        global $wpdb;
        if ($wpdb->delete($this->table(), ['id' => $id], ['%d']) === \false) {
            throw new \RuntimeException($wpdb->last_error ?: 'Unable to delete the font record.');
        }
    }
    public function deleteByType(string $type): void
    {
        global $wpdb;
        if ($wpdb->delete($this->table(), ['type' => $type], ['%s']) === \false) {
            throw new \RuntimeException($wpdb->last_error ?: 'Unable to delete the font records.');
        }
    }
    /**
     * @return mixed
     */
    public function transaction(callable $callback)
    {
        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === \false) {
            throw new \RuntimeException($wpdb->last_error ?: 'Unable to start the database transaction.');
        }
        try {
            $result = $callback($this);
            if ($wpdb->query('COMMIT') === \false) {
                throw new \RuntimeException($wpdb->last_error ?: 'Unable to commit the database transaction.');
            }
            return $result;
        } catch (\Throwable $throwable) {
            $wpdb->query('ROLLBACK');
            throw $throwable;
        }
    }
}
