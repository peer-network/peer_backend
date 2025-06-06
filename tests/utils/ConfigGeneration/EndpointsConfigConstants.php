<?php
declare(strict_types=1);

namespace Tests\Utils\ConfigGeneration;

class EndpointsConfigConstants {
    static $testBackendServerEndpoint = "https://peer-network.eu/graphql";
    static $testProductionServerEndpoint = "https://getpeer.eu/graphql";
    static $productionServerEndpoint = "https://peernetwork.eu/graphql";

    static $requiredPlatforms = ["ios","android","web"];
}