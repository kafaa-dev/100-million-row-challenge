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
    private const string URI_PREFIX = 'https://stitcher.io/blog/';
    private const int URI_PREFIX_LEN = 25;
    private const int NEWLINE_LEN = 1;
    private const int COMMA_LEN = 1;
    private const int DATE_LEN = 25;

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

        $uris = array_column(Visit::all(), 'uri');

        $slugKeyLen = 1;
        while (true) {
            $keys = [];
            foreach ($uris as $uri) {
                $key = substr($uri, -$slugKeyLen);
                if (isset($keys[$key])) {
                    $slugKeyLen++; continue 2;
                }
                $keys[$key] = true;
            }
            break;
        }

        $slugMap = [];
        foreach ($uris as $i => $uri) {
            $slug = substr($uri, self::URI_PREFIX_LEN);
            $slugId = $i * $days;
            $slugMap[substr($uri, -$slugKeyLen)] = (strlen($slug) << 20) | $slugId;
        }
        $slugCount = $i + 1;
        $slugMask = (1 << 20) - 1; // to get slugId

        $slugSorted = [];
        $slugSortedCount = 0;
        $fp = fopen($inputPath, 'r');
        stream_set_read_buffer($fp, 0);

        $sample = fread($fp, 181_000);
        $lastNewline = strrpos($sample, "\n");
        $pos = self::URI_PREFIX_LEN;
        while ($pos < $lastNewline) {
            $comma = strpos($sample, ',', $pos);
            $slug = substr($sample, $pos, $comma - $pos);
            $pos = $comma + self::COMMA_LEN + self::DATE_LEN + self::NEWLINE_LEN + self::URI_PREFIX_LEN;

            if (!isset($slugSorted[$slug])) {
                $slugSorted[$slug] = $slugSortedCount++;
            }
        }
        $slugSorted = array_flip($slugSorted);

        fseek($fp, 0);
        $counts = array_fill(0, $slugCount * $days, 0);
        $slugKeyOffset = self::DATE_LEN + self::COMMA_LEN + $slugKeyLen;
        while (!feof($fp)) {
            $chunk = fread($fp, 1_048_576);

            $lastNewline = strrpos($chunk, "\n");

            $pos = $lastNewline;
            while ($pos > self::URI_PREFIX_LEN) {
                $s = $slugMap[substr($chunk, $pos - $slugKeyOffset, $slugKeyLen)];
                $counts[($s & $slugMask) + $dateIds[substr($chunk, $pos - (self::DATE_LEN - 3), 7)]]++;
                $pos -= self::DATE_LEN + self::COMMA_LEN + ($s >> 20) + self::URI_PREFIX_LEN + self::NEWLINE_LEN;
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
            $slugKey = substr(self::URI_PREFIX . $slug, -$slugKeyLen);
            $s = $slugMap[$slugKey];
            $firstDate = true;
            foreach ($dateIds as $date => $d) {
                $count = $counts[($s & $slugMask) + $d];
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