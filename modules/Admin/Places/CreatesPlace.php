<?php

namespace Modules\Admin\Places;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Place ids are chosen by the admin but are not mass-assignable on the models, so the
 * create pages set the id themselves.
 */
trait CreatesPlace
{
    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $record = new ($this->getModel());
        $record->setAttribute('id', $data['id']);
        $record->fill(Arr::except($data, 'id'));
        $record->save();

        return $record;
    }
}
