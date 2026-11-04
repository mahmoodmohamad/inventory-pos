<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\ShopSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * ترقيم متسلسل لكل محل. لازم يتنادى جوه نفس الـ transaction بتاعة إنشاء الفاتورة،
 * فلو الإنشاء فشل الرقم بيرجع (مفيش فجوات من الفشل).
 * المسودات المحذوفة بتسيب فجوة، وده معروف ومقبول.
 */
final class InvoiceNumberGenerator
{
    public function next(string $key = 'sales_invoice'): int
    {
        return DB::transaction(function () use ($key): int {
            // نعمل الصف قبل الـ lock عشان نتجنب gap-lock deadlock لما اتنين يبدأوا في نفس اللحظة.
            if (! ShopSequence::query()->where('sequence_key', $key)->exists()) {
                try {
                    ShopSequence::query()->create(['sequence_key' => $key, 'last_number' => 0]);
                } catch (UniqueConstraintViolationException) {
                    // request تاني سبقنا وعمله، عادي.
                }
            }

            $sequence = ShopSequence::query()
                ->where('sequence_key', $key)
                ->lockForUpdate()
                ->first() ?? throw new LogicException("Sequence [{$key}] could not be created.");

            $next = $sequence->last_number + 1;
            $sequence->forceFill(['last_number' => $next])->save();

            return $next;
        });
    }
}
