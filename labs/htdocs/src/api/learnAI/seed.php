<?php
require_once __DIR__ . '/../../load.php';

$db = DatabaseConnection::getDefaultDatabase();

// 1. Clear existing data (optional, but good for testing)
$db->ai_lessons->drop();
$db->ai_chapters->drop();

echo "Seeding Learn AI data...\n";

// 2. Add Lessons
$lessons = [
    [
        '_id' => new MongoDB\BSON\ObjectId('635c6ee2ccf24bc9b5d3614f'),
        'title' => 'Designing and Managing AI Learning Assistant (Intermediate)',
        'description' => 'This course guides learners through designing a learning AI assistant for a \'Learn AI\' application. It covers database design, context memory, agent architecture, and integration techniques.',
        'level' => 'Intermediate',
        'visibility' => 'Private',
        'modules_count' => 4,
        'chapters_count' => 20,
        'tags' => ['ai-assistant', 'database-design', 'context-memory', '+4'],
        'thumbnail' => '/assets/images/learn/ai-assistant.png',
        'progress' => 35,
        'author' => 'sathish46'
    ],
    [
        '_id' => new MongoDB\BSON\ObjectId('635c6ee2ccf24bc9b5d36150'),
        'title' => 'WebSockets, STOMP, Message Queues, and Real-Time Streaming for AI Projects',
        'description' => 'This intermediate-level course covers WebSocket connections, STOMP protocol, message queues, publish/subscribe architecture, and real-time data streaming fundamentals.',
        'level' => 'Intermediate',
        'visibility' => 'Public',
        'modules_count' => 3,
        'chapters_count' => 15,
        'tags' => ['websocket', 'stomp', 'message-queues', '+5'],
        'thumbnail' => '/assets/images/learn/websockets.png',
        'progress' => 20,
        'author' => 'sathish46'
    ],
    [
        '_id' => new MongoDB\BSON\ObjectId('635c6ee2ccf24bc9b5d36151'),
        'title' => 'Code Obfuscation Techniques for Beginners',
        'description' => 'An introductory course on understanding code obfuscation techniques used for protecting source code, preventing reverse engineering, and securing applications.',
        'level' => 'Beginner',
        'visibility' => 'Public',
        'modules_count' => 4,
        'chapters_count' => 20,
        'tags' => ['code-obfuscation', 'reverse-engineering', 'source-code-protection', '+4'],
        'thumbnail' => '/assets/images/learn/obfuscation.png',
        'progress' => 0,
        'author' => 'sathish46'
    ]
];

foreach ($lessons as $lesson) {
    $db->ai_lessons->insertOne($lesson);
}

// 3. Add Chapters for the first lesson
$chapters = [
    [
        'lesson_id' => new MongoDB\BSON\ObjectId('635c6ee2ccf24bc9b5d3614f'),
        'module_name' => '1. Database Design and Organization',
        'title' => 'Choosing the Right Database Technology',
        'content' => '## Choosing the Right Database Technology
When designing and managing an AI learning assistant for a "Learn AI" application, one of the most critical decisions you\'ll face is choosing the right database technology. The database forms the backbone of your system for storing lessons, chapters, user progress, context memory, and more. Selecting an appropriate database not only affects the ease and speed of development but also impacts performance, scalability, data consistency, and the overall user experience.

For an AI learning assistant, which needs to efficiently organize learning content, track users\' progress, and manage conversational context, the database tech must align well with both structured data (like lesson metadata) and unstructured or semi-structured data (such as chat logs or AI context memory).

This chapter explores the most relevant database technologies—relational, NoSQL, and in-memory—and guides you through choosing the right database for your AI assistant project, considering your requirements regarding data models, query types, performance needs, and scalability.

### Understanding Database Types and Their Role in AI Learning Assistants
At its core, a database is a system for organizing, storing, and retrieving data. Different database types excel at different types of data and workloads. Let\'s examine the main categories relevant for AI learning applications.

#### 1. Relational Databases (RDBMS)
Relational databases like MySQL, PostgreSQL, and MariaDB organize data into tables with rows and columns. They enforce schemas with strict data types and relationships. These databases are excellent for transactions...',
        'order' => 1,
        'status' => 'completed'
    ],
    [
        'lesson_id' => new MongoDB\BSON\ObjectId('635c6ee2ccf24bc9b5d3614f'),
        'module_name' => '1. Database Design and Organization',
        'title' => 'Data Schema for Lessons, Chapters, and Content',
        'content' => '...',
        'order' => 2,
        'status' => 'completed'
    ],
    [
        'lesson_id' => new MongoDB\BSON\ObjectId('635c6ee2ccf24bc9b5d3614f'),
        'module_name' => '1. Database Design and Organization',
        'title' => 'User Profiles and Progress Tracking Model',
        'content' => '...',
        'order' => 3,
        'status' => 'completed'
    ]
];

foreach ($chapters as $chapter) {
    $db->ai_chapters->insertOne($chapter);
}

echo "Done seeding.\n";
