<?php

namespace App\Enums;

enum PromptPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
}
