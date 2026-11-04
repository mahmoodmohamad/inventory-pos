<?php

namespace App\Enums;

enum LedgerDirection: string
{
    /** العميل بقى مديون للمحل أكتر */
    case Debit = 'debit';

    /** العميل سدّد أو له رصيد */
    case Credit = 'credit';
}
