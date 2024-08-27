<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;
use Http;
use Illuminate\Support\Facades\Cache;

class previousWeather extends Controller
{
    public function fetchDataBasedOnLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'city' => 'required',
            "state" => 'required',
            "country" => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        $data = "";

        if (Cache::has('lat') && Cache::has('long')) {
            $data = [
                "lat" => Cache::get('lat'),
                "long" => Cache::get('long')
            ];
        }
        else{
            $data = $this->getCoordinatefromCity($request->city, $request->state, $request->country);
            Cache::add("lat", $data['lat'], now()->addHours(4));
            Cache::add("long", $data['long'], now()->addHours(4));
        }
        if (Cache::has('history')) {
            $value = Cache::get('history');
            return response()->json($value);
        }
        else{
            $res = $this->getWeather($data['lat'], $data['long']);
            Cache::add('history', $res, now()->addHours(1));
            return response()->json($res);
        }


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
            ])->get("$api_base_url?latitude=$lat&longitude=$long&hourly=$temprature,$precipitation_probability,$precipitation&daily=temperature_2m_max&timezone=Asia%2FKolkata&past_days=7&forecast_days=1");


            return $response->json();

        } catch (\Exception $e) {
            echo "<pre>";
            print_r($e);
            echo "</pre>";
        }

    }
}














