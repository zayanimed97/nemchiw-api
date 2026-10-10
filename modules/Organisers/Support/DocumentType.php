<?php

namespace Modules\Organisers\Support;

final class DocumentType
{
    public const ALL = ['id_front', 'id_back', 'licence', 'insurance'];

    public const MIMES = 'jpg,jpeg,png,webp,heic,pdf';

    public const MAX_KB = 10240;
}
