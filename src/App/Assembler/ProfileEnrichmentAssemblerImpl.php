<?php

namespace Fawaz\App\Assembler;

use Fawaz\App\Contracts\HasUserRefs;
use Fawaz\Database\Interfaces\ProfileRepository;
use Fawaz\Services\ContentFiltering\Replacers\ContentReplacer;
final class ProfileEnrichmentAssemblerImpl {
    public function __construct(
        private ProfileRepository $profileRepository
    ) {}

    /**
     * @param array  $items             Array of transaction rows
     * @param array  $specs             Content filtering specifications
     * @param int    $currentUserId     For privacy logic
     * @param array  $mappings          Field → profileKey mapping
     * 
     * Example:
     * [
     *    'senderid' => 'sender',
     *    'recipientid' => 'recipient',
     * ]
     */
    public function enrichAndPlaceholderWithProfile(
        array $items,
        array $specs,
        string $currentUserId,
        array $mappings
    ): array {

        // Gather user IDs
        $ids = [];
        foreach ($items as $item) {
            foreach ($mappings as $idField => $_) {
                if (!empty($item[$idField])) {
                    $ids[$item[$idField]] = true;
                }
            }
        }
        $ids = array_keys($ids); 

        if (!$ids) {
            return $items; // nothing to enrich
        }

        // Load profiles
        $profiles = $this->profileRepository->fetchByIds($ids, $currentUserId, $specs);

        // Placeholder each profile once instead of on every usage
        foreach ($profiles as $id => $profile) {
            ContentReplacer::placeholderProfile($profile, $specs);
            $profiles[$id] = $profile->getArrayCopy();
        }

        // Attach enriched profiles
        foreach ($items as &$item) {
            foreach ($mappings as $idField => $dstField) {
                $userId = $item[$idField] ?? null;
                if ($userId && isset($profiles[$userId])) {
                    $item[$dstField] = $profiles[$userId];
                }
            }
        }
        unset($item);

        return $items;
    }

    /**
     * Enrich a list of read-models that implement HasUserRefs by:
     *  - Collecting all referenced user IDs
     *  - Fetching their profiles in one call
     *  - Applying content placeholdering once per profile
     *  - Attaching the result back to each object via attachUserProfile
     *
     * The method mutates the objects passed in `$items`.
     *
     * @param HasUserRefs[] $items
     */
    public function enrichHasUserRefs(
        array $items,
        array $specs,
        string $currentUserId
    ): void {
        if (!$items) {
            return;
        }

        // Collect all user refs and ids, and remember where to attach later
        $idIndex = [];
        $attachments = [];
        foreach ($items as $idx => $item) {
            if (!$item instanceof HasUserRefs) {
                // Skip unknown inputs silently to be defensive
                continue;
            }
            foreach ($item->getUserRefs() as $ref) {
                $userId = $ref->userId();
                $key = $ref->key();
                if ($userId === '') {
                    continue;
                }
                $idIndex[$userId] = true;
                $attachments[] = [$item, $key, $userId];
            }
        }

        $ids = array_keys($idIndex);
        if (!$ids) {
            return; // nothing to enrich
        }

        // Fetch and placeholder profiles once
        $profiles = $this->profileRepository->fetchByIds($ids, $currentUserId, $specs);
        foreach ($profiles as $id => $profile) {
            ContentReplacer::placeholderProfile($profile, $specs);
            $profiles[$id] = $profile->getArrayCopy();
        }

        // Attach back to each item
        foreach ($attachments as [$item, $refKey, $userId]) {
            if (isset($profiles[$userId])) {
                $item->attachUserProfile($refKey, $profiles[$userId]);
            }
        }
    }
}
