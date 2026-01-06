codex resume 019b9265-dc57-7080-ae74-2c8e15c5efef

# User Input Length Validations

Chat link: (CLI session does not expose a shareable URL.)

## Key Findings
- `PeerInputFilter` and `PeerInputGenericValidator` provide reusable `StringLength` checks for passwords (8–128 chars), usernames (3–23 chars, `[a-zA-Z0-9_-]`, at least one letter), tags (2–53 chars), Solana pubkeys (43–44 chars), phone numbers (9–21 chars by regex), and activation/reset tokens (exactly 64 hex characters) per `src/Filter/PeerInputFilter.php:676-843` and `src/Filter/PeerInputGenericValidator.php:261-520` with limits sourced from `src/config/constants/ConstantsConfig.php:313-523`.
- Chat/message payload validators (`PeerInputFilter::ValidateChatStructure`, `ValidateParticipantStructure`, `ValidateUserStructure`, `ValidatePostStructure`, `ValidatePostPureStructure`) enforce chat message contents at 1–500 chars, participant/user avatars 0/30–100 chars, profile post titles 2–63 chars, media blobs 30–3000 chars, and media descriptions 3–500 chars (`src/Filter/PeerInputFilter.php:551-674`).
- Model-level filters mirror these bounds for CRUD endpoints: `src/App/Post.php:143-195`, `src/App/PostAdvanced.php:282-325`, and `src/App/PostMedia.php:143-200` constrain `title`, `media`, `cover`, `mediadescription`, and serialized `options` using the POST constants; `src/App/Comment*.php` variants restrict `content` to 2–200 chars as defined in `ConstantsConfig::comment()`.
- User/profile flows (`src/App/User.php:436-456`, `src/App/UserAdvanced.php:525-545`, `src/App/Profile.php:392-412`, `src/App/ProfilUser.php:141-150`) cap avatar URLs at 0–100 chars and biographies at 3–5000 chars, aligning with the shared `ValidateUserStructure` helper.
- Contact forms and wallet/token actions apply their own string limits: `src/App/Contactus.php:150-170` requires contact names 3–53 chars and messages 3–500 chars; `src/App/PeerTokenService.php:101-124` limits optional transfer messages to 500 chars and disallows control chars/URLs via the `USER.TRANSFER_MESSAGE` config.
- Financial/miscellaneous tokens also have fixed lengths: pool/wallet tokens must be exactly 12 characters (`src/App/Pool.php:162-172`, `src/App/Wallet.php:164-174`), multipart uploads require eligibility tokens 30–1000 chars (`src/App/Models/MultipartPost.php:600-606`), and transaction records constrain `transactiontype`, `transferaction`, and memo `message` fields between 0–200 chars (`src/App/Models/Transaction.php:100-135`).

## Inputs to Update
- Profile post payloads (`title`, `mediadescription`) and related CRUD models (`src/App/Post*.php`, `src/App/PostMedia.php`) that share the POST constants.
- Comment `content` in `src/App/Comment.php`, `src/App/Commented.php`, and `src/App/CommentAdvanced.php`.
- User/profile biography across `src/App/User.php`, `src/App/UserAdvanced.php`, `src/App/Profile.php`, and `src/App/ProfilUser.php`.
- Contact form `name`/`message`, transfer `message` in `PeerTokenService`, and wallet/pool token inputs (`src/App/Contactus.php`, `src/App/PeerTokenService.php`, `src/App/Wallet.php`, `src/App/Pool.php`).
- Financial utility fields such as transaction `message` (`src/App/Models/Transaction.php`).

## DB Columns with Size Constraints
- Account records: `users.email` (`VARCHAR(249)`), `users.username` (`VARCHAR(33)`), `users.password` (`VARCHAR(255)`), `users.img` (`VARCHAR(100)`), and `users.visibility_status` (`VARCHAR(25)`) (`sql_files_for_import/001_structure.sql:6-15`, `sql_files_for_import/20251002000000_moderation_table.sql:70-79`).
- Extended profile info: `users_info.phone` (`VARCHAR(21)`) and `users_info.pkey` (`VARCHAR(44)`) (`sql_files_for_import/001_structure.sql:24-37`).
- Referral assets: `user_referral_info.referral_link`/`qr_code_url`, both `VARCHAR(255)` (`sql_files_for_import/001_structure.sql:55-60`).
- Chat/feed metadata: `chats.name` (`VARCHAR(50)`), `chats.image` (`VARCHAR(100)`), `newsfeed.name` (`VARCHAR(50)`), `newsfeed.image` (`VARCHAR(100)`) (`sql_files_for_import/001_structure.sql:80-100`).
- Posting tables: `posts.contenttype` (`VARCHAR(13)`), `posts.title` (`VARCHAR(63)`), `posts.mediadescription` (`VARCHAR(500)`), plus `posts_media.media`/`options` (`VARCHAR(500)`) (`sql_files_for_import/001_structure.sql:129-175`).
- Contact-us form persistence: `contactus.email` (`VARCHAR(249)`), `contactus.name` (`VARCHAR(33)`), `contactus.message` (`VARCHAR(500)`) (`sql_files_for_import/001_structure.sql:209-214`).
- Logging tables capturing request metadata: `logdata.browser` (`VARCHAR(255)`), `logdata.action_type` (`VARCHAR(30)`), `logdaten.browser` (`VARCHAR(255)`), `logdaten.url` (`VARCHAR(255)`), `logdaten.http_method` (`VARCHAR(10)`), `logdaten.location` (`VARCHAR(255)`), `logdaten.action_type` (`VARCHAR(30)`), `logdaten.auth_status` (`VARCHAR(50)`) (`sql_files_for_import/001_structure.sql:261-287`).
- Credential/token artifacts: `token_holders.token` (`VARCHAR(128)`), `password_resets.token` (`VARCHAR(128)`), `password_reset_requests.token` (`VARCHAR(255)`), and `wallet.token` (`VARCHAR(12)`) (`sql_files_for_import/001_structure.sql:320-549`).
- Taxonomy/enums: `tags.name` (`VARCHAR(62)`), `user_reports.targettype` (`VARCHAR(13)`), `user_reports.message` (`VARCHAR(500)`), and `action_prices.currency` (`VARCHAR(10)`) (`sql_files_for_import/001_structure.sql:344-590`).
- Financial/transactional text: `transactions.transactiontype`, `transactions.tokenamount`, `transactions.transferaction`, `transactions.message` (each `VARCHAR(255)`), `eligibility_token.status` (`VARCHAR(33)`), and advertisement status fields (`VARCHAR(12)`) (`sql_files_for_import/20250723000000_transactions_table.sql:3-12`, `sql_files_for_import/20250729000000_eligibility_token_expires_table.sql:3-7`, `sql_files_for_import/20250804000000_advertisements_table.sql:4-35`).
- Moderation flow state: `moderation_tickets.status`/`contenttype` and `moderations.status` are all `VARCHAR(25)` (`sql_files_for_import/20251002000000_moderation_table.sql:4-23`).

## Emoji-Friendly Fields (only those)
- Chat messages persist raw UTF-8 (including emoji) in `chatmessages.content` because no anti-emoji filter exists in `PeerInputFilter::ValidateChatStructure` and the column type is `TEXT` (`src/Filter/PeerInputFilter.php:541-552`, `sql_files_for_import/001_structure.sql:103-112`).
- Comment bodies accept emoji since validation only trims and bounds length; the DB column is `comments.content TEXT` (`src/App/Comment.php:160-176`, `src/App/Commented.php:178-188`, `src/App/CommentAdvanced.php:205-221`, `sql_files_for_import/001_structure.sql:179-189`).
- Post titles and media descriptions are UTF-8-safe `VARCHAR` fields with no regex forbidding emoji (`src/App/Post.php:143-195`, `src/App/PostAdvanced.php:282-325`, `sql_files_for_import/001_structure.sql:129-139`).
- User biographies and avatars go through HTML escaping but still keep emoji (stored as UTF-8/HTML entities) in `users.biography` (`TEXT`) and `users.img` (`VARCHAR(100)`) (`src/App/User.php:436-456`, `src/App/Profile.php:392-412`, `sql_files_for_import/001_structure.sql:13-15`).
- Contact form `message` retains emoji after sanitization (converted to HTML entities) and is stored in `contactus.message VARCHAR(500)` (`src/App/Contactus.php:150-170`, `sql_files_for_import/001_structure.sql:209-214`).
