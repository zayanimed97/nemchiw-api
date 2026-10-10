<?php

namespace Modules\Identity\Contracts;

/** How other modules read people. Arrays are the app's wire shapes. */
interface People
{
    /**
     * PublicProfile shapes keyed by user id. Unknown ids are left out.
     *
     * @param  list<string>  $userIds
     * @return array<string, array<string, mixed>>
     */
    public function publicProfiles(array $userIds): array;

    /**
     * The private fields an organiser gets once a camper has joined. Never send to anyone else.
     *
     * @return array{firstName: ?string, lastName: ?string, gender: ?string, age: ?int, phone: ?string, emergencyContact: ?array{name: string, phone: string}, medicalInfo: ?array{note: ?string}}
     */
    public function participantDetails(string $userId): array;

    /**
     * Same rules as the app's tripReadiness: names, gender, birth date (18+), verified phone,
     * verified photo, emergency contact.
     *
     * @return array{ready: bool, gender: ?string, verifiedPhoto: bool}
     */
    public function tripReadiness(string $userId): array;

    /** The verified phone, for showing a community host's number to joined campers. */
    public function phone(string $userId): ?string;
}
