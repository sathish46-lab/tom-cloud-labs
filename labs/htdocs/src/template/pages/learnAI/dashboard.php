<?php
$db = DatabaseConnection::getDefaultDatabase();
$lessons = $db->ai_lessons->find()->toArray();
$user = Session::getUser();
?>

<div class="learn-app-wrapper pb-5">
    <!-- Main Dashboard Area - Natural Scroll -->
    <div class="p-2 p-lg-3">
        <div class="text-center mb-5 mt-4">
            <h1 class="display-6 fw-bold mb-3">Tell us what you'd like to Learn!</h1>
            
            <div class="mx-auto px-2" style="max-width: 800px;">
                <div class="mb-3 d-flex flex-wrap justify-content-center gap-2">
                    <span class="badge rounded-pill bg-secondary text-white px-3 py-2">Node.js Backend</span>
                    <span class="badge rounded-pill bg-secondary text-white px-3 py-2">Web Engineering Fundamentals</span>
                    <span class="badge rounded-pill bg-secondary text-white px-3 py-2">Docker Containerization</span>
                </div>
                
                <div class="card bg-dark border-secondary border-opacity-10 rounded-4 shadow-lg overflow-hidden">
                    <div class="card-body p-0">
                        <textarea class="form-control bg-transparent text-white border-0 p-3" 
                                  placeholder="Search lessons or describe a topic..." 
                                  rows="2" style="resize: none; font-size: 1rem;"></textarea>
                        <div class="d-flex align-items-center justify-content-end p-2 bg-dark bg-opacity-25 border-top border-secondary border-opacity-10">
                            <select class="form-select form-select-sm bg-dark text-white border-secondary border-opacity-25 rounded-pill me-2" style="width: auto; font-size: 0.75rem;">
                                <option>Beginner</option>
                                <option>Intermediate</option>
                                <option>Advanced</option>
                            </select>
                            <button class="btn btn-primary btn-sm rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;">
                                 <i class="bx bx-send"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid mt-5">
            <div class="d-flex flex-column flex-md-row align-items-md-center mb-4 gap-3">
                <div class="d-flex gap-2 align-items-center overflow-auto no-scrollbar pb-1">
                    <span class="badge bg-dark border border-secondary border-opacity-10 text-white px-3 py-2 rounded-pill whitespace-nowrap" style="font-size: 0.7rem;">
                        <i class="bx bxs-book-open text-primary me-1"></i> <?= count($lessons) ?> Lessons
                    </span>
                </div>
                <div class="ms-md-auto d-flex gap-2">
                    <button class="btn btn-xs btn-outline-secondary rounded-pill active border-opacity-25 px-3">Continue</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-10 px-3">Explore</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-10 px-3">My Lessons</button>
                </div>
            </div>

            <h4 class="fw-bold mb-4 px-1">Learning Paths</h4>

            <div class="row g-3 mb-5">
                <?php foreach ($lessons as $lesson): ?>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                    <div class="card h-100 bg-dark bg-opacity-50 border-secondary border-opacity-10 rounded-4 shadow-sm overflow-hidden transition-all-lite">
                        <div class="card-body d-flex flex-column p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 rounded-pill" style="font-size: 0.6rem;">
                                    <i class="bx bxs-star me-1"></i><?= $lesson['level'] ?>
                                </span>
                                <div class="dropdown">
                                    <button class="btn btn-link text-secondary p-0" data-coreui-toggle="dropdown">
                                        <i class="bx bx-dots-vertical-rounded" style="font-size: 0.9rem;"></i>
                                    </button>
                                </div>
                            </div>

                            <h6 class="card-title fw-bold mb-2 text-white"><?= $lesson['title'] ?></h6>
                            <p class="card-text text-secondary mb-3 flex-grow-1" style="font-size: 0.75rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= $lesson['description'] ?></p>

                            <div class="d-flex gap-2 text-secondary mb-3" style="font-size: 0.65rem;">
                                <span><i class="bx bx-book me-1"></i><?= $lesson['modules_count'] ?> Mod</span>
                                <span><i class="bx bx-layer me-1"></i><?= $lesson['chapters_count'] ?> Chap</span>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-end mb-1">
                                    <span class="text-secondary" style="font-size: 0.65rem;">Progress</span>
                                    <span class="fw-bold text-white" style="font-size: 0.65rem;"><?= $lesson['progress'] ?>%</span>
                                </div>
                                <div class="progress bg-secondary bg-opacity-10 rounded-pill" style="height: 3px;">
                                    <div class="progress-bar bg-success rounded-pill" style="width: <?= $lesson['progress'] ?>%"></div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center pt-2 border-top border-secondary border-opacity-10 mt-1">
                                <div class="d-flex align-items-center overflow-hidden">
                                    <img src="<?= Session::getAvatar() ?>" alt="Author" class="rounded-circle me-2 border border-secondary border-opacity-25" width="18" height="18">
                                    <span class="text-secondary text-truncate" style="font-size: 0.65rem;"><?= $lesson['author'] ?></span>
                                </div>
                                <div class="ms-auto">
                                    <a href="/learn/lesson/<?= $lesson['_id'] ?>" class="btn btn-xs btn-primary rounded-pill px-3 shadow-sm" style="font-size: 0.65rem;">Continue</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* App Layout Overrides */
.learn-app-wrapper { background: transparent; }
.no-scrollbar::-webkit-scrollbar { display: none; }
.whitespace-nowrap { white-space: nowrap; }
.transition-all-lite { transition: all 0.2s ease; }


/* Extra small button */
.btn-xs { 
    padding: 0.2rem 0.6rem;
    font-size: 0.65rem;
    line-height: 1.2;
}

@media (max-width: 768px) {
    .display-6 { font-size: 1.5rem !important; }
}
</style>
