<?php

namespace App\Http\Controllers;

use App\Models\Reading;

use Illuminate\Http\Request;

class ReadingController extends Controller
{
    public function index(Request $request)
    {
        $query = Reading::query();

        if ($request->has('parameter_id') && $request->parameter_id != '') {
            $query->where('parameter_id', (int) $request->parameter_id);
        }

        if ($request->has('device_id') && $request->device_id != '') {
            $query->where('device_id', (int) $request->device_id);
        }

        if ($request->has('recorded_time') && $request->recorded_time != '') {
            $query->where('recorded_time', '>=', (string) $request->recorded_time);
        }

        $limit = $request->get('limit', 100);
        $limit = min($limit, 1000);

        $readings = $query->latest('recorded_time')->take($limit)->get();

        return response()->json([
            'data' => $readings,
            'count' => $readings->count(),
        ]);
    }

    public function list(Request $request)
    {
        $query = Reading::query();

        if ($request->has('parameter_id') && $request->parameter_id != '') {
            $query->where('parameter_id', (int) $request->parameter_id);
        }

        if ($request->has('device_id') && $request->device_id != '') {
            $query->where('device_id', (int) $request->device_id);
        }

        if ($request->has('recorded_time') && $request->recorded_time != '') {
            $query->where('recorded_time', '>=', (string) $request->recorded_time);
        }

        $limit = $request->get('limit', 100); 
        $limit = min($limit, 1000);

        $readings = $query->latest('recorded_time')->take($limit)->get();

        return response()->json([
            'data' => $readings,
            'count' => $readings->count(),
        ]);
    }

    public function show(Request $request)
    {
        $query = Reading::query();

        if ($request->has('parameter_id') && $request->parameter_id != '') {
            $query->where('parameter_id', (int) $request->parameter_id);
        }

        if ($request->has('device_id') && $request->device_id != '') {
            $query->where('device_id', (int) $request->device_id);
        }

        $reading = $query->latest('recorded_time')->first();

        return response()->json($reading);
    }

    public function calculatePower(Request $request)
    {
        $parameter_one = $request->input('parameter_current_a');
        $parameter_two = $request->input('parameter_current_b');
        $parameter_three = $request->input('parameter_current_c');
        $voltage = $request->input('voltage', 240);
        $phase = $request->input('phase', 'single');
        $power_factor = $request->input('power_factor', 0.85);

        if (!empty($parameter_one)) {
            $current_a = Reading::where('parameter_id', (int) $parameter_one)->latest('recorded_time')->first();
        }

        if (!empty($parameter_two)) {
            $current_b = Reading::where('parameter_id', (int) $parameter_two)->latest('recorded_time')->first();
        }

        if (!empty($parameter_three)) {
            $current_c = Reading::where('parameter_id', (int) $parameter_three)->latest('recorded_time')->first();
        }

        if (empty($current_b || $current_c)) {
            $average_current = $current_a->reading;
        } else {
            $average_current = ($current_a->reading + $current_b->reading + $current_c->reading) / 3;
        }

        if ($phase === 'three') {
            $power = ($average_current * $voltage * 1.732 * $power_factor) / 1000;
        } else {
            $power = ($average_current * $voltage * $power_factor) / 1000;
        }

        return $power;
    }
}
