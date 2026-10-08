<?php

namespace App\Support;

class Mask
{
    /** budi.santoso@gmail.com => bud***@gmail.com */
    public static function email(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return substr($local, 0, min(3, strlen($local))).'***@'.$domain;
    }

    /** +6281234567890 => +62812****7890 */
    public static function phone(string $phone): string
    {
        return strlen($phone) <= 10 ? $phone : substr($phone, 0, 6).'****'.substr($phone, -4);
    }

    public static function destination(string $channel, string $destination): string
    {
        return $channel === 'email' ? self::email($destination) : self::phone($destination);
    }
}
