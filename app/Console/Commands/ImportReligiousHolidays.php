<?php

namespace App\Console\Commands;

use App\Models\ReligiousHoliday;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('holidays:import {--year=}')]
#[Description('Uvezi verske praznike sa pravoslavnikalendar.at')]
class ImportReligiousHolidays extends Command
{
    public function handle(): int
    {
        $year = $this->option('year') ?? date('Y');

        $this->info("Učitavam praznikе za godinu $year...");

        try {
            $html = Http::get('https://pravoslavnikalendar.at/crvena-slova')->body();
        } catch (\Exception $e) {
            $this->error("Greška pri učitavanju stranice: {$e->getMessage()}");

            return self::FAILURE;
        }

        $holidays = $this->parseHolidays($html, $year);

        if (empty($holidays)) {
            $this->warn("Nisu pronađeni praznici za $year godinu.");

            return self::SUCCESS;
        }

        foreach ($holidays as $holiday) {
            ReligiousHoliday::updateOrCreate(
                ['date' => $holiday['date']],
                ['year' => $year, 'description' => $holiday['description']]
            );
        }

        $this->info(count($holidays)." praznika je učitano za godinu $year.");

        return self::SUCCESS;
    }

    private function parseHolidays(string $html, int $year): array
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument;
        $dom->loadHTML($html);

        $xpath = new DOMXPath($dom);

        $rows = $xpath->query('//tr[td]');
        $holidays = [];

        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length < 2) {
                continue;
            }

            $dateStr = trim($cells->item(0)->textContent);
            $description = trim($cells->item(1)->textContent);

            if (empty($dateStr) || empty($description)) {
                continue;
            }

            $date = $this->parseDate($dateStr, $year);
            if ($date === null) {
                continue;
            }

            $holidays[] = [
                'date' => $date,
                'description' => $this->cyrillicToLatin($description),
            ];
        }

        return $holidays;
    }

    private function parseDate(string $dateStr, int $year): ?string
    {
        $months = [
            'Јануар' => 1, 'janSU' => 1,
            'Фебруар' => 2, 'februar' => 2,
            'Март' => 3, 'mart' => 3,
            'Април' => 4, 'april' => 4,
            'Мај' => 5, 'maj' => 5,
            'Јун' => 6, 'jun' => 6,
            'Јул' => 7, 'jul' => 7,
            'Август' => 8, 'august' => 8,
            'Септембар' => 9, 'septembar' => 9,
            'Октобар' => 10, 'oktobar' => 10,
            'Новембар' => 11, 'novembar' => 11,
            'Децембар' => 12, 'decembar' => 12,
        ];

        preg_match('/(\d+)\.\s+(.+)/', $dateStr, $matches);

        if (empty($matches)) {
            return null;
        }

        $day = (int) $matches[1];
        $monthName = trim($matches[2]);

        $month = null;
        foreach ($months as $cyrillic => $monthNum) {
            if (str_contains($monthName, $cyrillic)) {
                $month = $monthNum;
                break;
            }
        }

        if ($month === null) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function cyrillicToLatin(string $text): string
    {
        $cyrillic = [
            'Љ' => 'Lj', 'љ' => 'lj',
            'Њ' => 'Nj', 'њ' => 'nj',
            'Џ' => 'Dž', 'џ' => 'dž',
            'А' => 'A', 'а' => 'a',
            'Б' => 'B', 'б' => 'b',
            'В' => 'V', 'в' => 'v',
            'Г' => 'G', 'г' => 'g',
            'Д' => 'D', 'д' => 'd',
            'Ђ' => 'Đ', 'ђ' => 'đ',
            'Е' => 'E', 'е' => 'e',
            'Ж' => 'Ž', 'ж' => 'ž',
            'З' => 'Z', 'з' => 'z',
            'И' => 'I', 'и' => 'i',
            'Ј' => 'J', 'ј' => 'j',
            'К' => 'K', 'к' => 'k',
            'Л' => 'L', 'л' => 'l',
            'М' => 'M', 'м' => 'm',
            'Н' => 'N', 'н' => 'n',
            'О' => 'O', 'о' => 'o',
            'П' => 'P', 'п' => 'p',
            'Р' => 'R', 'р' => 'r',
            'С' => 'S', 'с' => 's',
            'Т' => 'T', 'т' => 't',
            'Ћ' => 'Ć', 'ћ' => 'ć',
            'У' => 'U', 'у' => 'u',
            'Ф' => 'F', 'ф' => 'f',
            'Х' => 'H', 'х' => 'h',
            'Ц' => 'C', 'ц' => 'c',
            'Ч' => 'Č', 'ч' => 'č',
            'Ш' => 'Š', 'ш' => 'š',
        ];

        return strtr($text, $cyrillic);
    }
}
