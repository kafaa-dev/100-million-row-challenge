<?php

namespace App;

use App\Commands\Visit;

use function array_fill;
use function array_flip;
use function array_map;
use function count;
use function date;
use function fclose;
use function feof;
use function fgets;
use function fopen;
use function fread;
use function fseek;
use function fwrite;
use function gc_disable;
use function stream_set_read_buffer;
use function stream_set_write_buffer;
use function strlen;
use function strpos;
use function strtotime;
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
        while ($line = fgets($fp)) {
            $slug = substr($line, 25, -27);

            if (!isset($slugSorted[$slug])) {
                $slugSorted[$slug] = $slugSortedCount++;
            }

            if ($slugSortedCount === $slugCount) {
                break;
            }
        }
        $slugSorted = array_flip($slugSorted);

        fseek($fp, 0);
        $counts = array_fill(0, $slugCount * $days, 0);
        while (!feof($fp)) {
            $chunk = fread($fp, 1_048_576);

            $start = 0;
            while (($pos = strpos($chunk, "\n", $start)) !== false) {
                $slug = substr($chunk, $start + 28, $pos - $start - 54);
                $date = substr($chunk, $pos - 22, 7);

                $s = $slugIds[$slug];
                $d = $dateIds[$date];

                $counts[$s + $d]++;

                $start = $pos + 1;
            }

            $leftover = strlen($chunk) - $start;
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