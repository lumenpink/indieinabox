<?php

declare(strict_types=1);

namespace Indieinabox\Support;

use DateTime;
use DateTimeZone;
use Indieinabox\Core\Database;
use Indieinabox\Page;

/**
 * Class DateFormatter
 *
 * Formats relative timestamps, localized dates according to site intl settings,
 * and sorts collections chronologically.
 */
class DateFormatter
{
    /**
     * Returns a human-readable relative time string (e.g. "5 minutes ago").
     *
     * @param int $timestamp
     * @return string
     */
    public static function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return $diff . " seconds ago";
        } elseif ($diff < 3600) {
            return floor($diff / 60) . " minutes ago";
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . " hours ago";
        } else {
            return floor($diff / 86400) . " days ago";
        }
    }

    /**
     * Formats a page's date into localized long and ISO strings.
     *
     * @param Page|array<string, mixed> $page
     * @return array{long: string, iso: string}
     */
    public static function localizeddate(Page|array $page): array
    {
        global $originaldaysofweek, $originalmonths, $intl;

        if (empty($intl)) {
            $intl = Database::getSetting('intl', []);
            if (empty($intl)) {
                $intl = [
                    'pt-br' => [
                        'localizeddate' => [
                            'date' => 'd \d\e F \de\ Y',
                            'time' => 'H:iP',
                            'full' => 'l, d \d\e F \d\e Y \à\s H:i e',
                            'shortdate' => 'd/m/Y',
                            'shorttime' => 'H:i',
                            'shortfull' => 'd/m/Y H:i',
                            'daysofweek' => ["Domingo", "Segunda-feira", "Terça-feira", "Quarta-feira", "Quinta-feira", "Sexta-feira", "Sábado"],
                            'months' => ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"],
                        ],
                    ],
                    'en' => [
                        'localizeddate' => [
                            'date' => 'F d, Y',
                            'time' => 'h:i A',
                            'full' => 'l, F d, Y \a\t h:i A',
                            'shortdate' => 'm/d/Y',
                            'shorttime' => 'h:i A',
                            'shortfull' => 'm/d/Y h:i A',
                            'daysofweek' => ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
                            'months' => ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"],
                        ],
                    ],
                    'es' => [
                        'localizeddate' => [
                            'date' => 'd \d\e F \d\e Y',
                            'time' => 'H:iP',
                            'full' => 'l, d \d\e F \d\e Y \à\s H:iP',
                            'shortdate' => 'd/m/Y',
                            'shorttime' => 'H:i',
                            'shortfull' => 'd/m/Y H:i',
                            'daysofweek' => ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"],
                            'months' => ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
                        ],
                    ],
                ];
            }
        }

        if (empty($originaldaysofweek)) {
            $originaldaysofweek = Database::getSetting('originaldaysofweek', []);
            if (empty($originaldaysofweek)) {
                $originaldaysofweek = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
            }
        }

        if (empty($originalmonths)) {
            $originalmonths = Database::getSetting('originalmonths', []);
            if (empty($originalmonths)) {
                $originalmonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            }
        }

        setlocale(LC_TIME, 'en-us');

        if ($page instanceof Page) {
            $epoch = $page->date;
            $lang = $page->lang;
        } else {
            $epoch = $page["date"] ?? time();
            $lang = $page["lang"] ?? "en";
        }

        if (!isset($intl[$lang])) {
            if (($lang === 'pt' || str_starts_with($lang, 'pt-')) && isset($intl['pt-br'])) {
                $lang = 'pt-br';
            } elseif (($lang === 'es' || str_starts_with($lang, 'es-')) && isset($intl['es'])) {
                $lang = 'es';
            } else {
                $lang = 'en';
            }
        }

        if ($epoch instanceof DateTime) {
            $date = $epoch;
        } else {
            if (is_float($epoch)) {
                $epoch = (int) $epoch;
            }
            if (is_int($epoch) || (is_string($epoch) && is_numeric($epoch))) {
                $epoch = (string) $epoch;
                $date = DateTime::createFromFormat("U", $epoch);
            } else {
                $date = new DateTime((string) $epoch);
            }
        }

        if (!$date) {
            $date = new DateTime();
        }

        $date->setTimezone(new DateTimeZone("America/Sao_Paulo"));
        $isoformat = date_format($date, 'c');
        $longformat = date_format($date, $intl[$lang]["localizeddate"]["full"]);
        $longformat = str_replace("America/Sao_Paulo", ($date->format('I') === '1') ? 'BRST' : 'BRT', $longformat);
        $longformat = str_replace($originaldaysofweek, $intl[$lang]["localizeddate"]["daysofweek"], $longformat);
        $longformat = str_replace($originalmonths, $intl[$lang]["localizeddate"]["months"], $longformat);

        return [
            "long" => $longformat,
            "iso" => $isoformat,
        ];
    }

    /**
     * Sorts pages by date descending.
     *
     * @param array<int, array<string, mixed>|Page> $pages
     * @return array<int, array<string, mixed>|Page>
     */
    public static function sortByDate(array $pages): array
    {
        usort(
            $pages,
            function ($a, $b) {
                $dateA = $a instanceof Page ? $a->date : ($a["date"] ?? -1);
                $dateB = $b instanceof Page ? $b->date : ($b["date"] ?? -1);

                return $dateB <=> $dateA;
            }
        );

        return $pages;
    }
}
