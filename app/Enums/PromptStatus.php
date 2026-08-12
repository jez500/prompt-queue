<?php

namespace App\Enums;

enum PromptStatus: string
{
    case Todo = 'todo';
    case Implementing = 'implementing';
    case Done = 'done';

    /**
     * The statuses shown by default — everything that is not finished.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Todo, self::Implementing];
    }
}
