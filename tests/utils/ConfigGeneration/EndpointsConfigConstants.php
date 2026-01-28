<?php

declare(strict_types=1);

namespace Tests\utils\ConfigGeneration;

class EndpointsConfigConstants
{
    public static $testBackendServerEndpoint = "https://peer-network.eu/graphql";
    public static $testProductionServerEndpoint = "https://backend.peerapp.eu/graphql";
    public static $productionServerEndpoint = "https://peernetwork.eu/graphql";

    public static $requiredPlatforms = ["ios","android","web"];
}
