<?php

class AttendanceManager {
    private string $timeLogFilePath;

    public function __construct() {
        $this->timeLogFilePath = __DIR__ . '/../data/time_log.json';
        // Ensure timezone is set if not already globally done
        if (date_default_timezone_get() !== 'Asia/Manila') {
            date_default_timezone_set('Asia/Manila');
        }
    }

    private function loadTimeLogs(): array {
        if (!file_exists($this->timeLogFilePath) || !is_readable($this->timeLogFilePath)) {
            return [];
        }
        $json = file_get_contents($this->timeLogFilePath);
        return json_decode($json, true) ?? [];
    }

    public function getUserTimeLogs(string $username): array {
        $allLogs = $this->loadTimeLogs();
        $userLogs = array_filter($allLogs, function ($log) use ($username) {
            return isset($log['username']) && $log['username'] === $username;
        });
        // Sort by timestamp descending to get recent logs first
        usort($userLogs, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        return $userLogs;
    }

    public function getCurrentStatus(string $username): array {
        $userLogs = $this->getUserTimeLogs($username);
        if (empty($userLogs)) {
            return ['status' => 'Never Timed In', 'timestamp' => null, 'action' => null];
        }
        $lastLog = $userLogs[0]; // Most recent log
        $status = ($lastLog['action'] === 'time_in') ? 'Timed In' : 'Timed Out';
        return ['status' => $status, 'timestamp' => $lastLog['timestamp'], 'action' => $lastLog['action']];
    }

    public function getAttendanceSummary(string $username, int $days = 7): array {
        $userLogs = $this->getUserTimeLogs($username);
        $summary = ['total_timed_in_days' => 0, 'recent_activity' => []];
        $timedInDays = [];

        // Get logs for the specified number of past days
        $limitDate = date('Y-m-d H:i:s', strtotime("-{$days} days")); // Uses default timezone

        foreach (array_reverse($userLogs) as $log) { // Process oldest first for pairing
            if ($log['timestamp'] < $limitDate) continue;

            $day = date('Y-m-d', strtotime($log['timestamp'])); // Uses default timezone
            if ($log['action'] === 'time_in') {
                if (!in_array($day, $timedInDays)) {
                    $timedInDays[] = $day;
                }
            }
            // For recent activity, let's just show the last 5 actions
            if (count($summary['recent_activity']) < 5) {
                 // Add to beginning to keep recent_activity sorted new to old
                array_unshift($summary['recent_activity'], [
                    'action' => $log['action'],
                    'timestamp' => date('M d, Y H:i', strtotime($log['timestamp'])) // Uses default timezone
                ]);
            }
        }
        $summary['total_timed_in_days'] = count($timedInDays);
        return $summary;
    }
}