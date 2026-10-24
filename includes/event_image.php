<?php
function event_image_src($image): ?string
{
    if (!is_string($image) || trim($image) === '') {
        return null;
    }

    $image = trim($image);
    if (filter_var($image, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string) parse_url($image, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $image : null;
    }

    $relative_path = ltrim(str_replace('\\', '/', $image), '/');
    $has_directory = strpos($relative_path, '/') !== false;
    $paths = $has_directory
        ? [$relative_path]
        : ['uploads/events/' . $relative_path, 'images/' . $relative_path];
    $project_root = dirname(__DIR__);

    foreach ($paths as $path) {
        if (in_array('..', explode('/', $path), true)) {
            continue;
        }

        $file_path = $project_root . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($file_path)) {
            return implode('/', array_map('rawurlencode', explode('/', $path)));
        }
    }

    return null;
}
