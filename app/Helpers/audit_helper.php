<?php

if (! function_exists('audit_label')) {
    /**
     * Devuelve una etiqueta corta (minúsculas) para mostrar la acción de auditoría en un badge.
     */
    function audit_label(string $action): string
    {
        $action = strtolower(trim($action));

        static $map = [
            'login_success'                 => 'login ok',
            'login_failed'                  => 'login fail',
            'login_blocked'                 => 'login block',
            'login_2fa_challenge'           => '2fa reto',
            'login_2fa_success'             => '2fa ok',
            'login_2fa_failed'              => '2fa fail',
            'login_2fa_blocked'             => '2fa block',
            'logout'                        => 'logout',
            '2fa_enabled'                   => '2fa on',
            '2fa_disabled'                  => '2fa off',
            'admin_general_update'          => 'ajustes',
            'settings_general_update'       => 'ajustes',
            'admin_smtp_update'             => 'smtp edit',
            'settings_smtp_update'          => 'smtp edit',
            'admin_smtp_test'               => 'smtp test',
            'settings_smtp_test'            => 'smtp test',
            'admin_backup_retention_update' => 'retención',
            'backup_retention_update'       => 'retención',
            'backup_create'                 => 'backup new',
            'backup_create_failed'          => 'backup fail',
            'backup_restore'                => 'backup rest',
            'backup_restore_failed'         => 'restore fail',
            'backup_delete'                 => 'backup del',
            'backup_download'               => 'backup dl',
            'proxy_create'                  => 'proxy new',
            'proxy_create_failed'           => 'proxy fail',
            'proxy_update'                  => 'proxy edit',
            'proxy_update_failed'           => 'proxy fail',
            'proxy_delete'                  => 'proxy del',
            'proxy_toggle'                  => 'proxy toggle',
            'proxy_toggle_failed'           => 'toggle fail',
            'ssl_issue_success'             => 'ssl ok',
            'ssl_issue_failed'              => 'ssl fail',
            'ssl_renew_success'             => 'renew ok',
            'ssl_renew_failed'              => 'renew fail',
            'ssl_delete'                    => 'ssl del',
            'ip_banned'                     => 'ip ban',
            'ip_auto_banned'                => 'auto ban',
            'ip_unbanned'                   => 'ip unban',
            'maintenance_sessions_clear'    => 'sesiones cln',
            'maintenance_cache_clear'       => 'caché cln',
            'maintenance_logs_clear'        => 'logs cln',
            'maintenance_audit_clear'       => 'audit cln',
            'maintenance_db_optimize'       => 'db optim',
            'maintenance_full'              => 'mantenim.',
        ];

        if (isset($map[$action])) {
            return $map[$action];
        }

        $label = str_replace('_', ' ', preg_replace('/^(admin|settings)_/', '', $action));

        return mb_strlen($label) > 12 ? mb_substr($label, 0, 11) . '…' : $label;
    }
}

if (! function_exists('audit_badge_class')) {
    function audit_badge_class(string $action): string
    {
        $act = strtolower($action);
        if (str_contains($act, 'failed') || str_contains($act, 'blocked') || str_contains($act, 'delete') || str_contains($act, 'banned') && !str_contains($act, 'unbanned')) {
            return 'bg-danger-subtle text-danger border-danger';
        }
        if (str_contains($act, 'create') || str_contains($act, 'success') || str_contains($act, 'enable') || str_contains($act, 'unbanned') || str_contains($act, 'optimize')) {
            return 'bg-success-subtle text-success border-success';
        }
        if (str_contains($act, 'update') || str_contains($act, 'toggle') || str_contains($act, 'renew')
            || str_contains($act, 'test') || str_contains($act, 'download') || str_contains($act, 'challenge')
            || str_contains($act, 'restore') || str_contains($act, 'disabled') || str_contains($act, 'clear')
            || str_contains($act, 'maintenance')) {
            return 'bg-warning-subtle text-warning border-warning';
        }
        return 'bg-secondary-subtle text-secondary border-secondary';
    }
}
