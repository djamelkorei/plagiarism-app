<?php

namespace App\Services;

use DOMDocument;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScraperService
{

    public static string $HOST = 'https://www.turnitin.com';

    public static array $HEADERS = [
        "accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "accept-language" => "en-GB,en-US;q=0.9,en;q=0.8,fr;q=0.7",
        "cache-control" => "max-age=0",
        "content-type" => "application/x-www-form-urlencoded",
        "sec-ch-ua" => "\"Chromium\";v=\"122\", \"Not(A:Brand\";v=\"24\", \"Google Chrome\";v=\"122\"",
        "sec-ch-ua-mobile" => "?0",
        "sec-ch-ua-platform" => "\"macOS\"",
        "sec-fetch-dest" => "document",
        "sec-fetch-mode" => "navigate",
        "sec-fetch-site" => "same-origin",
        "sec-fetch-user" => "?1",
        "upgrade-insecure-requests" => "1",
        "cookie" => "_gid=GA1.2.462294455.1708643428; _ga_NC20BWG2KH=GS1.1.1708643440.1.1.1708645261.0.0.0; __utmz=162339897.1708645287.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none); _ga_KQJGYCZ3D0=GS1.2.1708645483.1.1.1708645860.0.0.0; apt.uid=AP-H6XRJYUGEBGP-2-1708645862529-12848542.0.2.6df83dca-f6a5-46fc-aafa-e968659a8404; test_cookie=1; __utma=162339897.1890918324.1708643428.1708645287.1708709521.2; __utmc=162339897; lang=fr; lagrange_session=cb2a09c6-060f-410f-8563-bbb56227c56f; wcid=C2B0lFMlNtdwAAAB; _gcl_au=1.1.350978788.1708710842; t=658874f4ece84c54ace073707fd4b7dd; __utmb=162339897.5.10.1708709521; wlid=NTgzMTgzMzQ0NTQ1ODQzNzIxNA==; OptanonConsent=isGpcEnabled=0&datestamp=Fri+Feb+23+2024+18%3A23%3A00+GMT%2B0000+(Greenwich+Mean+Time)&version=6.24.0&isIABGlobal=false&hosts=&landingPath=NotLandingPage&groups=C0001%3A1%2CC0003%3A0%2CC0002%3A0%2CC0004%3A0&AwaitingReconsent=false; session-id=78e2d616a8b843cda8d94caeb9b9b875; legacy-session-id=78e2d616a8b843cda8d94caeb9b9b875; _ga_EJF27WH1D9=GS1.1.1708709517.2.1.1708712582.0.0.0; _ga=GA1.2.1890918324.1708643428; _ga_HX5QNRS9GM=GS1.2.1708709518.2.1.1708712582.0.0.0",
        "Referer" => "https => //www.turnitin.com/login_page.asp?lang=en_us",
        "Referrer-Policy" => "strict-origin-when-cross-origin",
        "User-agent" => "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36"
    ];


    public static array $HEADERS_WITH_SESSIONS = [
        "accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "accept-language" => "en-GB,en-US;q=0.9,en;q=0.8,fr;q=0.7",
        "cache-control" => "max-age=0",
        "content-type" => "application/x-www-form-urlencoded",
        "sec-ch-ua" => "\"Chromium\";v=\"122\", \"Not(A:Brand\";v=\"24\", \"Google Chrome\";v=\"122\"",
        "sec-ch-ua-mobile" => "?0",
        "sec-ch-ua-platform" => "\"macOS\"",
        "sec-fetch-dest" => "document",
        "sec-fetch-mode" => "navigate",
        "sec-fetch-site" => "same-origin",
        "sec-fetch-user" => "?1",
        "upgrade-insecure-requests" => "1",
        "cookie" => "_gid=GA1.2.462294455.1708643428; _ga_NC20BWG2KH=GS1.1.1708643440.1.1.1708645261.0.0.0; __utmz=162339897.1708645287.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none); _ga_KQJGYCZ3D0=GS1.2.1708645483.1.1.1708645860.0.0.0; apt.uid=AP-H6XRJYUGEBGP-2-1708645862529-12848542.0.2.6df83dca-f6a5-46fc-aafa-e968659a8404; test_cookie=1; __utma=162339897.1890918324.1708643428.1708645287.1708709521.2; __utmc=162339897; lang=fr; lagrange_session=cb2a09c6-060f-410f-8563-bbb56227c56f; wcid=C2B0lFMlNtdwAAAB; _gcl_au=1.1.350978788.1708710842; t=658874f4ece84c54ace073707fd4b7dd; __utmb=162339897.5.10.1708709521; wlid=NTgzMTgzMzQ0NTQ1ODQzNzIxNA==; OptanonConsent=isGpcEnabled=0&datestamp=Fri+Feb+23+2024+18%3A23%3A00+GMT%2B0000+(Greenwich+Mean+Time)&version=6.24.0&isIABGlobal=false&hosts=&landingPath=NotLandingPage&groups=C0001%3A1%2CC0003%3A0%2CC0002%3A0%2CC0004%3A0&AwaitingReconsent=false; _ga_EJF27WH1D9=GS1.1.1708709517.2.1.1708712582.0.0.0; _ga=GA1.2.1890918324.1708643428; _ga_HX5QNRS9GM=GS1.2.1708709518.2.1.1708712582.0.0.0 legacy-session-id=473809e4cf0947058c892e2fa7a21a3e; session-id=473809e4cf0947058c892e2fa7a21a3e; t=473809e4cf0947058c892e2fa7a21a3e; test_cookie=1",
        "Referer" => "https => //www.turnitin.com/login_page.asp?lang=en_us",
        "Referrer-Policy" => "strict-origin-when-cross-origin",
        "User-agent" => "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36"
    ];

    public function getClasses()
    {
        $response =  Http::withHeaders(ScraperService::$HEADERS_WITH_SESSIONS)->get(ScraperService::$HOST.'/s_home.asp');
        $doc = new DOMDocument();
        $doc->loadHTML(htmlspecialchars($response->body()));
        $elementLink = $doc->getElementsByTagName('table')->count();
        return $response->body();
    }

    public function getSession()
    {
        $data = null;
        try {
            $response =  Http::withHeaders(ScraperService::$HEADERS)->get(ScraperService::$HOST.'/login_page.asp?lang=en_us');
            $formData = $this::loginPageExtractFormData($response->body());
            $responseLogin = Http::withHeaders(ScraperService::$HEADERS)
                ->withOptions(['allow_redirects' => true])
                ->asForm()
                ->post(ScraperService::$HOST.'/login_page.asp?lang=en_us', $formData);

            $data = $this::loginPageExtractSessionCookie($responseLogin);

        } catch (\Exception $exception) {
            Log::error($exception);
        }
        return $data;
    }

    protected function loginPageExtractSessionCookie(PromiseInterface|Response $responseLogin): string
    {
        $defaultCookie = "_gid=GA1.2.462294455.1708643428; _ga_NC20BWG2KH=GS1.1.1708643440.1.1.1708645261.0.0.0; __utmz=162339897.1708645287.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none); _ga_KQJGYCZ3D0=GS1.2.1708645483.1.1.1708645860.0.0.0; apt.uid=AP-H6XRJYUGEBGP-2-1708645862529-12848542.0.2.6df83dca-f6a5-46fc-aafa-e968659a8404; test_cookie=1; __utma=162339897.1890918324.1708643428.1708645287.1708709521.2; __utmc=162339897; lang=fr; lagrange_session=cb2a09c6-060f-410f-8563-bbb56227c56f; wcid=C2B0lFMlNtdwAAAB; _gcl_au=1.1.350978788.1708710842; t=658874f4ece84c54ace073707fd4b7dd; __utmb=162339897.5.10.1708709521; wlid=NTgzMTgzMzQ0NTQ1ODQzNzIxNA==; OptanonConsent=isGpcEnabled=0&datestamp=Fri+Feb+23+2024+18%3A23%3A00+GMT%2B0000+(Greenwich+Mean+Time)&version=6.24.0&isIABGlobal=false&hosts=&landingPath=NotLandingPage&groups=C0001%3A1%2CC0003%3A0%2CC0002%3A0%2CC0004%3A0&AwaitingReconsent=false; session-id=78e2d616a8b843cda8d94caeb9b9b875; legacy-session-id=78e2d616a8b843cda8d94caeb9b9b875; _ga_EJF27WH1D9=GS1.1.1708709517.2.1.1708712582.0.0.0; _ga=GA1.2.1890918324.1708643428; _ga_HX5QNRS9GM=GS1.2.1708709518.2.1.1708712582.0.0.0";
        $cookieString = $defaultCookie;

        $header = $responseLogin->header('Set-Cookie');
        $sessionIdMatch = [];
        preg_match('/session-id=([^;]+)/', $header, $sessionIdMatch);
        if (!empty($sessionIdMatch[1])) {
            $sessionId = $sessionIdMatch[1];
            $cookieString .= " legacy-session-id=$sessionId; session-id=$sessionId; t=$sessionId; test_cookie=1";
        }
        return $cookieString;
    }

    /**
     * Login page extract form data
     *
     * @param string $pageContent
     * @return array
     */
    protected function loginPageExtractFormData(string $pageContent): array
    {
        $loginIdMatch = [];
        $loginTokenMatch = [];
        $browserFpMatch = [];

        preg_match('/name="login_id" type="hidden" value="([^"]+)"/', $pageContent, $loginIdMatch);
        preg_match('/name="login_token" type="hidden" value="([^"]+)"/', $pageContent, $loginTokenMatch);
        preg_match('/name="browser_fp" type="hidden" value="([^"]+)"/', $pageContent, $browserFpMatch);

        $login_id = null;
        $login_token = null;
        $browser_fp = null;

        if (!empty($loginIdMatch) && !empty($loginTokenMatch) && !empty($browserFpMatch)) {
            $login_id = $loginIdMatch[1];
            $login_token = $loginTokenMatch[1];
            $browser_fp = $browserFpMatch[1];
        }

        // Default set browser fp
        $browser_fp = '8b11bbdc38d09999d7d906765cf9c157';

        return [
            'javascript_enabled' => '0',
            'email' => env('ACCOUNT_EMAIL'),
            'user_password' => env('ACCOUNT_PASSWORD'),
            'submit' => 'Log in',
            'browser_fp' => $browser_fp,
            'login_id' => $login_id,
            'login_token' => $login_token
        ];
    }

}
