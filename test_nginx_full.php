<?php
// Veamos qué pasa en TEST_WRAPPER:
// events { worker_connections 1024; }
// http {
//     include /etc/nginx/mime.types;
//     include "${REAL_PATH}";
// }
// Cuando nginx -t -c "${TEST_WRAPPER}" se ejecuta:
// En REAL_PATH tenemos:
// location / {
//     if ($geoip_blocked = 1) { return 403; }
// }
// Al no incluirse conf.d/*.conf ni definirse $geoip_blocked:
// Nginx falla diciendo:
// [emerg] unknown "geoip_blocked" variable in /tmp/nginx_val_...
