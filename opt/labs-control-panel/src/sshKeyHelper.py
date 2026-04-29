import os

class sshKeyHelper:
    def __init__(self):
        # Path where you might store public keys locally, or fetch from DB
        self.keys_dir = "/etc/labs-control-panel/storage/keys"
        os.makedirs(self.keys_dir, exist_ok=True)

    def get_key_by_email(self, email):
        # For now, let's assume keys are named 'email.pub'
        key_path = f"{self.keys_dir}/{email}.pub"
        if os.path.exists(key_path):
            with open(key_path, 'r') as f:
                return f.read().strip()
        
        # Fallback or logic to fetch from your Gitlab/Database
        print(f"[!] Warning: No SSH key found for {email}")
        return ""