<?php

namespace App\Support;

/**
 * Laravel's mail templates run lines through Markdown. Visitor-supplied text
 * is escaped first, so a message can't inject links, images or formatting
 * into the emails staff receive.
 */
class MarkdownText
{
    public static function escape(?string $text): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|<>~])/', '\\\\$1', (string) $text);
    }
}
