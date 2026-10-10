<?php

namespace Modules\Organisers\Support;

final class ApplicationStatus
{
    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const REVOKED = 'revoked';

    /** The person may change fields and documents. */
    public const EDITABLE = [self::DRAFT, self::REJECTED];
}
