<?php

declare(strict_types=1);

namespace PreCommande;

final class Money
{
    public const CURRENCY = 'EUR';
    public const SYMBOL = '€';

    public static function format(?float $amount): string
    {
        return $amount === null ? '' : number_format_i18n($amount, 2) . "\u{00A0}" . self::SYMBOL;
    }

    /** Accepte « 27,50 », « 27.5 », « 27,50 € »… Renvoie null si vide ou invalide. */
    public static function parse(string $raw): ?float
    {
        $raw = str_replace([' ', "\u{00A0}", self::SYMBOL], '', trim($raw));
        $raw = str_replace(',', '.', $raw);

        if ($raw === '' || !is_numeric($raw) || (float) $raw < 0) {
            return null;
        }
        return round((float) $raw, 2);
    }

    /** Format de saisie (virgule décimale, sans symbole). */
    public static function toInput(?float $amount): string
    {
        return $amount === null ? '' : number_format($amount, 2, ',', '');
    }
}
