<?php

namespace App\Enums;

/**
 * How a site_settings row stores its value. Media settings keep the file in
 * media_id, never in value.
 */
enum SettingType: string
{
    case String = 'string';
    case Text = 'text';
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Json = 'json';
    case Media = 'media';
}
