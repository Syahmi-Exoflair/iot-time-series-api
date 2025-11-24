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
        
        if (empty($current_b) || empty($current_c)) {
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

    public function calculateMonthlyPowerUsage(Request $request)
    {
        $parameter_current_a = $request->input('parameter_current_a');
        $parameter_current_b = $request->input('parameter_current_b');
        $parameter_current_c = $request->input('parameter_current_c');
        $voltage = $request->input('voltage', 240);
        $phase = $request->input('phase', 'single');
        $power_factor = $request->input('power_factor', 0.85);
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));

        $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        // Get last reading of the month for current A
        $current_a_reading = null;
        if (!empty($parameter_current_a)) {
            $current_a_reading = Reading::where('parameter_id', (int) $parameter_current_a)
                ->whereBetween('recorded_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->orderBy('recorded_time', 'desc')
                ->first();
        }

        // Get last reading of the month for current B
        $current_b_reading = null;
        if (!empty($parameter_current_b)) {
            $current_b_reading = Reading::where('parameter_id', (int) $parameter_current_b)
                ->whereBetween('recorded_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->orderBy('recorded_time', 'desc')
                ->first();
        }

        // Get last reading of the month for current C
        $current_c_reading = null;
        if (!empty($parameter_current_c)) {
            $current_c_reading = Reading::where('parameter_id', (int) $parameter_current_c)
                ->whereBetween('recorded_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->orderBy('recorded_time', 'desc')
                ->first();
        }

        // Get previous month readings for comparison
        $previousMonthEnd = date('Y-m-d', strtotime($startDate . ' -1 day'));
        $previousMonthStart = date('Y-m-01', strtotime($previousMonthEnd));

        $previous_a_reading = null;
        if (!empty($parameter_current_a)) {
            $previous_a_reading = Reading::where('parameter_id', (int) $parameter_current_a)
                ->whereBetween('recorded_time', [$previousMonthStart . ' 00:00:00', $previousMonthEnd . ' 23:59:59'])
                ->orderBy('recorded_time', 'desc')
                ->first();
        }

        $previous_b_reading = null;
        if (!empty($parameter_current_b)) {
            $previous_b_reading = Reading::where('parameter_id', (int) $parameter_current_b)
                ->whereBetween('recorded_time', [$previousMonthStart . ' 00:00:00', $previousMonthEnd . ' 23:59:59'])
                ->orderBy('recorded_time', 'desc')
                ->first();
        }

        $previous_c_reading = null;
        if (!empty($parameter_current_c)) {
            $previous_c_reading = Reading::where('parameter_id', (int) $parameter_current_c)
                ->whereBetween('recorded_time', [$previousMonthStart . ' 00:00:00', $previousMonthEnd . ' 23:59:59'])
                ->orderBy('recorded_time', 'desc')
                ->first();
        }

        // Calculate difference for current A
        $current_a_diff = 0;
        if ($current_a_reading && $previous_a_reading) {
            $current_a_diff = $current_a_reading->reading - $previous_a_reading->reading;
        } elseif ($current_a_reading) {
            $current_a_diff = $current_a_reading->reading;
        }

        // Calculate difference for current B
        $current_b_diff = 0;
        if ($current_b_reading && $previous_b_reading) {
            $current_b_diff = $current_b_reading->reading - $previous_b_reading->reading;
        } elseif ($current_b_reading) {
            $current_b_diff = $current_b_reading->reading;
        }

        // Calculate difference for current C
        $current_c_diff = 0;
        if ($current_c_reading && $previous_c_reading) {
            $current_c_diff = $current_c_reading->reading - $previous_c_reading->reading;
        } elseif ($current_c_reading) {
            $current_c_diff = $current_c_reading->reading;
        }

        // Calculate average current usage
        if (empty($current_b_diff) && empty($current_c_diff)) {
            $average_current = $current_a_diff;
        } else {
            $count = 1;
            $total = $current_a_diff;
            if (!empty($current_b_diff)) {
                $total += $current_b_diff;
                $count++;
            }
            if (!empty($current_c_diff)) {
                $total += $current_c_diff;
                $count++;
            }
            $average_current = $total / $count;
        }

        // Calculate power usage
        if ($phase === 'three') {
            $power_usage = ($average_current * $voltage * 1.732 * $power_factor) / 1000;
        } else {
            $power_usage = ($average_current * $voltage * $power_factor) / 1000;
        }

        return response()->json([
            'year' => (int) $year,
            'month' => (int) $month,
            'power_usage_kwh' => round($power_usage, 2),
            'average_current' => round($average_current, 2),
            'phase' => $phase,
            'voltage' => $voltage,
            'power_factor' => $power_factor,
        ]);
    }

    public function getMonthlyReadingsPeopleCounter(Request $request)
    {
        $parameter_id = $request->input('parameter_id');
        $device_id = $request->input('device_id');
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));
        $day = $request->input('day');

        // If day is provided, filter by specific day
        if ($day) {
            $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            $endDate = $startDate;

            // Get readings for the requested day
            $currentReading = Reading::where('parameter_id', (int) $parameter_id)
                ->where('device_id', (int) $device_id)
                ->whereBetween('recorded_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->orderBy('recorded_time', 'desc')
                ->first();

            // Get last reading from previous day
            $previousDate = date('Y-m-d', strtotime($startDate . ' -1 day'));
            $previousReading = Reading::where('parameter_id', (int) $parameter_id)
                ->where('device_id', (int) $device_id)
                ->whereBetween('recorded_time', [$previousDate . ' 00:00:00', $previousDate . ' 23:59:59'])
                ->orderBy('recorded_time', 'desc')
                ->first();
        } else {
            // Filter by month
            $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));

            // Get readings for the requested month
            $currentReading = Reading::where('parameter_id', (int) $parameter_id)
                ->where('device_id', (int) $device_id)
                ->whereBetween('recorded_time', [$startDate, $endDate . ' 23:59:59'])
                ->orderBy('recorded_time', 'desc')
                ->first();

            // Calculate previous month dates
            $previousMonthEnd = date('Y-m-d', strtotime($startDate . ' -1 day'));
            $previousMonthStart = date('Y-m-01', strtotime($previousMonthEnd));

            // Get last reading from previous month
            $previousReading = Reading::where('parameter_id', (int) $parameter_id)
                ->where('device_id', (int) $device_id)
                ->whereBetween('recorded_time', [$previousMonthStart, $previousMonthEnd . ' 23:59:59'])
                ->orderBy('recorded_time', 'desc')
                ->first();
        }

        $difference = 0;
        if ($currentReading && $previousReading) {
            $difference = $currentReading->reading - $previousReading->reading;
        } elseif ($currentReading) {
            $difference = $currentReading->reading;
        }

        return response()->json([
            'current_reading' => $currentReading,
            'previous_reading' => $previousReading,
            'difference' => $difference,
        ]);
    }

    public function getPeopleCounterReading(Request $request)
    {
        $parameter_id = $request->input('parameter_id');
        $device_id = $request->input('device_id');
        $param_request = $request->input('param_request'); // 'max' or 'min'
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));

        // If param_request is not provided, return latest reading (default behavior)
        if (!$param_request) {
            $latestReading = Reading::where('parameter_id', (int) $parameter_id)
                ->where('device_id', (int) $device_id)
                ->orderBy('recorded_time', 'desc')
                ->first();

            return response()->json($latestReading);
        }

        // Calculate daily differences for the entire month
        $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
        $daysInMonth = date('t', strtotime($startDate));
        
        $dailyDifferences = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            
            // Get last reading for current day
            $currentReading = Reading::where('parameter_id', (int) $parameter_id)
                ->where('device_id', (int) $device_id)
                ->whereBetween('recorded_time', [$currentDate . ' 00:00:00', $currentDate . ' 23:59:59'])
                ->orderBy('recorded_time', 'desc')
                ->first();

            // Skip if no reading for current day
            if (!$currentReading) {
                continue;
            }

            // Get last reading from previous day
            $previousDate = date('Y-m-d', strtotime($currentDate . ' -1 day'));
            $previousReading = Reading::where('parameter_id', (int) $parameter_id)
                ->where('device_id', (int) $device_id)
                ->whereBetween('recorded_time', [$previousDate . ' 00:00:00', $previousDate . ' 23:59:59'])
                ->orderBy('recorded_time', 'desc')
                ->first();

            // Skip if no previous reading (can't calculate valid difference)
            if (!$previousReading) {
                continue;
            }

            // Calculate difference
            $difference = $currentReading->reading - $previousReading->reading;

            $dailyDifferences[] = [
                'day' => (int) $day,
                'month' => (int) $month,
                'year' => (int) $year,
                'date' => $currentDate,
                'difference' => $difference,
                'reading' => $currentReading->reading,
                'recorded_time' => $currentReading->recorded_time,
            ];
        }

        // Find max or min difference
        if (empty($dailyDifferences)) {
            return response()->json(['message' => 'No readings found for the specified month'], 404);
        }

        if ($param_request === 'max') {
            $result = collect($dailyDifferences)->sortByDesc('difference')->first();
        } elseif ($param_request === 'min') {
            $result = collect($dailyDifferences)->sortBy('difference')->first();
        } else {
            return response()->json(['error' => 'Invalid param_request. Use "max" or "min"'], 400);
        }

        return response()->json($result);
    }
}
