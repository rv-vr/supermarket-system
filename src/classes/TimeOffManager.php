<?php
class TimeOffManager {
    private string $requestsFilePath;
    public const STATUS_PENDING = 'Pending';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_DENIED = 'Denied';

    public function __construct() {
        $this->requestsFilePath = __DIR__ . '/../data/time_off_requests.json';
        if (!file_exists($this->requestsFilePath)) {
            $this->saveRequests([]);
        }
        if (date_default_timezone_get() !== 'Asia/Manila') {
            date_default_timezone_set('Asia/Manila');
        }
    }

    private function loadRequests(): array {
        if (!file_exists($this->requestsFilePath) || !is_readable($this->requestsFilePath)) {
            return [];
        }
        $json = file_get_contents($this->requestsFilePath);
        $requests = json_decode($json, true);
        return is_array($requests) ? $requests : [];
    }

    private function saveRequests(array $requests): bool {
        $dataDir = dirname($this->requestsFilePath);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0775, true);
        }
        if (!is_writable($dataDir) || (file_exists($this->requestsFilePath) && !is_writable($this->requestsFilePath))) {
            error_log("TimeOffManager: Data directory or file is not writable: " . $this->requestsFilePath);
            return false;
        }
        $json = json_encode(array_values($requests), JSON_PRETTY_PRINT); // Ensure it's a JSON array
        if ($json === false) {
            error_log("TimeOffManager: Failed to encode requests to JSON. Error: " . json_last_error_msg());
            return false;
        }
        return file_put_contents($this->requestsFilePath, $json, LOCK_EX) !== false;
    }

    private function generateRequestId(): string {
        return 'TOR' . date('YmdHis') . substr(uniqid(), -4);
    }

    public function submitRequest(string $username, string $startDate, string $endDate, string $reason): array {
        if (empty($username) || empty($startDate) || empty($endDate)) {
            return ['success' => false, 'message' => 'Username, start date, and end date are required.'];
        }
        if (strtotime($startDate) > strtotime($endDate)) {
            return ['success' => false, 'message' => 'Start date cannot be after end date.'];
        }
        // Basic validation for date format can be added here if needed

        $requests = $this->loadRequests();
        $newRequest = [
            'request_id' => $this->generateRequestId(),
            'username' => $username,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => trim($reason),
            'status' => self::STATUS_PENDING,
            'requested_on' => date('Y-m-d H:i:s'),
            'action_taken_by' => null,
            'action_taken_on' => null,
        ];
        $requests[] = $newRequest;

        if ($this->saveRequests($requests)) {
            return ['success' => true, 'message' => 'Time off request submitted successfully.', 'request_id' => $newRequest['request_id']];
        }
        return ['success' => false, 'message' => 'Failed to submit time off request.'];
    }

    public function getAllRequests(string $statusFilter = null, string $usernameFilter = null): array {
        $requests = $this->loadRequests();
        
        if ($statusFilter) {
            $requests = array_filter($requests, function($req) use ($statusFilter) {
                return isset($req['status']) && $req['status'] === $statusFilter;
            });
        }
        if ($usernameFilter) {
             $requests = array_filter($requests, function($req) use ($usernameFilter) {
                return isset($req['username']) && $req['username'] === $usernameFilter;
            });
        }
        
        // Sort by requested_on date, newest first
        usort($requests, function($a, $b) {
            return strtotime($b['requested_on'] ?? 0) <=> strtotime($a['requested_on'] ?? 0);
        });
        return array_values($requests); // Re-index
    }
    
    public function getPendingRequests(): array {
        return $this->getAllRequests(self::STATUS_PENDING);
    }

    public function getRequestById(string $requestId): ?array {
        $requests = $this->loadRequests();
        foreach ($requests as $request) {
            if (isset($request['request_id']) && $request['request_id'] === $requestId) {
                return $request;
            }
        }
        return null;
    }

    public function updateRequestStatus(string $requestId, string $newStatus, string $managerUsername): array {
        if (!in_array($newStatus, [self::STATUS_APPROVED, self::STATUS_DENIED])) {
            return ['success' => false, 'message' => 'Invalid status provided.'];
        }
        $requests = $this->loadRequests();
        $requestFound = false;
        foreach ($requests as $key => $request) {
            if (isset($request['request_id']) && $request['request_id'] === $requestId) {
                if ($request['status'] !== self::STATUS_PENDING) {
                     return ['success' => false, 'message' => 'This request has already been actioned.'];
                }
                $requests[$key]['status'] = $newStatus;
                $requests[$key]['action_taken_by'] = $managerUsername;
                $requests[$key]['action_taken_on'] = date('Y-m-d H:i:s');
                $requestFound = true;
                break;
            }
        }

        if (!$requestFound) {
            return ['success' => false, 'message' => 'Time off request not found.'];
        }

        if ($this->saveRequests($requests)) {
            // Future: Notify employee, integrate with schedule (e.g., mark as unavailable)
            return ['success' => true, 'message' => "Request {$requestId} has been {$newStatus}."];
        }
        return ['success' => false, 'message' => "Failed to update status for request {$requestId}."];
    }
}