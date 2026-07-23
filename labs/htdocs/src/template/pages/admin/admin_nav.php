<?php
$currentUri = $_SERVER['REQUEST_URI'];

$tabs = [
    'users' => [
        'label' => 'Users',
        'url' => '/admin/users',
        'icon' => 'bx-group',
        'matcher' => '/admin/users'
    ],
    'api' => [
        'label' => 'API & Features',
        'url' => '/admin/api',
        'icon' => 'bx-slider-alt',
        'matcher' => '/admin/api'
    ],
    'server-monitor' => [
        'label' => 'Server Monitor',
        'url' => '/admin/server-monitor',
        'icon' => 'bx-desktop',
        'matcher' => '/admin/server-monitor'
    ]
];
?>

<ul class="nav lab-nav-tabs border-0 mb-0">
    <?php foreach($tabs as $key => $tab): 
        $isActive = (strpos($currentUri, $tab['matcher']) !== false);
    ?>
    <li class="nav-item">
        <a class="nav-link <?= $isActive ? 'active' : '' ?>" 
           href="<?= $tab['url'] ?>">
            <i class='bx <?= $tab['icon'] ?> me-1'></i> <?= $tab['label'] ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
