<?php
class ScheduleManager {
    private string $schedulesFilePath;

    public function __construct() {
        $this->schedulesFilePath = __DIR__ . '/../data/schedules.json';
        if (!file_exists($this->schedulesFilePath)) {
            // Initialize with an empty array if the file doesn't exist
            $this->saveSchedules([]);
        }
    }

    private function loadSchedules(): array {
        if (!file_exists($this->schedulesFilePath) || !is_readable($this->schedulesFilePath)) {
            return [];
        }
        $json = file_get_contents($this->schedulesFilePath);
        $schedules = json_decode($json, true);
        return is_array($schedules) ? $schedules : [];
    }

    private function saveSchedules(array $schedules): bool {
        // Ensure the data directory exists and is writable
        $dataDir = dirname($this->schedulesFilePath);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0775, true); // Create directory if it doesn't exist
        }
        if (!is_writable($dataDir) || (file_exists($this->schedulesFilePath) && !is_writable($this->schedulesFilePath))) {
            error_log("ScheduleManager: Schedules data directory or file is not writable: " . $this->schedulesFilePath);
            return false;
        }
        $json = json_encode($schedules, JSON_PRETTY_PRINT);
        if ($json === false) {
            error_log("ScheduleManager: Failed to encode schedules to JSON. Error: " . json_last_error_msg());
            return false;
        }
        return file_put_contents($this->schedulesFilePath, $json, LOCK_EX) !== false;
    }

    public function getAllSchedules(): array {
        return $this->loadSchedules();
    }

    public function getUserSchedule(string $username): array {
        $schedules = $this->loadSchedules();
        foreach ($schedules as $entry) {
            if (isset($entry['username']) && $entry['username'] === $username) {
                // Ensure schedule items have all expected keys
                $normalizedSchedule = [];
                if (isset($entry['schedule']) && is_array($entry['schedule'])) {
                    foreach($entry['schedule'] as $daySchedule) {
                        $normalizedSchedule[] = [
                            'day' => $daySchedule['day'] ?? 'Unknown',
                            'start_time' => $daySchedule['start_time'] ?? '',
                            'end_time' => $daySchedule['end_time'] ?? '',
                            'notes' => $daySchedule['notes'] ?? ''
                        ];
                    }
                }
                return $normalizedSchedule;
            }
        }
        return []; // No schedule found for the user
    }

    public function setScheduleForUser(string $username, array $newDailySchedules): array {
        if (empty($username)) {
            return ['success' => false, 'message' => 'Username cannot be empty.'];
        }

        $allSchedules = $this->loadSchedules();
        $userFound = false;
        $updatedSchedules = [];

        // Validate and normalize new schedule entries
        $validatedDailySchedules = [];
        $daysOfWeek = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
        foreach ($newDailySchedules as $daySchedule) {
            if (isset($daySchedule['day']) && in_array($daySchedule['day'], $daysOfWeek)) {
                // Only add if start or end time is provided, or notes.
                if (!empty($daySchedule['start_time']) || !empty($daySchedule['end_time']) || !empty($daySchedule['notes'])) {
                     $validatedDailySchedules[] = [
                        'day' => $daySchedule['day'],
                        'start_time' => trim($daySchedule['start_time'] ?? ''),
                        'end_time' => trim($daySchedule['end_time'] ?? ''),
                        'notes' => trim($daySchedule['notes'] ?? '')
                    ];
                }
            }
        }
        
        // Sort the schedule by day of the week for consistency
        usort($validatedDailySchedules, function($a, $b) use ($daysOfWeek) {
            return array_search($a['day'], $daysOfWeek) <=> array_search($b['day'], $daysOfWeek);
        });


        foreach ($allSchedules as $key => $entry) {
            if (isset($entry['username']) && $entry['username'] === $username) {
                $allSchedules[$key]['schedule'] = $validatedDailySchedules;
                $userFound = true;
                break; 
            }
        }

        if (!$userFound) {
            $allSchedules[] = [
                'username' => $username,
                'schedule' => $validatedDailySchedules
            ];
        }
        
        // Remove users with completely empty schedules to keep the file clean
        $cleanedSchedules = array_filter($allSchedules, function($entry) {
            return !(isset($entry['schedule']) && empty($entry['schedule']));
        });


        if ($this->saveSchedules(array_values($cleanedSchedules))) { // Re-index array
            return ['success' => true, 'message' => "Schedule for {$username} updated successfully."];
        } else {
            return ['success' => false, 'message' => "Failed to save schedule for {$username}."];
        }
    }
}