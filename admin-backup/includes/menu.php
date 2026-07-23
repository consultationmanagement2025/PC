<?php

function getAdminMenuItems(): array
{
    return [
        'public' => [
            'label' => 'PUBLIC CONSULTATION',
            'items' => [
                [
                    'id' => 'consultation-dashboard',
                    'label' => 'Consultation Dashboard',
                    'icon' => 'fa-users',
                    'url' => 'consultation-dashboard.php',
                ],
                [
                    'id' => 'consultation-management',
                    'label' => 'Consultation Management',
                    'icon' => 'fa-clipboard-list',
                    'url' => 'consultation-management.php',
                ],
                [
                    'id' => 'public-feedback-queue',
                    'label' => 'Public Feedback Queue',
                    'icon' => 'fa-comment-dots',
                    'url' => 'public-feedback-queue.php',
                ],
                [
                    'id' => 'document-management',
                    'label' => 'Document Management',
                    'icon' => 'fa-folder',
                    'url' => 'document-management.php',
                ],
            ],
        ],
        'administration' => [
            'label' => 'ADMINISTRATION',
            'items' => [
                [
                    'id' => 'user-management',
                    'label' => 'User Management',
                    'icon' => 'fa-user-group',
                    'url' => 'user-management.php',
                ],
                [
                    'id' => 'audit-log',
                    'label' => 'Audit Log',
                    'icon' => 'fa-shield-halved',
                    'url' => 'audit-log.php',
                ],
            ],
        ],
    ];
}
