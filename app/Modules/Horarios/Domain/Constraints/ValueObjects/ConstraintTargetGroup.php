<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\ValueObjects;

use InvalidArgumentException;

final class ConstraintTargetGroup
{
    /**
     * @var list<int>
     */
    private array $lessonIds;

    /**
     * @param list<int> $lessonIds
     */
    public function __construct(array $lessonIds)
    {
        $normalized = array_values(array_unique(array_map(static fn (int $id): int => $id, $lessonIds)));

        if ($normalized === []) {
            throw new InvalidArgumentException('ConstraintTargetGroup requer ao menos um lesson_id.');
        }

        foreach ($normalized as $lessonId) {
            if ($lessonId <= 0) {
                throw new InvalidArgumentException('ConstraintTargetGroup recebeu lesson_id invalido.');
            }
        }

        $this->lessonIds = $normalized;
    }

    /**
     * @return list<int>
     */
    public function lessonIds(): array
    {
        return $this->lessonIds;
    }

    public function count(): int
    {
        return count($this->lessonIds);
    }

    public function intersects(self $other): bool
    {
        return array_intersect($this->lessonIds, $other->lessonIds()) !== [];
    }

    public function toArray(): array
    {
        return [
            'lesson_ids' => $this->lessonIds,
        ];
    }
}
