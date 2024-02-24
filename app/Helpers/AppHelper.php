<?php


namespace App\Helpers;


use Carbon\Carbon;

class AppHelper
{

    public static function getSocials()
    {
        return [
            ["key" => "twitter", "link" => "#"],
            ["key" => "facebook", "link" => "#"],
            ["key" => "github", "link" => "#"],
            ["key" => "instagram", "link" => "#"],
            ["key" => "dribbble", "link" => "#"],
            ["key" => "linkedin", "link" => "#"],
        ];
    }

    public static function getBrowserItems()
    {
        return ['chrome' => 'chrome', 'firefox-browser' => 'firefox-browser', 'safari' => 'safari', 'edge' => 'edge', 'internet-explorer' => 'internet-explorer'];
    }

    public static function getFrameworkItems()
    {
        return [
            'js' => 'vanilla.js',
            'angular' => 'angular',
            'react' => 'react',
            'vuejs' => 'vue.js',
        ];
    }

    /**
     * Get the product's released at by month.
     *
     * @return string
     * @throws \Exception
     */
    public static function getDateDifference($date)
    {
        $to = Carbon::parse($date)->setTimezone(config('app.timezone'));
        $from = Carbon::now()->setTimezone(config('app.timezone'));

        $second = $to->diffInSeconds($from);
        $minute = $to->diffInMinutes($from);
        $hour = $to->diffInHours($from);
        $day = $to->diffInDays($from);
        $month = $to->diffInMonths($from);
        $year = $to->diffInYears($from);

        return $year > 0
            ? $year . ' years ago'
            : ($month > 0
                    ? $month . ' months ago'
                    : ($day > 0
                        ? $day . ' days ago'
                        : ($hour > 0
                            ? $hour . ' hours ago'
                            : ($minute > 0 ? $minute . ' minutes ago' : $second . ' seconds ago'))));
    }

}
