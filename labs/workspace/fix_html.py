from pymongo import MongoClient
import markdown

MONGO_URI = 'mongodb://admin:Tombootroot@TomCloudLab_mongodb:27017/tom_labs_db?authSource=admin'
client = MongoClient(MONGO_URI)
db = client['tom_labs_db']

chapters = db.ai_chapters.find()
for chapter in chapters:
    if 'content' in chapter and chapter['content']:
        rendered_html = markdown.markdown(
            chapter['content'],
            extensions=['fenced_code', 'codehilite', 'tables', 'nl2br', 'sane_lists']
        )
        db.ai_chapters.update_one(
            {'_id': chapter['_id']},
            {'$set': {'content_html': rendered_html}}
        )
        print(f"Fixed chapter: {chapter.get('title', 'Unknown')}")
print("Done!")
