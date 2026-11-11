<?php

return [

    /*
     * The proxies whose forwarded headers are believed: the visitor's address,
     * scheme and host as they reached the proxy rather than as the proxy
     * reached the application. Read by Laravel's own TrustProxies middleware.
     *
     * Traefik runs as a pod in k3s, so its address is drawn from the cluster's
     * pod range rather than fixed: the default trusts that whole range. A
     * comma-separated list of addresses or CIDR ranges, or "*" for whichever
     * peer is calling.
     */
    'proxies' => env('TRUSTED_PROXIES', '10.42.0.0/16'),

];
