<?php
namespace TomLabs\Labs;

/**
 * LabTemplateConfig
 * 
 * Defines the dynamic "Lab Information" fields for each lab type.
 * This replaces hardcoded HTML in the dashboard.
 */
class LabTemplateConfig {
    
    /**
     * Get the template configuration for a specific lab type
     * 
     * @param string $labType The ID of the lab (e.g., 'essentials', 'minio')
     * @param array $labData The deploy subdocument from instances collection
     * @param string|null $currentUser The current session username
     * @return array Configuration array with 'fields'
     */
    public static function getTemplate(string $labType, array $labData, ?string $currentUser = null): array {
        
        $creds = $labData['credentials'] ?? [];
        $internalIp = $labData['internal_ip'] ?? '0.0.0.0';
        $tunnelIp = $creds['tunnel_ip'] ?? $internalIp;
        $password = $creds['password'] ?? '********';
        $suPassword = $creds['su_pass'] ?? $password;
        $codeServerPass = $creds['code_server_pass'] ?? $password;
        
        // Common Values
        $sshCommand = (isset($creds['tunnel_ip'])) ? "ssh " . $currentUser . "@" . $creds['tunnel_ip'] : "#";

        $action = null;
        $title = ucfirst($labType) . " Lab Instance";
        $description = [
            "Access your lab environment securely.",
            "Manage files and run commands via SSH.",
            "Interact with your services directly."
        ];
        $instruction = "You need these credentials in the next screen to login to your lab - Happy Coding!";
        $primary = ['label' => 'Lab Password', 'value' => $password];

        switch ($labType) {
            case 'minio':
                $minioAccess = $creds['minio_access_key'] ?? $currentUser;
                $minioSecret = $creds['minio_secret_key'] ?? $password;
                $minioConsoleUrl = !empty($creds['minio_url_console'])
                    ? LabUrl::withScheme((string)$creds['minio_url_console'])
                    : LabUrl::url(LabUrl::serviceHost($labData, 's3-'));
                $minioApiUrl = !empty($creds['minio_url_api'])
                    ? LabUrl::withScheme((string)$creds['minio_url_api'])
                    : LabUrl::url(LabUrl::serviceHost($labData, 'api-'));

                $fields = [
                    ['label' => 'MinIO Access Key', 'value' => $minioAccess, 'type' => 'text', 'copy' => true],
                    ['label' => 'Minio Secret Key', 'value' => $minioSecret, 'type' => 'password', 'copy' => true],
                    ['label' => 'Console URL', 'value' => $minioConsoleUrl, 'type' => 'text', 'copy' => true],
                    ['label' => 'S3 API Endpoint', 'value' => $minioApiUrl, 'type' => 'text', 'copy' => true],
                    ['label' => 'Internal IP', 'value' => $internalIp, 'type' => 'text', 'copy' => true],
                    ['label' => 'SSH Command', 'value' => $sshCommand, 'type' => 'text', 'copy' => true, 'mono' => true],
                    ['label' => 'su Password', 'value' => $suPassword, 'type' => 'password', 'copy' => true]
                ];
                break;

            case 'n8n':
                $n8nUser = $creds['n8n_username'] ?? $currentUser;
                $n8nPass = $creds['n8n_password'] ?? $password;
                $n8nUrl = !empty($creds['n8n_url'])
                    ? LabUrl::withScheme((string)$creds['n8n_url'])
                    : LabUrl::url(LabUrl::serviceHost($labData, 'n8n-'));
                
                $fields = [
                    ['label' => 'n8n Username', 'value' => $n8nUser, 'type' => 'text', 'copy' => true],
                    ['label' => 'n8n Password', 'value' => $n8nPass, 'type' => 'password', 'copy' => true],
                    ['label' => 'Public URL', 'value' => $n8nUrl, 'type' => 'text', 'copy' => true],
                    ['label' => 'Device IP', 'value' => $tunnelIp, 'type' => 'text', 'copy' => true],
                    ['label' => 'SSH Command', 'value' => $sshCommand, 'type' => 'text', 'copy' => true, 'mono' => true],
                    ['label' => 'su Password', 'value' => $suPassword, 'type' => 'password', 'copy' => true]
                ];
                break;

            case 'gui_essentials':
                $codeServerUrl = LabUrl::ideUrl($labData);
                $guiUrl = LabUrl::guiUrl($labData);
                $vncPass = $creds['vnc_pass'] ?? $password;
                $fields = [
                    ['label' => 'Device IP', 'value' => $tunnelIp, 'type' => 'text', 'copy' => true],
                    ['label' => 'SSH Command', 'value' => $sshCommand, 'type' => 'text', 'copy' => true, 'mono' => true],
                    ['label' => 'Username', 'value' => $currentUser, 'type' => 'text', 'copy' => true],
                    ['label' => 'su Password', 'value' => $suPassword, 'type' => 'password', 'copy' => true],
                    ['label' => 'GUI URL', 'value' => $guiUrl, 'type' => 'text', 'copy' => true],
                    ['label' => 'VNC Password', 'value' => $vncPass, 'type' => 'password', 'copy' => true],
                    ['label' => 'Code-Server URL', 'value' => $codeServerUrl, 'type' => 'text', 'copy' => true],
                    ['label' => 'Code-Server Password', 'value' => $codeServerPass, 'type' => 'password', 'copy' => true],
                ];
                break;

            case 'docker_lab':
            case 'essentials':
            default:
                $codeServerUrl = LabUrl::ideUrl($labData);
                $fields = [
                    ['label' => 'Device IP', 'value' => $tunnelIp, 'type' => 'text', 'copy' => true],
                    ['label' => 'SSH Command', 'value' => $sshCommand, 'type' => 'text', 'copy' => true, 'mono' => true],
                    ['label' => 'Username', 'value' => $currentUser, 'type' => 'text', 'copy' => true],
                    ['label' => 'su Password', 'value' => $suPassword, 'type' => 'password', 'copy' => true],
                    ['label' => 'Code-Server URL', 'value' => $codeServerUrl, 'type' => 'text', 'copy' => true],
                    ['label' => 'Code-Server Password', 'value' => $codeServerPass, 'type' => 'password', 'copy' => true],
                ];
                break;
        }

        return [
            'type' => $labType,
            'title' => ucfirst($labType) . " Connection Details",
            'fields' => $fields
        ];
    }

    /**
     * Check if a lab type supports a specific feature.
     * Delegates to LabFeatures — edit LabFeatures.php to change per-lab support.
     */
    public static function supportsFeature(string $labType, string $feature): bool {
        return LabFeatures::supports($labType, $feature);
    }
}
