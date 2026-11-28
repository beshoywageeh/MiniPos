<?php

namespace App\Support;

use App\Enums\ResetPeriod;
use App\Models\DocumentSequence;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\StockAdjustment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DocumentNumber
{
    public static function next(string $documentType): string
    {
        return DB::transaction(function () use ($documentType): string {
            $sequence = DocumentSequence::query()
                ->where('document_type', $documentType)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw new RuntimeException("No document sequence configured for [{$documentType}].");
            }

            $now = now();

            if (self::shouldReset($sequence->reset_period, $sequence->last_reset_at, $now)) {
                $sequence->next_number = 1;
                $sequence->last_reset_at = $now;
            } elseif ($sequence->last_reset_at === null) {
                $sequence->last_reset_at = $now;
            }

            $number = self::preview($sequence);

            while (self::isNumberTaken($documentType, $number)) {
                $sequence->next_number++;
                $number = self::preview($sequence);
            }

            $sequence->next_number++;
            $sequence->save();

            return $number;
        });
    }

    public static function preview(DocumentSequence $sequence): string
    {
        return sprintf(
            '%s-%s',
            $sequence->prefix,
            str_pad((string) $sequence->next_number, $sequence->padding, '0', STR_PAD_LEFT),
        );
    }

    private static function isNumberTaken(string $documentType, string $number): bool
    {
        $modelClass = match ($documentType) {
            'sale' => Sale::class,
            'purchase' => Purchase::class,
            'payment' => Payment::class,
            'expense' => Expense::class,
            'sales_return' => SalesReturn::class,
            'purchase_return' => PurchaseReturn::class,
            'stock_adjustment' => StockAdjustment::class,
            default => null,
        };

        return $modelClass !== null && $modelClass::query()->where('document_number', $number)->exists();
    }

    private static function shouldReset(ResetPeriod $period, ?Carbon $lastResetAt, Carbon $now): bool
    {
        if ($period === ResetPeriod::NEVER || $lastResetAt === null) {
            return false;
        }

        return match ($period) {
            ResetPeriod::DAILY => ! $lastResetAt->isSameDay($now),
            ResetPeriod::WEEKLY => ! $lastResetAt->isSameWeek($now),
            ResetPeriod::MONTHLY => ! $lastResetAt->isSameMonth($now),
            ResetPeriod::YEARLY => ! $lastResetAt->isSameYear($now),
        };
    }
}
