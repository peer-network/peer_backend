## Analysis: User Content Handling

  Overview

  - User media arrives either as multipart form files (/upload-post) or as Base64 payloads embedded in GraphQL requests. Multipart uploads are staged under runtime-data/
  media/tmp before being moved into their final typed folders and persisted with metadata. Base64 uploads are decoded immediately, validated, written into type-specific
  folders within runtime-data/media, and referenced in database rows (posts, user profiles, biographies, etc.).

  Entry Points

  - src/config/routes.php:34-37 – registers /upload-post to MultipartPostHandler.
  - src/Handler/MultipartPostHandler.php:28-124 – parses headers/files, extracts bearer token and eligibility token, and delegates to MultipartPostService.
  - src/App/PostService.php:434-606 – main post creation flow consuming staged uploads or Base64 media prior to DB insert.
  - src/Services/FileUploadDispatcher.php:29-116 – dispatch layer used for Base64 uploads (posts, covers).
  - src/App/UserService.php:387-414 and src/App/UserInfoService.php:373-477 – use the Base64 handler for profile images and biographies.

  Core Implementation

  1. Multipart Upload Validation & Staging (src/App/MultipartPostService.php:31-205)
      - setCurrentUserId() decodes the Authorization bearer token and caches the uid for subsequent DB checks (src/App/MultipartPostService.php:31-55).
      - checkForBasicValidation() enforces multipart/form-data content type and a 500 MB limit before any file work begins (src/App/MultipartPostService.php:65-90).
      - handleFileUpload() validates authentication, consults PostService->postEligibility() for credits, verifies the single-use eligibility token (checkTokenExpiry()
  queries eligibility_token for conflicting status at src/App/MultipartPostService.php:178-205), and instantiates MultipartPost to run required field, MIME-consistency, and
  per-type count checks (src/App/MultipartPostService.php:99-125).
      - Accepted files are written to runtime-data/media/tmp/<uuid>.<ext> through MultipartPost::moveFileToTmp() (src/App/Models/MultipartPost.php:283-315), and the
  eligibility token row is flagged FILE_UPLOADED (src/App/MultipartPostService.php:150-172). The response returns uploadedFiles as a comma-separated list of generated
  filenames.
  2. Multipart Model Utilities & Finalization (src/App/Models/MultipartPost.php:200-590)
      - validateSameContentTypes() reuses stored filenames to ensure all staged media share the same folder category (image/video/audio/text) before publishing (src/App/
  Models/MultipartPost.php:200-226).
      - moveFileTmpToMedia() reopens each staged file as a Slim\Psr7\UploadedFile, infers its canonical folder (image, video, audio, text), moves it under runtime-data/
  media/<subfolder>/<filename>, and collects metadata such as formatted size, duration, ratio, and resolution via getID3 helpers (src/App/Models/MultipartPost.php:321-383,
  411-458).
      - revertFileToTmp() performs the inverse move for cleanup when post creation aborts (src/App/Models/MultipartPost.php:463-495). These utilities underpin
  PostMapper::handelFileMoveToMedia() and PostMapper::revertFileToTmp() (see below).
  3. Post Creation and Storage (src/App/PostService.php:421-640, src/Database/PostMapper.php:804-870)
      - When createPost() receives Base64 media, it routes through FileUploadDispatcher->handleUploads() to the appropriate content-specific handler, then stores the
  resulting path/metadata JSON string into the posts.media column (src/App/PostService.php:434-453).
      - For staged uploadedFiles, the service reconstructs a MultipartPost, ensures the files still exist, determines the shared content type, and asks
  PostMapper->handelFileMoveToMedia() to move them out of tmp and collect metadata arrays (src/App/PostService.php:454-489, src/Database/PostMapper.php:804-819). Successful
  moves trigger PostMapper->updateTokenStatus() to mark the user’s eligibility token as POST_CREATED (src/Database/PostMapper.php:825-854).
      - Post rows are inserted with JSON-encoded media and optional cover; each media entry is also stored in posts_media through PostMedia entities holding media and
  serialized options fields (src/App/PostService.php:517-566).
      - On failure at any point in the transaction, staged files are reverted to tmp to lock them back to the user session (src/App/PostService.php:477-605).
  4. Base64 Upload Pipeline (src/Services/FileUploadDispatcher.php:29-74, src/Services/Base64FileHandler.php:18-282)
      - FileUploadDispatcher selects ImagePostService, VideoPostService, PodcastPostService, NotesPostService, or CoverPostService based on requested content type and loops
  through each Base64 string, building unique identifiers like <postId>_<index> for multi-part uploads (src/Services/FileUploadDispatcher.php:37-74).
      - Each service wraps Base64FileHandler->handleFileUpload() which performs extension/MIME validation, maximum size enforcement (default 79 MB), and writes decoded data
  into runtime-data/media/<subfolder> derived from the content type or overrides like profile and userData (src/Services/Base64FileHandler.php:18-64, 159-282).
      - Metadata is computed via getID3 (duration, aspect ratio, resolution) and stored in the response options array so consumers such as posts and cover uploads can
  persist it (src/Services/Base64FileHandler.php:89-142, 254-276).
  1. User Profile & Biography Assets (src/App/UserService.php:387-414, src/App/UserInfoService.php:373-477)
      - During registration or avatar updates, UserService::uploadMedia() and UserInfoService::setProfilePicture() call Base64FileHandler->handleFileUpload() with
  the profile subfolder override so files land under runtime-data/media/profile/<userId>.ext; the resulting /profile/... path is saved into the users.img column via
  UserMapper->update() (src/App/UserService.php:387-414, src/App/UserInfoService.php:444-472).
      - UserInfoService::updateBio() treats biography text as a Base64 “text” document, storing it under runtime-data/media/userData/<userId>.txt and persisting the
  returned path in users.biography (src/App/UserInfoService.php:373-405).
  1. Video Cover Generation (src/App/PostService.php:504-640, src/Services/VideoCoverGenerator.php:12-41)
      - If a video post lacks an explicit cover, generateCoverFromVideo() captures the first frame via FFMpeg into a temp JPEG, Base64-encodes it, and uploads it as a cover
  asset using the same dispatcher pipeline (src/App/PostService.php:520-640, src/Services/VideoCoverGenerator.php:12-35). Temporary cover files are deleted after either
  success or failure (src/App/PostService.php:617-630, src/Services/VideoCoverGenerator.php:35-41).

  Data Flow

  1. Client submits /upload-post with Authorization bearer and eligibility token → MultipartPostHandler validates headers and wraps $_FILES into UploadedFile objects (src/
  Handler/MultipartPostHandler.php:28-124).
  2. MultipartPostService checks auth + token, validates media limits, and writes each file to runtime-data/media/tmp/<uuid>.<ext>, returning the filenames string (src/App/
  5. For direct Base64 uploads (posts, covers, avatars, biographies), the payload bypasses staging: dispatcher decodes immediately, writes to the folder derived from content type
  (Base64FileHandler->getSubfolder()), and returns the path string used in the respective table column (src/Services/Base64FileHandler.php:211-282, src/App/UserInfoService.php:373-405).
  6. Video posts without covers trigger VideoCoverGenerator, which uses ffmpeg to extract a frame, feeds it into the same Base64 pipeline, and stores the resulting /cover/... asset (src/App/PostService.php:520-
  640, src/Services/VideoCoverGenerator.php:12-35).

  Key Patterns

  - Staging & Recovery: Multipart uploads always enter runtime-data/media/tmp first, with explicit helpers to move forward (moveFileTmpToMedia) or roll back (revertFileToTmp), ensuring DB persistence and file
  placement stay in sync (src/App/Models/MultipartPost.php:321-495).
  - Metadata Persistence: Both multipart and Base64 flows collect size, duration, ratio, and resolution using shared helpers so each DB row stores not just file paths but descriptive options JSON for clients
  (src/App/Models/MultipartPost.php:351-380, src/Services/Base64FileHandler.php:254-276).
  - Token Gating: Eligibility tokens guard the lifecycle—MultipartPostService marks them FILE_UPLOADED post-staging, and PostMapper marks them POST_CREATED to prevent reusing the same upload payload (src/App/
  MultipartPostService.php:150-205, src/Database/PostMapper.php:825-854).
  - Directory Layout: All persistent user content lives under runtime-data/media/<subfolder> where subfolder names map to logical content types (image, video, audio, text, cover, profile, userData) per
  Base64FileHandler::getSubfolder() and MultipartPost::getSubfolder() (src/Services/Base64FileHandler.php:46-64, src/App/Models/MultipartPost.php:528-545).