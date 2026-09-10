<?php

declare(strict_types=1);

namespace Mova\Mail;

use Mova\Core\Database;

class MailAccountRepository
{
    public function all(): array
    {
        return Database::fetchAll(
            "SELECT * FROM mail_accounts ORDER BY is_default DESC, sort_order ASC, id ASC"
        ) ?: [];
    }

    public function find(int $id): ?array
    {
        return Database::fetch("SELECT * FROM mail_accounts WHERE id = :id", ['id' => $id]) ?: null;
    }

    public function defaultAccount(): ?array
    {
        $row = Database::fetch("SELECT * FROM mail_accounts WHERE is_default = 1 ORDER BY id ASC LIMIT 1");
        if ($row) {
            return $row;
        }
        $all = $this->all();
        return $all[0] ?? null;
    }

    public function save(array $data, ?int $id = null): int
    {
        $now = date('c');
        $fields = [
            'label' => trim((string) ($data['label'] ?? 'Account')),
            'email' => trim((string) ($data['email'] ?? '')),
            'display_name' => trim((string) ($data['display_name'] ?? '')),
            'avatar' => trim((string) ($data['avatar'] ?? '')),
            'imap_host' => trim((string) ($data['imap_host'] ?? '')),
            'imap_port' => (int) ($data['imap_port'] ?? 993),
            'imap_encryption' => (string) ($data['imap_encryption'] ?? 'ssl'),
            'imap_username' => trim((string) ($data['imap_username'] ?? '')),
            'imap_password' => (string) ($data['imap_password'] ?? ''),
            'smtp_host' => trim((string) ($data['smtp_host'] ?? '')),
            'smtp_port' => (int) ($data['smtp_port'] ?? 587),
            'smtp_encryption' => (string) ($data['smtp_encryption'] ?? 'tls'),
            'smtp_username' => trim((string) ($data['smtp_username'] ?? '')),
            'smtp_password' => (string) ($data['smtp_password'] ?? ''),
            'is_default' => !empty($data['is_default']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => $now,
        ];

        if ($id) {
            $existing = $this->find($id);
            if ($existing && $fields['imap_password'] === '') {
                $fields['imap_password'] = $existing['imap_password'];
            }
            if ($existing && $fields['smtp_password'] === '') {
                $fields['smtp_password'] = $existing['smtp_password'];
            }
            if ($fields['is_default']) {
                Database::query("UPDATE mail_accounts SET is_default = 0");
            }
            Database::update('mail_accounts', $fields, 'id = :id', ['id' => $id]);
            return $id;
        }

        $fields['created_at'] = $now;
        if ($fields['is_default'] || !$this->all()) {
            Database::query("UPDATE mail_accounts SET is_default = 0");
            $fields['is_default'] = 1;
        }
        return (int) Database::insert('mail_accounts', $fields);
    }

    public function delete(int $id): bool
    {
        return Database::delete('mail_accounts', 'id = :id', ['id' => $id]) > 0;
    }

    public function setDefault(int $id): void
    {
        Database::query("UPDATE mail_accounts SET is_default = 0");
        Database::update('mail_accounts', ['is_default' => 1, 'updated_at' => date('c')], 'id = :id', ['id' => $id]);
    }

    // —— Contacts ——
    public function contacts(?int $accountId = null): array
    {
        if ($accountId) {
            return Database::fetchAll(
                "SELECT * FROM mail_contacts WHERE account_id IS NULL OR account_id = :a ORDER BY name ASC, email ASC",
                ['a' => $accountId]
            ) ?: [];
        }
        return Database::fetchAll("SELECT * FROM mail_contacts ORDER BY name ASC, email ASC") ?: [];
    }

    public function saveContact(array $data, ?int $id = null): int
    {
        $now = date('c');
        $row = [
            'account_id' => !empty($data['account_id']) ? (int) $data['account_id'] : null,
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'updated_at' => $now,
        ];
        if ($id) {
            Database::update('mail_contacts', $row, 'id = :id', ['id' => $id]);
            return $id;
        }
        $row['created_at'] = $now;
        return (int) Database::insert('mail_contacts', $row);
    }

    public function deleteContact(int $id): bool
    {
        return Database::delete('mail_contacts', 'id = :id', ['id' => $id]) > 0;
    }

    // —— Filters ——
    public function filters(?int $accountId = null): array
    {
        if ($accountId) {
            return Database::fetchAll(
                "SELECT * FROM mail_filters WHERE account_id IS NULL OR account_id = :a ORDER BY sort_order ASC, id ASC",
                ['a' => $accountId]
            ) ?: [];
        }
        return Database::fetchAll("SELECT * FROM mail_filters ORDER BY sort_order ASC, id ASC") ?: [];
    }

    public function saveFilter(array $data, ?int $id = null): int
    {
        $row = [
            'account_id' => !empty($data['account_id']) ? (int) $data['account_id'] : null,
            'name' => trim((string) ($data['name'] ?? 'Filter')),
            'match_field' => (string) ($data['match_field'] ?? 'from'),
            'match_op' => (string) ($data['match_op'] ?? 'contains'),
            'match_value' => trim((string) ($data['match_value'] ?? '')),
            'action' => (string) ($data['action'] ?? 'flag'),
            'action_value' => trim((string) ($data['action_value'] ?? '')),
            'is_active' => isset($data['is_active']) ? (int) !empty($data['is_active']) : 1,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
        if ($id) {
            Database::update('mail_filters', $row, 'id = :id', ['id' => $id]);
            return $id;
        }
        $row['created_at'] = date('c');
        return (int) Database::insert('mail_filters', $row);
    }

    public function deleteFilter(int $id): bool
    {
        return Database::delete('mail_filters', 'id = :id', ['id' => $id]) > 0;
    }
}
