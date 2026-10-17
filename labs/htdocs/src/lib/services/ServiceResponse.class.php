<?php

namespace TomLabs\Services;

require_once __DIR__ . '/ServiceStatusCodes.class.php';

class ServiceResponse
{
    public int $code;
    public string $message;
    public $data;
    private bool $isResult = false;

    public function __construct(int $code, string $message = '', $data = null, bool $isResult = false)
    {
        $this->code = $code;
        $this->message = $message !== '' ? $message : ServiceStatusCodes::message($code);
        $this->data = $data;
        $this->isResult = $isResult;
    }

    public static function success(string $message = '', array $data = [], int $code = ServiceStatusCodes::SUCCESS): self
    {
        return new self($code, $message, $data);
    }

    public static function created(string $message = '', array $data = []): self
    {
        return new self(ServiceStatusCodes::CREATED, $message, $data);
    }

    public static function error(int $code, string $message = ''): self
    {
        return new self($code, $message);
    }

    public static function result(array $rows): self
    {
        return new self(ServiceStatusCodes::SUCCESS, '', $rows, true);
    }

    public function isSuccess(): bool
    {
        return $this->code >= 200 && $this->code < 300;
    }

    public function isError(): bool
    {
        return !$this->isSuccess();
    }

    public function toArray(): array
    {
        if ($this->isResult) {
            return ['result' => $this->data ?? []];
        }

        if ($this->isSuccess()) {
            $payload = ['success' => true];
            if ($this->message !== '') {
                $payload['message'] = $this->message;
            }
            if ($this->data !== null && $this->data !== []) {
                $payload['data'] = $this->data;
            }
            return $payload;
        }

        return ['success' => false, 'error' => $this->message];
    }

    public function send(): void
    {
        echo json_encode($this->toArray());
        exit;
    }

    public static function sendError(string $message): void
    {
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}
