import os
import json

class LabTemplate:
    def __init__(self, name, global_config):
        self.name = name
        # Use the global config to find where templates are stored
        self.templates_dir = global_config.get('templates_dir', '/opt/labs-control-panel/lab-templates')
        self.path = os.path.join(self.templates_dir, name)
        self.config = self._load_template_config()

    def _load_template_config(self):
        """Loads the specific config.json for the chosen lab template."""
        config_file = os.path.join(self.path, 'config.json')
        if os.path.exists(config_file):
            with open(config_file, 'r') as f:
                return json.load(f)
        return {}

    def exists(self):
        """Checks if the template folder actually exists on the VPS."""
        return os.path.isdir(self.path)

    def get_config(self, key, default=None):
        return self.config.get(key, default)