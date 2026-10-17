<?php

namespace TomLabs\Services;

class DatabaseInfoDTO
{
    public string $name;
    public string $charset;
    public string $collation;
    public int $size;

    public function __construct(string $name, string $collation, string $charset = 'utf8mb4', int $size = 0)
    {
        $this->name = $name;
        $this->collation = $collation;
        $this->charset = $charset;
        $this->size = $size;
    }

    public static function fromMongo(object $doc): self
    {
        return new self(
            $doc['db_name'] ?? '',
            $doc['collation'] ?? 'utf8mb4_0900_ai_ci',
            'utf8mb4',
            0
        );
    }

    public function toArray(): array
    {
        return [
            'db_name' => $this->name,
            'character_set' => $this->charset,
            'collation' => $this->collation,
            'size' => $this->size
        ];
    }
}
