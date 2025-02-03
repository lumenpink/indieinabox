<?php

namespace Indieinabox;

// Helper Classes
class Helper
{
    /**
     * Helper function to get a value from nested array with default
     *
     * @param  array  $array
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function arrayGet(array $array, string $key, $default = null)
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }
    /**
     * Helper function to determine the kind of content
     *
     * @param  array $page
     * @return array
     */
    public static function kind(array $page): array
    {
        // This should be implemented based on your content classification system
        // Here's a basic implementation
        $kind = $page["layout"] ?? "page";
        $localized = [
            "page" => "Page",
            "post" => "Blog Post",
            "project" => "Project"
        ][$kind] ?? $kind;

        return [
            "localized" => $localized,
            "kind" => $kind
        ];
    }

    /**
     * Helper function to format dates
     *
     * @param  array $page
     * @return array
     */
    public static function localizeddate(array $page): array
    {
        $date = $page["date"] ?? time();

        if (!is_numeric($date)) {
            $date = strtotime($date);
        }

        return [
            "long" => date("F j, Y", $date),
            "iso" => date("Y-m-d", $date)
        ];
    }
}
