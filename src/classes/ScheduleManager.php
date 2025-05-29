<?php
class ScheduleManager {
    private string $schedulesFilePath;

    public function __construct() {
        $this->schedulesFilePath = __DIR__ . '/../data/schedules.json';
    }

    private function loadSchedules(): array {
        if (!file_exists($this->schedulesFilePath) || !is_readable($this->schedulesFilePath)) {
            return [];
        }
        $json = file_get_contents($this->schedulesFilePath);
        return json_decode($json, true) ?? [];
    }

    public function getUserSchedule(string $username): array {
        $schedules = $this->loadSchedules();
        foreach ($schedules as $entry) {
            if (isset($entry['username']) && $entry['username'] === $username) {
                return $entry['schedule'] ?? [];
            }
        }
        return []; // No schedule found for the user
    }
}