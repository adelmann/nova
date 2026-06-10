<?php

declare(strict_types=1);

namespace Nova\Models;

final class UserRepository extends BaseRepository
{
    protected string $table = 'user';

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->db()->fetch(
            'SELECT * FROM user WHERE email = :email',
            ['email' => $email]
        );
    }

    public function create(string $email, string $passwordHash, string $name): int
    {
        return $this->insert([
            'email'         => $email,
            'password_hash' => $passwordHash,
            'name'          => $name,
            'role'          => 'admin',
        ]);
    }

    /** Legt einen Benutzer mit Rolle an (für die Benutzerverwaltung). */
    public function createWithRole(string $email, string $passwordHash, string $name, string $role): int
    {
        return $this->insert([
            'email'         => $email,
            'password_hash' => $passwordHash,
            'name'          => $name,
            'role'          => $role,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function allOrdered(): array
    {
        return $this->db()->fetchAll('SELECT * FROM user ORDER BY is_active DESC, name, email');
    }

    public function setRole(int $id, string $role): void
    {
        $this->updateById($id, ['role' => $role]);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->updateById($id, ['is_active' => $active ? 1 : 0]);
    }

    public function updateName(int $id, string $name): void
    {
        $this->updateById($id, ['name' => $name]);
    }

    /** Anzahl aktiver Admins (für Schutz vor Aussperrung). */
    public function activeAdminCount(): int
    {
        return (int) $this->db()->fetchColumn(
            "SELECT COUNT(*) FROM user WHERE role = 'admin' AND is_active = 1"
        );
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->updateById($id, ['password_hash' => $passwordHash]);
    }

    /** Speichert einen (gehashten) Reset-Token mit Ablaufzeitpunkt. */
    public function setResetToken(int $id, string $tokenHash, string $expiresAt): void
    {
        $this->updateById($id, ['reset_token_hash' => $tokenHash, 'reset_expires_at' => $expiresAt]);
    }

    /** Findet einen Benutzer mit gültigem (nicht abgelaufenem) Reset-Token. */
    public function findByValidResetToken(string $tokenHash): ?array
    {
        return $this->db()->fetch(
            "SELECT * FROM user
             WHERE reset_token_hash = :h AND reset_token_hash <> ''
               AND reset_expires_at IS NOT NULL AND reset_expires_at > :now",
            ['h' => $tokenHash, 'now' => date('Y-m-d H:i:s')]
        );
    }

    /** Setzt das neue Passwort und entwertet den Reset-Token. */
    public function resetPassword(int $id, string $passwordHash): void
    {
        $this->updateById($id, [
            'password_hash'    => $passwordHash,
            'reset_token_hash' => '',
            'reset_expires_at' => null,
        ]);
    }

    public function updateEmail(int $id, string $email): void
    {
        $this->updateById($id, ['email' => $email]);
    }

    /** Prüft, ob die E-Mail bereits einem anderen Benutzer gehört. */
    public function emailTakenByOther(string $email, int $exceptId): bool
    {
        $existing = $this->findByEmail($email);
        return $existing !== null && (int) $existing['id'] !== $exceptId;
    }

    /**
     * Aktiviert 2FA mit Secret und (gehashten) Recovery-Codes.
     *
     * @param array<int,string> $recoveryHashes
     */
    public function enableTotp(int $id, string $secret, array $recoveryHashes): void
    {
        $this->updateById($id, [
            'totp_secret'    => $secret,
            'totp_enabled'   => 1,
            'recovery_codes' => json_encode(array_values($recoveryHashes)),
        ]);
    }

    public function disableTotp(int $id): void
    {
        $this->updateById($id, ['totp_secret' => '', 'totp_enabled' => 0, 'recovery_codes' => '']);
    }

    /**
     * Prüft einen Recovery-Code gegen die gespeicherten Hashes und verbraucht
     * ihn bei Erfolg (einmalig). Gibt true bei Treffer zurück.
     */
    public function consumeRecoveryCode(int $id, string $code): bool
    {
        $user = $this->find($id);
        if ($user === null) {
            return false;
        }
        $codes = json_decode((string) ($user['recovery_codes'] ?? ''), true);
        if (!is_array($codes)) {
            return false;
        }
        foreach ($codes as $i => $hash) {
            if (is_string($hash) && password_verify($code, $hash)) {
                unset($codes[$i]);
                $this->updateById($id, ['recovery_codes' => json_encode(array_values($codes))]);
                return true;
            }
        }
        return false;
    }
}
