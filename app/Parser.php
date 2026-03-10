<?php

namespace App;

use DateTimeImmutable;
use DateTimeInterface;

final class Parser
{
    public function parse(string $inputPath, string $outputPath): void
    {
        /**
         * @var array<string, array<string, int>>
         */
        $output = [];

        $lines = file($inputPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            [$url, $timestamp] = explode(',', $line, 2);

            $path = parse_url($url, PHP_URL_PATH);
            $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ISO8601, $timestamp)->format('Y-m-d');

            if (!isset($output[$path])) {
                $output[$path] = [];
            }

            if (!isset($output[$path][$date])) {
                $output[$path][$date] = 0;
            }

            $output[$path][$date]++;
        }

        foreach ($output as &$visits) {
            ksort($visits, SORT_STRING);
        }

        file_put_contents($outputPath, json_encode($output, JSON_PRETTY_PRINT));
    }
}