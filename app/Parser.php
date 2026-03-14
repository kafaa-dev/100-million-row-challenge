<?php

namespace App;

use App\Commands\Visit;
use DateTimeImmutable;

final class Parser
{
    public function parse(string $inputPath, string $outputPath): void
    {
        gc_disable();

        $epoch = new DateTimeImmutable('2021-01-01');
        $days = 2191;
        $dateIds = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $epoch->modify("+$i days")->format('Y-m-d');
            $dateIds[$date] = $i;
        }

        $paths = array_map(fn (Visit $v) => substr($v->uri, 19), Visit::all());
        $pathCount = count($paths);
        $pathIds = array_map(fn ($v) => $v * $days, array_flip($paths));
        $pathSorted = [];

        $counts = array_fill(0, $pathCount * $days, 0);

        $fp = fopen($inputPath, 'r');
        while ($line = fgets($fp)) {
            $path = substr($line, 19, -27);
            $date = substr($line, -26, 10);

            if (!isset($pathSorted[$path])) {
                $pathSorted[$path] = count($pathSorted);
            }

            $p = $pathIds[$path];
            $d = $dateIds[$date];

            $counts[$p + $d]++;
        }
        fclose($fp);

        $pathSorted = array_flip($pathSorted);

        $fp = fopen($outputPath, 'w');
        stream_set_write_buffer($fp, 1_048_576);
        fwrite($fp, '{');
        $firstPath = true;
        foreach ($pathSorted as $path) {
            $buffer = $firstPath ? '' : ',';
            $buffer .= "\n    " . json_encode($path) . ': {';
            $p = $pathIds[$path];
            $firstDate = true;
            foreach ($dateIds as $date => $d) {
                $count = $counts[$p + $d];
                if ($count === 0) continue;
                $buffer .= $firstDate ? '' : ',';
                $buffer .= "\n        " . json_encode($date).  ": $count";

                $firstDate = false;
            }
            $buffer .= "\n    }";
            fwrite($fp, $buffer);

            $firstPath = false;
        }
        fwrite($fp, "\n}");
        fclose($fp);
    }
}