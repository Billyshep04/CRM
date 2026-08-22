<?php
return [
    'provisioning_mode'=>env('HOSTING_PROVISIONING_MODE','mock'), 'allow_live_provisioning'=>(bool)env('ALLOW_HOSTING_PROVISIONING',false),
    'termination_mode'=>env('HOSTING_TERMINATION_MODE','mock'), 'allow_live_termination'=>(bool)env('ALLOW_HOSTING_TERMINATION',false),
    'wordpress_admin_username'=>env('HOSTING_WORDPRESS_ADMIN_USERNAME','webstamp_admin'), 'wordpress_admin_email'=>env('HOSTING_WORDPRESS_ADMIN_EMAIL'),
    'ssh'=>['port'=>(int)env('HOSTING_SSH_PORT',722),'host_fingerprint'=>env('HOSTING_SSH_HOST_FINGERPRINT')],
    'dns_retry_minutes'=>(int)env('HOSTING_DNS_RETRY_MINUTES',10),
    'ssl_retry_minutes'=>(int)env('HOSTING_SSL_RETRY_MINUTES',10),
    'health'=>['disk_warning_percent'=>(int)env('HOSTING_DISK_WARNING_PERCENT',80),'disk_critical_percent'=>(int)env('HOSTING_DISK_CRITICAL_PERCENT',92),'backup_warning_hours'=>(int)env('HOSTING_BACKUP_WARNING_HOURS',48)],
];
