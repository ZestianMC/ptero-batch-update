<?php

namespace Pterodactyl\BlueprintFramework\Extensions\batchupdate\Support;

/** Outcome of one write attempt. Immutable; always serialisable to a JSON body. */
final class WriteResult
{
    public const OK = 'ok';
    public const SKIPPED = 'skipped';
    public const ERROR = 'error';

    private function __construct(
        public readonly string $status,
        public readonly ?string $reason,
        public readonly int $httpStatus,
    ) {
    }

    public static function ok(): self
    {
        return new self(self::OK, null, 200);
    }

    public static function skipped(string $reason): self
    {
        return new self(self::SKIPPED, $reason, 200);
    }

    public static function error(string $reason, int $httpStatus): self
    {
        return new self(self::ERROR, $reason, $httpStatus);
    }

    /** @return array{status: string, reason?: string} */
    public function toArray(): array
    {
        $out = ['status' => $this->status];
        if ($this->reason !== null) {
            $out['reason'] = $this->reason;
        }

        return $out;
    }
}
