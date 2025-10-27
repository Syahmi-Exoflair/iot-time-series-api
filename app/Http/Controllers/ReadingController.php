<?php

namespace App\Http\Controllers;

use App\Models\Reading;

use Illuminate\Http\Request;

class ReadingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Reading::query();

        // Filter by parameter_id if provided
        if ($request->has('parameter_id') && $request->parameter_id != '') {
            $query->where('parameter_id', (int) $request->parameter_id);
        }

        // Filter by device_id if provided
        if ($request->has('device_id') && $request->device_id != '') {
            $query->where('device_id', (int) $request->device_id);
        }

        // Optional: Add date range filtering
        if ($request->has('recorded_time') && $request->recorded_time != '') {
            $query->where('recorded_time', '>=', (string) $request->recorded_time);
        }

        // Get results with pagination or limit
        $limit = $request->get('limit', 100); // Default to 100 records
        $limit = min($limit, 1000); // Maximum 1000 records

        $readings = $query->latest('recorded_time')->take($limit)->get();

        return response()->json([
            'data' => $readings,
            'count' => $readings->count(),
        ]);
    }

    public function list(Request $request)
    {
        $query = Reading::query();

        // Filter by parameter_id if provided
        if ($request->has('parameter_id') && $request->parameter_id != '') {
            $query->where('parameter_id', (int) $request->parameter_id);
        }

        // Filter by device_id if provided
        if ($request->has('device_id') && $request->device_id != '') {
            $query->where('device_id', (int) $request->device_id);
        }

        // Optional: Add date range filtering
        if ($request->has('recorded_time') && $request->recorded_time != '') {
            $query->where('recorded_time', '>=', (string) $request->recorded_time);
        }

        // Get results with pagination or limit
        $limit = $request->get('limit', 100); // Default to 100 records
        $limit = min($limit, 1000); // Maximum 1000 records

        $readings = $query->latest('recorded_time')->take($limit)->get();

        return response()->json([
            'data' => $readings,
            'count' => $readings->count(),
        ]);
    }

    public function show(Request $request)
    {
        $query = Reading::query();

        // Filter by parameter_id if provided
        if ($request->has('parameter_id') && $request->parameter_id != '') {
            $query->where('parameter_id', (int) $request->parameter_id);
        }

        // Filter by device_id if provided
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
        
        $current_a = Reading::where('parameter_id', (int) $parameter_one)->latest('recorded_time')->first();

        $current_b = Reading::where('parameter_id', (int) $parameter_two)->latest('recorded_time')->first();

        $current_c = Reading::where('parameter_id', (int) $parameter_three)->latest('recorded_time')->first();

        $voltage = 240; // Assuming a fixed voltage value
        $average_current = ($current_a->reading + $current_b->reading + $current_c->reading) / 3;
        
        $power = ($voltage * $average_current)/1000; // in kW
        return $power;
        return response()->json(['power_kw' => $power]);
    }
}
