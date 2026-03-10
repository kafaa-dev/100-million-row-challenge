<?php

namespace App;

final class Parser
{
    public function parse(string $inputPath, string $outputPath): void
    {
        /**
         * @var array<string, array<string, int>>
         */
        $output = [];

        $fp = fopen($inputPath, 'r');
        while ($data = fgets($fp)) {
            $comma = strpos($data, ',');
            $path = parse_url(substr($data, 0, $comma), PHP_URL_PATH);
            $date = substr($data, $comma + 1, 10);

            if (!isset($output[$path])) {
                $output[$path] = [];
            }

            if (!isset($output[$path][$date])) {
                $output[$path][$date] = 0;
            }

            $output[$path][$date]++;
        }
        fclose($fp);

        foreach ($output as &$visits) {
            ksort($visits, SORT_STRING);
        }

        file_put_contents($outputPath, json_encode($output, JSON_PRETTY_PRINT));
    }
}