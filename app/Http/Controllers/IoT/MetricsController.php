<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\IoT\Device;
use App\Models\IoT\DeviceMetric;
use App\Models\IoT\SecurityEvent;
use Illuminate\Support\Facades\Log;

class MetricsController extends Controller
{

    private const REQUIRED_DATE_RULE = 'required|date';
    private const REQUIRED_ARRAY_RULE = 'required|array';
    private const REQUIRED_STRING_RULE = 'required|string';

    private const REQUIRED_NUMERIC_RULE = 'required|numeric|min:0';
    private const REQUIRED_IP_RULE = 'required|ip';
    private const NULLABLE_STRING_RULE = 'nullable|string';
    private const REQUIRED_INTEGER_RULE = 'required|integer';
    /**
     * Store network data from device
     */
    public function storeNetworkData(Request $request)
    {
        $device = $request->attributes->get('device');
        
        $validated = $request->validate([
            'timestamp' => self::REQUIRED_DATE_RULE,
            'devices' => self::REQUIRED_ARRAY_RULE,
            'devices.*.ip' => self::REQUIRED_IP_RULE,
            'devices.*.mac' => self::REQUIRED_STRING_RULE,
            'devices.*.hostname' => self::NULLABLE_STRING_RULE,
            'devices.*.device_type' => self::REQUIRED_STRING_RULE,
            'devices.*.bandwidth.upload' => self::REQUIRED_NUMERIC_RULE,
            'devices.*.bandwidth.download' => self::REQUIRED_NUMERIC_RULE,
            'summary' => self::REQUIRED_ARRAY_RULE,
            'summary.active_devices' => self::REQUIRED_INTEGER_RULE,
            'summary.bandwidth_utilization' => self::REQUIRED_NUMERIC_RULE,
        ]);

        $metric = DeviceMetric::create([
            'device_id' => $device->id,
            'metric_type' => 'network_data',
            'data' => $validated,
            'collected_at' => $validated['timestamp'],
        ]);

        Log::debug('Network data stored', [
            'device_id' => $device->device_id,
            'active_devices' => $validated['summary']['active_devices'],
            'bandwidth_utilization' => $validated['summary']['bandwidth_utilization']
        ]);

        return response()->json([
            'status' => 'stored',
            'metric_id' => $metric->id,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Store system data from device
     */
    public function storeSystemData(Request $request)
    {
        $device = $request->attributes->get('device');
        
        $validated = $request->validate([
            'timestamp' => self::REQUIRED_DATE_RULE,
            'cpu.usage' => self::REQUIRED_NUMERIC_RULE,
            'cpu.cores' => self::REQUIRED_INTEGER_RULE,
            'cpu.temperature' => 'nullable|numeric',
            'memory.total' => self::REQUIRED_INTEGER_RULE,
            'memory.used' => self::REQUIRED_INTEGER_RULE,
            'memory.usage_percent' => self::REQUIRED_NUMERIC_RULE,
            'disk.total' => self::REQUIRED_INTEGER_RULE,
            'disk.used' => self::REQUIRED_INTEGER_RULE,
            'disk.usage_percent' => self::REQUIRED_NUMERIC_RULE,
            'system.uptime' => self::REQUIRED_STRING_RULE,
            'system.load_average' => self::REQUIRED_NUMERIC_RULE,
        ]);

        $metric = DeviceMetric::create([
            'device_id' => $device->id,
            'metric_type' => 'system_data',
            'data' => $validated,
            'collected_at' => $validated['timestamp'],
        ]);

        Log::debug('System data stored', [
            'device_id' => $device->device_id,
            'cpu_usage' => $validated['cpu']['usage'],
            'memory_usage' => $validated['memory']['usage_percent']
        ]);

        return response()->json([
            'status' => 'stored',
            'metric_id' => $metric->id,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Store security event from device
     */
    public function storeSecurityEvent(Request $request)
    {
        $device = $request->attributes->get('device');
        
        $validated = $request->validate([
            'id' => self::REQUIRED_STRING_RULE,
            'timestamp' => self::REQUIRED_DATE_RULE,
            'type' => 'required|in:blocked_request,malware_detected,intrusion_attempt',
            'severity' => 'required|in:low,medium,high,critical',
            'source_ip' => self::REQUIRED_IP_RULE,
            'domain' => self::NULLABLE_STRING_RULE,
            'category' => self::NULLABLE_STRING_RULE,
            'action' => self::REQUIRED_STRING_RULE,
            'reason' => self::REQUIRED_STRING_RULE,
            'details' => 'nullable|array',
        ]);

        $event = SecurityEvent::create([
            'device_id' => $device->id,
            'event_id' => $validated['id'],
            'event_type' => $validated['type'],
            'severity' => $validated['severity'],
            'source_ip' => $validated['source_ip'],
            'domain' => $validated['domain'],
            'category' => $validated['category'],
            'action' => $validated['action'],
            'reason' => $validated['reason'],
            'details' => $validated['details'],
            'occurred_at' => $validated['timestamp'],
        ]);

        Log::info('Security event stored', [
            'device_id' => $device->device_id,
            'event_type' => $validated['type'],
            'severity' => $validated['severity'],
            'source_ip' => $validated['source_ip'],
            'domain' => $validated['domain'] ?? 'N/A'
        ]);

        // TODO: Trigger real-time notification to dashboard
        
        return response()->json([
            'status' => 'stored',
            'event_id' => $event->id,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Store multiple metrics at once (for offline devices)
     */
    public function storeBulkMetrics(Request $request)
    {
        $device = $request->attributes->get('device');
        
        $validated = $request->validate([
            'metrics' => self::REQUIRED_ARRAY_RULE,
            'metrics.*.type' => 'required|in:network_data,system_data',
            'metrics.*.timestamp' => self::REQUIRED_DATE_RULE,
            'metrics.*.data' => 'required|array',
        ]);

        $storedCount = 0;
        foreach ($validated['metrics'] as $metricData) {
            DeviceMetric::create([
                'device_id' => $device->id,
                'metric_type' => $metricData['type'],
                'data' => $metricData['data'],
                'collected_at' => $metricData['timestamp'],
            ]);
            $storedCount++;
        }

        Log::info('Bulk metrics stored', [
            'device_id' => $device->device_id,
            'metrics_count' => $storedCount
        ]);

        return response()->json([
            'status' => 'stored',
            'metrics_stored' => $storedCount,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get device metrics for dashboard
     */
    public function getDeviceMetrics(Request $request, Device $device)
    {
        $validated = $request->validate([
            'type' => 'sometimes|in:network_data,system_data,bandwidth_usage',
            'limit' => 'sometimes|integer|min:1|max:1000',
            'from' => 'sometimes|date',
            'to' => 'sometimes|date',
        ]);

        $query = $device->metrics();

        if (isset($validated['type'])) {
            $query->where('metric_type', $validated['type']);
        }

        if (isset($validated['from'])) {
            $query->where('collected_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->where('collected_at', '<=', $validated['to']);
        }

        $metrics = $query->orderBy('collected_at', 'desc')
            ->limit($validated['limit'] ?? 100)
            ->get();

        return response()->json([
            'device_id' => $device->device_id,
            'metrics' => $metrics,
            'total' => $metrics->count(),
        ]);
    }

    /**
     * Get security events for dashboard
     */
    public function getSecurityEvents(Request $request, Device $device)
    {
        $validated = $request->validate([
            'severity' => 'sometimes|in:low,medium,high,critical',
            'limit' => 'sometimes|integer|min:1|max:1000',
            'from' => 'sometimes|date',
            'to' => 'sometimes|date',
        ]);

        $query = $device->securityEvents();

        if (isset($validated['severity'])) {
            $query->where('severity', $validated['severity']);
        }

        if (isset($validated['from'])) {
            $query->where('occurred_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->where('occurred_at', '<=', $validated['to']);
        }

        $events = $query->orderBy('occurred_at', 'desc')
            ->limit($validated['limit'] ?? 50)
            ->get();

        return response()->json([
            'device_id' => $device->device_id,
            'security_events' => $events,
            'total' => $events->count(),
        ]);
    }
}
