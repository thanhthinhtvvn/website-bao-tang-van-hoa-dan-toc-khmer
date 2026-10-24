<?php
function event_description_without_time(?string $description): string
{
    if ($description === null || trim($description) === '') {
        return '';
    }

    $description = preg_replace(
        '/(?:^|\s*[-–—]\s*)Thời gian\s*:\s*.*?(?=(?:\s*[-–—]\s*)?(?:Ý nghĩa|Hoạt động|Nội dung)\s*:|$)/isu',
        '',
        $description
    );
    $description = preg_replace(
        '/\s*[-–—]\s*(?=(?:Ý nghĩa|Hoạt động|Nội dung)\s*:)/iu',
        "\n",
        $description
    );

    return trim($description, " \t\n\r\0\x0B-–—.:;");
}
