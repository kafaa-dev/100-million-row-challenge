<?php

namespace App;

use App\Commands\Visit;

use function array_fill;
use function array_flip;
use function fclose;
use function feof;
use function fopen;
use function fread;
use function fseek;
use function fwrite;
use function gc_disable;
use function stream_set_read_buffer;
use function stream_set_write_buffer;
use function strlen;
use function strpos;
use function strrpos;
use function substr;

use const SEEK_CUR;

final class Parser
{
    public function parse(string $inputPath, string $outputPath): void
    {
        gc_disable();

        $dateIds = [];
        $days = 0;
        for ($y = 1; $y <= 6; $y++) {
            for ($m = 1; $m <= 12; $m++) {
                $ms = $m < 10 ? "0$m" : "$m";
                $daysInMonth = match ($m) {
                    4, 6, 9, 11 => 30,
                    2 => $y === 4 ? 29 : 28,
                    default => 31,
                };

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $ds = $d < 10 ? "0$d" : "$d";
                    $dateIds["$y-$ms-$ds"] = $days++;
                }
            }
        }

        $slugIds = [];
        foreach (Visit::all() as $i => $visit) {
            $slugIds[substr($visit->uri, 28)] = $i * $days;
        }
        $slugCount = $i + 1;

        $slugSorted = [];
        $slugSortedCount = 0;
        $fp = fopen($inputPath, 'r');
        stream_set_read_buffer($fp, 0);

        $sample = fread($fp, 131_072);
        $lastNewline = strrpos($sample, "\n");
        $pos = 25;
        while ($pos < $lastNewline) {
            $comma = strpos($sample, ',', $pos);
            $slug = substr($sample, $pos, $comma - $pos);
            $pos = $comma + 52;

            if (!isset($slugSorted[$slug])) {
                $slugSorted[$slug] = $slugSortedCount++;
            }
        }
        $slugSorted = array_flip($slugSorted);

        fseek($fp, 0);
        $counts = array_fill(0, $slugCount * $days, 0);
        while (!feof($fp)) {
            $chunk = fread($fp, 1_048_576);

            $lastNewline = strrpos($chunk, "\n");

            $pos = 28;
            while ($pos < $lastNewline) {
                $comma = strpos($chunk, ',', $pos);
                $counts[$slugIds[substr($chunk, $pos, $comma - $pos)] + $dateIds[substr($chunk, $comma + 4, 7)]]++;
                $pos = $comma + 55;
            }

            $leftover = strlen($chunk) - $lastNewline - 1;
            if ($leftover > 0) {
                fseek($fp, -$leftover, SEEK_CUR);
            }
        }
        fclose($fp);

        $fp = fopen($outputPath, 'w');
        stream_set_write_buffer($fp, 1_048_576);
        fwrite($fp, '{');
        $firstSlug = true;
        foreach ($slugSorted as $slug) {
            $buffer = $firstSlug ? '' : ',';
            $buffer .= "\n    \"\/blog\/$slug\": {";
            $s = $slugIds[substr($slug, 3)];
            $firstDate = true;
            foreach ($dateIds as $date => $d) {
                $count = $counts[$s + $d];
                if ($count === 0) continue;
                $buffer .= $firstDate ? '' : ',';
                $buffer .= "\n        \"202$date\": $count";

                $firstDate = false;
            }
            $buffer .= "\n    }";
            fwrite($fp, $buffer);

            $firstSlug = false;
        }
        fwrite($fp, "\n}");
        fclose($fp);
    }
}