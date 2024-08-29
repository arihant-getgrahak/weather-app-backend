<?php

namespace App\Http\Controllers;

use App\Transformers\ForecastTransformer;
use Illuminate\Http\Request;
use Validator;
use Http;
use DB;
use Cache;
class ForecastController extends Controller
{
    public function fetchDataBasedOnLocation(Request $request)
    {
        if ($request->city == null && $request->state == null && $request->country == null && $request->loc == null) {
            return response()->json([
                "message" => "Please provide atleast any one of city, state, country or loc"
            ]);
        }


        $keys = DB::table('cache')->pluck('key');
        $data = [];
        if ($request->loc != null) {
            sscanf($request->loc, "%[^,],%[^,]", $lat, $long);
            $data = [
                "lat" => $lat,
                "long" => $long
            ];
        } else {
            $data = $this->getCoordinatefromCity($request->city, $request->state, $request->country);
        }

        $locationKey = 'loc: ' . $data['lat'] . ',' . $data['long'];
        if (Cache::has($locationKey)) {
            $cache = Cache::get($locationKey);
            if (!empty($cache["forecast"])) {
                return response()->json($cache['forecast']);
            }
        }

        foreach ($keys as $key) {
            $lat_lon = str_replace("loc: ", "", $key);
            sscanf($lat_lon, "%[^,],%[^,]", $lata, $longa);
            $dataSent = [
                "long" => $data['long'],
                "lat" => $data['lat'],
                "longa" => $longa,
                "lata" => $lata,
            ];
            if (checkisinCircle($dataSent)) {
                $cache = Cache::get($key);
                if (!empty($cache["forecast"])) {
                    return response()->json($cache["forecast"]);
                }
            }
        }
        $res = $this->getWeather($data['lat'], $data['long']);
        $arr = [$res];
        $response = fractal($arr, new ForecastTransformer())->toArray();
        $cache = Cache::get($locationKey);

        $cacheData = [];
        if (empty($cache)) {
            $cache = [
                "forecast" => $response
            ];
            $cache = Cache::add($locationKey, $cache, now()->addHours(4));

            return response()->json($response);
        } else {
            if (empty($cache["weather"])) {
                $cacheData = [
                    'forecast' => $response,
                ];
            } else {
                $cacheData = [
                    'history' => $cache['history'] ?? null,
                    'weather' => $cache['weather'],
                    'alert' => $cache['alert'],
                    'forecast' => $response,
                ];
            }
        }
        $cache = Cache::put($locationKey, $cacheData, now()->addHours(4));
        return response()->json($response);
    }
    public function getCoordinatefromCity(string $city, string $state, string $country)
    {

        $ninja_api_url = config("api.ninja_api_url");
        $ninja_api_key = config("api.ninja_api_key");

        $lat = "";
        $long = "";

        try {
            $response = Http::withHeader(
                'X-Api-Key',
                $ninja_api_key
            )->get("$ninja_api_url?city=$city&country=$country");

            foreach ($response->json() as $res) {
                $validdata = $state;
                if ($res['state'] == $validdata) {
                    $lat = $res['latitude'];
                    $long = $res['longitude'];
                    $data = [
                        "lat" => $lat,
                        "long" => $long
                    ];
                    return $data;
                }
            }

        } catch (\Exception $e) {
            echo "<pre>";
            print_r($e);
            echo "</pre>";
        }

    }

    public function getWeather(string $lat, string $long)
    {
        $api_base_url = config("api.api_base_url");
        $precipitation = config("api.precipitation");
        $precipitation_probability = config("api.precipitation_probability");
        $temprature = config("api.temprature");

        try {

            $response = Http::withHeaders([
                "Content-Type" => "application/json",
            ])->get("$api_base_url?latitude=$lat&longitude=$long&hourly=$temprature,$precipitation_probability,$precipitation&daily=temperature_2m_max&timezone=Asia%2FKolkata&forecast_days=14");


            return $response->json();

        } catch (\Exception $e) {
            echo "<pre>";
            print_r($e);
            echo "</pre>";
        }

    }
}
