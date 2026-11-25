<?php

namespace App\Support;

class Money
{
    public const MODES = [
        'cash' => 'Cash',
        'bank' => 'Bank deposit',
        'cheque' => 'Cheque',
        'esewa' => 'eSewa',
        'khalti' => 'Khalti',
        'fonepay' => 'Fonepay',
        'other' => 'Other',
    ];

    /** 1500 -> "Rs. 1,500", 1500.5 -> "Rs. 1,500.50" */
    public static function format($value): string
    {
        $v = (float) $value;

        return 'Rs. '.number_format($v, fmod($v, 1) ? 2 : 0);
    }

    /** 12500.50 -> "Twelve Thousand Five Hundred Rupees and Fifty Paisa Only" (lakh / crore system) */
    public static function words(float $amount): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven',
            'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $below100 = fn (int $n) => $n < 20 ? $ones[$n] : trim($tens[intdiv($n, 10)].' '.$ones[$n % 10]);
        $below1000 = function (int $n) use ($ones, $below100) {
            $words = '';
            if ($n >= 100) {
                $words = $ones[intdiv($n, 100)].' Hundred ';
                $n %= 100;
            }

            return trim($words.($n ? $below100($n) : ''));
        };

        $rupees = (int) floor($amount);
        $paisa = (int) round(($amount - $rupees) * 100);

        if ($rupees === 0 && $paisa === 0) {
            return 'Zero Rupees Only';
        }

        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$size, $label]) {
            if ($rupees >= $size) {
                $parts[] = $below1000(intdiv($rupees, $size)).' '.$label;
                $rupees %= $size;
            }
        }
        if ($rupees > 0) {
            $parts[] = $below1000($rupees);
        }

        $text = trim(implode(' ', $parts));
        $text = $text !== '' ? $text.' Rupees' : '';
        if ($paisa > 0) {
            $text .= ($text !== '' ? ' and ' : '').$below100($paisa).' Paisa';
        }

        return $text.' Only';
    }
}
