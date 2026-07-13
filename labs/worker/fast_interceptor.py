import re
import json
import random
import requests

class FastInterceptor:
    def __init__(self, api_base, api_headers):
        self.api_base = api_base
        self.api_headers = api_headers
        
        self.endpoint_map = {
            "check_lab_status": "connection_info.php",
            "list_running_labs": "list_running.php",
            "get_lab_user_info": "userinfo.php",
            "read_chapter_content": "read_chapters.php",
            "get_lesson_outline": "outline.php",
            "read_student_progress": "read_progress.php",
            "execute_command_in_lab": "exec.php",
            "read_file_content": "read_file.php",
            "detect_tool_versions": "detect_versions.php"
        }

    def match_intents(self, query):
        """
        Splits query by conjunctions and matches each part to a tool.
        Returns a list of (tool_name, tool_args). If any part fails, returns [] (fallback).
        """
        q = query.lower().strip()
        clauses = [c.strip() for c in re.split(r'\b(?:and|then)\b|,', q) if c.strip()]
        matched_tools = []
        
        for clause in clauses:
            matched = False
            
            # 1. Detect versions (Prioritize this over status check!)
            if re.search(r'\b(version|versions)\b', clause):
                version_match = re.search(r'\b([a-zA-Z0-9_.-]+)\s+(?:version|versions)\b', clause)
                if version_match:
                    tool_to_check = version_match.group(1).lower()
                    if tool_to_check in ["tool", "the", "all", "lab", "my", "any"]:
                        matched_tools.append(("detect_tool_versions", {"tools": ["python3", "node", "npm", "docker", "php", "go"]}))
                    elif tool_to_check == "python":
                        matched_tools.append(("detect_tool_versions", {"tools": ["python", "python3"]}))
                    elif tool_to_check == "node" or tool_to_check == "nodejs":
                        matched_tools.append(("detect_tool_versions", {"tools": ["node", "npm"]}))
                    else:
                        matched_tools.append(("detect_tool_versions", {"tools": [tool_to_check]}))
                else:
                    # They just said "check versions"
                    matched_tools.append(("detect_tool_versions", {"tools": ["python3", "node", "npm", "docker", "php", "go"]}))
                matched = True
                continue
            
            # 2. Check lab status
            if re.search(r'\b(check|status|is online|is offline)\b.*\blabs?\b', clause) or clause in ["lab status", "status", "labs status"]:
                matched_tools.append(("check_lab_status", {}))
                matched = True
                continue
                
            # 2. List running labs
            if re.search(r'\b(list|show|what)\b.*\b(running|active)\b.*\blabs?\b', clause):
                matched_tools.append(("list_running_labs", {}))
                matched = True
                continue
                
            # 3. Read file
            file_match = re.search(r'\b(read|show|cat)\b.*\bfile\b\s+([/\w\.-]+)', clause)
            if file_match:
                matched_tools.append(("read_file_content", {"file_path": file_match.group(2)}))
                matched = True
                continue
                
            # 4. Execute command
            cmd_match = re.search(r'\b(run|execute)\b.*\b(command)\b\s+(.*)', clause)
            if not cmd_match:
                cmd_match = re.search(r'^(run|execute)\s+(?!command\b)(.*)', clause)
            if cmd_match:
                cmd = cmd_match.group(2) if 'command' not in cmd_match.group(0) else cmd_match.group(3)
                matched_tools.append(("execute_command_in_lab", {"command": cmd.strip()}))
                matched = True
                continue
                
            # 5. User info
            if re.search(r'\b(who am i|my username|my user)\b', clause):
                matched_tools.append(("get_lab_user_info", {}))
                matched = True
                continue
                
            # 6. Read chapter / outline
            if re.search(r'\b(read|show)\b.*\bchapter\b', clause):
                matched_tools.append(("read_chapter_content", {}))
                matched = True
                continue
            if re.search(r'\b(lesson outline|what is this lesson)\b', clause):
                matched_tools.append(("get_lesson_outline", {}))
                matched = True
                continue
                

                
            if not matched:
                return []
                
        return matched_tools

    def execute_tool(self, tool_name, args, user_id, lesson_id, chapter_id):
        payload = dict(args)
        payload['user_id'] = user_id
        payload['lesson_id'] = lesson_id
        payload['chapter_id'] = chapter_id

        try:
            resp = requests.post(
                f"{self.api_base}/{self.endpoint_map[tool_name]}",
                json=payload,
                headers=self.api_headers,
                timeout=30
            )
            if resp.status_code == 200:
                return resp.json()
            return {"error": f"HTTP {resp.status_code}", "response": resp.text[:200]}
        except Exception as e:
            return {"error": str(e)}

    def generate_response(self, tool_name, result):
        """
        Dynamic Response Engine. Randomly selects a conversational template and injects tool data.
        """
        if isinstance(result, dict) and "error" in result:
            return random.choice([
                f"Oops, I ran into an error trying to do that: {result['error']}",
                f"I tried to execute the tool, but got this error: {result['error']}"
            ])
            
        if tool_name == "check_lab_status":
            status = result.get("status", "").lower()
            name = result.get("name", "Lab")
            ip = result.get("ip", "")
            if status == "running" or status == "online":
                return random.choice([
                    f"Good news! Your **{name}** is up and running. The IP address is `{ip}`.",
                    f"I just checked the system—your **{name}** is active and ready to go at `{ip}`.",
                    f"System Status: **Online**. The **{name}** is responding normally at `{ip}`."
                ])
            else:
                return random.choice([
                    f"It looks like your **{name}** is currently offline or stopped.",
                    f"I checked the connection, but the **{name}** is down right now.",
                    f"The lab is currently offline. You'll need to start it from your dashboard."
                ])
                
        elif tool_name == "execute_command_in_lab":
            output = result.get("output", "")
            return random.choice([
                "Here is the output from your command:\n```\n" + output + "\n```",
                "I executed that for you. The result is:\n```\n" + output + "\n```",
                "Command finished! Here's what it returned:\n```\n" + output + "\n```"
            ])
            
        elif tool_name == "read_file_content":
            content = result.get("content", "")
            return random.choice([
                "I've read the file for you. Here is the content:\n```\n" + content + "\n```",
                "Here's what I found inside that file:\n```\n" + content + "\n```"
            ])
            
        elif tool_name == "get_lab_user_info":
            username = result.get("username", "Unknown")
            return random.choice([
                f"Your active lab username is `{username}`.",
                f"You are currently logged in to the lab as `{username}`."
            ])
            
        elif tool_name == "list_running_labs":
            labs = result.get("running_labs", [])
            if not labs:
                return "You don't have any labs running right now."
            names = ", ".join([l.get("name", "Lab") for l in labs])
            return f"You currently have the following labs running: {names}"
            
        elif tool_name == "detect_tool_versions":
            versions = result.get("versions", {})
            if not versions:
                return "I couldn't detect any tool versions in this lab."
                
            formatted_blocks = []
            for k, v in versions.items():
                val_lower = str(v).lower()
                # Skip showing "not installed" if we checked an alias that IS installed
                if k == "python" and "not installed" in val_lower and versions.get("python3", "not installed") != "not installed":
                    continue
                if k == "python3" and "not installed" in val_lower and versions.get("python", "not installed") != "not installed":
                    continue
                    
                if "not installed" in val_lower or "not allowed" in val_lower:
                    formatted_blocks.append(f"- **{k}**: <span style='color:#ef4444;'>Not installed</span>")
                else:
                    formatted_blocks.append(f"The **{k}** version installed in your lab is:\n\n```text\n{v}\n```\n")
                    
            return "Here are the version details from your lab:\n\n" + "\n".join(formatted_blocks)
            
        # Fallback for other tools
        return "I executed the tool successfully. You can see the detailed output in the tool badge!"
