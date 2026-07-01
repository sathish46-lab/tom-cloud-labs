import json
import pymongo
import os

# Connect to MongoDB - Use 127.0.0.1 for strict host resolution
mongo_uri = "mongodb://admin:Tombootroot@127.0.0.1:27018/?authSource=admin"

try:
    client = pymongo.MongoClient(mongo_uri, serverSelectionTimeoutMS=5000)
    client.server_info() # Force connection check
    db = client.tom_labs_db
    print(f"Connected to MongoDB via {mongo_uri}")
except Exception:
    # Fallback for container execution
    mongo_uri = "mongodb://admin:Tombootroot@TomCloudLab_mongodb:27017/?authSource=admin"
    try:
        client = pymongo.MongoClient(mongo_uri, serverSelectionTimeoutMS=5000)
        client.server_info()
        db = client.tom_labs_db
        print(f"Connected to MongoDB via {mongo_uri}")
    except Exception as e:
        print(f"❌ Error: Could not connect to MongoDB: {e}")
        exit(1)

# Load quiz_topics.json
json_path = "/Users/sathish/Development/local_dev_lab/labs/htdocs/src/data/quiz_topics.json"
with open(json_path, 'r') as f:
    data = json.load(f)

# Import Categories and Subtopics
db.quiz_categories.delete_many({})
db.quiz_subtopics.delete_many({})

for section, categories in data.items():
    print(f"Migrating section: {section}...")
    for cat in categories:
        cat_id = cat['id']
        subtopics = cat.get('subtopics', [])
        
        # Store Category
        db.quiz_categories.insert_one({
            "_id": cat_id,
            "section": section,
            "title": cat['title'],
            "desc": cat['desc'],
            "slug": cat['title'].lower().replace(' ', '-')
        })
        
        # Store Subtopics
        for sub in subtopics:
            db.quiz_subtopics.insert_one({
                "_id": sub['id'],
                "category_id": cat_id,
                "title": sub['title'],
                "desc": sub['desc'],
                "slug": sub['title'].lower().replace(' ', '-')
            })

print("✅ Quiz topics and subtopics migrated to MongoDB successfully.")
