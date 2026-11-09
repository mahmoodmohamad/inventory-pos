<?php

// app/Enums/ProductCondition.php

namespace App\Enums;

enum ProductCondition: string
{
    case Original   = 'original';
    case Commercial = 'commercial';
    case Imported   = 'imported';
    case Used       = 'used';

    /**
     * Locale-aware label.
     * Falls back to the raw enum value if no translation exists
     * for the active locale, so the UI never shows a raw key
     * like "lang.condition.original".
     */
    public function label(): string
    {
        $key        = 'lang.condition.' . $this->value;
        $translated = __($key);

        return $translated === $key ? $this->value : $translated;
    }
}