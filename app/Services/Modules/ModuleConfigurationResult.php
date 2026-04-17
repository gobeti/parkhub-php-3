<?php

declare(strict_types=1);

namespace App\Services\Modules;

/** Exhaustive status enum for ModuleConfigurationResult. */
enum ModuleConfigurationStatus: string
{
    case Ok = 'ok';
    case NotFound = 'not_found';
    case NotToggleable = 'not_toggleable';
    case ValidationFailed = 'validation_failed';
}

/**
 * Result object returned by ModuleConfigurationService. Status enum is
 * exhaustive so controllers can match over it without default arms.
 */
final class ModuleConfigurationResult
{
    /**
     * @param  array<string, list<string>>|null  $details
     */
    private function __construct(
        public readonly ModuleConfigurationStatus $status,
        public readonly ?string $moduleName = null,
        public readonly ?array $details = null,
    ) {}

    public static function ok(): self
    {
        return new self(ModuleConfigurationStatus::Ok);
    }

    public static function notFound(string $moduleName): self
    {
        return new self(ModuleConfigurationStatus::NotFound, moduleName: $moduleName);
    }

    public static function notToggleable(string $moduleName): self
    {
        return new self(ModuleConfigurationStatus::NotToggleable, moduleName: $moduleName);
    }

    /**
     * @param  array<string, list<string>>  $details
     */
    public static function validationFailed(array $details): self
    {
        return new self(ModuleConfigurationStatus::ValidationFailed, details: $details);
    }

    public function isOk(): bool
    {
        return $this->status === ModuleConfigurationStatus::Ok;
    }
}
