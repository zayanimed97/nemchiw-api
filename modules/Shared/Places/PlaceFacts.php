<?php

namespace Modules\Shared\Places;

/** Facts every kind of place shares, so modules don't reach into each other for them. */
final class PlaceFacts
{
    public const COVERAGE = ['none', 'weak', 'good'];

    public const PERMIT = ['yes', 'no', 'unknown'];

    /** `example-` ids belong to the app's seed rows, which the first sync removes. */
    public const ID_RULE = ['required', 'string', 'max:64', 'regex:/^(?!example-)[a-z0-9-]+$/'];
}
