<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Support;

final class JsonCommandResult
{
    /**
     * @param array<string, mixed> $data
     * @return array{exit_code: int, line: string, payload: array<string, mixed>}
     */
    public static function success(string $message, array $data = []): array
    {
        return self::emit(true, $message, $data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{exit_code: int, line: string, payload: array<string, mixed>}
     */
    public static function failure(string $message, array $data = []): array
    {
        return self::emit(false, $message, $data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{exit_code: int, line: string, payload: array<string, mixed>}
     */
    private static function emit(bool $success, string $message, array $data): array
    {
        $payload = array_merge([
            'success' => $success,
            'message' => $message,
        ], $data);

        return [
            'exit_code' => $success ? 0 : 1,
            'line' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'payload' => $payload,
        ];
    }
}
