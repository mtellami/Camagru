<?php
declare(strict_types=1);
namespace Camagru\Security;
use Camagru\Database\Database;
use PDO;
final class DatabaseSession implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    private ?PDO $db = null;
    private function db(): PDO
    {
        // A separate transaction holds the row lock without enclosing application writes.
        return $this->db ??= Database::open();
    }
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool
    {
        if ($this->db?->inTransaction()) { $this->db->commit(); }
        $this->db = null;
        return true;
    }
    public function validateId(string $id): bool
    {
        $q = Database::query('SELECT 1 FROM sessions WHERE id=? AND expires_at>NOW()', [hash('sha256', $id)]);
        return (bool) $q->fetchColumn();
    }
    public function read(string $id): string|false
    {
        $db = $this->db();
        if (!$db->inTransaction()) { $db->beginTransaction(); }
        $key = hash('sha256', $id);
        $q = $db->prepare("INSERT INTO sessions(id,payload,expires_at) VALUES (?,'',NOW()+INTERVAL '1 day') ON CONFLICT DO NOTHING");
        $q->execute([$key]);
        $q = $db->prepare('SELECT payload,expires_at>NOW() AS valid FROM sessions WHERE id=? FOR UPDATE');
        $q->execute([$key]);
        $row = $q->fetch();
        return $row['valid'] ? $row['payload'] : '';
    }
    public function write(string $id, string $data): bool
    {
        $q = $this->db()->prepare("INSERT INTO sessions(id,payload,expires_at) VALUES (?,?,NOW()+INTERVAL '1 day') ON CONFLICT(id) DO UPDATE SET payload=EXCLUDED.payload,expires_at=EXCLUDED.expires_at");
        return $q->execute([hash('sha256', $id), $data]);
    }
    public function destroy(string $id): bool
    {
        $q = $this->db()->prepare('DELETE FROM sessions WHERE id=?');
        return $q->execute([hash('sha256', $id)]);
    }
    public function gc(int $max_lifetime): int|false { return $this->db()->exec('DELETE FROM sessions WHERE expires_at<NOW()'); }
    public function updateTimestamp(string $id, string $data): bool { return $this->write($id, $data); }
}
