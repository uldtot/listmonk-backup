<?php

// Import necessary PHPMailer classes for potential use (e.g., sending error emails)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Define the ListmonkAPI class to interact with the Listmonk service and manage configuration
class ListmonkAPI
{

    // Declare class properties for configuration values
    private $retentionDays; // Number of days to retain backups
    private $baseUrl;       // Base URL of the Listmonk API
    private $username;      // Username for Listmonk API authentication
    private $password;      // Password for Listmonk API authentication
    private $mailTo;        // Recipient email address for reports
    private $mailFrom;      // Sender email address for reports
    private $mailSubject;   // Subject line for backup report emails

    // SMTP server details
    private $smtpHost;      // SMTP server host address
    private $smtpPort;      // SMTP server port number
    private $smtpPass;      // SMTP password for authentication
    private $smtpUser;      // SMTP username for authentication


    // Constructor is executed when an object of this class is created
    public function __construct()
    {
        // Parse configuration values from config.ini file
        $ini = parse_ini_file("config.ini");

        // Throw an exception if the config file could not be read
        if ($ini === false) {
            throw new Exception("Could not load config.ini");
        }

        // Initialize class properties with values from config.ini
        $this->baseUrl = rtrim($ini['LISTMONK_URL'], '/'); // Remove trailing slash if present
        $this->username = $ini['LISTMONK_USER'];            // Set username from config
        $this->password = $ini['LISTMONK_PASS'];            // Set password from config

        // Retrieve email settings or set defaults
        $this->mailTo = $ini['MAIL_TO'];                          // Recipient email address
        $this->mailFrom = $ini['MAIL_FROM'];     // Sender email address
        $this->mailSubject = $ini['MAIL_SUBJECT']; // Email subject

        // SMTP server details
        $this->smtpHost = $ini['SMTP_HOST'];
        $this->smtpPort = $ini['SMTP_PORT'];
        $this->smtpPass = $ini['SMTP_PASS'];
        $this->smtpUser = $ini['SMTP_USER'];


        // Set the retention period for backups, defaulting to 30 days if not defined
        $this->retentionDays = isset($ini['BACKUP_RETENTION_DAYS']) ? (int)$ini['BACKUP_RETENTION_DAYS'] : 30;
    }


    /**
     * Sends an HTTP request to the Listmonk API using the specified method and endpoint.
     *
     * Supports GET, POST, PUT, and other HTTP methods, with optional JSON data payload.
     * Automatically includes Basic Authentication using credentials from config.ini.
     *
     * @param string $method   The HTTP method to use (e.g. 'GET', 'POST', 'PUT').
     * @param string $endpoint The API endpoint to call (relative to /api/).
     * @param array  $data     Optional data to send as JSON in the request body (for POST/PUT).
     *
     * @return array           An associative array with 'status' (HTTP status code)
     *                         and 'body' (decoded JSON response).
     *
     * @throws Exception       If a cURL error occurs during the request.
     */
    private function request(string $method, string $endpoint, array $queryString = [])
    {
        // Build the full API URL by appending the endpoint to the base URL
        $params = "";
        if($queryString) {
             $params = http_build_query($queryString);    
        } 
        
        $url = $this->baseUrl . '/api/' . ltrim($endpoint, '').'?'.$params.'';

        // Initialize a new cURL session
        $ch = curl_init();

        // Set up HTTP headers, including Basic Auth and JSON content type
        $headers = [
            'Authorization: Basic ' . base64_encode("{$this->username}:{$this->password}"),
            'Content-Type: application/json',
        ];

        // Configure cURL options for the request
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,                          // Set the target URL
            CURLOPT_RETURNTRANSFER => true,              // Return response as a string
            CURLOPT_CUSTOMREQUEST => strtoupper($method), // Use the specified HTTP method
            CURLOPT_HTTPHEADER => $headers,              // Set HTTP headers
        ]);

        // Execute the request and store the response
        $response = curl_exec($ch);

        // Retrieve the HTTP status code from the response
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Check for any cURL errors and throw an exception if found
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        // Close the cURL session to free resources
        curl_close($ch);

        // Return an array containing the HTTP status code and the decoded response body
        return [
            'status' => $httpCode,
            'body' => json_decode($response, true)
        ];
    }


    /**
     * Saves a CSV backup of the provided data array to the local /backup directory.
     *
     * - Creates the backup directory if it doesn't exist.
     * - Generates a timestamped filename using the optional prefix.
     * - Flattens nested arrays before writing to CSV.
     *
     * @param array  $data            The data to save (array of associative arrays).
     * @param string $filenamePrefix Optional prefix for the filename (default is 'backup').
     *
     * @return string|null            The full path to the saved file, or null if no data was provided.
     *
     * @throws RuntimeException       If the file cannot be opened for writing.
     */
    public function saveCsvBackup(array $data, string $filenamePrefix = 'backup')
    {
        // Return early if there's no data to save
        if (empty($data)) {
            return;
        }

        // Create the backup directory if it doesn't exist
        $backupDir = __DIR__ . '/backup';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Generate a timestamped filename, e.g. backup_140625_153045.csv
        $timestamp = date('dmY_His');
        $filename = "{$backupDir}/{$filenamePrefix}_{$timestamp}.csv";

        // Open the file for writing
        $fp = fopen($filename, 'w');
        if (!$fp) {
            throw new RuntimeException("Could not open file for writing: $filename");
        }

        // Get column headers from the first data row, flattening nested arrays if needed
        $headers = array_keys($this->flattenArray($data[0]));
        fputcsv($fp, $headers);

        // Write each row to the CSV file, flattening nested arrays
        foreach ($data as $row) {
            fputcsv($fp, $this->flattenArray($row));
        }

        // Close the file after writing
        fclose($fp);

        // Return the full path to the created CSV file
        return $filename;
    }


    /**
     * Recursively flattens a multi-dimensional associative array into a single-level array.
     *
     * Nested keys are concatenated using dot notation (e.g. 'parent.child.key').
     *
     * Example:
     * Input:  ['user' => ['name' => 'Alice', 'age' => 30]]
     * Output: ['user.name' => 'Alice', 'user.age' => 30]
     *
     * @param array  $array  The array to flatten.
     * @param string $prefix (Optional) The prefix used for nested keys (used during recursion).
     *
     * @return array         A single-level associative array with dot-notated keys.
     */
    private function flattenArray(array $array, string $prefix = '')
    {
        $result = [];

        // Loop through each key-value pair in the input array
        foreach ($array as $key => $value) {
            // Create a new key with dot notation if a prefix is provided
            $new_key = $prefix === '' ? $key : "{$prefix}.{$key}";

            // If the value is an array, recursively flatten it
            if (is_array($value)) {
                $result += $this->flattenArray($value, $new_key);
            } else {
                // Otherwise, assign the scalar value to the new flattened key
                $result[$new_key] = $value;
            }
        }

        // Return the fully flattened array
        return $result;
    }

    /**
     * Cleans up old CSV backup files in the /backup directory based on the retention period.
     *
     * - Skips cleanup if the backup directory doesn't exist.
     * - Deletes .csv files older than the configured number of retention days.
     * - Outputs the number of deleted files.
     *
     */
    public function cleanOldBackups()
    {
        // Define path to the backup directory
        $backupDir = __DIR__ . '/backup';

        // Exit if the directory does not exist — nothing to clean
        if (!is_dir($backupDir)) {
            return;
        }

        // Get all CSV files in the backup directory
        $files = glob($backupDir . '/*.csv');
        $now = time();      // Current timestamp
        $deleted = 0;       // Counter for deleted files

        // Iterate over each file
        foreach ($files as $file) {
            // Ensure it's a regular file (not a directory or symlink)
            if (is_file($file)) {
                // Get the file's last modification time
                $fileModified = filemtime($file);

                // Calculate the file's age in days
                $fileAgeInDays = ($now - $fileModified) / (60 * 60 * 24);

                // Delete file if older than the configured retention period
                if ($fileAgeInDays > $this->retentionDays) {
                    unlink($file);
                    $deleted++;
                }
            }
        }

        // Output the cleanup result
        echo "Cleanup complete. {$deleted} old backup(s) deleted.\n";
    }





    /**
     * Downloads a file from the given URL using cURL with proper URL encoding.
     *
     * - Ensures the path segments (e.g. filenames with spaces or special chars) are correctly encoded.
     * - Follows redirects and uses a custom User-Agent.
     * - Logs a warning if the request fails or returns a non-200 status code.
     *
     * @param string $url The URL to download from.
     * @return string|null The file content as a string, or null on failure.
     */
    private function downloadFile(string $url)
    {
        // Parse the URL into components (scheme, host, path, query, etc.)
        $parts = parse_url($url);
        if (!$parts) {
            return null; // Invalid URL format
        }

        // Split the path into segments (e.g. folder/file.png) for encoding
        $pathParts = explode('/', $parts['path']);

        // Encode each segment to handle spaces, special characters, etc.
        foreach ($pathParts as &$segment) {
            $segment = rawurlencode($segment);
        }

        // Reconstruct the encoded path
        $encodedPath = implode('/', $pathParts);

        // Rebuild the full URL with encoded path and optional query string
        $encodedUrl = $parts['scheme'] . '://' . $parts['host'] . $encodedPath;
        if (isset($parts['query'])) {
            $encodedUrl .= '?' . $parts['query'];
        }

        // Initialize cURL with the encoded URL
        $ch = curl_init($encodedUrl);

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response as string
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ListmonkBackup/1.0)'); // Custom User-Agent
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects

        // Execute the request and capture the response
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Close the cURL session
        curl_close($ch);

        // Check for errors or non-200 status
        if ($httpCode !== 200 || $data === false) {
            // Log up to 200 characters of the response for debugging
            error_log("Download failed: HTTP $httpCode, response: " . substr($data ?? '', 0, 200));
            return null;
        }

        // Return the downloaded content
        return $data;
    }

    /**
     * Retrieves all media items from the API and saves local copies of each media file.
     *
     * - Creates a local backup/media directory if it doesn't exist.
     * - Downloads each media file temporarily and compares it with the existing local copy (if any).
     * - If the file is new or changed, saves it locally with an optional timestamp to avoid overwriting.
     * - Adds a 'local_copy' key to each media item with the filename of the saved copy or null if failed.
     *
     * @return array The list of media items with an added 'local_copy' property indicating the local filename or null.
     */
    public function getAllMedia()
    {
        // Request media items from the API
        $response = $this->request('GET', 'media');
        $mediaItems = $response['body']['data']['results'] ?? [];

        // Define local directory for media backups
        $mediaDir = __DIR__ . '/backup/media';
        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0755, true);
        }

        // Iterate over each media item
        foreach ($mediaItems as &$item) {
            // If no URL is set, mark local copy as null and continue
            if (!isset($item['url'])) {
                $item['local_copy'] = null;
                continue;
            }

            $mediaUrl = $item['url'];
            // Extract filename from URL path
            $filename = basename(parse_url($mediaUrl, PHP_URL_PATH));
            $localPath = $mediaDir . '/' . $filename;

            // Download remote media file temporarily for comparison
            $temp = tempnam(sys_get_temp_dir(), 'media_');
            $data = $this->downloadFile($mediaUrl);

            // If download failed, clean up temp file and mark null
            if ($data === null) {
                unlink($temp);
                $item['local_copy'] = null;
                continue;
            }

            // Write downloaded data to temp file
            file_put_contents($temp, $data);

            // If a local copy exists, compare hashes to check if file changed
            if (file_exists($localPath)) {
                if (md5_file($localPath) === md5_file($temp)) {
                    // Files are identical; remove temp file and reuse existing
                    unlink($temp);
                    $item['local_copy'] = $filename;
                    continue;
                }

                // New or updated file — add timestamp to filename to avoid overwriting
                $timestamp = date('dmY_His');
                $filename = pathinfo($filename, PATHINFO_FILENAME) . "_{$timestamp}." . pathinfo($filename, PATHINFO_EXTENSION);
                $localPath = $mediaDir . '/' . $filename;
            }

            // Move the temp file to final local path
            rename($temp, $localPath);
            $item['local_copy'] = basename($localPath);
        }

        // Return updated media items with local_copy info
        return $mediaItems;
    }


    /**
     * Generates a backup report based on a log array and saves it as a text file.
     *
     * The report includes sections with counts, backup filenames and sizes,
     * number of downloaded media files, and any errors encountered.
     *
     * @param array $log An associative array containing backup information.
     */
    public function generateBackupReport(array $log)
    {
        // Current timestamp for the report header
        $timestamp = date('d.m.Y H:i:s');

        // Start the report content with a title and timestamp
        $report = "Listmonk Backup Report\n";
        $report .= "Date and Time: {$timestamp}\n\n";

        // Loop through each section of the log
        foreach ($log as $section => $entry) {
            $report .= strtoupper($section) . "\n";
            $report .= "--------------------------\n";

            // Include count of records if present
            if (isset($entry['count'])) {
                $report .= "Number of records: {$entry['count']}\n";
            }

            // Include backup file info if file exists
            if (isset($entry['file']) && file_exists($entry['file'])) {
                $size = filesize($entry['file']);
                $report .= "Backup file: " . basename($entry['file']) . " (" . number_format($size / 1024, 2) . " KB)\n";
            }

            // Include number of downloaded media files if present
            if (isset($entry['media_downloaded'])) {
                $report .= "Downloaded media: {$entry['media_downloaded']}\n";
            }

            // Include error messages if any
            if (isset($entry['error'])) {
                $report .= "ERROR: {$entry['error']}\n";
            }

            $report .= "\n";
        }

        // Define the reports directory and create it if it doesn't exist
        $reportsDir = __DIR__ . '/reports';
        if (!is_dir($reportsDir)) {
            mkdir($reportsDir, 0755, true);
        }

        // Define the path for the report file with timestamp
        $reportPath = $reportsDir . '/backup_report_' . date('dmY_His') . '.txt';

        // Save the report content to the file
        file_put_contents($reportPath, $report);

        // Output confirmation message with file location
        echo "Report saved as: {$reportPath}\n";
    }



    /**
     * Sends a backup report email using PHPMailer.
     *
     * Reads SMTP and mail configuration from a config file, formats the log array
     * into a readable message, and sends an HTML email with a plain text fallback.
     *
     * @param array $log Array containing backup log information to include in the email.
     */
    public function sendReportEmail(array $log)
    {
        // Retrieve email settings or set defaults
        $to = $this->mailTo ?? null;                          // Recipient email address
        $from = $this->mailFrom ?? 'no-reply@localhost';     // Sender email address
        $subject = $this->mailSubject ?? 'Listmonk Backup Rapport'; // Email subject


        // Check if recipient address is defined
        if (!$to) {
            echo "No recipient defined in MAIL_TO\n";
            return;
        }

        // Construct the email body from the log array
        $message = "Backup report - " . date('d.m.Y H:i:s') . "\n\n";

        foreach ($log as $section => $info) {
            $message .= strtoupper($section) . ":\n";
            foreach ($info as $key => $value) {
                $message .= "  - $key: $value\n";
            }
            $message .= "\n";
        }

        // Load PHPMailer classes (adjust paths if needed)
        require dirname(__FILE__) . '/PHPMailer-6.10.0/src/Exception.php';
        require dirname(__FILE__) . '/PHPMailer-6.10.0/src/PHPMailer.php';
        require dirname(__FILE__) . '/PHPMailer-6.10.0/src/SMTP.php';

        $mail = new PHPMailer(true);

        try {
            // Configure SMTP server settings
            $mail->isSMTP();
            $mail->Host       = $this->smtpHost;  
            // SMTP server host, e.g., smtp.gmail.com
            $mail->SMTPAuth   = true;                     // Enable SMTP authentication
            $mail->Username   = $this->smtpUser;                // SMTP username (your email)
            $mail->Password   = $this->smtpPass;                // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Encryption type (SSL/TLS)
            $mail->Port       = $this->smtpPort;                // SMTP port, e.g. 465 for SSL, 587 for TLS

            // Set email character encoding and encoding type
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            // Set sender and recipient addresses
            $mail->setFrom($from);
            $mail->addAddress($to);

            // Set email format and content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = nl2br($message);             // HTML body with line breaks
            $mail->AltBody = strip_tags($message);        // Plain text fallback body

            // Send the email
            $mail->send();
            echo 'Mail sent';
        } catch (Exception $e) {
            // Display error message if email sending fails
            echo "Mail error: {$mail->ErrorInfo}";
        }
    }


    // Retrieve all mailing lists
    public function getAllLists()
    {
        return $this->request('GET', 'lists', array('per_page' => 'all'));
    }

    // Retrieve all subscribers
    public function getAllSubscribers()
    {
        return $this->request('GET', 'subscribers', array('per_page' => 'all'));
    }

    // Retrieve all campaigns
    public function getAllCampaigns()
    {
        return $this->request('GET', 'campaigns', array('per_page' => 'all'));
    }

    // Retrieve all email templates
    public function getAllTemplates()
    {
        return $this->request('GET', 'templates', array('per_page' => 'all'));
    }

    // Retrieve all bounce records
    public function getAllBounces()
    {
        return $this->request('GET', 'bounces', array('per_page' => 'all'));
    }

    // Retrieve all import jobs
    public function getAllImport()
    {
        return $this->request('GET', 'import', array('per_page' => 'all'));
    }
}





// Backup alle Listmonk data typer i CSV
$listmonk = new ListmonkAPI();
$log = [];

// Backup Lists
$response = $listmonk->getAllLists();
if ($response['status'] === 200) {
    $data = $response['body']['data']['results'];
    $filename = __DIR__ . '/backup/lists_' . date('dmY_His') . '.csv';
    $listmonk->saveCsvBackup($data, 'lists');
    $log['lists'] = [
        'count' => count($data),
        'file' => $filename
    ];
}

// Backup Subscribers
$response = $listmonk->getAllSubscribers();
if ($response['status'] === 200) {
    $data = $response['body']['data']['results'];
    $filename = __DIR__ . '/backup/subscribers_' . date('dmY_His') . '.csv';
    $listmonk->saveCsvBackup($data, 'subscribers');
    $log['subscribers'] = [
        'count' => count($data),
        'file' => $filename
    ];
}

// Backup Campaigns
$response = $listmonk->getAllCampaigns();
if ($response['status'] === 200) {
    $data = $response['body']['data']['results'];
    $filename = __DIR__ . '/backup/campaigns_' . date('dmY_His') . '.csv';
    $listmonk->saveCsvBackup($data, 'campaigns');
    $log['campaigns'] = [
        'count' => count($data),
        'file' => $filename
    ];
}

// Backup Templates
$response = $listmonk->getAllTemplates();
if ($response['status'] === 200) {
    $data = $response['body']['data'];
    $filename = __DIR__ . '/backup/templates_' . date('dmY_His') . '.csv';
    $listmonk->saveCsvBackup($data, 'templates');
    $log['templates'] = [
        'count' => count($data),
        'file' => $filename
    ];
}

// Backup Bounces
$response = $listmonk->getAllBounces();
if ($response['status'] === 200) {
    $data = $response['body']['data']['results'];
    $filename = __DIR__ . '/backup/bounces_' . date('dmY_His') . '.csv';
    $listmonk->saveCsvBackup($data, 'bounces');
    $log['bounces'] = [
        'count' => count($data),
        'file' => $filename
    ];
}

// Backup Import
$response = $listmonk->getAllImport();
if ($response['status'] === 200) {
    $data = $response['body']['data']['results'];
    $filename = __DIR__ . '/backup/import_' . date('dmY_His') . '.csv';
    $listmonk->saveCsvBackup($data, 'import');
    $log['import'] = [
        'count' => count($data),
        'file' => $filename
    ];
}

// Backup Media (henter filer og laver lokal kopi)
try {
    $mediaItems = $listmonk->getAllMedia();
    $filename = __DIR__ . '/backup/media_' . date('dmY_His') . '.csv';
    $listmonk->saveCsvBackup($mediaItems, 'media');
    $filename = $listmonk->saveCsvBackup($mediaItems, 'media');
    $log['media'] = [
        'count' => count($mediaItems),
        'file' => $filename,
        'media_downloaded' => count(array_filter($mediaItems, function ($i) {
            return isset($i['local_copy']) && $i['local_copy'] !== null;
        }))
    ];
} catch (Exception $e) {
    $log['media'] = ['error' => $e->getMessage()];
}




// Generer rapport
$listmonk->generateBackupReport($log);

$listmonk->sendReportEmail($log);
