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
     * participantDetails for many people in one query, keyed by user id. Unknown ids are left out.
     *
     * @param  list<string>  $userIds
     * @return array<string, array{firstName: ?string, lastName: ?string, gender: ?string, age: ?int, phone: ?string, emergencyContact: ?array{name: string, phone: string}, medicalInfo: ?array{note: ?string}}>
     */
    public function participantDetailsFor(array $userIds): array;

    /**
     * Same rules as the app's tripReadiness (see readiness()): names, gender, birth date (18+), verified phone,
     * verified photo, emergency contact.
     *
     * @return array{ready: bool, gender: ?string, verifiedPhoto: bool}
     */
    public function tripReadiness(string $userId): array;

    /**
     * What a trip still needs from this person. `missing` uses the app's item names in
     * REQUIRED_ITEMS order (firstName, phone, lastName, gender, birthDate, photo,
     * emergencyContact); a birth date under the minimum age is `tooYoung`, not missing.
     *
     * @return array{ready: bool, missing: list<string>, tooYoung: bool, gender: ?string, verifiedPhoto: bool}
     */
    public function readiness(string $userId): array;

    /** The id of the account whose verified phone or provider-confirmed email is this normalised contact. */
    public function findByContact(string $normalised): ?string;

    /** The verified phone, for showing a community host's number to joined campers. */
    public function phone(string $userId): ?string;
}
