<?php

namespace App\Enums;

enum TaskStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case REVIEW = 'review';
    case COMPLETED = 'completed';

    /**
     * Define allowed transitions from current status.
     * Prevents invalid transitions such as Completed -> Open.
     *
     * @return array<TaskStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::OPEN => [self::IN_PROGRESS, self::REVIEW],
            self::IN_PROGRESS => [self::OPEN, self::REVIEW],
            self::REVIEW => [self::IN_PROGRESS, self::COMPLETED],
            self::COMPLETED => [self::REVIEW], // Completed cannot directly jump back to Open
        };
    }

    /**
     * Check if transition to target status is valid.
     */
    public function canTransitionTo(TaskStatus $target): bool
    {
        if ($this === $target) {
            return true;
        }

        return in_array($target, $this->allowedTransitions(), true);
    }
}
