<?php

namespace App\Modules\Base\DTOs;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use ReflectionClass;

abstract readonly class BaseDTO
{
    public static function fromArray(array $data): static
    {
        $class = new ReflectionClass(static::class);
        $constructor = $class->getConstructor();

        if (! $constructor) {
            return new static;
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

            if (array_key_exists($name, $data)) {
                $arguments[$name] = $data[$name];

                continue;
            }

            if (array_key_exists($snake, $data)) {
                $arguments[$name] = $data[$snake];

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[$name] = $parameter->getDefaultValue();
            }
        }

        return new static(...$arguments);
    }

    public function toArray(): array
    {
        $values = get_object_vars($this);

        return collect($values)
            ->filter(fn (mixed $value): bool => $value !== null)
            ->mapWithKeys(function (mixed $value, string $key): array {
                $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $key));

                return [$snake => $value];
            })
            ->all();
    }
}
