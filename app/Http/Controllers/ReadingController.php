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
        $previousMonthEnd = date('Y-m-d', strtotime($startDate . ' -1 day'));
        $previousMonthStart = date('Y-m-01', strtotime($previousMonthEnd));

        // Collect all parameter IDs that are provided
        $parameterIds = [];
        if (!empty($parameter_current_a)) $parameterIds[] = (int) $parameter_current_a;
        if (!empty($parameter_current_b)) $parameterIds[] = (int) $parameter_current_b;
        if (!empty($parameter_current_c)) $parameterIds[] = (int) $parameter_current_c;

        // Early return if no parameters provided
        if (empty($parameterIds)) {
            return response()->json([
                'error' => 'At least one current parameter is required'
            ], 400);
        }

        // Fetch ALL readings for all parameters in ONE query
        $allReadings = Reading::whereIn('parameter_id', $parameterIds)
            ->where('recorded_time', '>=', $previousMonthStart . ' 00:00:00')
            ->where('recorded_time', '<=', $endDate . ' 23:59:59')
            ->select('parameter_id', 'reading', 'recorded_time')
            ->orderBy('recorded_time', 'desc')
            ->get();

        // Group readings by parameter_id and period
        $readingsByParameter = [];
        foreach ($allReadings as $reading) {
            $parameterId = $reading->parameter_id;
            $recordDate = substr($reading->recorded_time, 0, 10);
            
            // Determine if this is current month or previous month
            if ($recordDate >= $startDate && $recordDate <= $endDate) {
                if (!isset($readingsByParameter[$parameterId]['current'])) {
                    $readingsByParameter[$parameterId]['current'] = $reading;
                }
            } elseif ($recordDate >= $previousMonthStart && $recordDate <= $previousMonthEnd) {
                if (!isset($readingsByParameter[$parameterId]['previous'])) {
                    $readingsByParameter[$parameterId]['previous'] = $reading;
                }
            }
        }

        // Extract readings for each parameter
        $current_a_reading = $readingsByParameter[$parameter_current_a]['current'] ?? null;
        $previous_a_reading = $readingsByParameter[$parameter_current_a]['previous'] ?? null;
        
        $current_b_reading = !empty($parameter_current_b) ? ($readingsByParameter[$parameter_current_b]['current'] ?? null) : null;
        $previous_b_reading = !empty($parameter_current_b) ? ($readingsByParameter[$parameter_current_b]['previous'] ?? null) : null;
        
        $current_c_reading = !empty($parameter_current_c) ? ($readingsByParameter[$parameter_current_c]['current'] ?? null) : null;
        $previous_c_reading = !empty($parameter_current_c) ? ($readingsByParameter[$parameter_current_c]['previous'] ?? null) : null;

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
            // $power_usage = ($average_current * $voltage * 1.732 * $power_factor) / 1000;
            $current_a_power = ($current_a_diff * $voltage * 1.732 * $power_factor) / 1000;
            $current_b_power = ($current_b_diff * $voltage * 1.732 * $power_factor) / 1000;
            $current_c_power = ($current_c_diff * $voltage * 1.732 * $power_factor) / 1000;
            $power_usage = $current_a_power + $current_b_power + $current_c_power;
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
            $previousDate = date('Y-m-d', strtotime($startDate . ' -1 day'));

            // Fetch both current and previous day readings in one query
            $readings = Reading::where('parameter_id', (int) $parameter_id)
                ->where('device_id', (int) $device_id)
                ->where('recorded_time', '>=', $previousDate . ' 00:00:00')
                ->where('recorded_time', '<=', $startDate . ' 23:59:59')
                ->select('reading', 'recorded_time')
                ->orderBy('recorded_time', 'desc')
                ->get();

            $currentReading = $readings->first(function($reading) use ($startDate) {
                return strpos($reading->recorded_time, $startDate) === 0;
            });

            $previousReading = $readings->first(function($reading) use ($previousDate) {
                return strpos($reading->recorded_time, $previousDate) === 0;
            });
        } else {
            // Filter by month
            $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            $previousMonthEnd = date('Y-m-d', strtotime($startDate . ' -1 day'));
            $previousMonthStart = date('Y-m-01', strtotime($previousMonthEnd));

            // Fetch both current and previous month readings in one query
            $readings = Reading::where('parameter_id', (int) $parameter_id)
                ->where('device_id', (int) $device_id)
                ->where('recorded_time', '>=', $previousMonthStart . ' 00:00:00')
                ->where('recorded_time', '<=', $endDate . ' 23:59:59')
                ->select('reading', 'recorded_time')
                ->orderBy('recorded_time', 'desc')
                ->get();

            $currentReading = $readings->first(function($reading) use ($startDate, $endDate) {
                $recordDate = substr($reading->recorded_time, 0, 10);
                return $recordDate >= $startDate && $recordDate <= $endDate;
            });

            $previousReading = $readings->first(function($reading) use ($previousMonthStart, $previousMonthEnd) {
                $recordDate = substr($reading->recorded_time, 0, 10);
                return $recordDate >= $previousMonthStart && $recordDate <= $previousMonthEnd;
            });
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
                ->select('reading', 'recorded_time', 'parameter_id', 'device_id')
                ->orderBy('recorded_time', 'desc')
                ->first();

            return response()->json($latestReading);
        }

        // Calculate daily differences for the entire month
        $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $previousMonthEnd = date('Y-m-d', strtotime($startDate . ' -1 day'));
        $previousMonthStart = date('Y-m-01', strtotime($previousMonthEnd));

        // Fetch ALL readings for current and previous month in ONE query
        $allReadings = Reading::where('parameter_id', (int) $parameter_id)
            ->where('device_id', (int) $device_id)
            ->where('recorded_time', '>=', $previousMonthStart . ' 00:00:00')
            ->where('recorded_time', '<=', $endDate . ' 23:59:59')
            ->select('reading', 'recorded_time')
            ->orderBy('recorded_time', 'desc')
            ->get();

        if ($allReadings->isEmpty()) {
            return response()->json(['message' => 'No readings found for the specified month'], 404);
        }

        // Group readings by date and get last reading per day
        $readingsByDate = [];
        foreach ($allReadings as $reading) {
            $date = substr($reading->recorded_time, 0, 10);
            if (!isset($readingsByDate[$date])) {
                $readingsByDate[$date] = $reading;
            }
        }

        // Calculate daily differences
        $dailyDifferences = [];
        $daysInMonth = date('t', strtotime($startDate));

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            $previousDate = date('Y-m-d', strtotime($currentDate . ' -1 day'));

            // Check if readings exist for both dates
            if (!isset($readingsByDate[$currentDate]) || !isset($readingsByDate[$previousDate])) {
                continue;
            }

            $currentReading = $readingsByDate[$currentDate];
            $previousReading = $readingsByDate[$previousDate];

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
            return response()->json(['message' => 'No valid daily differences found for the specified month'], 404);
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
