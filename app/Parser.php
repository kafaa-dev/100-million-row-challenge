<?php

namespace App;

final class Parser
{
    public function parse(string $inputPath, string $outputPath): void
    {
        gc_disable();

        /**
         * @var array<string, array<string, int>>
         */
        $output = [];

        $fp = fopen($inputPath, 'r');
        while ($line = fgets($fp)) {
            $path = substr($line, 19, -27);
            $date = substr($line, -26, 10);

            if (isset($output[$path][$date])) {
                $output[$path][$date]++;
            } else {
                $output[$path][$date] = 1;
            }
        }
        fclose($fp);

        foreach ($output as &$visits) {
            ksort($visits, SORT_STRING);
        }

        file_put_contents($outputPath, json_encode($output, JSON_PRETTY_PRINT));
    }
}