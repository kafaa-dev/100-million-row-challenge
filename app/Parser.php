<?php

namespace App;

use App\Commands\Visit;
use DateTimeImmutable;

final class Parser
{
    public function parse(string $inputPath, string $outputPath): void
    {
        gc_disable();

        $epoch = new DateTimeImmutable('2021-02-08');
        $days = 1846;
        $dateIds = [];
        for ($i = 0; $i < $days; $i++) {
            $date = substr($epoch->modify("+$i days")->format('y-m-d'), 1);
            $dateIds[$date] = $i;
        }

        $slugs = array_map(fn (Visit $v) => substr($v->uri, 25), Visit::all());
        $slugCount = count($slugs);
        $slugIds = array_map(fn ($v) => $v * $days, array_flip($slugs));

        $slugSorted = [];
        $fp = fopen($inputPath, 'r');
        stream_set_read_buffer($fp, 0);
        while ($line = fgets($fp)) {
            $slug = substr($line, 25, -27);

            if (!isset($slugSorted[$slug])) {
                $slugSorted[$slug] = count($slugSorted);
            }

            if (count($slugSorted) === $slugCount) {
                break;
            }
        }
        fclose($fp);
        $slugSorted = array_flip($slugSorted);

        $counts = array_fill(0, $slugCount * $days, 0);
        $fp = fopen($inputPath, 'r');
        stream_set_read_buffer($fp, 0);
        while (!feof($fp)) {
            $chunk = fread($fp, 1_048_576);

            $start = 0;
            while (($pos = strpos($chunk, "\n", $start)) !== false) {
                $slug = substr($chunk, $start + 25, $pos - $start - 51);
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
            $s = $slugIds[$slug];
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