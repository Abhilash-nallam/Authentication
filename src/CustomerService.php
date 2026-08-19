<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;

final class CustomerService
{
    public function __construct(private PDO $db) {}

    public function register(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 320) {
            throw new \InvalidArgumentException('Invalid email address.');
        }
        if (strlen($password) < 10 || strlen($password) > 200) {
            throw new \InvalidArgumentException('Password must be 10-200 characters.');
        }
        $stmt = $this->db->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) throw new \DomainException('Account already exists.');

        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare(
            'INSERT INTO customers (email,password_hash,email_verification_hash,email_verification_expires_at,status)
             VALUES (?,?,?,?,\'pending\')'
        );
        $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), hash('sha256', $token), gmdate('Y-m-d H:i:s', time()+86400)]);
        return ['id' => (int)$this->db->lastInsertId(), 'email' => $email, 'verification_token' => $token];
    }

    public function verifyEmail(string $token): bool
    {
        $hash = hash('sha256', trim($token));
        $stmt = $this->db->prepare(
            "UPDATE customers SET email_verified_at=NOW(), status='active', email_verification_hash=NULL, email_verification_expires_at=NULL
             WHERE email_verification_hash=? AND email_verification_expires_at > UTC_TIMESTAMP() AND status='pending'"
        );
        $stmt->execute([$hash]);
        return $stmt->rowCount() === 1;
    }

    public function login(string $email, string $password): string
    {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE email=? AND status='active' LIMIT 1");
        $stmt->execute([strtolower(trim($email))]);
        $customer = $stmt->fetch();
        if (!$customer || !password_verify($password, $customer['password_hash'])) {
            throw new \DomainException('Invalid credentials.');
        }
        $token = bin2hex(random_bytes(32));
        $this->db->prepare('INSERT INTO customer_sessions (customer_id,token_hash,expires_at) VALUES (?,?,?)')
            ->execute([$customer['id'], hash('sha256', $token), gmdate('Y-m-d H:i:s', time()+86400)]);
        return $token;
    }

    public function customerFromSession(string $token): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT c.* FROM customer_sessions s JOIN customers c ON c.id=s.customer_id
             WHERE s.token_hash=? AND s.expires_at > UTC_TIMESTAMP() AND c.status='active' LIMIT 1"
        );
        $stmt->execute([hash('sha256', $token)]);
        return $stmt->fetch() ?: null;
    }

    public function logout(string $token): void
    {
        $this->db->prepare('DELETE FROM customer_sessions WHERE token_hash=?')->execute([hash('sha256', $token)]);
    }
}
