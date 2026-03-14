<?php

return [
    'parallel_evaluation' => true,
    'max_workers' => env('AG_MAX_WORKERS', 8),
    'log_window_penalty' => env('AG_LOG_WINDOW_PENALTY', false),
];
