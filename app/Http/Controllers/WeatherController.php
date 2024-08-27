<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class WeatherController extends Controller
{
    public function sendDataBasedOnLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'city' => 'required',
            "state" => 'required',
            "country" => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Check if latitude and longitude are cached
        $lat = Cache::get('lat');
        $long = Cache::get('long');
        
        if (!$lat || !$long) {
            $data = $this->getCoordinatefromCity($request->city, $request->state, $request->country);

            if (!$data) {
                return response()->json(['error' => 'Unable to retrieve coordinates.'], 500);
            }

            $lat = $data['lat'];
            $long = $data['long'];

            // Cache latitude and longitude for 4 hours
            Cache::put('lat', $lat, now()->addHours(4));
            Cache::put('long', $long, now()->addHours(4));
        }

        // Check if weather data is cached
        $cachedWeather = Cache::get('weather');
        
        if ($cachedWeather) {
            return response()->json($cachedWeather);
        }

        // Fetch weather data if not cached
        $weatherData = $this->getWeather($lat, $long);

        if (!$weatherData) {
            return response()->json(['error' => 'Unable to retrieve weather data.'], 500);
        }

        // Cache weather data for 1 hour
        Cache::put('weather', $weatherData, now()->addHours(1));

        return response()->json($weatherData);
    }

    private function getCoordinatefromCity(string $city, string $state, string $country)
    {
        $ninja_api_url = config("api.ninja_api_url");
        $ninja_api_key = config("api.ninja_api_key");

        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $ninja_api_key,
            ])->get("$ninja_api_url?city=$city&country=$country");

            foreach ($response->json() as $res) {
                if ($res['state'] === $state) {
                    return [
                        'lat' => $res['latitude'],
                        'long' => $res['longitude'],
                    ];
                }
            }

            return null;

        } catch (\Exception $e) {
            // Log the exception or handle it as needed
            return null;
        }
    }

    private function getWeather(string $lat, string $long)
    {
        $api_base_url = config("api.api_base_url");
        $parameters = [
            config("api.temperature"),
            config("api.relative_humidity"),
            config("api.precipitation_probability"),
            config("api.precipitation"),
            config("api.visibility"),
            config("api.wind_speed_10m"),
            config("api.wind_speed_80m"),
        ];

        $dailyParams = [
            'temperature_2m_max',
            'temperature_2m_min',
            'sunrise',
            'sunset',
            'uv_index_max',
        ];

        try {
            $response = Http::withHeaders([
                "Content-Type" => "application/json",
            ])->get($api_base_url, [
                'latitude' => $lat,
                'longitude' => $long,
                'hourly' => implode(',', $parameters),
                'daily' => implode(',', $dailyParams),
                'forecast_days' => 1,
                'timezone' => 'Asia/Kolkata',
            ]);

            return $response->json();

        } catch (\Exception $e) {
            // Log the exception or handle it as needed
            return null;
        }
    }
}
