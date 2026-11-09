<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Enums\LedgerDirection;
use App\Enums\LedgerSource;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * تحصيل دفعة من عميل من غير فاتورة (سداد جزء من الأجل).
 * بيكتب Payment (sale_id = null) + قيد Credit في دفتر العميل في transaction واحدة.
 * الرصيد = مجموع المدين - مجموع الدائن (نفس معادلة شاشة العملاء).
 */
final class CustomerPaymentService
{
    /**
     * @param  bool  $allowAdvance  true = يسمح بدفع أكتر من المتبقي (يتسجل رصيد لصالح العميل)
     */
    public function receive(
        User $actor,
        int $customerId,
        string $amount,
        PaymentMethod $method,
        ?string $reference = null,
        ?string $notes = null,
        bool $allowAdvance = false,
    ): Payment {
        $amount = $this->money($amount);

        if (Money::cmp($amount, '0', 2) !== 1) {
            throw new InvalidArgumentException(__('المبلغ لازم يكون أكبر من صفر.'));
        }

        return DB::transaction(function () use ($actor, $customerId, $amount, $method, $reference, $notes, $allowAdvance): Payment {
            // الـ ShopScope: عميل محل تاني = 404. القفل بيمنع تحصيلين متزامنين على نفس العميل.
            // العميل الموقوف (is_active = false) لسه ينفع يسدد.
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);

            Gate::forUser($actor)->authorize('collect', $customer);

            if (! $allowAdvance) {
                $balance = $this->balance($customer->id);

                if (Money::cmp($balance, '0', 2) <= 0) {
                    throw new DomainException(__('العميل ملوش مبلغ متبقي.'));
                }

                if (Money::cmp($amount, $balance, 2) === 1) {
                    throw new DomainException(__('المبلغ أكبر من المتبقي على العميل.'));
                }
            }

            $now = now();

            $payment = Payment::query()->create([
                'sale_id' => null,
                'customer_id' => $customer->id,
                'received_by' => $actor->id,
                'amount' => $amount,
                'method' => $method->value,
                'reference_number' => filled($reference) ? trim($reference) : null,
                'notes' => filled($notes) ? trim($notes) : null,
                'paid_at' => $now,
            ]);

            CustomerLedger::query()->create([
                'customer_id' => $customer->id,
                'sale_id' => null,
                'payment_id' => $payment->id,
                'created_by' => $actor->id,
                'source' => LedgerSource::Payment,
                'direction' => LedgerDirection::Credit,
                'amount' => $amount,
                'notes' => filled($notes) ? trim($notes) : null,
                'occurred_at' => $now,
            ]);

            return $payment;
        });
    }

    /** موجب = على العميل، سالب = رصيد لصالحه. */
    public function balance(int $customerId): string
    {
        $row = CustomerLedger::query()
            ->where('customer_id', $customerId)
            ->toBase()
            ->selectRaw(
                'coalesce(sum(case when direction = ? then amount else 0 end), 0) as debit,'
                .' coalesce(sum(case when direction = ? then amount else 0 end), 0) as credit',
                [LedgerDirection::Debit->value, LedgerDirection::Credit->value],
            )
            ->first();

        return Money::sub((string) $row->debit, (string) $row->credit, 2);
    }

    /** @return numeric-string */
    private function money(string $value): string
    {
        $value = trim($value);

        if (preg_match('/\A\d{1,10}(?:\.\d{1,2})?\z/', $value) !== 1) {
            throw new InvalidArgumentException(__('قيمة غير صالحة.'));
        }

        assert(is_numeric($value));

        return bcadd($value, '0', 2);
    }
}
