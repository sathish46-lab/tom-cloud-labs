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
     * @param array $labData The full document from deployed_labs
     * @param string|null $currentUser The current session username
     * @return array Configuration array with 'fields'
     */
    public static function getTemplate(string $labType, array $labData, ?string $currentUser = null): array {
        
        $creds = $labData['credentials'] ?? [];
        $internalIp = $labData['internal_ip'] ?? '0.0.0.0';
        $tunnelIp = $creds['tunnel_ip'] ?? $internalIp;
        $password = $creds['password'] ?? '********';
        
        // Common Values
        $sshCommand = (isset($creds['tunnel_ip'])) ? "ssh " . $currentUser . "@" . $creds['tunnel_ip'] : "#";

        // Base Template
        $fields = [];
        
        switch ($labType) {
            case 'minio':
                $minioAccess = $creds['minio_access_key'] ?? $currentUser;
                $minioSecret = $creds['minio_secret_key'] ?? $password;
                $minioConsolePort = $creds['minio_port_console'] ?? 9001;
                $minioApiPort = $creds['minio_port_api'] ?? 9000;
                
                $hash = $labData['instance_hash'];
                
		// Default to new convention if missing from DB
		// Console = s3-HASH, API = api-HASH
		$minioConsoleUrl = $creds['minio_url_console'] ?? "https://s3-{$hash}.tomweb.shop";
		$minioApiUrl = $creds['minio_url_api'] ?? "https://api-{$hash}.tomweb.shop";

                $fields = [
                    [
                        'label' => 'MinIO Access Key',
                        'value' => $minioAccess,
                        'type'  => 'text',
                        'copy'  => true
                    ],
                    [
                        'label' => 'Minio Secret Key',
                        'value' => $minioSecret,
                        'type'  => 'password',
                        'copy'  => true
                    ],
                    [
                        'label' => 'SSH Command',
                        'value' => $sshCommand,
                        'type'  => 'text',
                        'copy'  => true,
                        'mono'  => true
                    ],
                    [
                        'label' => 'Username',
                        'value' => $currentUser,
                        'type'  => 'text',
                        'copy'  => true
                    ],
                    [
                        'label' => 'su Password',
                        'value' => $password,
                        'type'  => 'password',
                        'copy'  => true
                    ],
                    [
                        'label' => 'IP Address',
                        'value' => $tunnelIp,
                        'type'  => 'text',
                        'copy'  => true
                    ],
                    [
                        'label' => 'Minio Port',
                        'value' => $minioApiPort,
                        'type'  => 'text',
                        'copy'  => true
                    ],
                    [
                        'label' => 'MinIO Console Port',
                        'value' => $minioConsolePort,
                        'type'  => 'text',
                        'copy'  => true
                    ],
                    [
                        'label' => 'MinIO S3 Endpoint',
                        'value' => $minioApiUrl,
                        'type'  => 'text',
                        'copy'  => true
                    ],
                    [
                        'label' => 'MinIO Console Endpoint',
                        'value' => $minioConsoleUrl,
                        'type'  => 'text',
                        'copy'  => true
                    ]
                ];
                break;

            case 'n8n':
                $n8nUser = $creds['n8n_username'] ?? $currentUser;
                $n8nPass = $creds['n8n_password'] ?? $password;
                $n8nUrl = $creds['n8n_url'] ?? "https://n8n-{$labData['instance_hash']}.tomweb.shop";
                
                $fields = [
                    [
                        'label' => 'n8n Username',
                        'value' => $n8nUser,
                        'type'  => 'text',
                        'copy'  => true
                    ],
                    [
                        'label' => 'n8n Password',
                        'value' => $n8nPass,
                        'type'  => 'password',
                        'copy'  => true
                    ],
                    [
                        'label' => 'Public URL',
                        'value' => $n8nUrl,
                        'type'  => 'text',
                        'copy'  => true
                    ],
                    [
                        'label' => 'Device IP',
                        'value' => $tunnelIp,
                        'type'  => 'text',
                        'copy'  => true
                    ],
                    [
                        'label' => 'SSH Command',
                        'value' => $sshCommand,
                        'type'  => 'text',
                        'copy'  => true,
                        'mono'  => true
                    ],
                    [
                        'label' => 'su Password',
                        'value' => $password,
                        'type'  => 'password',
                        'copy'  => true
                    ]
                ];
                break;
            default:
                $fields = [
                    [
                        'label' => 'Device IP',
                        'value' => $tunnelIp,
                        'type'  => 'text',
                        'copy'  => true
                    ],
                    [
                        'label' => 'SSH Command',
                        'value' => $sshCommand,
                        'type'  => 'text',
                        'copy'  => true,
                        'mono'  => true
                    ],
                    [
                        'label' => 'Username',
                        'value' => $currentUser,
                        'type'  => 'text',
                        'copy'  => true
                    ],
                    [
                        'label' => 'su Password',
                        'value' => $password,
                        'type'  => 'password',
                        'copy'  => true
                    ]
                ];
                break;
        }

        return [
            'type' => $labType,
            'fields' => $fields
        ];
    }
}
