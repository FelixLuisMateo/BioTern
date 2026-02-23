<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BiometricEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $authError = $this->authenticateRequest($request);
        if ($authError !== null) {
            return $authError;
        }

        $validated = $request->validate([
            'student_id' => 'required|integer|min:1',
            'event_type' => 'required|string|in:morning_in,morning_out,break_in,break_out,afternoon_in,afternoon_out',
            'event_at' => 'nullable|date',
            'attendance_date' => 'nullable|date_format:Y-m-d',
            'device_id' => 'nullable|string|max:100',
        ]);

        $clockMap = [
            'morning_in' => 'morning_time_in',
            'morning_out' => 'morning_time_out',
            'break_in' => 'break_time_in',
            'break_out' => 'break_time_out',
            'afternoon_in' => 'afternoon_time_in',
            'afternoon_out' => 'afternoon_time_out',
        ];

        $column = $clockMap[$validated['event_type']];
        $studentId = (int) $validated['student_id'];

        $studentExists = DB::table('students')->where('id', $studentId)->exists();
        if (!$studentExists) {
            return response()->json([
                'ok' => false,
                'error' => 'student_not_found',
            ], 404);
        }

        $eventAt = !empty($validated['event_at']) ? Carbon::parse($validated['event_at']) : Carbon::now();
        $attendanceDate = !empty($validated['attendance_date']) ? $validated['attendance_date'] : $eventAt->toDateString();
        $eventTime = $eventAt->format('H:i:s');

        try {
            DB::beginTransaction();

            $row = DB::table('attendances')
                ->where('student_id', $studentId)
                ->where('attendance_date', $attendanceDate)
                ->lockForUpdate()
                ->first();

            if ($row) {
                DB::table('attendances')
                    ->where('id', $row->id)
                    ->update([
                        $column => $eventTime,
                        'updated_at' => DB::raw('NOW()'),
                    ]);
                $attendanceId = (int) $row->id;
                $action = 'updated';
            } else {
                $attendanceId = (int) DB::table('attendances')->insertGetId([
                    'student_id' => $studentId,
                    'attendance_date' => $attendanceDate,
                    $column => $eventTime,
                    'status' => 'pending',
                    'created_at' => DB::raw('NOW()'),
                    'updated_at' => DB::raw('NOW()'),
                ]);
                $action = 'created';
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Biometric event store failed', [
                'student_id' => $studentId,
                'event_type' => $validated['event_type'],
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'store_failed',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'action' => $action,
            'attendance_id' => $attendanceId,
            'student_id' => $studentId,
            'attendance_date' => $attendanceDate,
            'event_type' => $validated['event_type'],
            'stored_column' => $column,
            'stored_time' => $eventTime,
        ]);
    }

    private function authenticateRequest(Request $request): ?JsonResponse
    {
        $apiKey = (string) config('app.biometric_api_key', env('BIOMETRIC_API_KEY', ''));
        $apiSecret = (string) config('app.biometric_api_secret', env('BIOMETRIC_API_SECRET', ''));
        $maxSkewSeconds = (int) env('BIOMETRIC_MAX_SKEW_SECONDS', 300);

        if ($apiKey === '' || $apiSecret === '') {
            Log::warning('Biometric API key/secret not configured.');
            return response()->json(['ok' => false, 'error' => 'server_not_configured'], 503);
        }

        $keyHeader = (string) $request->header('X-Biometric-Key', '');
        $timestampHeader = (string) $request->header('X-Biometric-Timestamp', '');
        $signatureHeader = strtolower((string) $request->header('X-Biometric-Signature', ''));

        if ($keyHeader === '' || $timestampHeader === '' || $signatureHeader === '') {
            return response()->json(['ok' => false, 'error' => 'missing_auth_headers'], 401);
        }

        if (!hash_equals($apiKey, $keyHeader)) {
            return response()->json(['ok' => false, 'error' => 'invalid_key'], 401);
        }

        if (!ctype_digit($timestampHeader)) {
            return response()->json(['ok' => false, 'error' => 'invalid_timestamp'], 401);
        }

        $timestamp = (int) $timestampHeader;
        $now = time();
        if (abs($now - $timestamp) > $maxSkewSeconds) {
            return response()->json(['ok' => false, 'error' => 'timestamp_expired'], 401);
        }

        $body = (string) $request->getContent();
        $expectedSignature = hash_hmac('sha256', $timestampHeader . '.' . $body, $apiSecret);

        if (!hash_equals($expectedSignature, $signatureHeader)) {
            return response()->json(['ok' => false, 'error' => 'invalid_signature'], 401);
        }

        $replayKey = 'biometric:replay:' . hash('sha256', $timestampHeader . ':' . $signatureHeader);
        if (Cache::has($replayKey)) {
            return response()->json(['ok' => false, 'error' => 'replayed_request'], 409);
        }
        Cache::put($replayKey, true, now()->addSeconds($maxSkewSeconds));

        return null;
    }
}

