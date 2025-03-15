<?php

namespace Fawaz\Services;

class FileUploadDispatcher
{
    private ImageChatService $imageChatService;
    private UserAvatarService $userAvatarService;
    private UserBiographyService $userBiographyService;
    private ImagePostService $imagePostService;
    private PodcastPostService $podcastPostService;
    private VideoPostService $videoPostService;
    private NotesPostService $notesPostService;

    public function __construct(
        ImageChatService $imageChatService,
        UserAvatarService $userAvatarService,
        UserBiographyService $userBiographyService,
        ImagePostService $imagePostService,
        PodcastPostService $podcastPostService,
        VideoPostService $videoPostService,
        NotesPostService $notesPostService
    ) {
        $this->imageChatService = $imageChatService;
        $this->userAvatarService = $userAvatarService;
        $this->userBiographyService = $userBiographyService;
        $this->imagePostService = $imagePostService;
        $this->podcastPostService = $podcastPostService;
        $this->videoPostService = $videoPostService;
        $this->notesPostService = $notesPostService;
    }

    public function handleUploads(array $files): string
    {
        $results = [];
        $identifiers = [];
        $imageCount = count(array_filter($files, fn($file) => $file['type'] === 'image'));

        foreach ($files as $index => $file) {
            $contentType = $file['type'] ?? null;
            $base64Data = $file['data'] ?? null;

            if (!$contentType || !$base64Data) {
                $results[] = ['success' => false, 'error' => 'Invalid file data'];
                continue;
            }

            if ($contentType === 'image' && $imageCount > 1) {
                if (!isset($identifiers['image'])) {
                    $identifiers['image'] = uniqid();
                }
                $identifier = $identifiers['image'] . '_' . ($index + 1);
            } else {
                $identifier = uniqid();
            }

            switch ($contentType) {
                case 'chatimage':
                    $results[] = $this->imageChatService->handleFileUpload($base64Data, $identifier);
                    break;

                case 'profile':
                    $results[] = $this->userAvatarService->handleFileUpload($base64Data, $identifier);
                    break;

                case 'biography':
                    $results[] = $this->userBiographyService->handleFileUpload($base64Data, $identifier);
                    break;

                case 'image':
                    $results[] = $this->imagePostService->handleFileUpload($base64Data, $identifier);
                    break;

                case 'audio':
                    $results[] = $this->podcastPostService->handleFileUpload($base64Data, $identifier);
                    break;

                case 'video':
                    $results[] = $this->videoPostService->handleFileUpload($base64Data, $identifier);
                    break;

                case 'text':
                    $results[] = $this->notesPostService->handleFileUpload($base64Data, $identifier);
                    break;

                default:
                    $results[] = ['success' => false, 'error' => "Unsupported file type: $contentType"];
            }
        }

        //return !empty($results) ? json_encode($results) : null;
        return !empty($results) ? implode(',', $results) : null;
    }
}
