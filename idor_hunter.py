import json
import logging
import os
import requests
from mitmproxy import http, ctx

class IDORHunterAgent:
    def __init__(self):
        # Target parameter names that usually indicate an ID
        self.target_params = ["user_id", "id", "account_id", "doc_id", "profile_id", "org_id", "uuid"]
        
        # Configuration for LLM API (Using Gemini API as an example)
        self.llm_api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent"
        self.api_key = os.environ.get("GEMINI_API_KEY", "")
        
        # In a real-world scenario, you configure the attacker's session token
        # This is User B's cookie, testing access to User A's data
        self.attacker_cookie = os.environ.get("ATTACKER_COOKIE", "session=attacker_token")

    def request(self, flow: http.HTTPFlow):
        # Keep traffic clean: ignore static files
        if flow.request.path.endswith(('.png', '.css', '.js', '.jpg', '.svg', '.ico', '.woff2')):
            return

        # Optional: Only analyze domains in scope
        # if "targetdomain.com" not in flow.request.pretty_host:
        #     return

        # 1. Search for common ID patterns
        suspicious_ids = {}
        
        # Check query parameters
        for param in self.target_params:
            if param in flow.request.query:
                suspicious_ids[param] = flow.request.query[param]
            # Check form data
            elif flow.request.urlencoded_form and param in flow.request.urlencoded_form:
                suspicious_ids[param] = flow.request.urlencoded_form[param]

        # Check JSON body
        if flow.request.headers.get("Content-Type", "").startswith("application/json"):
            try:
                body = json.loads(flow.request.content)
                for param in self.target_params:
                    if param in body:
                        suspicious_ids[param] = body[param]
            except Exception:
                pass

        # 2. If IDs are found, trigger the LLM to analyze
        if suspicious_ids:
            ctx.log.info(f"[!] IDOR target found at {flow.request.url} with IDs: {suspicious_ids}")
            self.analyze_with_llm(flow, suspicious_ids)

    def analyze_with_llm(self, flow: http.HTTPFlow, suspicious_ids: dict):
        if not self.api_key:
            ctx.log.warn("No GEMINI_API_KEY set. Skipping LLM analysis.")
            return

        prompt = f"""
        Here is an HTTP request intercepted by an agent:
        URL: {flow.request.url}
        Method: {flow.request.method}
        Suspicious IDs found: {json.dumps(suspicious_ids)}
        
        Suggest 3 modified IDs to test for IDOR vulnerabilities based on these IDs. 
        Respond strictly in JSON format as a list of dictionaries, for example:
        [
            {{"param": "user_id", "test_value": "104", "reason": "sequential decrement"}},
            {{"param": "user_id", "test_value": "0", "reason": "boundary value"}},
            {{"param": "user_id", "test_value": "999999", "reason": "high out of bounds"}}
        ]
        """
        
        try:
            # Send the request to the LLM "Brain"
            response = requests.post(
                f"{self.llm_api_url}?key={self.api_key}",
                headers={"Content-Type": "application/json"},
                json={
                    "contents": [{"parts": [{"text": prompt}]}]
                }
            )
            
            if response.status_code == 200:
                data = response.json()
                text_response = data['candidates'][0]['content']['parts'][0]['text']
                # Clean up markdown JSON block if present
                clean_json = text_response.replace('```json', '').replace('```', '').strip()
                test_cases = json.loads(clean_json)
                
                ctx.log.info(f"[⚡] LLM suggested {len(test_cases)} test cases.")
                self.execute_tests(flow, test_cases)
            else:
                ctx.log.error(f"LLM API error (Status {response.status_code}): {response.text}")
                
        except Exception as e:
            ctx.log.error(f"Error calling LLM: {str(e)}")

    def execute_tests(self, flow: http.HTTPFlow, test_cases: list):
        # 3. The Replay Logic (The "Attack")
        # Ensure we execute requests out-of-band using Python `requests` so we don't block the proxy
        
        headers = dict(flow.request.headers)
        # Apply attacker session to check if they can access the victim's ID
        headers["Cookie"] = self.attacker_cookie
        
        for case in test_cases:
            param = case.get("param")
            test_value = case.get("test_value")
            
            ctx.log.info(f"[⚔️] Testing IDOR: Changing '{param}' to '{test_value}' ({case.get('reason')})")
            
            # Re-construct out-of-band request
            if param in flow.request.query:
                new_query = flow.request.query.copy()
                new_query[param] = str(test_value)
                test_url = flow.request.url.split('?')[0]
                
                try:
                    res = requests.request(
                        method=flow.request.method,
                        url=test_url,
                        params=dict(new_query),
                        headers=headers,
                        verify=False, # Ignore cert errors for local testing
                        timeout=5
                    )
                    
                    self.evaluate_response(res, flow.request.url, test_value)
                except Exception as e:
                    ctx.log.error(f"Failed to replay request: {e}")

    def evaluate_response(self, response, original_url, test_value):
        # 4. Analyze Attack Result
        # If the attacker successfully gets a 200 OK and valid data, it might indicate IDOR
        if response.status_code in [200, 201]:
            # In a full-scale agent, you'd use the LLM again here to verify if the response 
            # actually contains sensitive user data, or if it's just a generic success message.
            ctx.log.info(f"[🔥 P1 CONFIRMED?] Potential IDOR success on {original_url} with ID '{test_value}' (Status: {response.status_code})")
            
            with open("idor_findings.log", "a") as f:
                f.write(f"VULNERABLE: {original_url} | ID: {test_value} | Length: {len(response.text)}\n")
        elif response.status_code in [401, 403]:
            ctx.log.info(f"[🛡️] Server protected (Status: {response.status_code})")
        else:
            ctx.log.info(f"[ℹ️] Server responded with {response.status_code}")

addons = [
    IDORHunterAgent()
]
