<?php
declare(strict_types=1);

class ReferenceID
{
    public static function generate(): string
    {
        $prefix    = 'XCM';
        $timestamp = strtoupper(base_convert((string) time(), 10, 36));
        $random    = strtoupper(bin2hex(random_bytes(4)));
        return $prefix . '-' . $timestamp . '-' . $random;
    }

    public static function store(array $record, string $dataPath): bool
    {
        $file    = rtrim($dataPath, '/') . '/quotes.json';
        $records = [];

        if (file_exists($file)) {
            $raw = file_get_contents($file);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $records = $decoded;
                }
            }
        }

        $records[] = $record;

        $written = file_put_contents(
            $file,
            json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        return $written !== false;
    }

    public static function lookup(string $refID, string $dataPath): ?array
    {
        $file = rtrim($dataPath, '/') . '/quotes.json';

        if (!file_exists($file)) {
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $records = json_decode($raw, true);
        if (!is_array($records)) {
            return null;
        }

        foreach ($records as $record) {
            if (($record['ref_id'] ?? '') === $refID) {
                return $record;
            }
        }

        return null;
    }

    public static function isExpired(array $record): bool
    {
        return time() > ($record['expires_at'] ?? 0);
    }
}
