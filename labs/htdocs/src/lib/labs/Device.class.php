<?php
namespace TomLabs\Labs;

class Device {
    private $templateId;
    private $data;

    public function __construct($templateId) {
        $this->templateId = $templateId;
        // SOURCE OF TRUTH: Always read from the template directory
        $path = "/opt/labs-control-panel/lab-templates/$templateId/config.json";
        $this->data = json_decode(file_get_contents($path), true);
    }

    public function getResources() {
        // No hardcoding! Just return what the JSON says.
        return $this->data['resources'] ?? [];
    }

    public function getMetadata() {
        return [
            'name' => $this->data['lab_name'],
            'version' => $this->data['version']
        ];
    }
}