<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class SystemPermissions
{
    public const ORG_SYS_PROFILE_MANAGE = 'org:sys_profile:manage';

    public const ORG_SYS_PROFILE_DELETE = 'org:sys_profile:delete';

    public const ORG_SYS_MEMBERSHIPS_READ = 'org:sys_memberships:read';

    public const ORG_SYS_MEMBERSHIPS_MANAGE = 'org:sys_memberships:manage';

    public const ORG_SYS_DOMAINS_READ = 'org:sys_domains:read';

    public const ORG_SYS_DOMAINS_MANAGE = 'org:sys_domains:manage';

    public const ORG_SYS_BILLING_READ = 'org:sys_billing:read';

    public const ORG_SYS_BILLING_MANAGE = 'org:sys_billing:manage';

    public const ORG_SYS_SSO_READ = 'org:sys_sso:read';

    public const ORG_SYS_SSO_MANAGE = 'org:sys_sso:manage';

    public const ORG_SYS_PROVISIONING_READ = 'org:sys_provisioning:read';

    public const ORG_SYS_PROVISIONING_MANAGE = 'org:sys_provisioning:manage';
}
