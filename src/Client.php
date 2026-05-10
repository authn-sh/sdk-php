<?php

declare(strict_types=1);

namespace Authn\Sdk;

use Authn\Sdk\Http\Transport;
use Authn\Sdk\Resources\AllowlistIdentifiersManager;
use Authn\Sdk\Resources\BlocklistIdentifiersManager;
use Authn\Sdk\Resources\InstanceManager;
use Authn\Sdk\Resources\InvitationsManager;
use Authn\Sdk\Resources\OauthProvidersManager;
use Authn\Sdk\Resources\OrganizationsManager;
use Authn\Sdk\Resources\PermissionsManager;
use Authn\Sdk\Resources\RedirectUrlsManager;
use Authn\Sdk\Resources\RolesManager;
use Authn\Sdk\Resources\SessionsManager;
use Authn\Sdk\Resources\UsersManager;
use Authn\Sdk\Resources\WebhookEndpointsManager;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

final class Client
{
    public const VERSION = '0.1.0-dev';

    private readonly Config $config;

    private readonly Transport $transport;

    public function __construct(
        string $secretKey,
        ?string $apiUrl = null,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->config = new Config($secretKey, $apiUrl);
        $this->transport = new Transport(
            config: $this->config,
            http: $http,
            logger: $logger,
        );
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function transport(): Transport
    {
        return $this->transport;
    }

    public function users(): UsersManager
    {
        return new UsersManager($this->transport);
    }

    public function sessions(): SessionsManager
    {
        return new SessionsManager($this->transport);
    }

    public function invitations(): InvitationsManager
    {
        return new InvitationsManager($this->transport);
    }

    public function allowlistIdentifiers(): AllowlistIdentifiersManager
    {
        return new AllowlistIdentifiersManager($this->transport);
    }

    public function blocklistIdentifiers(): BlocklistIdentifiersManager
    {
        return new BlocklistIdentifiersManager($this->transport);
    }

    public function redirectUrls(): RedirectUrlsManager
    {
        return new RedirectUrlsManager($this->transport);
    }

    public function instance(): InstanceManager
    {
        return new InstanceManager($this->transport);
    }

    public function webhookEndpoints(): WebhookEndpointsManager
    {
        return new WebhookEndpointsManager($this->transport);
    }

    public function organizations(): OrganizationsManager
    {
        return new OrganizationsManager($this->transport);
    }

    public function roles(): RolesManager
    {
        return new RolesManager($this->transport);
    }

    public function permissions(): PermissionsManager
    {
        return new PermissionsManager($this->transport);
    }

    public function oauthProviders(): OauthProvidersManager
    {
        return new OauthProvidersManager($this->transport);
    }
}
