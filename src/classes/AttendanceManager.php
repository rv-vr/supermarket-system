<?php

class AttendanceManager {
    private string $timeLogFilePath;

    public function __construct() {
        $this->timeLogFilePath = __DIR__ . '/../data/time_log.json';
        // Ensure timezone is set if not already globally done
        if (date_default_timezone_get() !== 'Asia/Manila') {
            date_default_timezone_set('Asia/Manila');
        }
        // Ensure the log file exists and is an array
        if (!file_exists($this->timeLogFilePath)) {
            $dataDir = dirname($this->timeLogFilePath);
            if (!is_dir($dataDir)) {
                @mkdir($dataDir, 0777, true);
            }
            file_put_contents($this->timeLogFilePath, json_encode([]));
        } else {
            // Ensure it's a valid JSON array, otherwise reset it
            $content = file_get_contents($this->timeLogFilePath);
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                file_put_contents($this->timeLogFilePath, json_encode([]));
            }
        }
    }

    private function loadTimeLogs(): array {
        if (!file_exists($this->timeLogFilePath) || !is_readable($this->timeLogFilePath)) {
            return [];
        }
        $json = file_get_contents($this->timeLogFilePath);
        $logs = json_decode($json, true);
        return is_array($logs) ? $logs : []; // Ensure it returns an array
    }

    private function saveTimeLogs(array $logs): bool {
        $dir = dirname($this->timeLogFilePath);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
                error_log("AttendanceManager: Log directory ({$dir}) does not exist and could not be created.");
                return false;
            }
        } elseif (!is_writable($dir)) {
            error_log("AttendanceManager: Log directory ({$dir}) is not writable.");
            return false;
        }

        $jsonData = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($jsonData === false) {
            error_log("AttendanceManager: Failed to encode time logs to JSON. Error: " . json_last_error_msg());
            return false;
        }
        if (file_put_contents($this->timeLogFilePath, $jsonData, LOCK_EX) !== false) {
            return true;
        } else {
            error_log("AttendanceManager: Failed to write time logs to file: {$this->timeLogFilePath}");
            return false;
        }
    }

    public function timeIn(string $username): array {
        $currentStatus = $this->getCurrentStatus($username);
        if ($currentStatus['action'] === 'time_in') {
            return ['success' => false, 'message' => 'You are already timed in.'];
        }

        $logs = $this->loadTimeLogs();
        $newLog = [
            'username' => $username,
            'action' => 'time_in',
            'timestamp' => date('Y-m-d H:i:s') // Uses default timezone
        ];
        $logs[] = $newLog;

        if ($this->saveTimeLogs($logs)) {
            return ['success' => true, 'message' => 'Successfully timed in at ' . date('h:i A', strtotime($newLog['timestamp'])) . '.'];
        } else {
            return ['success' => false, 'message' => 'Failed to record time in. Please try again.'];
        }
    }

    public function timeOut(string $username): array {
        $currentStatus = $this->getCurrentStatus($username);
        if ($currentStatus['action'] === 'time_out' || $currentStatus['status'] === 'Never Timed In') {
            return ['success' => false, 'message' => 'You are not currently timed in.'];
        }

        $logs = $this->loadTimeLogs();
        $newLog = [
            'username' => $username,
            'action' => 'time_out',
            'timestamp' => date('Y-m-d H:i:s') // Uses default timezone
        ];
        $logs[] = $newLog;

        if ($this->saveTimeLogs($logs)) {
            return ['success' => true, 'message' => 'Successfully timed out at ' . date('h:i A', strtotime($newLog['timestamp'])) . '.'];
        } else {
            return ['success' => false, 'message' => 'Failed to record time out. Please try again.'];
        }
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