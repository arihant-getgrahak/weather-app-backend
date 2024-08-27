<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;
use Http;
use Illuminate\Support\Facades\Cache;

class forecastWeather extends Controller
{
    public function fetchDataBasedOnLocation(Request $request)
{
    // Validate incoming request data
    $validator = Validator::make($request->all(), [
        'city' => 'required',
        'state' => 'required',
        'country' => 'required'
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors());
    }

    // Retrieve coordinates from cache or fetch and cache them
    $lat = Cache::get('lat');
    $long = Cache::get('long');

    if (!$lat || !$long) {
        $data = $this->getCoordinatefromCity($request->city, $request->state, $request->country);

        if (!$data) {
            return response()->json(['error' => 'Unable to retrieve coordinates.'], 500);
        }

        $lat = $data['lat'];
        $long = $data['long'];

        Cache::put('lat', $lat, now()->addHours(4));
        Cache::put('long', $long, now()->addHours(4));
    }

    // Retrieve forecast from cache or fetch and cache it
    $forecast = Cache::get('forecast');

    if (!$forecast) {
        $forecast = $this->getWeather($lat, $long);

        if (!$forecast) {
            return response()->json(['error' => 'Unable to retrieve weather data.'], 500);
        }

        Cache::put('forecast', $forecast, now()->addHours(1));
    }

    return response()->json($forecast);
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
