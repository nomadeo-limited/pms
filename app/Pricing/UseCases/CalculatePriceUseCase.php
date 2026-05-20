<?php

namespace App\Pricing\UseCases;

use App\Models\Discount;
use App\Models\PricingRule;
use Brick\Money\Money;
use Illuminate\Support\Carbon;

class CalculatePriceUseCase
{
    public function execute(
        string $priceableType,
        string $priceableId,
        string $checkIn,
        string $checkOut,
        int $guests,
        ?string $discountCode = null,
        ?string $discountId = null,
    ): array {
        $nights = Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));

        $rule = $this->resolveRule($priceableType, $priceableId, $checkIn, $checkOut);

        if (!$rule) {
            return [
                'base_price' => null,
                'discount_amount' => null,
                'total_price' => null,
                'currency' => null,
                'nights' => $nights,
                'guests' => $guests,
                'rule_applied' => null,
                'discount_applied' => null,
                'error' => 'No active pricing rule found for this period.',
            ];
        }

        $currency = $rule->currency;
        $basePrice = $this->computeBase($rule, $nights, $guests, $currency);

        $discount = $this->resolveDiscount($discountCode, $discountId, $nights);
        $discountAmount = Money::of(0, $currency);

        if ($discount) {
            $discountAmount = $this->computeDiscount($discount, $basePrice);
        }

        $total = $basePrice->minus($discountAmount);
        if ($total->isNegative()) {
            $total = Money::of(0, $currency);
        }

        return [
            'base_price' => $basePrice->getAmount()->toFloat(),
            'discount_amount' => $discountAmount->getAmount()->toFloat(),
            'total_price' => $total->getAmount()->toFloat(),
            'currency' => $currency,
            'nights' => $nights,
            'guests' => $guests,
            'rule_applied' => [
                'id' => $rule->id,
                'name' => $rule->name,
                'model' => $rule->model,
                'amount' => (float) $rule->amount,
            ],
            'discount_applied' => $discount ? [
                'id' => $discount->id,
                'code' => $discount->code,
                'type' => $discount->type,
                'value' => (float) $discount->value,
            ] : null,
        ];
    }

    private function resolveRule(string $type, string $id, string $checkIn, string $checkOut): ?PricingRule
    {
        return PricingRule::where('priceable_type', $type)
            ->where('priceable_id', $id)
            ->where('is_active', true)
            ->where(function ($q) use ($checkIn) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $checkIn);
            })
            ->where(function ($q) use ($checkOut) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $checkOut);
            })
            ->orderByDesc('priority')
            ->first();
    }

    private function computeBase(PricingRule $rule, int $nights, int $guests, string $currency): Money
    {
        $amount = Money::of($rule->amount, $currency);

        return match ($rule->model) {
            'per_night' => $amount->multipliedBy($nights),
            'per_person_per_night' => $amount->multipliedBy($nights * $guests),
            'fixed_package' => $amount,
            default => $amount,
        };
    }

    private function resolveDiscount(?string $code, ?string $id, int $nights): ?Discount
    {
        if ($code) {
            return Discount::where('code', $code)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()->toDateString());
                })
                ->where(function ($q) {
                    $q->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses');
                })
                ->where(function ($q) use ($nights) {
                    $q->whereNull('min_nights')->orWhere('min_nights', '<=', $nights);
                })
                ->first();
        }

        if ($id) {
            return Discount::where('id', $id)->where('is_active', true)->first();
        }

        return null;
    }

    private function computeDiscount(Discount $discount, Money $base): Money
    {
        return match ($discount->type) {
            'percentage', 'early_bird', 'last_minute', 'long_stay' =>
                $base->multipliedBy((float) $discount->value / 100, \Brick\Math\RoundingMode::HALF_UP),
            'fixed_amount' =>
                Money::of(min((float) $discount->value, $base->getAmount()->toFloat()), $base->getCurrency()),
            default => Money::of(0, $base->getCurrency()),
        };
    }
}
