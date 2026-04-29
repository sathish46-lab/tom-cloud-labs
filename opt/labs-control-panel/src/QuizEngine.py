import os
import json
import time
import uuid
import secrets
import google.generativeai as genai
from pymongo import MongoClient

class QuizEngine:
    def __init__(self, db):
        self.db = db
        self.config = self._load_env_config()
        self.api_key = self.config.get('ai_api_key')
        
        if self.api_key:
            genai.configure(api_key=self.api_key)
            # Use the state-of-the-art gemini-2.5-flash-lite model for high-speed generation
            try:
                self.model = genai.GenerativeModel('gemini-2.5-flash-lite')
            except Exception:
                self.model = None
        else:
            self.model = None

    def _load_env_config(self):
        env_path = '/var/www/env.json'
        if os.path.exists(env_path):
            with open(env_path, 'r') as f:
                return json.load(f)
        return {}

    def generate_quiz(self, topic_id, subtopic_id, difficulty="normal", job_id=None):
        """Generate a quiz using AI and persist to MongoDB"""
        
        if not self.model:
            return {"error": "AI Engine not configured. Missing api_key."}

        # 1. Fetch Context from Dual JSON (Source of Truth)
        cats_path = '/var/www/labs/htdocs/src/data/quiz_categories.json'
        subs_path = '/var/www/labs/htdocs/src/data/quiz_subtopics.json'
        topic = None
        subtopic = None
        
        if os.path.exists(cats_path):
            with open(cats_path, 'r') as f:
                categories = json.load(f)
                for cat in categories:
                    if cat.get('id') == topic_id:
                        topic = cat
                        break
        
        if topic and os.path.exists(subs_path):
            with open(subs_path, 'r') as f:
                subtopics = json.load(f)
                for sub in subtopics:
                    if sub.get('id') == subtopic_id and sub.get('category_id') == topic_id:
                        subtopic = sub
                        break
        
        if not topic or not subtopic:
            return {"error": "Invalid topic or subtopic ID from split JSON source."}

        self._update_job(job_id, 10, "Context verified. Initializing AI...")

        # 2. Construct Prompt
        prompt = f"""
        Act as a world-class Cybersecurity Education Architect.
        Generate a high-density technical quiz that feels like a professional tournament challenge.
        
        Context:
        - Main Topic: {topic['title']}
        - Subtopic: {subtopic['title']}
        - Focus: {subtopic['desc']}
        - Target Difficulty: {difficulty.upper()}
        
        CRITICAL CONTENT RULES:
        1. UNIQUE TITLE: DO NOT use "{subtopic['title']}" as the starting word. Create a narrative, high-impact title (e.g., "Guarding the Midnight Datastream", "Cracking the Digital Vault", "Sentinels of the Perimeter").
        2. IMMERSIVE DESCRIPTION: DO NOT start with "Test your knowledge" or "This quiz...". Use an immersive, role-play narrative (e.g., "Step into the shoes of a lead SOC analyst...", "The digital sirens are wailing; only your grasp of {subtopic['title']} can silence them.").
        3. DYNAMIC TAGS: Provide 5-8 highly specific technical tags (e.g., "snort", "heuristics", "pcp-inspection") instead of generic ones.
        4. QUESTION QUALITY: Ensure professional, senior-level technical accuracy for the {difficulty} level.
        
        Return ONLY a strictly valid JSON object:
        {{
          "title": "Narrative Catchy Title",
          "desc": "Immersive role-play description (2-3 sentences).",
          "tags": ["tech-tag-1", "tech-tag-2", "tech-tag-3", "tech-tag-4"],
          "questions": [
            {{
              "text": "Technical challenge question?",
              "options": ["Choice 0", "Choice 1", "Choice 2", "Choice 3"],
              "correct": 0,
              "explanation": "Professional breakdown of the correct mechanics."
            }}
          ]
        }}
        """

        self._update_job(job_id, 30, "Prompt engineered. Consulting AI...")

        try:
            # 3. Call AI
            response = self.model.generate_content(prompt)
            raw_text = response.text.strip()
            
            # Clean JSON using a more robust regex method
            import re
            json_match = re.search(r'(\{.*\})', raw_text, re.DOTALL)
            if json_match:
                cleaned_content = json_match.group(1)
            else:
                cleaned_content = raw_text

            quiz_data = json.loads(cleaned_content)
            self._update_job(job_id, 70, "Expert evaluation completed. Finalizing...")

            # 4. Persistence & Hashing
            quiz_hash = secrets.token_hex(16)
            
            quiz_doc = {
                "subtopic_id": subtopic_id,
                "category_id": topic_id,
                "hash": quiz_hash,
                "difficulty": difficulty,
                "title": quiz_data.get('title', f"{subtopic['title']} - {difficulty.capitalize()} Mode"),
                "desc": quiz_data.get('desc', subtopic['desc']),
                "tags": quiz_data.get('tags', [topic['title'].lower(), subtopic.get('id', 'tech')]),
                "questions": quiz_data['questions'],
                "created_at": time.time(),
                "points_per_correct": {"easy": 10, "normal": 25, "hard": 50}.get(difficulty, 25)
            }
            
            # Save the quiz to the database
            self.db.quizzes.insert_one(quiz_doc)
            
            update_data = {
                "percentage": 100,
                "status": "completed",
                "status_text": "Success",
                "available": True,
                "generation_success": True,
                "generation_success_at": time.time(),
                "result_hash": quiz_hash,
                "updated_at": time.time()
            }
            self.db.quiz_jobs.update_one(
                {"_id": job_id},
                {"$set": update_data}
            )

            return {"success": True, "hash": quiz_hash}

        except Exception as e:
            if job_id:
                self.db.quiz_jobs.update_one(
                    {"_id": job_id},
                    {"$set": {
                        "status": "failed",
                        "status_text": f"Error: {str(e)}",
                        "generation_failed": True,
                        "generation_failed_at": time.time(),
                        "updated_at": time.time()
                    }}
                )
            return {"error": str(e)}

    def _update_job(self, job_id, percentage, status="", result_hash=None):
        if not job_id:
            return
        
        update_data = {
            "percentage": percentage,
            "status_text": status,
            "updated_at": time.time()
        }
        if result_hash:
            update_data["result_hash"] = result_hash
        if percentage == -1:
            update_data["status"] = "failed"
        elif percentage == 100:
            update_data["status"] = "completed"
        
        self.db.quiz_jobs.update_one(
            {"_id": job_id},
            {"$set": update_data}
        )
