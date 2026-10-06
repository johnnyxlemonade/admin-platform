<?php

declare(strict_types=1);

return [
    'module' => ['name' => 'Media'],
    'permissions' => ['view' => 'View media'],
    'list' => ['title' => 'Media', 'description' => 'Overview of files stored in the system.', 'search' => 'Search media…', 'loading' => 'Loading media…', 'empty' => 'There are no files yet.', 'all' => 'All', 'image' => 'Images', 'document' => 'Documents', 'video' => 'Videos', 'other' => 'Other'],
    'fields' => ['file' => 'File', 'type' => 'Type', 'size_dimensions' => 'Size / dimensions', 'owner_usage' => 'Owner / usage', 'created_at' => 'Uploaded at'],
    'filters' => ['module' => 'Module', 'all_modules' => 'All modules'],
];
