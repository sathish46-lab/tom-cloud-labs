<?php
/**
 * League of Ronin — the zeal rank ladder.
 * See src/utils/leagues.php for the ladder maths.
 */
require_once __DIR__ . '/../../utils/leagues.php';

$lgUser  = Session::getUser();
$lgStats = $lgUser ? \TomLabs\Labs\Quiz::getUserStats($lgUser->getEmail()) : ['zeal' => 0, 'jolt' => 0];
$lgZeal  = (int)($lgStats['zeal'] ?? 0);

$lgCurrent = league_for_zeal($lgZeal);
$lgLadder  = leagues_ladder($lgZeal);
$lgNext    = $lgCurrent['next_level'];
?>
<div class="container p-1 py-3">
    <div class="card blur mb-3">
        <div class="card-header p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="fs-4">
                    <strong>League of Ronin</strong>
                    <span class="small ms-1 text-medium-emphasis">Zeal Ranking System 🔥📈🚀</span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="small text-medium-emphasis" title="Total Zeal (Experience Points)">
                        <i class="bx bxs-hot text-danger"></i>
                        <span class="fw-bold text-body-emphasis" id="league-zeal"><?= number_format($lgZeal) ?></span>
                    </span>
                    <span class="small text-medium-emphasis" title="Available Jolt (Fuel)">
                        <i class="bx bxs-zap text-warning"></i>
                        <span class="fw-bold text-body-emphasis"><?= number_format((int)($lgStats['jolt'] ?? 0)) ?></span>
                    </span>
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Current standing -->
            <div class="row g-2 mb-4 text-center" id="league-summary">
                <div class="col-6 col-lg-3">
                    <div class="card achievement simple-blur h-100">
                        <div class="card-body py-3">
                            <div class="small text-medium-emphasis text-uppercase">Current Title</div>
                            <div class="fs-6 fw-bold mb-0"><?= htmlspecialchars($lgCurrent['title']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card achievement simple-blur h-100">
                        <div class="card-body py-3">
                            <div class="small text-medium-emphasis text-uppercase">Highest Level</div>
                            <div class="fs-6 fw-bold mb-0">
                                <?= $lgCurrent['reached'] ? $lgCurrent['level'] : '—' ?>
                                <span class="fw-normal text-medium-emphasis small">/ <?= LEAGUE_LEVEL_COUNT ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card achievement simple-blur h-100">
                        <div class="card-body py-3">
                            <div class="small text-medium-emphasis text-uppercase">League</div>
                            <div class="fs-6 fw-bold mb-0">
                                League 🏆 <?= $lgCurrent['league_roman'] ?>
                                <span class="fw-normal text-medium-emphasis small"><?= htmlspecialchars($lgCurrent['league_name']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card achievement simple-blur h-100">
                        <div class="card-body py-3">
                            <div class="small text-medium-emphasis text-uppercase">Next Level</div>
                            <div class="fs-6 fw-bold mb-0">
                                <?php if ($lgCurrent['is_max']): ?>
                                    Maxed 🎉
                                <?php else: ?>
                                    Level <?= $lgNext ?> · <?= $lgCurrent['progress'] ?>%
                                <?php endif; ?>
                            </div>
                            <?php if (!$lgCurrent['is_max']): ?>
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated"
                                     style="width: <?= (int)$lgCurrent['progress'] ?>%;"
                                     role="progressbar"
                                     aria-valuenow="<?= (int)$lgCurrent['progress'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 25 leagues x 4 levels -->
            <div class="accordion accordion-flush" id="leagueFlush">
                <?php foreach ($lgLadder as $lg): ?>
                <?php
                    $lgHash   = md5('league-' . $lg['index']);
                    $lgOpen   = $lg['is_current'];
                    $lgOpenId = 'flushc-' . $lgHash;
                ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="flushh-<?= $lgHash ?>">
                        <button class="accordion-button <?= $lgOpen ? '' : 'collapsed' ?>" type="button"
                                data-coreui-toggle="collapse" data-coreui-target="#<?= $lgOpenId ?>"
                                aria-expanded="<?= $lgOpen ? 'true' : 'false' ?>" aria-controls="<?= $lgOpenId ?>">
                            <div class="avatars-stack me-3">
                                <div class="avatar">
                                    <img class="avatar-img" src="<?= league_avatar_url($lg['from']) ?>"
                                         alt="League <?= $lg['roman'] ?>" title="<?= htmlspecialchars($lg['name']) ?>">
                                </div>
                            </div>
                            <div>
                                <strong>League 🏆 <?= $lg['roman'] ?></strong> &nbsp; <?= htmlspecialchars($lg['name']) ?>
                                <span class="small text-medium-emphasis ms-1">(From Level <?= $lg['from'] ?> to <?= $lg['to'] ?>)</span>
                            </div>
                            <?php if ($lg['is_current']): ?>
                            <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger ms-auto me-2">You are here</span>
                            <?php endif; ?>
                        </button>
                    </h2>
                    <div id="<?= $lgOpenId ?>" class="accordion-collapse collapse <?= $lgOpen ? 'show' : '' ?>"
                         data-coreui-parent="#leagueFlush">
                        <div class="accordion-body">
                            <div class="row">
                                <?php foreach ($lg['levels'] as $lv): ?>
                                <div class="col-sm-auto col-md-3 col-lg-3 mb-2">
                                    <div class="card achievement simple-blur h-100 <?= 'league-state-' . $lv['status'] ?>">
                                        <div class="card-body text-center">
                                            <img class="modal-bg-image" src="<?= htmlspecialchars($lv['image']) ?>"
                                                 alt="<?= htmlspecialchars($lv['title']) ?>" loading="lazy">
                                            <div class="fs-4 py-3 mb-0 fw-semibold"><?= htmlspecialchars($lv['title']) ?></div>
                                            <div class="fs-5 mb-0">Level <?= $lv['level'] ?></div>
                                            <div class="fs-6 text-medium-emphasis">League <?= $lg['roman'] ?></div>
                                            <div class="py-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="float-start fw-semibold"><?= $lv['percent'] ?>%</span>
                                                    <span class="float-end text-medium-emphasis small"><?= htmlspecialchars($lv['text']) ?></span>
                                                </div>
                                                <div class="progress mb-4" style="height: 8px;">
                                                    <div class="progress-bar <?= $lv['status'] === 'progress' ? 'progress-bar-striped progress-bar-animated' : ($lv['status'] === 'completed' ? 'bg-success' : 'bg-secondary') ?>"
                                                         role="progressbar"
                                                         style="width: <?= $lv['percent'] ?>%;"
                                                         aria-valuenow="<?= $lv['percent'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <div class="small text-medium-emphasis mb-1">Requires</div>
                                                <span class="reward zeal-reward fs-4"
                                                      title="Earn <?= number_format($lv['required']) ?> zeal 🔥 to cross this level"><?= number_format($lv['required']) ?> 🔥</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
